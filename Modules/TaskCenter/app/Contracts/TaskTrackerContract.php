<?php

namespace Modules\TaskCenter\Contracts;

use Modules\TaskCenter\DTOs\CreateTaskRunDto;
use Modules\TaskCenter\DTOs\TaskProgressUpdateDto;
use Modules\TaskCenter\Models\SystemTaskRun;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng theo dõi tiến trình mà mọi module dùng để báo
 * cáo về TaskCenter.
 * =====================================================================
 *
 * Mọi module có tác vụ ngầm inject TaskTrackerContract (bind tới
 * TaskTrackerService) để: tạo task, cập nhật progress, kết thúc.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - create()         : tạo task (dedupe theo dedupe_key).
 * - progress()       : cập nhật % + thông điệp hiển thị.
 * - start()          : đánh dấu running + started_at.
 * - complete()       : kết thúc completed (+progress=100).
 * - partial()        : kết thúc partial.
 * - fail()           : kết thúc failed + error_code/message.
 * - cancel()         : hủy task.
 * - get()            : lấy task theo id.
 * INPUT/OUTPUT: điều khiển vòng đời system_task_runs.
 * =====================================================================
 */
interface TaskTrackerContract
{
    /**
     * CHỨC NĂNG: Tạo một bản ghi task. Nếu dedupe_key đã tồn tại -> trả
     * về bản ghi cũ (không tạo trùng), kèm cờ 'existing'.
     * INPUT: CreateTaskRunDto. OUTPUT: SystemTaskRun.
     */
    public function create(CreateTaskRunDto $dto): SystemTaskRun;

    /**
     * CHỨC NĂNG: Cập nhật progress (0-100) + message ngắn vào meta_json.
     * INPUT: task id string, TaskProgressUpdateDto. OUTPUT: SystemTaskRun.
     */
    public function progress(string $id, TaskProgressUpdateDto $dto): SystemTaskRun;

    /**
     * CHỨC NĂNG: Đánh dấu task chuyển sang running (started_at=now).
     * INPUT: task id. OUTPUT: SystemTaskRun.
     */
    public function start(string $id): SystemTaskRun;

    /**
     * CHỨC NĂNG: Đánh dấu task completed (progress=100, completed_at=now).
     * INPUT: task id, meta bổ sung (nullable). OUTPUT: SystemTaskRun.
     */
    public function complete(string $id, ?array $meta = null): SystemTaskRun;

    /**
     * CHỨC NĂNG: Đánh dấu task kết thúc một phần (partial).
     * INPUT: task id, meta bổ sung. OUTPUT: SystemTaskRun.
     */
    public function partial(string $id, ?array $meta = null): SystemTaskRun;

    /**
     * CHỨC NĂNG: Đánh dấu task failed + error_code/message.
     * INPUT: task id, error_code, error_message. OUTPUT: SystemTaskRun.
     */
    public function fail(string $id, string $errorCode, string $errorMessage): SystemTaskRun;

    /**
     * CHỨC NĂNG: Hủy một task đang queued/running.
     * INPUT: task id. OUTPUT: SystemTaskRun.
     */
    public function cancel(string $id): SystemTaskRun;

    /**
     * CHỨC NĂNG: Lấy một task theo id.
     * INPUT: task id. OUTPUT: SystemTaskRun.
     */
    public function get(string $id): SystemTaskRun;
}
