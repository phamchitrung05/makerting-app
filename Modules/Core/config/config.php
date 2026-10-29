<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình riêng của module Core.
 * =====================================================================
 * Hạt nhân nền tảng: BaseResponse, hệ thống Slugs, CredentialVault, OAuthPool, chống SSRF, Idempotency và AccountRateLimiter.
 * Truy cập bằng config('core.name'). Mảng merge vào app config khi module
 * được nạp; thêm khóa cấu hình của module tại đây.
 * =====================================================================
 */

return [
    'name' => 'Core',
];
