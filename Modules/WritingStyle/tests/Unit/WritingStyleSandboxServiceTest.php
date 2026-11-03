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
use Modules\WritingStyle\Services\WritingStyleSandboxService;
use Tests\TestCase;

/**
 * Test WritingStyleSandboxService (Task 8) — mock CoreAi, snapshot thật.
 */
class WritingStyleSandboxServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(string $text, ?object $run = null, ?callable $capture = null): void
    {
        $this->app->instance(CoreAiGatewayContract::class, new class($text, $run, $capture) implements CoreAiGatewayContract {
            public function __construct(private $text, private $run, private $capture) {}
            public function executeStructured(AiStructuredRequest $r): AiStructuredResponse {
                if ($this->capture) { ($this->capture)($r); }
                return new AiStructuredResponse(text: $this->text, remoteModelId: 'fake');
            }
            public function generateImage(AiImageRequest $r): AiImageResponse { throw new \LogicException; }
            public function synthesizeSpeech(AiSpeechRequest $r): AiSpeechResponse { throw new \LogicException; }
            public function transcribeAudio(AiTranscribeRequest $r): AiTranscribeResponse { throw new \LogicException; }
            public function lastRun() { return $this->run; }
        });
    }

    private function profile(): WritingProfile
    {
        $p = new WritingProfile;
        $p->id = 9;
        $p->name = 'Giọng Test';
        $p->dominant_tier = WritingProfile::TIER_MEDIUM;
        $p->rules_json = ['tone' => 'ấm áp', 'headings' => 'H2', 'uncertainties' => []];
        $p->evidence_json = [['rule_key' => 'tone', 'excerpt' => 'câu mẫu bí mật', 'note' => 'x']];
        return $p;
    }

    public function test_sandbox_tra_text_va_snapshot_khong_evidence(): void
    {
        $captured = null;
        $this->fakeGateway('Văn bản thử nghiệm viết ra.', null, function ($r) use (&$captured) { $captured = $r; });

        $out = app(WritingStyleSandboxService::class)->sandbox($this->profile(), 'chủ đề X', 'medium');

        $this->assertSame('Văn bản thử nghiệm viết ra.', $out->text);
        $this->assertSame('medium', $out->targetTier);
        $this->assertSame(9, $out->profileId);
        // Snapshot trong result không có evidence.
        $this->assertArrayNotHasKey('evidence', $out->snapshot['rules']);
        $this->assertArrayNotHasKey('evidence_json', $out->snapshot['rules']);
        // Prompt gửi cho AI chứa topic + system_prompt của snapshot.
        $this->assertNotNull($captured);
        $this->assertStringContainsString('chủ đề X', $captured->messages[1]['content'] ?? '');
        $this->assertStringContainsString('ấm áp', $captured->messages[0]['content'] ?? '');
    }

    public function test_sandbox_ghi_ai_run_id(): void
    {
        $this->fakeGateway('text', (object) ['id' => 'run-9']);
        $out = app(WritingStyleSandboxService::class)->sandbox($this->profile(), 't', 'medium');
        $this->assertSame('run-9', $out->aiRunId);
    }

    public function test_sandbox_khong_luu_db(): void
    {
        $this->fakeGateway('text');
        app(WritingStyleSandboxService::class)->sandbox($this->profile(), 't', 'medium');
        // Không tạo thêm WritingProfile hay analysis nào.
        $this->assertSame(0, WritingProfile::count());
    }
}
