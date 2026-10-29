<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình riêng của module CoreAi.
 * =====================================================================
 * Trạm điều phối AI duy nhất (Text/Structured/Vision/Image/Audio), quản lý Providers/Models và AiRun.
 * Truy cập bằng config('coreai.name'). Mảng merge vào app config khi module
 * được nạp; thêm khóa cấu hình của module tại đây.
 * =====================================================================
 */

return [
    'name' => 'CoreAi',
];
