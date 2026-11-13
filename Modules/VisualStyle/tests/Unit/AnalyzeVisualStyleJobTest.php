<?php

namespace Modules\VisualStyle\Tests\Unit;

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
use Modules\TaskCenter\Contracts\TaskTrackerContract;
use Modules\VisualStyle\Jobs\AnalyzeVisualStyleJob;
use Modules\VisualStyle\Models\VisualStyle;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ kiểm thử AnalyzeVisualStyleJob (Task 4).
 * =====================================================================
 *
 * Mock `CoreAiGatewayContract` + spy `TaskTrackerContract`; refresh DB để
 * tạo VisualStyle thật có reference_media_id.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - trackerSpy(): Mock TaskTrackerContract ghi nhận start/complete/fail.
 * - fakeGateway(): Mock gateway trả DNA payload.
 * - fakeGatewayThrow(): Mock gateway ném lỗi chỉ định.
 * - makeStyle(): Tạo VisualStyle trạng thái analyzing với reference_media_id.
 * - test_job_marks_analyzed_and_completes_task_run(): Job thành công →
 *   style status=analyzed + dna_json + tracker.complete.
 * - test_job_keeps_analyzing_and_throws_on_transient_error(): Lỗi transient
 *   → giữ status=analyzing + ném lại (retry), không fail tracker.
 * - test_job_fails_permanently_on_non_transient_error(): Lỗi vĩnh viễn →
 *   status=failed + tracker.fail.
 * - test_failed_hook_marks_failed_and_fails_task_run(): Hook failed() sau
 *   hết lượt retry → status=failed + tracker.fail.
 * - test_job_requires_reference_media(): Không có ảnh tham chiếu →
 *   status=failed + tracker.fail reference_media_missing.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Mock gateway/tracker + fixtures trong DB test.
 * - OUTPUT: Assertions thành công hoặc lỗi kiểm thử.
 *
 * BỐI CẢNH SỬ DỤNG: PHPUnit; không gọi AI thật.
 * =====================================================================
 */
class AnalyzeVisualStyleJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * =====================================================================
     * CHỨC NĂNG: Mock TaskTrackerContract ghi nhận phương thức đã gọi
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số.
     *
     * OUTPUT:
     * - \stdClass với property calls[] và tracker instance bind vào container.
     *
     * SIDE EFFECT:
     * - Bind TaskTrackerContract vào container.
     * =====================================================================
     */
    private function trackerSpy(): object
    {
        $spy = new \stdClass;
        $spy->calls = [];

        $tracker = new class($spy) implements TaskTrackerContract {
            public function __construct(private $spy) {}

            private function run(string $id): \Modules\TaskCenter\Models\SystemTaskRun
            {
                return \Modules\TaskCenter\Models\SystemTaskRun::firstOrCreate(
                    ['id' => $id],
                    [
                        'dedupe_key' => 'dedupe:'.$id,
                        'module'     => 'visualstyle',
                        'task_type'  => 'visual_style.analyze',
                        'title'      => 'Giải mã DNA ảnh',
                        'status'     => 'running',
                    ],
                );
            }

            public function create(\Modules\TaskCenter\DTOs\CreateTaskRunDto $dto): \Modules\TaskCenter\Models\SystemTaskRun {
                $this->spy->calls[] = ['create', $dto];
                return $this->run($dto->taskableId ?? 'run-created');
            }
            public function progress(string $id, \Modules\TaskCenter\DTOs\TaskProgressUpdateDto $dto): \Modules\TaskCenter\Models\SystemTaskRun {
                $this->spy->calls[] = ['progress', $id, $dto];
                return $this->run($id);
            }
            public function start(string $id): \Modules\TaskCenter\Models\SystemTaskRun {
                $this->spy->calls[] = ['start', $id];
                return $this->run($id);
            }
            public function complete(string $id, ?array $meta = null): \Modules\TaskCenter\Models\SystemTaskRun {
                $this->spy->calls[] = ['complete', $id, $meta];
                return $this->run($id);
            }
            public function partial(string $id, ?array $meta = null): \Modules\TaskCenter\Models\SystemTaskRun {
                $this->spy->calls[] = ['partial', $id, $meta];
                return $this->run($id);
            }
            public function fail(string $id, string $errorCode, string $errorMessage): \Modules\TaskCenter\Models\SystemTaskRun {
                $this->spy->calls[] = ['fail', $id, $errorCode, $errorMessage];
                return $this->run($id);
            }
            public function cancel(string $id): \Modules\TaskCenter\Models\SystemTaskRun {
                $this->spy->calls[] = ['cancel', $id];
                return $this->run($id);
            }
            public function get(string $id): \Modules\TaskCenter\Models\SystemTaskRun {
                return $this->run($id);
            }
        };

        $this->app->instance(TaskTrackerContract::class, $tracker);

        return $spy;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Mock gateway trả DNA payload đầy đủ
     * =====================================================================
     *
     * INPUT:
     * - array $data — payload AI trả về.
     *
     * OUTPUT:
     * - void — instance CoreAiGatewayContract bind vào container.
     *
     * SIDE EFFECT:
     * - Ghi mock vào container.
     * =====================================================================
     */
    private function fakeGateway(array $data): void
    {
        $resp = new AiStructuredResponse(
            text: json_encode($data),
            data: $data,
            remoteModelId: 'vision-fake-model',
            providerKey: 'fake-vision',
            raw: ['choices' => [['finish_reason' => 'stop']]],
        );

        $this->app->instance(CoreAiGatewayContract::class, new class($resp) implements CoreAiGatewayContract {
            public function __construct(private $resp) {}
            public function executeStructured(AiStructuredRequest $r): AiStructuredResponse { return $this->resp; }
            public function generateImage(AiImageRequest $r): AiImageResponse { throw new \LogicException; }
            public function synthesizeSpeech(AiSpeechRequest $r): AiSpeechResponse { throw new \LogicException; }
            public function transcribeAudio(AiTranscribeRequest $r): AiTranscribeResponse { throw new \LogicException; }
            public function lastRun() { return null; }
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Mock gateway ném lỗi chỉ định
     * =====================================================================
     *
     * INPUT:
     * - \Throwable $e — lỗi sẽ ném khi executeStructured.
     *
     * OUTPUT:
     * - void — instance CoreAiGatewayContract ném lỗi.
     *
     * SIDE EFFECT:
     * - Ghi mock vào container.
     * =====================================================================
     */
    private function fakeGatewayThrow(\Throwable $e): void
    {
        $this->app->instance(CoreAiGatewayContract::class, new class($e) implements CoreAiGatewayContract {
            public function __construct(private \Throwable $e) {}
            public function executeStructured(AiStructuredRequest $r): AiStructuredResponse { throw $this->e; }
            public function generateImage(AiImageRequest $r): AiImageResponse { throw new \LogicException; }
            public function synthesizeSpeech(AiSpeechRequest $r): AiSpeechResponse { throw new \LogicException; }
            public function transcribeAudio(AiTranscribeRequest $r): AiTranscribeResponse { throw new \LogicException; }
            public function lastRun() { return null; }
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo VisualStyle với reference_media_id trỏ MediaAsset thật
     * =====================================================================
     *
     * INPUT:
     * - ?int $mediaAssetId — null = không gắn media.
     *
     * OUTPUT:
     * - VisualStyle đã lưu DB (status=draft → job sẽ markAnalyzing).
     *
     * SIDE EFFECT:
     * - INSERT visual_styles (rollback sau test).
     * =====================================================================
     */
    private function makeStyle(?int $mediaAssetId = null): VisualStyle
    {
        return VisualStyle::create([
            'name' => 'Test Style',
            'status' => 'draft',
            'reference_media_id' => $mediaAssetId,
            'created_by' => null,
        ]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Fixture DNA đầy đủ
     * =====================================================================
     *
     * OUTPUT:
     * - array dna_json.
     * =====================================================================
     */
    private function dnaPayload(): array
    {
        return [
            'composition' => ['camera_angle' => 'low_angle', 'framing_rule' => 'rule_of_thirds', 'depth_of_field' => 'shallow_dof'],
            'camera'      => ['lens_type' => '85mm', 'shot_medium' => 'photograph', 'perspective' => 'medium'],
            'lighting'    => ['tone' => 'warm_golden', 'key_light' => 'rim_light'],
            'environment' => ['setting' => 'studio', 'backdrop' => 'nền xám', 'atmosphere' => 'clean'],
            'color_mood'  => ['palette_hex' => ['#1A1A2E'], 'temperature' => 'warm', 'contrast' => 'high', 'grade' => 'vivid'],
            'subject_rules' => ['preserve' => ['logo'], 'forbidden' => []],
            'style_constraints' => 'Ấm, điện ảnh.',
            'confidence' => 0.9,
            'taxonomy_suggestion' => [],
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo MediaAsset có file ảnh thật (PNG 1px) trên disk test
     * =====================================================================
     *
     * OUTPUT:
     * - \Modules\Media\Models\MediaAsset — asset có file Spatie.
     * =====================================================================
     */
    private function makeImageAsset(): \Modules\Media\Models\MediaAsset
    {
        $asset = \Modules\Media\Models\MediaAsset::create([
            'kind' => \Modules\Media\Models\MediaAsset::KIND_IMAGE,
            'title' => 'Ref',
            'visibility' => 'public',
            'checksum_sha256' => hash('sha256', (string) microtime(true).rand()),
        ]);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'tst_');
        file_put_contents($tmp, $png);
        $asset->addMedia($tmp)->toMediaCollection(\Modules\Media\Models\MediaAsset::COLLECTION_LIBRARY, 'media_public');

        return $asset;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Job thành công → style status=analyzed + dna_json + tracker.complete
     * =====================================================================
     *
     * INPUT:
     * - VisualStyle + MediaAsset thật; gateway trả DNA; tracker spy.
     *
     * OUTPUT:
     * - void; assertions status=analyzed, dna_json ghi vào DB, complete được gọi.
     *
     * SIDE EFFECT:
     * - INSERT/UPDATE visual_styles; gọi tracker.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Transaction rollback sau test.
     * =====================================================================
     */
    public function test_job_marks_analyzed_and_completes_task_run(): void
    {
        $spy = $this->trackerSpy();
        $this->fakeGateway($this->dnaPayload());
        $asset = $this->makeImageAsset();
        $style = $this->makeStyle($asset->id);

        $job = new AnalyzeVisualStyleJob($style->id, 'task-run-uuid');
        $job->handle(app(TaskTrackerContract::class), app(\Modules\VisualStyle\Services\VisualStyleAnalyzerService::class));

        $style->refresh();

        $this->assertSame('analyzed', $style->status);
        $this->assertNotNull($style->analyzed_at);
        $this->assertSame('low_angle', $style->dna_json['composition']['camera_angle']);
        $this->assertSame(0.9, $style->dna_json['confidence']);
        $this->assertNotNull($style->analysis_meta_json);
        $this->assertSame('vision-fake-model', $style->analysis_meta_json['model']);

        // Tracker ghi complete (không fail).
        $this->assertTrue(collect($spy->calls)->contains(fn ($c) => $c[0] === 'complete'));
        $this->assertFalse(collect($spy->calls)->contains(fn ($c) => $c[0] === 'fail'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lỗi transient → giữ analyzing + ném lại (retry)
     * =====================================================================
     *
     * INPUT:
     * - Gateway ném AiGatewayException rate_limit (transient).
     *
     * OUTPUT:
     * - void; assertions status giữ 'analyzing', exception ném lại,
     *   tracker KHÔNG gọi fail.
     *
     * SIDE EFFECT:
     * - Gọi job->handle.
     *
     * EXCEPTION/TRANSACTION:
     * - AiGatewayException là kết quả mong đợi.
     * =====================================================================
     */
    public function test_job_keeps_analyzing_and_throws_on_transient_error(): void
    {
        $spy = $this->trackerSpy();
        $this->fakeGatewayThrow(new \Modules\CoreAi\Exceptions\AiGatewayException('Rate limited', 'rate_limit'));
        $asset = $this->makeImageAsset();
        $style = $this->makeStyle($asset->id);

        try {
            $job = new AnalyzeVisualStyleJob($style->id, 'task-run-uuid');
            $job->handle(app(TaskTrackerContract::class), app(\Modules\VisualStyle\Services\VisualStyleAnalyzerService::class));
            $this->fail('Kỳ vọng ném lại để worker retry.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\Modules\CoreAi\Exceptions\AiGatewayException::class, $e);
        }

        $style->refresh();
        $this->assertSame('analyzing', $style->status);
        $this->assertFalse(collect($spy->calls)->contains(fn ($c) => $c[0] === 'fail'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lỗi vĩnh viễn (analyzer_error) → status=failed + tracker.fail
     * =====================================================================
     *
     * INPUT:
     * - Gateway ném AiGatewayException analyzer_error (không trong danh sách transient).
     *
     * OUTPUT:
     * - void; assertions status=failed, tracker gọi fail.
     *
     * SIDE EFFECT:
     * - Gọi job->handle.
     *
     * EXCEPTION/TRANSACTION:
     * - Không ném ra ngoài.
     * =====================================================================
     */
    public function test_job_fails_permanently_on_non_transient_error(): void
    {
        $spy = $this->trackerSpy();
        $this->fakeGatewayThrow(new \Modules\CoreAi\Exceptions\AiGatewayException('Lỗi vĩnh viễn', 'analyzer_error'));
        $asset = $this->makeImageAsset();
        $style = $this->makeStyle($asset->id);

        $job = new AnalyzeVisualStyleJob($style->id, 'task-run-uuid');
        $job->handle(app(TaskTrackerContract::class), app(\Modules\VisualStyle\Services\VisualStyleAnalyzerService::class));

        $style->refresh();
        $this->assertSame('failed', $style->status);
        $this->assertTrue(collect($spy->calls)->contains(fn ($c) => $c[0] === 'fail' && $c[2] === 'analyzer_error'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Hook failed() sau hết lượt retry → status=failed + fail task run
     * =====================================================================
     *
     * INPUT:
     * - Job + exception của lần chạy cuối (rate_limit).
     *
     * OUTPUT:
     * - void; assertions status=failed + tracker fail 'job_failed'.
     *
     * SIDE EFFECT:
     * - Gọi job->failed().
     *
     * EXCEPTION/TRANSACTION:
     * - Hook nuốt lỗi, không ném ra ngoài.
     * =====================================================================
     */
    public function test_failed_hook_marks_failed_and_fails_task_run(): void
    {
        $spy = $this->trackerSpy();
        $this->fakeGateway($this->dnaPayload());
        $asset = $this->makeImageAsset();
        $style = $this->makeStyle($asset->id);

        $job = new AnalyzeVisualStyleJob($style->id, 'task-run-uuid');
        $job->failed(new \Modules\CoreAi\Exceptions\AiGatewayException('Rate limit', 'rate_limit'));

        $style->refresh();
        $this->assertSame('failed', $style->status);
        $this->assertTrue(collect($spy->calls)->contains(fn ($c) => $c[0] === 'fail' && $c[2] === 'job_failed'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Không có ảnh tham chiếu → status=failed + fail reference_media_missing
     * =====================================================================
     *
     * INPUT:
     * - VisualStyle không có reference_media_id.
     *
     * OUTPUT:
     * - void; assertions status=failed, tracker fail 'reference_media_missing'.
     *
     * SIDE EFFECT:
     * - Gọi job->handle.
     *
     * EXCEPTION/TRANSACTION:
     * - Không ném ra ngoài.
     * =====================================================================
     */
    public function test_job_requires_reference_media(): void
    {
        $spy = $this->trackerSpy();
        $this->fakeGateway($this->dnaPayload());
        $style = $this->makeStyle(null);

        $job = new AnalyzeVisualStyleJob($style->id, 'task-run-uuid');
        $job->handle(app(TaskTrackerContract::class), app(\Modules\VisualStyle\Services\VisualStyleAnalyzerService::class));

        $style->refresh();
        $this->assertSame('failed', $style->status);
        $this->assertTrue(collect($spy->calls)->contains(fn ($c) => $c[0] === 'fail' && $c[2] === 'reference_media_missing'));
    }
}
