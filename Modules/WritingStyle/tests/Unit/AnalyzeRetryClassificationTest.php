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
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\WritingStyle\Services\WritingStyleAnalyzerService;
use Tests\TestCase;

/**
 * Test phân loại lỗi retry của analyze() (transient ném lại, permanent nuốt).
 */
class AnalyzeRetryClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function gw(\Throwable $ex): void
    {
        $this->app->instance(CoreAiGatewayContract::class, new class($ex) implements CoreAiGatewayContract {
            public function __construct(private $ex) {}
            public function executeStructured(AiStructuredRequest $r): AiStructuredResponse { throw $this->ex; }
            public function generateImage(AiImageRequest $r): AiImageResponse { throw new \LogicException; }
            public function synthesizeSpeech(AiSpeechRequest $r): AiSpeechResponse { throw new \LogicException; }
            public function transcribeAudio(AiTranscribeRequest $r): AiTranscribeResponse { throw new \LogicException; }
        });
    }

    public function test_loi_transient_nem_lai_de_queue_retry_va_giu_running(): void
    {
        $this->gw(AiGatewayException::rateLimit('429'));

        $this->expectException(AiGatewayException::class);
        try {
            app(WritingStyleAnalyzerService::class)->analyze('Bài mẫu transient lỗi.');
        } finally {
            // Lỗi TẠM THỜI -> GIỮ `running` (KHÔNG markFailed) để FE poll không
            // báo thất bại sai trong lúc attempt 2 của job còn chạy. error_code
            // vẫn ghi để chẩn đoán.
            $this->assertSame(WritingProfileAnalysis::STATUS_RUNNING, WritingProfileAnalysis::first()->status);
            $this->assertSame('rate_limit', WritingProfileAnalysis::first()->error_code);
        }
    }

    public function test_loi_permanent_chi_markfailed_khong_nem(): void
    {
        $this->gw(AiGatewayException::auth('sai key'));

        $a = app(WritingStyleAnalyzerService::class)->analyze('Bài mẫu permanent lỗi.');

        $this->assertSame(WritingProfileAnalysis::STATUS_FAILED, $a->status);
        $this->assertSame('auth', $a->error_code);
    }

    public function test_hook_failed_chot_failed_cho_loi_transient_het_luot(): void
    {
        // Khi job hết lượt retry, hook failed() phải chốt `failed` — nếu không
        // bản ghi transient sẽ treo mãi ở `running` và FE poll tới timeout.
        $this->gw(AiGatewayException::rateLimit('429'));

        $analysis = WritingProfileAnalysis::create([
            'created_by'     => null,
            'source_type'    => WritingProfileAnalysis::SOURCE_PASTE,
            'source_url'     => null,
            'reference_text' => 'Bài mẫu transient lỗi.',
            'status'         => WritingProfileAnalysis::STATUS_RUNNING,
            'expires_at'     => now()->addDays(30),
        ]);
        $analysis->markTransientError('rate_limit', 'HTTP 429');

        $job = new \Modules\WritingStyle\Jobs\AnalyzeWritingProfileJob($analysis->id, 'task-x');
        $job->failed(new \RuntimeException('HTTP 429'));

        $this->assertSame(WritingProfileAnalysis::STATUS_FAILED, $analysis->fresh()->status);
    }

    public function test_hook_failed_khong_ghi_de_khi_da_succeeded(): void
    {
        // Guard: nếu lần chạy trước đã succeeded (edge — job vẫn vào hook),
        // hook KHÔNG được đè `failed` lên bản ghi thành công.
        $analysis = WritingProfileAnalysis::create([
            'created_by'     => null,
            'source_type'    => WritingProfileAnalysis::SOURCE_PASTE,
            'source_url'     => null,
            'reference_text' => 'Bài mẫu đã xong.',
            'status'         => WritingProfileAnalysis::STATUS_RUNNING,
            'expires_at'     => now()->addDays(30),
        ]);
        $analysis->markSucceeded(['detected_tier' => 'short', 'rules_json' => []], 'short');

        $job = new \Modules\WritingStyle\Jobs\AnalyzeWritingProfileJob($analysis->id, 'task-x');
        $job->failed(new \RuntimeException('vẫn bị gọi'));

        $this->assertSame(WritingProfileAnalysis::STATUS_SUCCEEDED, $analysis->fresh()->status);
    }
}
