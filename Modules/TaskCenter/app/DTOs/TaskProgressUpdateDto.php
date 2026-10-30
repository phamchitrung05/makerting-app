<?php

namespace Modules\TaskCenter\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO cập nhật tiến trình (progress + message + meta).
 * =====================================================================
 */
class TaskProgressUpdateDto
{
    public function __construct(
        public readonly int $progress,
        public readonly ?string $message = null,
        public readonly ?array $meta = null,
    ) {}
}
