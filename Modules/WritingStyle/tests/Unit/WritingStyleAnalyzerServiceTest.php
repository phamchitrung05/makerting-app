<?php

namespace Modules\WritingStyle\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiImageResponse;
use Modules\CoreAi\DTOs\AiSpeechRequest;
use Modules\CoreAi\DTOs\AiSpeechResponse;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\DTOs\AiTranscribeRequest;
use Modules\CoreAi\DTOs\AiTranscribeResponse;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\WritingStyle\Services\WritingStyleAnalyzerService;
use Tests\TestCase;

/**
 * Unit test WritingStyleAnalyzerService (Task 6) — mock CoreAi gateway.
 */
class WritingStyleAnalyzerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(array $data, ?object $run = null): void
    {
        $resp = new AiStructuredResponse(
            text: json_encode($data),
            data: $data,
            remoteModelId: 'fake-model',
        );
        // Anonymous class implement contract + lastRun() (concrete CoreAiGateway
        // có lastRun dù không khai trong interface).
        $this->app->instance(CoreAiGatewayContract::class, new class($resp, $run) implements CoreAiGatewayContract {
            public function __construct(private $resp, private $run) {}
            public function executeStructured(AiStructuredRequest $r): AiStructuredResponse { return $this->resp; }
            public function generateImage(AiImageRequest $r): AiImageResponse { throw new \LogicException; }
            public function synthesizeSpeech(AiSpeechRequest $r): AiSpeechResponse { throw new \LogicException; }
            public function transcribeAudio(AiTranscribeRequest $r): AiTranscribeResponse { throw new \LogicException; }
            public function lastRun() { return $this->run; }
        });
    }

    private function referenceText(): string
    {
        return "Tôi tin rằng nội dung là vua. Bạn có đồng ý không? Hãy bắt đầu ngay hôm nay. " .
            "Đây là câu bốn để đủ tier short. Câu năm tiếp theo. Câu sáu nữa. Câu bảy. Câu tám. Câu chín. Câu mười. Câu mười một.";
    }

    private function v3Payload(array $overrides = []): array
    {
        return array_replace([
            'features' => array_fill_keys(array_keys(config('writingstyle.v3.questions')), [
                'state' => 'unknown', 'description' => '', 'confidence' => 'low', 'evidence_ids' => [],
            ]),
            'evidence' => [],
            'signature_language' => ['self_reference' => [], 'audience_reference' => [], 'particles' => []],
            'source_specific_items' => [],
            'uncertainties' => [],
            'style_instructions' => 'Mở bằng câu hỏi.',
            'voice_texture_instructions' => '',
            'voice_texture_sliders_json' => array_fill_keys(WritingProfile::VOICE_TEXTURE_KEYS, null),
            'tone_sliders_json' => array_fill_keys(WritingProfile::TONE_AXIS_KEYS, null),
        ], $overrides);
    }

    public function test_analyze_khong_tao_profile_chi_ghi_result(): void
    {
        $payload = $this->v3Payload();
        $payload['features']['tone'] = ['state' => 'observed', 'description' => 'Giọng trang trọng', 'confidence' => 'high', 'evidence_ids' => ['ev_01']];
        $payload['evidence'] = [
            ['id' => 'ev_01', 'kind' => 'excerpt', 'block_ids' => [1], 'text' => 'Tôi tin rằng nội dung là vua'],
            ['id' => 'ev_02', 'kind' => 'excerpt', 'block_ids' => [1], 'text' => 'excerpt bịa không có'],
        ];

        $this->fakeGateway($payload);

        $a = app(WritingStyleAnalyzerService::class)->analyze($this->referenceText());

        $this->assertSame(WritingProfileAnalysis::STATUS_SUCCEEDED, $a->status);
        $this->assertSame('medium', $a->detected_tier);
        $this->assertNotNull($a->result_json);
        $this->assertSame('Giọng trang trọng', $a->result_json['rules_json']['tone']);
        // Excerpt bịa bị lọc — chỉ giữ excerpt có thật trong bài.
        $this->assertCount(1, $a->result_json['evidence_json']);
        $this->assertSame('Tôi tin rằng nội dung là vua', $a->result_json['evidence_json'][0]['excerpt']);
        // Style analysis giữ schema V3.
        $this->assertSame(3, $a->result_json['style_analysis_json']['schema_version']);
        $this->assertSame('observed', $a->result_json['style_analysis_json']['features']['tone']['state']);

        // Phân tích KHÔNG tạo WritingProfile (chỉ khi Lưu mới tạo).
        $this->assertNull($a->draft_profile_id);
        $this->assertSame(0, WritingProfile::count());
    }

    public function test_analyze_ghi_ai_run_id(): void
    {
        $this->fakeGateway(
            $this->v3Payload(),
            (object) ['id' => 'run-uuid-123'],
        );

        $a = app(WritingStyleAnalyzerService::class)->analyze($this->referenceText());

        $this->assertSame('run-uuid-123', $a->ai_run_id);
        $this->assertSame('run-uuid-123', $a->result_json['ai_run_id']);
    }

    public function test_analyze_failed_khi_ai_loi(): void
    {
        $mock = new class implements CoreAiGatewayContract {
            public function executeStructured(AiStructuredRequest $r): AiStructuredResponse { throw new \RuntimeException('provider down'); }
            public function generateImage(AiImageRequest $r): AiImageResponse { throw new \LogicException; }
            public function synthesizeSpeech(AiSpeechRequest $r): AiSpeechResponse { throw new \LogicException; }
            public function transcribeAudio(AiTranscribeRequest $r): AiTranscribeResponse { throw new \LogicException; }
        };
        $this->app->instance(CoreAiGatewayContract::class, $mock);

        $a = app(WritingStyleAnalyzerService::class)->analyze($this->referenceText());

        $this->assertSame(WritingProfileAnalysis::STATUS_FAILED, $a->status);
        $this->assertSame('analyzer_error', $a->error_code);
        $this->assertSame('provider down', $a->error_message);
    }
}
