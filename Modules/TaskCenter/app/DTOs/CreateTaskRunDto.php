<?php

namespace Modules\TaskCenter\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu vào tạo một system_task_runs.
 * =====================================================================
 * Bất biến (readonly). dedupe_key bắt buộc để chống enqueue trùng.
 * =====================================================================
 */
class CreateTaskRunDto
{
    public function __construct(
        public readonly string $dedupeKey,
        public readonly string $module,
        public readonly string $taskType,
        public readonly string $title,
        public readonly ?string $channelGroupId = null,
        public readonly ?string $taskableType = null,
        public readonly ?string $taskableId = null,
        public readonly ?int $initiatedBy = null,
        public readonly ?array $meta = null,
    ) {}
}
