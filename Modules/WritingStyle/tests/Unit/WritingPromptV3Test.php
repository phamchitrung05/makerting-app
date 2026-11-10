<?php

namespace Modules\WritingStyle\Tests\Unit;

use Illuminate\Validation\ValidationException;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Http\Resources\WritingAnalysisResource;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;
use Modules\WritingStyle\Pipelines\SandboxPipelineRunner;
use Modules\WritingStyle\Pipelines\WritingContentPlan;
use Modules\WritingStyle\Services\WritingProfileSnapshotService;
use Modules\WritingStyle\Services\WritingStyleAnalysisV3;
use Tests\TestCase;

class WritingPromptV3Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['writingstyle.prompt_version' => 3]);
    }

    public function test_analysis_preserves_bullets_and_drops_unsupported_observations(): void
    {
        $analyzer = new WritingStyleAnalysisV3("Nhà em gửi dâu rể vài lựa chọn.\n\n• Mẫu giấy nhám\n• Mẫu giấy mịn");
        $request = $analyzer->request();
        $this->assertArrayHasKey('bullets', $request->schema['properties']['features']['properties']);
        $this->assertStringNotContainsString('tone_instructions', json_encode($request->schema));
        $this->assertStringContainsString('• Mẫu giấy nhám', $request->messages[1]['content']);

        $data = [
            'features' => array_fill_keys(array_keys(config('writingstyle.v3.questions')), [
                'state' => 'unknown', 'description' => '', 'confidence' => 'low', 'evidence_ids' => [],
            ]),
            'evidence' => [
                ['id' => 'ev_01', 'kind' => 'structure_observation', 'block_ids' => [2, 3], 'text' => 'Hai lựa chọn trình bày thành hai gạch đầu dòng.'],
                ['id' => 'ev_02', 'kind' => 'excerpt', 'block_ids' => [1], 'text' => 'Trích dẫn không có trong bài'],
            ],
            'signature_language' => ['self_reference' => ['nhà em'], 'audience_reference' => ['dâu rể'], 'particles' => []],
            'source_specific_items' => [],
            'uncertainties' => ['Chưa đủ dữ liệu để kết luận điều tác giả tránh.'],
            'style_instructions' => 'Nói chuyện trực tiếp với người đọc.',
            'voice_texture_instructions' => 'Câu ngắn và tách danh sách.',
            'voice_texture_sliders_json' => array_fill_keys(WritingProfile::VOICE_TEXTURE_KEYS, null),
            'tone_sliders_json' => array_fill_keys(WritingProfile::TONE_AXIS_KEYS, null),
        ];
        $data['features']['bullets'] = ['state' => 'observed', 'description' => 'Dùng bullet liệt kê lựa chọn.', 'confidence' => 'high', 'evidence_ids' => ['ev_01']];
        $data['features']['avoid'] = ['state' => 'observed', 'description' => 'Không dùng emoji.', 'confidence' => 'high', 'evidence_ids' => ['ev_02']];
        $result = $analyzer->normalize($data);

        $this->assertArrayHasKey('bullets', $result['rules_json']);
        $this->assertArrayNotHasKey('avoid', $result['rules_json']);
        $this->assertSame('unknown', $result['style_analysis_json']['features']['avoid']['state']);
        $this->assertCount(1, $result['style_analysis_json']['evidence']);

        $analysis = new WritingProfileAnalysis(['result_json' => $result]);
        $serialized = (new WritingAnalysisResource($analysis))->resolve(request())['result_json'];
        $this->assertSame($data['voice_texture_instructions'], $serialized['voice_texture_instructions']);
        $this->assertSame($data['voice_texture_sliders_json'], $serialized['voice_texture_sliders_json']);
        $this->assertSame($data['tone_sliders_json'], $serialized['tone_sliders_json']);
        $this->assertSame($result['style_analysis_json'], $serialized['style_analysis_json']);
    }

    public function test_compiler_preserves_signature_and_honors_explicit_removal(): void
    {
        $profile = new WritingProfile([
            'rules_json' => ['pronouns' => 'Xưng nhà em', 'bullets' => 'Liệt kê từng lựa chọn'],
            'evidence_json' => [['excerpt' => 'TRÍCH DẪN RIÊNG TƯ']],
            'style_instructions' => 'Giới thiệu bằng câu hỏi.',
            'voice_texture_instructions' => 'Xen câu ngắn với câu dài.',
            'tone_instructions' => 'BÌNH TĨNH GỐC',
            'tone_sliders_json' => ['humor' => 80, 'urgency' => 20],
        ]);
        $snapshot = app(WritingProfileSnapshotService::class)->buildSnapshot($profile, 'short', 0, [
            'tone_instructions' => null,
            'tone_overrides' => ['humor' => 0, 'urgency' => null],
        ]);

        $this->assertStringContainsString('Xưng nhà em', $snapshot->systemPrompt);
        $this->assertStringContainsString('Xen câu ngắn', $snapshot->systemPrompt);
        $this->assertStringContainsString('"humor":{"score":0', $snapshot->systemPrompt);
        $this->assertStringNotContainsString('"urgency"', $snapshot->systemPrompt);
        $this->assertStringNotContainsString('BÌNH TĨNH GỐC', $snapshot->systemPrompt);
        $this->assertStringNotContainsString('TRÍCH DẪN RIÊNG TƯ', $snapshot->systemPrompt);
        $this->assertArrayHasKey('bullets', $snapshot->rulesJson);
        $this->assertSame('4–10 câu', $snapshot->sentenceHint);
    }

    public function test_brief_rejects_required_information_absent_from_provided_facts(): void
    {
        $this->expectException(ValidationException::class);
        WritingBrief::fromInput('Thiệp cưới', 'short', ['brief' => ['required_details' => ['Giá 80.000đ']]]);
    }

    public function test_plan_rejects_unknown_fact_references(): void
    {
        $brief = WritingBrief::fromInput('Thiệp cưới', 'long', ['brief' => $this->brief()]);
        $plan = $this->plan();
        $plan['sections'][0]['fact_ids'] = ['invented'];
        $this->expectException(InvalidPipelineException::class);
        WritingContentPlan::validate($plan, $brief);
    }

    public function test_long_pipeline_passes_style_to_planner_and_rejects_stale_plan_before_writing(): void
    {
        $ai = $this->mock(CoreAiGatewayContract::class);
        $ai->shouldReceive('lastRun')->andReturn(null);
        $ai->shouldReceive('executeStructured')->once()->withArgs(function (AiStructuredRequest $request): bool {
            return $request->operation === 'sandbox_step_topic_outline_v3'
                && str_contains($request->messages[1]['content'], 'Xưng nhà em')
                && $request->options['temperature'] === 0.4;
        })->andReturn(new AiStructuredResponse(text: '', data: $this->plan()));
        $ai->shouldReceive('executeStructured')->once()->withArgs(function (AiStructuredRequest $request): bool {
            return $request->operation === 'sandbox_step_article_writer_v3'
                && str_contains($request->messages[1]['content'], 'Giới thiệu chủ đề')
                && ! str_contains($request->messages[1]['content'], 'evidence_requests')
                && $request->options['temperature'] === 0.0;
        })->andReturn(new AiStructuredResponse(text: 'Dâu rể ơi, nhà em gửi vài lựa chọn. Inbox để xem mẫu.'));

        $profile = new WritingProfile(['rules_json' => ['pronouns' => 'Xưng nhà em']]);
        $runner = app(SandboxPipelineRunner::class);
        $overrides = ['brief' => $this->brief(), 'temperature' => 0];
        $result = $runner->runStep($profile, 'long', 'topic_outline', 'Thiệp cưới', overrides: $overrides);
        $this->assertSame(3, $result['artifact']['schema_version']);
        $this->assertNull($result['artifact']['sections'][0]['heading_level']);

        $overrides['outline_approved'] = true;
        $article = $runner->runStep($profile, 'long', 'article_writer', 'Thiệp cưới', ['topic_outline' => $result['artifact']], $overrides);
        $this->assertStringContainsString('nhà em', $article['artifact']['text']);
        $overrides['brief']['objective'] = 'Mời khách gọi điện';
        $this->expectExceptionMessage('Dàn ý đã cũ');
        $runner->runStep($profile, 'long', 'article_writer', 'Thiệp cưới', ['topic_outline' => $result['artifact']], $overrides);
    }

    public function test_writer_reports_length_format_and_missing_cta(): void
    {
        $ai = $this->mock(CoreAiGatewayContract::class);
        $ai->shouldReceive('lastRun')->andReturn(null);
        $ai->shouldReceive('executeStructured')->once()->andReturn(new AiStructuredResponse(text: "## Mở bài\nNhà em có mẫu mới."));
        $profile = new WritingProfile(['rules_json' => ['pronouns' => 'Xưng nhà em']]);
        $result = app(SandboxPipelineRunner::class)->runStep($profile, 'short', 'article_writer', 'Thiệp cưới', overrides: ['brief' => $this->brief()]);
        $report = $result['artifact']['quality_report'];
        $this->assertFalse($report['checks']['length']);
        $this->assertFalse($report['checks']['format']);
        $this->assertSame(['Inbox để xem mẫu'], $report['missing_details']);
    }

    public function test_truncated_writer_response_is_not_a_completed_article(): void
    {
        $this->mock(CoreAiGatewayContract::class)->shouldReceive('executeStructured')->once()->andReturn(
            new AiStructuredResponse(text: 'Bài bị cắt', raw: ['choices' => [['finish_reason' => 'length']]]),
        );
        $this->expectException(InvalidPipelineException::class);
        app(SandboxPipelineRunner::class)->runStep(new WritingProfile, 'short', 'article_writer', 'Thiệp cưới');
    }

    private function brief(): array
    {
        return [
            'format' => ['headings' => false],
            'cta' => 'Inbox để xem mẫu',
            'length' => ['unit' => 'words', 'min' => 20, 'max' => 40],
        ];
    }

    private function plan(): array
    {
        return [
            'central_message' => 'Thiệp cưới cho dâu rể',
            'target_angle' => 'Trò chuyện với cặp đôi đang chọn mẫu',
            'structure_type' => 'storytelling',
            'sections' => [[
                'id' => 'opening', 'parent_id' => null, 'role' => 'mở bài', 'heading_level' => null,
                'title' => 'Gợi mở', 'key_points' => ['Giới thiệu chủ đề'], 'fact_ids' => [], 'length_budget' => 30,
            ]],
            'evidence_requests' => [], 'missing_fields' => [], 'warnings' => [],
        ];
    }
}
