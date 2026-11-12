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
use Modules\WritingStyle\Services\PromptPack;
use Modules\WritingStyle\Services\WritingStyleAnalysisV3;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ kiểm thử prompt V3
 * =====================================================================
 *
 * PHPUnit chạy các ca hồi quy với fixture/mock trong môi trường kiểm thử.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - setUp(): Khởi tạo test và bật cấu hình prompt V3 trong ứng dụng kiểm thử.
 * - test_analysis_preserves_bullets_and_drops_unsupported_observations(): Kiểm chứng phân tích giữ bullet, loại trích dẫn sai và giữ slider chưa biết là null.
 * - test_analysis_accepts_long_sample_without_truncating_blocks(): Bài mẫu dài
 *   vượt trần sinh bài vẫn phân tích được và giữ đủ các block nguồn.
 * - test_analysis_rejects_context_above_its_own_limit(): Chặn mẫu vượt trần
 *   analyzer và trả lỗi đúng trường reference_text.
 * - test_large_analysis_budget_does_not_relax_other_requests(): Trần analyzer
 *   cao hơn không làm nới giới hạn chung của brief/planner/writer.
 * - test_compiler_preserves_signature_and_honors_explicit_removal(): Kiểm chứng compiler giữ dấu ấn, xóa chỉ dẫn theo null và áp dụng brief.
 * - test_brief_rejects_required_information_absent_from_provided_facts(): Kiểm chứng brief từ chối chi tiết bắt buộc chưa được cung cấp.
 * - test_plan_rejects_unknown_fact_references(): Kiểm chứng dàn ý từ chối fact ID ngoài brief.
 * - test_long_pipeline_passes_style_to_planner_and_rejects_stale_plan_before_writing(): Kiểm chứng planner nhận văn phong, writer nhận dàn ý và từ chối dàn ý cũ.
 * - test_writer_reports_length_format_and_missing_cta(): Kiểm chứng báo cáo nhận ra sai độ dài, headings bị cấm và CTA thiếu.
 * - test_truncated_writer_response_is_not_a_completed_article(): Kiểm chứng response AI bị cắt không được coi là bài hoàn tất.
 * - brief(): Cấp brief mẫu cấm headings và yêu cầu CTA, độ dài 20–40 từ.
 * - plan(): Cấp kế hoạch nội dung mẫu có một mục và ngân sách 30 từ.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Container, fixtures và các request giả lập.
 * - OUTPUT: Assertions thành công hoặc lỗi kiểm thử.
 *
 * BỐI CẢNH SỬ DỤNG: PHPUnit chạy các ca hồi quy với fixture/mock trong môi trường kiểm thử.
 * =====================================================================
 */
