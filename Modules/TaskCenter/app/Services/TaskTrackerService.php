<?php

namespace Modules\TaskCenter\Services;

use Illuminate\Support\Facades\DB;
use Modules\TaskCenter\Contracts\TaskTrackerContract;
use Modules\TaskCenter\DTOs\CreateTaskRunDto;
use Modules\TaskCenter\DTOs\TaskProgressUpdateDto;
use Modules\TaskCenter\Models\SystemTaskRun;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Triển khai theo dõi tiến trình — tạo task, cập nhật
 * progress, đánh dấu trạng thái, hủy; dedupe theo dedupe_key.
 * =====================================================================
 *
 * Mọi module inject TaskTrackerContract -> service này. Tất cả thao tác
 * ghi đều vào bảng system_task_runs; meta_json gộp thêm message mới nhất.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - create()   : firstOrCreate theo dedupe_key (không enqueue trùng).
 * - progress() : cập nhật progress + message + meta.
 * - start()    : running + started_at.
 * - complete() : completed + progress=100 + completed_at.
 * - partial()  : partial + completed_at.
 * - fail()     : failed + error + completed_at.
 * - cancel()   : cancelled + completed_at.
 * - get()      : lấy theo id.
 * - applyMeta(): gộp meta_json với message mới nhất.
 * INPUT/OUTPUT: điều khiển vòng đời SystemTaskRun.
 * =====================================================================
 */
class TaskTrackerService implements TaskTrackerContract
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo task; nếu dedupe_key đã có -> trả bản ghi cũ
     * =====================================================================
     * INPUT: CreateTaskRunDto. OUTPUT: SystemTaskRun (mới hoặc cũ).
     * SIDE EFFECT: INSERT khi dedupe_key chưa có.
     * =====================================================================
     */
    public function create(CreateTaskRunDto $dto): SystemTaskRun
    {
        return SystemTaskRun::firstOrCreate(
            ['dedupe_key' => $dto->dedupeKey],
            [
                'module' => $dto->module,
                'task_type' => $dto->taskType,
                'title' => $dto->title,
                'status' => SystemTaskRun::STATUS_QUEUED,
                'progress' => 0,
                'channel_group_id' => $dto->channelGroupId,
                'taskable_type' => $dto->taskableType,
                'taskable_id' => $dto->taskableId,
                'initiated_by' => $dto->initiatedBy,
                'meta_json' => $dto->meta,
            ],
        );
    }

    /**
     * CHỨC NĂNG: Cập nhật progress (0-100) + message vào meta_json.
     * INPUT: $id, TaskProgressUpdateDto. OUTPUT: SystemTaskRun.
     * SIDE EFFECT: UPDATE progress/meta_json.
     */
    public function progress(string $id, TaskProgressUpdateDto $dto): SystemTaskRun
    {
        $task = $this->get($id);
        $meta = $this->applyMeta($task->meta_json, $dto->message, $dto->meta);
        $task->update([
            'progress' => max(0, min(100, $dto->progress)),
            'meta_json' => $meta,
        ]);

        return $task;
    }

    /**
     * CHỨC NĂNG: Đánh dấu running (started_at=now, giữ progress hiện tại).
     * INPUT: $id. OUTPUT: SystemTaskRun. SIDE EFFECT: UPDATE status.
     */
    public function start(string $id): SystemTaskRun
    {
        $task = $this->get($id);
        $task->update([
            'status' => SystemTaskRun::STATUS_RUNNING,
            'started_at' => $task->started_at ?? now(),
        ]);

        return $task;
    }

    /**
     * CHỨC NĂNG: Đánh dấu completed (progress=100, completed_at=now).
     * INPUT: $id, meta bổ sung. OUTPUT: SystemTaskRun.
     */
    public function complete(string $id, ?array $meta = null): SystemTaskRun
    {
        $task = $this->get($id);
        $task->update([
            'status' => SystemTaskRun::STATUS_COMPLETED,
            'progress' => 100,
            'completed_at' => now(),
            'meta_json' => $this->applyMeta($task->meta_json, null, $meta),
        ]);

        return $task;
    }

    /**
     * CHỨC NĂNG: Đánh dấu partial (kết thúc một phần).
     * INPUT: $id, meta bổ sung. OUTPUT: SystemTaskRun.
     */
    public function partial(string $id, ?array $meta = null): SystemTaskRun
    {
        $task = $this->get($id);
        $task->update([
            'status' => SystemTaskRun::STATUS_PARTIAL,
            'completed_at' => now(),
            'meta_json' => $this->applyMeta($task->meta_json, null, $meta),
        ]);

        return $task;
    }

    /**
     * CHỨC NĂNG: Đánh dấu failed + error_code/message.
     * INPUT: $id, $errorCode, $errorMessage. OUTPUT: SystemTaskRun.
     */
    public function fail(string $id, string $errorCode, string $errorMessage): SystemTaskRun
    {
        $task = $this->get($id);
        $task->update([
            'status' => SystemTaskRun::STATUS_FAILED,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);

        return $task;
    }

    /**
     * CHỨC NĂNG: Hủy task đang queued/running.
     * INPUT: $id. OUTPUT: SystemTaskRun.
     */
    public function cancel(string $id): SystemTaskRun
    {
        $task = $this->get($id);
        $task->update([
            'status' => SystemTaskRun::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        return $task;
    }

    /**
     * CHỨC NĂNG: Lấy task theo id (404 nếu không có).
     * INPUT: $id. OUTPUT: SystemTaskRun.
     */
    public function get(string $id): SystemTaskRun
    {
        return SystemTaskRun::findOrFail($id);
    }

    /* ======================== helpers ======================== */

    /**
     * CHỨC NĂNG: Gộp meta_json — ghi last_message + merge meta mới.
     * INPUT: meta hiện tại, message, meta bổ sung. OUTPUT: array meta mới.
     */
    private function applyMeta(?array $meta, ?string $message, ?array $extra): array
    {
        $meta = $meta ?? [];
        if ($message !== null) { $meta['last_message'] = $message; }
        if (! empty($extra)) { $meta = array_merge($meta, $extra); }

        return $meta;
    }
}
