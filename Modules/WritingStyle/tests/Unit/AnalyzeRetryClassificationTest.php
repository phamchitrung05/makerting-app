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

    public function test_loi_transient_nem_lai_de_queue_retry(): void
    {
        $this->gw(AiGatewayException::rateLimit('429'));

        $this->expectException(AiGatewayException::class);
        try {
            app(WritingStyleAnalyzerService::class)->analyze('Bài mẫu transient lỗi.');
        } finally {
            // markFailed đã ghi trước khi ném.
            $this->assertSame(WritingProfileAnalysis::STATUS_FAILED, WritingProfileAnalysis::first()->status);
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
}
