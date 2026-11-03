<?php

namespace Modules\WritingStyle\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TaskCenter\Contracts\TaskTrackerContract;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\WritingStyle\Services\WritingStyleAnalyzerService;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Job giải mã DNA văn phong trên queue `ai-text`.
 * =====================================================================
 * Dispatch từ WritingAnalyzeController (Task 9): bản ghi
 * WritingProfileAnalysis (pending) + SystemTaskRun (queued) đã tạo sẵn —
 * job chỉ chạy pipeline analyzer và đồng bộ trạng thái 2 bảng.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - handle() : markRunning -> analyzer->analyze -> complete/fail task run.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : analysisId + taskRunId.
 * - OUTPUT: không — cập nhật WritingProfileAnalysis + SystemTaskRun.
 * =====================================================================
 */
class AnalyzeWritingProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Timeout 300s (khớp config/queue.php ai-text). */
    public int $timeout = 300;

    /** tries=2 theo config ai-text. */
    public int $tries = 2;

    /**
     * INPUT: id của WritingProfileAnalysis (pending) + id SystemTaskRun.
     */
    public function __construct(
        public readonly string $analysisId,
        public readonly string $taskRunId,
    ) {
        // Chạy SAU khi transaction tạo analysis + taskRun đã commit —
        // tránh worker đọc bản ghi trước khi ghi xuống DB (spec Task 7).
        $this->afterCommit();
        $this->onQueue('ai-text');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chạy pipeline giải mã + đồng bộ SystemTaskRun.
     * =====================================================================
     * INPUT: TaskTrackerContract + WritingStyleAnalyzerService.
     * OUTPUT: void — cập nhật status 2 bảng.
     * =====================================================================
     */
    public function handle(
        TaskTrackerContract $tracker,
        WritingStyleAnalyzerService $analyzer,
    ): void {
        $analysis = WritingProfileAnalysis::find($this->analysisId);
        if ($analysis === null) {
            $tracker->fail($this->taskRunId, 'analysis_not_found', 'Không tìm thấy bản ghi phân tích');
            return;
        }

        $tracker->start($this->taskRunId);

        // analyzer->analyze tự markRunning + markSucceeded/markFailed trên
        // bản ghi analysis (chỉ cần gọi, không bắt lỗi — nó đã nuốt Throwable).
        $done = $analyzer->analyze(
            $analysis->reference_text,
            $analysis->source_type,
            $analysis->source_url,
            $analysis->created_by,
            $analysis,
        );

        if ($done->status === WritingProfileAnalysis::STATUS_SUCCEEDED) {
            $tracker->complete($this->taskRunId, [
                'analysis_id'   => $done->id,
                'profile_id'    => $done->draft_profile_id,
                'detected_tier' => $done->detected_tier,
            ]);
        } else {
            $tracker->fail(
                $this->taskRunId,
                $done->error_code ?? 'analyze_failed',
                $done->error_message ?? 'Giải mã văn phong thất bại',
            );
        }
    }    /**
     * =====================================================================
     * CHỨC NĂNG: Hook khi job hết lượt retry ($tries) mà vẫn lỗi.
     * =====================================================================
     * Được gọi sau lần chạy cuối — đánh dấu SystemTaskRun failed vĩnh viễn
     * (analysis đã markFailed trong service trước khi ném).
     * =====================================================================
     */
    public function failed(?\Throwable $e = null): void
    {
        try {
            app(\Modules\TaskCenter\Contracts\TaskTrackerContract::class)->fail(
                $this->taskRunId,
                'job_failed',
                $e?->getMessage() ?? 'Job hết lượt retry',
            );
        } catch (\Throwable) {
            // nuốt — không để hook failed gây thêm lỗi.
        }
    }

}
