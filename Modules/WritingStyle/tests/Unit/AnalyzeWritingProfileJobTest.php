<?php

namespace Modules\WritingStyle\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\TaskCenter\Models\SystemTaskRun;
use Modules\WritingStyle\Jobs\AnalyzeWritingProfileJob;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\WritingStyle\Services\WritingStyleAnalyzerService;
use Tests\TestCase;

/**
 * Test dispatchAnalysis (Task 7) — job + SystemTaskRun record.
 */
class AnalyzeWritingProfileJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_tao_analysis_pending_taskrun_va_job(): void
    {
        Queue::fake();

        $out = app(WritingStyleAnalyzerService::class)->dispatchAnalysis(
            referenceText: 'Bài mẫu thử nghiệm có đủ nhiều câu để phân tầng độ dài. Câu hai. Câu ba.',
            name: 'Mẫu A',
            createdBy: 1,
        );

        $analysis = $out['analysis'];
        $taskRun = $out['taskRun'];

        // Analysis pending.
        $this->assertSame(WritingProfileAnalysis::STATUS_PENDING, $analysis->status);
        $this->assertSame('Mẫu A', $analysis->name);
        $this->assertNotEmpty($analysis->source_hash);

        // SystemTaskRun record đúng loại.
        $this->assertSame('writingstyle', $taskRun->module);
        $this->assertSame('writing_profile.analyze', $taskRun->task_type);
        $this->assertStringContainsString('Giải mã văn phong', $taskRun->title);
        $this->assertSame(SystemTaskRun::STATUS_QUEUED, $taskRun->status);
        $this->assertSame((string) $analysis->id, (string) $taskRun->taskable_id);

        // Job đã được dispatch lên queue ai-text.
        Queue::assertPushed(AnalyzeWritingProfileJob::class, function ($job) use ($analysis, $taskRun) {
            return $job->analysisId === $analysis->id
                && $job->taskRunId === $taskRun->id
                && $job->queue === 'ai-text';
        });
    }

    public function test_dispatch_dedupe_cung_source_hash(): void
    {
        Queue::fake();
        $svc = app(WritingStyleAnalyzerService::class);
        $text = 'Cùng một bài mẫu. Câu hai. Câu ba.';

        $a = $svc->dispatchAnalysis($text);
        $b = $svc->dispatchAnalysis($text);

        // TaskRun dedupe theo source_hash — cùng 1 record.
        $this->assertSame($a['taskRun']->id, $b['taskRun']->id);
        $this->assertSame(1, SystemTaskRun::count());
    }
    private function v3Payload(): array
    {
        return [
            'features' => array_fill_keys(array_keys(config('writingstyle.v3.questions')), [
                'state' => 'unknown', 'description' => '', 'confidence' => 'low', 'evidence_ids' => [],
            ]),
            'evidence' => [],
            'signature_language' => ['self_reference' => [], 'audience_reference' => [], 'particles' => []],
            'source_specific_items' => [],
            'uncertainties' => [],
            'style_instructions' => '',
            'voice_texture_instructions' => '',
            'voice_texture_sliders_json' => array_fill_keys(\Modules\WritingStyle\Models\WritingProfile::VOICE_TEXTURE_KEYS, null),
            'tone_sliders_json' => array_fill_keys(\Modules\WritingStyle\Models\WritingProfile::TONE_AXIS_KEYS, null),
        ];
    }

    public function test_handle_chay_pipeline_va_complete_taskrun(): void
    {
        $resp = new \Modules\CoreAi\DTOs\AiStructuredResponse(
            text: '{}',
            data: $this->v3Payload(),
            remoteModelId: 'fake',
        );
        $this->app->instance(\Modules\CoreAi\Contracts\CoreAiGatewayContract::class,
            new class($resp) implements \Modules\CoreAi\Contracts\CoreAiGatewayContract {
                public function __construct(private $r) {}
                public function executeStructured(\Modules\CoreAi\DTOs\AiStructuredRequest $x): \Modules\CoreAi\DTOs\AiStructuredResponse { return $this->r; }
                public function generateImage(\Modules\CoreAi\DTOs\AiImageRequest $x): \Modules\CoreAi\DTOs\AiImageResponse { throw new \LogicException; }
                public function synthesizeSpeech(\Modules\CoreAi\DTOs\AiSpeechRequest $x): \Modules\CoreAi\DTOs\AiSpeechResponse { throw new \LogicException; }
                public function transcribeAudio(\Modules\CoreAi\DTOs\AiTranscribeRequest $x): \Modules\CoreAi\DTOs\AiTranscribeResponse { throw new \LogicException; }
                public function lastRun() { return null; }
            });

        $out = app(WritingStyleAnalyzerService::class)->dispatchAnalysis('Bài mẫu cho handle. Câu hai.');
        $job = new AnalyzeWritingProfileJob($out['analysis']->id, $out['taskRun']->id);
        $job->handle(
            app(\Modules\TaskCenter\Contracts\TaskTrackerContract::class),
            app(WritingStyleAnalyzerService::class),
        );

        $a = $out['analysis']->fresh();
        $t = $out['taskRun']->fresh();
        $this->assertSame(WritingProfileAnalysis::STATUS_SUCCEEDED, $a->status);
        $this->assertSame(SystemTaskRun::STATUS_COMPLETED, $t->status);
    }

    public function test_dispatch_tra_lai_analysis_khi_dang_pending(): void
    {
        Queue::fake();
        $svc = app(WritingStyleAnalyzerService::class);
        $text = 'Cùng bài mẫu đang pending.';

        $a = $svc->dispatchAnalysis($text);
        $b = $svc->dispatchAnalysis($text);

        // Cùng analysis (đang pending) — không tạo bản ghi mồ côi.
        $this->assertSame($a['analysis']->id, $b['analysis']->id);
        $this->assertSame($a['taskRun']->id, $b['taskRun']->id);
        $this->assertSame(1, WritingProfileAnalysis::count());
        $this->assertSame(1, SystemTaskRun::count());
        // Và KHÔNG dispatch thêm job lần 2.
        Queue::assertPushed(AnalyzeWritingProfileJob::class, 1);
    }

    public function test_retry_tao_analysis_va_taskrun_moi_sau_failed(): void
    {
        Queue::fake();
        $svc = app(WritingStyleAnalyzerService::class);
        $text = 'Bài mẫu lần trước failed.';

        $a = $svc->dispatchAnalysis($text);
        // Giả lập lần 1 failed.
        $a['analysis']->markFailed('x', 'y');
        $a['taskRun']->update(['status' => SystemTaskRun::STATUS_FAILED]);

        // Bấm phân tích lại -> analysis MỚI + taskRun MỚI (không trả failed cũ).
        $b = $svc->dispatchAnalysis($text);

        $this->assertNotSame($a['analysis']->id, $b['analysis']->id);
        $this->assertNotSame($a['taskRun']->id, $b['taskRun']->id);
        $this->assertSame(WritingProfileAnalysis::STATUS_PENDING, $b['analysis']->status);
        $this->assertSame(SystemTaskRun::STATUS_QUEUED, $b['taskRun']->status);
        // Taskable trỏ đúng analysis mới (không còn trỏ analysis failed).
        $this->assertSame((string) $b['analysis']->id, (string) $b['taskRun']->taskable_id);
        $this->assertSame(2, WritingProfileAnalysis::count());
        $this->assertSame(2, SystemTaskRun::count());
        Queue::assertPushed(AnalyzeWritingProfileJob::class, 2);
    }
}
