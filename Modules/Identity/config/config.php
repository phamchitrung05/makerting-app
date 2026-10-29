<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình riêng của module Identity.
 * =====================================================================
 * Xác thực nhân sự nội bộ (Sanctum), phân quyền động và quản lý Nhóm kênh ChannelGroup.
 * Truy cập bằng config('identity.name'). Mảng merge vào app config khi module
 * được nạp; thêm khóa cấu hình của module tại đây.
 * =====================================================================
 */

return [
    'name' => 'Identity',
];