class WritingPromptPackTest extends TestCase
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo test và bật cấu hình prompt V3 trong ứng dụng kiểm thử
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Gọi parent setUp, thay config của ứng dụng kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi khởi tạo được truyền lên; không tự mở transaction.
     * =====================================================================
     */
    protected function setUp(): void
    {
        parent::setUp();
        config(['writingstyle.prompt_version' => 3]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng bài mẫu nhiều đoạn không bị chặn bởi trần sinh bài
     * =====================================================================
     *
     * INPUT:
     * - Fixture 632 block tiếng Việt, tổng prompt vượt 40.000 ký tự.
     *
     * OUTPUT:
     * - void; assertions xác nhận request giữ toàn bộ nội dung và thứ tự block.
     *
     * SIDE EFFECT:
     * - Dựng request trong bộ nhớ, không gọi gateway hoặc ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu còn dùng trần chung hoặc nội dung bị cắt.
     *   Không mở transaction.
     * =====================================================================
     */
    public function test_analysis_accepts_long_sample_without_truncating_blocks(): void
    {
        $blocks = array_fill(0, 632, 'Mỗi hành trình đều mở ra một góc nhìn mới về thành phố.');
        $request = (new WritingStyleAnalysisV3(implode("\n\n", $blocks)))->request();
        $promptChars = array_sum(array_map(static fn (array $message): int => mb_strlen($message['content']), $request->messages));
        $sourceBlocks = json_decode($request->messages[1]['content'], true, flags: JSON_THROW_ON_ERROR)['source_blocks'];

        $this->assertGreaterThan(PromptPack::limit('max_context_chars'), $promptChars);
        $this->assertLessThanOrEqual(PromptPack::limit('analyzer_max_context_chars'), $promptChars);
        $this->assertSame($blocks, array_column($sourceBlocks, 'text'));
        $this->assertSame(range(1, 632), array_column($sourceBlocks, 'id'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng analyzer vẫn từ chối đầu vào vượt trần riêng
     * =====================================================================
     *
     * INPUT:
     * - Bài mẫu có 120.001 ký tự; cấu hình analyzer tối đa 120.000 ký tự.
     *
     * OUTPUT:
     * - void; assertions xác nhận lỗi tại reference_text nêu rõ giới hạn.
     *
     * SIDE EFFECT:
     * - Đổi config trong ứng dụng kiểm thử; chỉ dựng request trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException là kết quả mong đợi; AssertionFailedError nếu không
     *   chặn hoặc sai trường lỗi. Không gọi AI, ghi DB hoặc mở transaction.
     * =====================================================================
     */
    public function test_analysis_rejects_context_above_its_own_limit(): void
    {
        config(['writingstyle.v3.limits.analyzer_max_context_chars' => 120000]);
        PromptPack::flush();

        try {
            (new WritingStyleAnalysisV3(str_repeat('a', 120001)))->request();
            $this->fail('Analyzer phải chặn context vượt giới hạn riêng.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('reference_text', $error->errors());
            $this->assertStringContainsString('120.000', $error->errors()['reference_text'][0]);
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng tăng trần analyzer không nới context sinh bài
     * =====================================================================
     *
     * INPUT:
     * - Cùng chuỗi 40.001 ký tự được đưa qua guard analyzer và guard chung.
     *
     * OUTPUT:
     * - void; analyzer chấp nhận, guard chung vẫn trả lỗi ở trường brief.
     *
     * SIDE EFFECT:
     * - Chỉ đếm chuỗi trong bộ nhớ; không gọi AI hoặc ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException của guard chung là kết quả mong đợi;
     *   AssertionFailedError nếu giới hạn bị thay đổi ngoài phạm vi analyzer.
     *   Không mở transaction.
     * =====================================================================
     */
    public function test_large_analysis_budget_does_not_relax_other_requests(): void
    {
        $prompt = str_repeat('a', 40001);
        PromptPack::checkAnalysisContext($prompt);

        try {
            PromptPack::checkContext($prompt);
            $this->fail('Trần context chung phải giữ nguyên 40.000 ký tự.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('brief', $error->errors());
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng phân tích giữ bullet, loại trích dẫn sai và giữ slider chưa biết là null
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên request và kết quả normalize
     *
     * SIDE EFFECT:
     * - Thiết lập fixture/mock và chạy assertions; feature test có thể tạo dữ liệu trong DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi hành vi khác kỳ vọng; transaction DB do RefreshDatabase quản lý khi trait được dùng.
     * =====================================================================
     */
    public function test_analysis_preserves_bullets_and_drops_unsupported_observations(): void
    {
        $analyzer = new WritingStyleAnalysisV3("Nhà em gửi dâu rể vài lựa chọn.\n\n• Mẫu giấy nhám\n• Mẫu giấy mịn");
        $request = $analyzer->request();
        $this->assertArrayHasKey('bullets', $request->schema['properties']['features']['properties']);
        $this->assertStringNotContainsString('tone_instructions', json_encode($request->schema));
        $this->assertStringContainsString('• Mẫu giấy nhám', $request->messages[1]['content']);

        $data = [
            'features' => array_fill_keys(array_keys(PromptPack::dict('questions')), [
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

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng compiler giữ dấu ấn, xóa chỉ dẫn theo null và áp dụng brief
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên snapshot và prompt
     *
     * SIDE EFFECT:
     * - Thiết lập fixture/mock và chạy assertions; feature test có thể tạo dữ liệu trong DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi hành vi khác kỳ vọng; transaction DB do RefreshDatabase quản lý khi trait được dùng.
     * =====================================================================
     */
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
        $this->assertSame('150–800 từ', $snapshot->sentenceHint);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng brief từ chối chi tiết bắt buộc chưa được cung cấp
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; mong đợi ValidationException
     *
     * SIDE EFFECT:
     * - Thiết lập fixture/mock và chạy assertions; feature test có thể tạo dữ liệu trong DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi hành vi khác kỳ vọng; transaction DB do RefreshDatabase quản lý khi trait được dùng.
     * =====================================================================
     */
    public function test_brief_rejects_required_information_absent_from_provided_facts(): void
    {
        $this->expectException(ValidationException::class);
        WritingBrief::fromInput('Thiệp cưới', 'short', ['brief' => ['required_details' => ['Giá 80.000đ']]]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng dàn ý từ chối fact ID ngoài brief
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; mong đợi InvalidPipelineException
     *
     * SIDE EFFECT:
     * - Thiết lập fixture/mock và chạy assertions; feature test có thể tạo dữ liệu trong DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi hành vi khác kỳ vọng; transaction DB do RefreshDatabase quản lý khi trait được dùng.
     * =====================================================================
     */
    public function test_plan_rejects_unknown_fact_references(): void
    {
        $brief = WritingBrief::fromInput('Thiệp cưới', 'long', ['brief' => $this->brief()]);
        $plan = $this->plan();
        $plan['sections'][0]['fact_ids'] = ['invented'];
        $this->expectException(InvalidPipelineException::class);
        WritingContentPlan::validate($plan, $brief);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng structure_type mô tả dài (tới 120 ký tự) vẫn hợp lệ
     * =====================================================================
     *
     * Bug: trước đây max:80 khiến planner trả mô tả kết hợp như
     * "storytelling_ket_hop_guide_va_listicle_dia_diem" (hoặc câu mô tả dài hơn
     * 80 ký tự) bị chặn. Giới hạn nới lên 120 để chấp nhận nhãn cấu trúc dài.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên plan hợp lệ và lỗi cho > 120 ký tự
     * =====================================================================
     */
    public function test_plan_accepts_descriptive_structure_type_up_to_limit(): void
    {
        $brief = WritingBrief::fromInput('Đà Nẵng', 'long', ['brief' => $this->brief()]);

        // structure_type dài ~95 ký tự (mô tả kết hợp) — trước fix bị max:80 chặn.
        $plan = $this->plan();
        $plan['structure_type'] = 'storytelling_ket_hop_guide_du_lich_va_listicle_diem_den_trai_nghiem_theo_thu_tu_tuyen_tinh';
        $validated = WritingContentPlan::validate($plan, $brief);
        $this->assertSame($plan['structure_type'], $validated['structure_type']);

        // Vượt 120 ký tự vẫn bị chặn.
        $plan['structure_type'] = str_repeat('x', 121);
        $this->expectException(ValidationException::class);
        WritingContentPlan::validate($plan, $brief);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng missing_fields/evidence_requests/warnings văn bản dài
     * =====================================================================
     *
     * Bug: khi AI sinh brief rồi planner trả missing_fields dài hơn 100 ký tự
     * → validate chặn toàn bộ plan. Các trường metadata cảnh báo này không phải
     * nội dung writer cần, nên giờ được truncate an toàn 500 ký tự/mục thay
     * vì ném exception.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên plan hợp lệ và truncation
     * =====================================================================
     */
    public function test_plan_truncates_long_meta_fields_instead_of_rejecting(): void
    {
        $brief = WritingBrief::fromInput('Đà Nẵng', 'long', ['brief' => $this->brief()]);
        $plan = $this->plan();

        // missing_fields văn bản ~200 ký tự — trước fix bị max:100 chặn.
        $plan['missing_fields'] = [
            'Cần thêm thông tin về lịch trình tour cụ thể, giá vé tham quan các điểm đến, chi phí ăn uống và khách sạn để viết được bài đầy đủ hơn cho người đọc.',
        ];
        // evidence_requests > 300 ký tự — trước fix bị max:300 chặn.
        $plan['evidence_requests'] = [str_repeat('dẫn chứng ', 40)]; // ~400+ ký tự
        $plan['warnings'] = [str_repeat('cảnh báo ', 50)]; // ~400+ ký tự

        $validated = WritingContentPlan::validate($plan, $brief);

        $this->assertSame(mb_substr($plan['missing_fields'][0], 0, 500), $validated['missing_fields'][0]);
        $this->assertSame(mb_substr($plan['evidence_requests'][0], 0, 500), $validated['evidence_requests'][0]);
        $this->assertSame(mb_substr($plan['warnings'][0], 0, 500), $validated['warnings'][0]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng planner nhận văn phong, writer nhận dàn ý và từ chối dàn ý cũ
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions bằng gateway mock và exception
     *
     * SIDE EFFECT:
     * - Thiết lập fixture/mock và chạy assertions; feature test có thể tạo dữ liệu trong DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi hành vi khác kỳ vọng; transaction DB do RefreshDatabase quản lý khi trait được dùng.
     * =====================================================================
     */
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

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng báo cáo nhận ra sai độ dài, headings bị cấm và CTA thiếu
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; assertions trên quality_report
     *
     * SIDE EFFECT:
     * - Thiết lập fixture/mock và chạy assertions; feature test có thể tạo dữ liệu trong DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi hành vi khác kỳ vọng; transaction DB do RefreshDatabase quản lý khi trait được dùng.
     * =====================================================================
     */
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

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng response AI bị cắt không được coi là bài hoàn tất
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void; mong đợi InvalidPipelineException
     *
     * SIDE EFFECT:
     * - Thiết lập fixture/mock và chạy assertions; feature test có thể tạo dữ liệu trong DB kiểm thử.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError khi hành vi khác kỳ vọng; transaction DB do RefreshDatabase quản lý khi trait được dùng.
     * =====================================================================
     */
    public function test_truncated_writer_response_is_not_a_completed_article(): void
    {
        $this->mock(CoreAiGatewayContract::class)->shouldReceive('executeStructured')->once()->andReturn(
            new AiStructuredResponse(text: 'Bài bị cắt', raw: ['choices' => [['finish_reason' => 'length']]]),
        );
        $this->expectException(InvalidPipelineException::class);
        app(SandboxPipelineRunner::class)->runStep(new WritingProfile, 'short', 'article_writer', 'Thiệp cưới');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Cấp brief mẫu cấm headings và yêu cầu CTA, độ dài 20–40 từ
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array brief fixture
     *
     * SIDE EFFECT:
     * - Tạo dữ liệu trong bộ nhớ, không gọi AI/DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    private function brief(): array
    {
        return [
            'format' => ['headings' => false],
            'cta' => 'Inbox để xem mẫu',
            'length' => ['unit' => 'words', 'min' => 20, 'max' => 40],
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Cấp kế hoạch nội dung mẫu có một mục và ngân sách 30 từ
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array content plan fixture
     *
     * SIDE EFFECT:
     * - Tạo dữ liệu trong bộ nhớ, không gọi AI/DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
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
