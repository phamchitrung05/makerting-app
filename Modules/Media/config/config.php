<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình riêng của module Media.
 * =====================================================================
 * Quản lý media 2 lớp (Spatie MediaAsset + usages), MediaSlotRegistry và bộ trait media dùng chung.
 * Truy cập bằng config('media.name'). Mảng merge vào app config khi module
 * được nạp; thêm khóa cấu hình của module tại đây.
 * =====================================================================
 */

return [
    'name' => 'Media',
];
