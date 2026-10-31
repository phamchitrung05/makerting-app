<?php

namespace Modules\Media\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu vào khi lưu media do AI sinh ra (VisualStyle/…).
 * =====================================================================
 *
 * Dùng cho `UploadMediaAssetAction::storeGenerated()` khi pipeline AI đã
 * sinh ảnh/video xong và muốn ghi vào `MediaAsset` + file vào Spatie.
 * `source` đánh dấu nguồn (`'ai_generation'`, `'render'`...) + `meta`
 * mang provenance (prompt/model/task_run_id...) lưu vào MediaAsset.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : nội dung file (tmp path/stream), kind, title, meta AI.
 * - OUTPUT: readonly object truyền vào action lưu media.
 * =====================================================================
 */
final readonly class StoreGeneratedMediaDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO lưu media đã sinh.
     * INPUT: tmpPath, kind, title, folder, visibility, channelGroupId,
     *        createdBy, source, meta, altText, fileName, mime, checksum.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $tmpPath,
        public string $kind,
        public string $title,
        public string $folder = 'root',
        public string $visibility = 'public',
        public ?int $channelGroupId = null,
        public ?int $createdBy = null,
        public ?string $source = null,
        public ?array $meta = null,
        public ?string $altText = null,
        public ?string $fileName = null,
        public ?string $mime = null,
        public ?string $checksumSha256 = null,
    ) {}
}
