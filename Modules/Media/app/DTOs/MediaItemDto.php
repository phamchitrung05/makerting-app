<?php

namespace Modules\Media\DTOs;

use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đại diện một media asset dùng trả về UI/service.
 * =====================================================================
 *
 * Gói metadata MediaAsset + URL gốc/conversions + trạng thái file Spatie
 * để controller/resource trả JSON đồng nhất. Không chứa quan hệ Eloquent.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : MediaAsset + media gốc Spatie (tùy chọn).
 * - OUTPUT: readonly object + toArray() serialize cho API.
 * =====================================================================
 */
final readonly class MediaItemDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO item media.
     * INPUT: toàn bộ trường metadata + urls + spatie.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public int $id,
        public string $kind,
        public string $visibility,
        public string $folder,
        public string $title,
        public ?string $altText,
        public ?string $url,
        public ?string $mime,
        public ?int $size,
        public ?int $width,
        public ?int $height,
        public ?int $durationMs,
        public ?int $channelGroupId,
        public ?int $createdBy,
        public array $conversions = [],
        public ?string $checksumSha256 = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: tạo DTO từ MediaAsset (tự lấy file Spatie 'library')
     * =====================================================================
     * INPUT: Modules\Media\Models\MediaAsset.
     * OUTPUT: MediaItemDto.
     * SIDE EFFECT: đọc getFirstMedia() + getFullUrl() (có thể truy cập storage).
     * =====================================================================
     */
    public static function fromModel(MediaAsset $asset): self
    {
        $media = $asset->getFirstMedia(MediaAsset::COLLECTION_LIBRARY);

        $conversions = [];
        if ($media && $asset->visibility === MediaAsset::VISIBILITY_PUBLIC) {
            // Gom URL conversions có sẵn (thumb/featured/og...) để UI dùng.
            // Chỉ cho asset public; private phải qua endpoint stream có quyền.
            foreach ($media->getGeneratedConversions() ?? [] as $name => $generated) {
                if ($generated) {
                    $conversions[$name] = $media->getUrl($name);
                }
            }
        }

        return new self(
            id: $asset->id,
            kind: $asset->kind,
            visibility: $asset->visibility,
            folder: $asset->folder,
            title: $asset->title,
            altText: $asset->alt_text,
            url: $asset->visibility === MediaAsset::VISIBILITY_PUBLIC
                ? $media?->getFullUrl()
                : null, // private: không lộ URL trực tiếp — dùng /stream
            mime: $media?->mime_type,
            size: $media?->size,
            width: $asset->width,
            height: $asset->height,
            durationMs: $asset->duration_ms,
            channelGroupId: $asset->channel_group_id,
            createdBy: $asset->created_by,
            conversions: $conversions,
            checksumSha256: $asset->checksum_sha256,
            createdAt: $asset->created_at?->toIso8601String(),
            updatedAt: $asset->updated_at?->toIso8601String(),
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: serialize DTO thành mảng JSON thân thiện UI
     * =====================================================================
     * INPUT: không. OUTPUT: array thuần (camelCase).
     * SIDE EFFECT: không.
     * =====================================================================
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'visibility' => $this->visibility,
            'folder' => $this->folder,
            'title' => $this->title,
            'altText' => $this->altText,
            'url' => $this->url,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'durationMs' => $this->durationMs,
            'channelGroupId' => $this->channelGroupId,
            'createdBy' => $this->createdBy,
            'conversions' => $this->conversions,
            'checksumSha256' => $this->checksumSha256,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
