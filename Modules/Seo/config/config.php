<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình riêng của module Seo.
 * =====================================================================
 * Siêu dữ liệu SEO đa hình qua trait HasSeoMeta (title, description, OpenGraph, Canonical).
 * Truy cập bằng config('seo.name'). Mảng merge vào app config khi module
 * được nạp; thêm khóa cấu hình của module tại đây.
 * =====================================================================
 */

return [
    'name' => 'Seo',
];
