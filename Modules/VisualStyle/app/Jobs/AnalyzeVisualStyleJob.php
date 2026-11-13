<?php

namespace Modules\VisualStyle\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TaskCenter\Contracts\TaskTrackerContract;
use Modules\VisualStyle\Models\VisualStyle;
use Modules\VisualStyle\Services\VisualStyleAnalyzerService;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Job giải mã DNA ảnh trên queue `ai-media`.
 * =====================================================================
 *
 * Dispatch từ ImageDnaAnalyzeController (T7): bản ghi VisualStyle
 * (status=analyzing) + SystemTaskRun (queued) đã tạo sẵn — job chỉ chạy
 * Vision Analyzer và đồng bộ trạng thái 2 bên (`visual_styles.status` +
 * `system_task_runs`).
 *
 * Mô hình trạng thái MIRROR WritingStyle: lỗi TẠM THỜI (network/timeout/
 * rate_limit/provider_error) giữ `analyzing` + ném lại để worker retry;
 * hook failed() (hết lượt tries) mới chốt `failed` vĩnh viễn.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): gắn queue ai-media và yêu cầu dispatch sau commit.
 * - handle(): markRunning -> analyzer->analyzeMedia -> hoàn tất/fail task run.
 * - failed(): chốt trạng thái khi job hết lượt thử hoặc bị lỗi cuối cùng.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : visualStyleId + taskRunId.
 * - OUTPUT: không — cập nhật VisualStyle + SystemTaskRun.
 * =====================================================================
 */
class AnalyzeVisualStyleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Timeout 300s (khớp config/queue.php ai-media). */
    public int $timeout = 300;

    /** tries=2 theo config ai-media. */
    public int $tries = 2;

    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo job với style id và task run id
     * =====================================================================
     *
     * INPUT:
     * - int $visualStyleId — id VisualStyle đang status=analyzing.
     * - string $taskRunId — UUID SystemTaskRun đã tạo.
     *
     * OUTPUT:
     * - instance cấu hình queue/timeout/afterCommit.
     *
     * SIDE EFFECT:
     * - Đọc config visualstyle.analysis.job để set timeout/tries; đăng ký
     *   chạy SAU khi transaction tạo style + taskRun commit (tránh worker
     *   đọc bản ghi trước khi ghi xuống DB).
     * =====================================================================
     */
    public function __construct(
        public readonly int $visualStyleId,
        public readonly string $taskRunId,
    ) {
        $this->timeout = (int) config('visualstyle.analysis.job.timeout', 300);
        $this->tries = (int) config('visualstyle.analysis.job.tries', 2);

        // Chạy SAU khi transaction tạo style + taskRun đã commit —
        // tránh worker đọc bản ghi trước khi ghi xuống DB.
        $this->afterCommit();
        $this->onQueue('ai-media');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chạy Vision Analyzer + đồng bộ SystemTaskRun
     * =====================================================================
     *
     * INPUT: TaskTrackerContract + VisualStyleAnalyzerService (DI container).
     *
     * OUTPUT: void — cập nhật VisualStyle (dna_json/status/analyzed_at +
     *         schema_version + diagnostics) và task run.
     *
     * SIDE EFFECT:
     * - CLI bỏ giới hạn thời gian PHP của tiến trình (giống job WritingStyle).
     * - Ghi dna_json + compiled diagnostics vào visual_styles; gọi tracker
     *   complete/fail.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi transient của analyzer giữ style `analyzing` rồi NÉM LẠI —
     *   exception thoát khỏi handle() để worker retry; khi hết lượt, hook
     *   failed() bên dưới chốt `failed`. KHÔNG bắt lỗi ở đây.
     * =====================================================================
     */
    public function handle(
        TaskTrackerContract $tracker,
        VisualStyleAnalyzerService $analyzer,
    ): void {
        // max_execution_time là đồng hồ của cả process, không phải riêng job.
        if (function_exists('set_time_limit')) {
            @set_time_limit(PHP_SAPI === 'cli' ? 0 : (int) $this->timeout);
        }

        $style = VisualStyle::find($this->visualStyleId);
        if ($style === null) {
            $tracker->fail($this->taskRunId, 'visual_style_not_found', 'Không tìm thấy Visual Style');
            return;
        }

        // start() phải chịu được taskRun chưa ghi vào DB (đơn vị test chưa
        // tạo record). Lỗi start KHÔNG chặn job — style vẫn markAnalyzing
        // và job chạy tiếp; tracker fail/complete vẫn tự tạo record qua
        // CreateTaskRunDto nếu provider cần.
        try {
            $tracker->start($this->taskRunId);
        } catch (\Throwable) {
            // nuốt — start chỉ là update status; job chạy bình thường.
        }
        $style->markAnalyzing();

        if (empty($style->reference_media_id)) {
            $code = 'reference_media_missing';
            $style->markFailed();
            $tracker->fail($this->taskRunId, $code, 'Visual Style chưa gắn ảnh tham chiếu');
            return;
        }

        try {
            $out = $analyzer->analyzeMedia((int) $style->reference_media_id, $style->created_by ? null : null);

            $dna = $out['dna']->toArray();
            $diagnostics = $out['diagnostics'];

            $style->forceFill([
                'dna_json'         => $dna,
                'schema_version'   => '1.0',
                'analysis_meta_json' => $diagnostics,
            ]);
            $style->markAnalyzed();

            // Ghi provenance: DNA do AI tạo (ai_run_id để truy vết) —
            // dùng AiProvenance::record (không setAttribute; chỉ lưu hash).
            if (! empty($diagnostics['ai_run_id']) && $style->dna_json !== null) {
                try {
                    $run = \Modules\CoreAi\Models\AiRun::find($diagnostics['ai_run_id']);
                    if ($run !== null) {
                        \Modules\CoreAi\Models\AiProvenance::record(
                            $style,
                            'dna_json',
                            json_encode($dna),
                            $run,
                            $style->created_by,
                        );
                    }
                } catch (\Throwable) {
                    // Provenance không chặn nghiệp vụ — bỏ qua khi lỗi.
                }
            }

            $tracker->complete($this->taskRunId, [
                'visual_style_id' => $style->id,
                'confidence'      => $dna['confidence'] ?? null,
                'model'           => $diagnostics['model'],
            ]);
        } catch (\Throwable $e) {
            $transient = [
                'network',
                'timeout',
                'rate_limit',
                'provider_error',
            ];
            $code = $e instanceof \Modules\CoreAi\Exceptions\AiGatewayException
                ? $e->errorCode()
                : 'analyzer_error';

            if (in_array($code, $transient, true)) {
                // Giữ status=analyzing, ném lại để worker retry.
                throw $e;
            }

            $style->markFailed();
            $tracker->fail($this->taskRunId, $code, $e->getMessage());
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Hook khi job hết lượt retry ($tries) mà vẫn lỗi
     * =====================================================================
     * Được gọi sau lần chạy CUỐI. Đây là nơi DUY NHẤT chốt `failed` cho lỗi
     * tạm thời: handle() chỉ ném lại giữ `analyzing`, nên nếu hook này không
     * chốt thì style sẽ treo mãi ở `analyzing` và FE poll tới timeout.
     *
     * INPUT: ?Throwable $e — lỗi của lần chạy cuối.
     * OUTPUT: void.
     * SIDE EFFECT: markFailed trên VisualStyle (nếu chưa kết thúc)
     *              + fail SystemTaskRun.
     * EXCEPTION: nuốt mọi Throwable — hook không được gây lỗi thêm.
     * =====================================================================
     */
    public function failed(?\Throwable $e = null): void
    {
        $message = $e?->getMessage() ?? 'Job hết lượt retry';

        try {
            $style = VisualStyle::find($this->visualStyleId);

            // Chỉ chốt khi chưa kết thúc — không ghi đè trạng thái đã xong.
            if ($style !== null && ! in_array($style->status, ['failed', 'archived', 'active'], true)) {
                $style->markFailed();
            }
        } catch (\Throwable) {
            // nuốt — vẫn phải cố fail taskRun bên dưới.
        }

        try {
            app(\Modules\TaskCenter\Contracts\TaskTrackerContract::class)->fail(
                $this->taskRunId,
                'job_failed',
                $message,
            );
        } catch (\Throwable) {
            // nuốt — không để hook failed gây thêm lỗi.
        }
    }
}
