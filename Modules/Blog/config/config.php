<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình riêng của module Blog.
 * =====================================================================
 * Quản lý bài viết website, lắp đủ 6 trait chuẩn và pipeline AI viết bài 3 bước kèm quality gate.
 * Truy cập bằng config('blog.name'). Mảng merge vào app config khi module
 * được nạp; thêm khóa cấu hình của module tại đây.
 * =====================================================================
 */

return [
    'name' => 'Blog',
];
