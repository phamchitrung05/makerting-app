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

    /*
     * Disk Spatie MediaLibrary theo visibility:
     * - media_public  : file public (URL trực tiếp /media/...)
     * - media_private : file private (chỉ đọc qua stream endpoint có auth)
     * Ghi chú: UploadMediaAssetAction/StoreGeneratedMediaAction tự chọn
     * theo visibility; 'disk' ở đây chỉ là fallback khi không khai báo.
     */
    'disk' => null,

    /* Giới hạn upload mặc định (bytes) — override bằng option 'max_bytes'. */
    'max_upload_bytes' => 50 * 1024 * 1024,
];
