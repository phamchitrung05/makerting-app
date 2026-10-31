<?php

namespace Modules\Media\Traits;

use Modules\Media\DTOs\MediaItemDto;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Trait gán thumbnail cho Domain Model qua slot 'thumbnail'.
 * =====================================================================
 *
 * Kế thừa `HasMediaSlots` với slot mặc định `'thumbnail'` —
 * `MediaSlotRegistry` đã khai báo allowedKinds=['image'], maxItems=1,
 * visibility='public'.
 *
 * Model `use HasThumbnail` có sẵn:
 * - thumbnail()      : MediaItemDto|null ảnh đại diện đầu tiên.
 * - thumbnailUrl()   : URL gốc (hoặc conversion 'thumb' khi truyền).
 * - thumbnailAlt()   : alt_text.
 * - setThumbnail()   : attach 1 asset vào slot thumbnail.
 * - clearThumbnail() : gỡ toàn bộ thumbnail.
 * - hasThumbnail()   : bool.
 *
 * Override `$thumbnailSlot` trong model khi slot khác (vd 'post.cover').
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : MediaAsset|id + slot alias.
 * - OUTPUT: MediaItemDto|string|bool|null.
 * =====================================================================
 */
trait HasThumbnail
{
    use HasMediaSlots;

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy thumbnail của model (ảnh đầu slot)
     * =====================================================================
     * INPUT: không. OUTPUT: ?MediaItemDto.
     * =====================================================================
     */
    public function thumbnail(): ?MediaItemDto
    {
        return $this->mediaItem($this->thumbnailSlotName());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: URL thumbnail (gốc hoặc conversion)
     * =====================================================================
     * INPUT: $conversion (mặc định null = URL gốc; 'thumb'|'featured'|'og').
     * OUTPUT: ?string.
     * =====================================================================
     */
    public function thumbnailUrl(?string $conversion = null): ?string
    {
        $item = $this->thumbnail();
        if (! $item) {
            return null;
        }

        if ($conversion !== null && isset($item->conversions[$conversion])) {
            return $item->conversions[$conversion];
        }

        return $item->url;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: alt_text của thumbnail (fallback title)
     * =====================================================================
     * INPUT: không. OUTPUT: ?string.
     * =====================================================================
     */
    public function thumbnailAlt(): ?string
    {
        $item = $this->thumbnail();

        return $item?->altText ?? $item?->title;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gán 1 asset làm thumbnail (thay thế cái cũ)
     * =====================================================================
     * INPUT: MediaAsset|id. OUTPUT: MediaAssetUsage.
     * =====================================================================
     */
    public function setThumbnail(MediaAsset|int $asset)
    {
        return $this->attachMedia($asset, $this->thumbnailSlotName(), null, true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gỡ toàn bộ thumbnail
     * =====================================================================
     * INPUT: không. OUTPUT: int (số usage đã xóa).
     * =====================================================================
     */
    public function clearThumbnail(): int
    {
        return $this->detachAllMedia($this->thumbnailSlotName());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Có thumbnail không
     * =====================================================================
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function hasThumbnail(): bool
    {
        return $this->mediaCount($this->thumbnailSlotName()) > 0;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tên slot thumbnail — override bằng $thumbnailSlot trong model
     * =====================================================================
     * INPUT: không. OUTPUT: string slot.
     * =====================================================================
     */
    protected function thumbnailSlotName(): string
    {
        return property_exists($this, 'thumbnailSlot')
            ? (string) $this->thumbnailSlot
            : 'thumbnail';
    }
}
