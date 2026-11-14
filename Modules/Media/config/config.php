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

    /* Queue xử lý tác vụ gọi AI tạo ảnh chạy nền (default để Dev dùng chung queue:listen). */
    'ai_image_queue' => env('MEDIA_AI_IMAGE_QUEUE', 'default'),

    /* Thời gian sống (phút) của file ảnh preview AI tạm trên temp_processing + Cache. */
    'ai_preview_ttl_minutes' => (int) env('MEDIA_AI_PREVIEW_TTL_MINUTES', 60),

    /*
     * Danh sách phần mở rộng (đuôi tệp) được phép tải lên, phân theo kind.
     * ScanMediaSecurityAction đọc trực tiếp khóa này khi kiểm tra tệp upload;
     * có thể bật/tắt nhanh từng định dạng hoặc từng nhóm loại tệp tại đây.
     */
    'allowed_upload_extensions' => [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico'],
        'video' => ['mp4', 'mov', 'webm', 'mkv', 'avi', 'm4v', 'mpg', 'mpeg'],
        'audio' => ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac', 'opus'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md', 'json', 'xml'],
        'archive' => ['zip', 'rar', '7z', 'gz', 'tar', 'bz2'],
    ],
];
