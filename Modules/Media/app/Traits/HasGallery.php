<?php

namespace Modules\Media\Traits;

use Illuminate\Support\Collection;
use Modules\Media\DTOs\MediaItemDto;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Trait gán gallery (nhiều media) vào Domain Model
 * qua slot 'gallery'.
 * =====================================================================
 *
 * Kế thừa `HasMediaSlots` với slot mặc định `'gallery'` —
 * `MediaSlotRegistry` khai báo allowedKinds=['image','video'], maxItems=0
 * (không giới hạn), visibility='public', sortable=true.
 *
 * Model `use HasGallery` có sẵn:
 * - gallery()         : Collection<MediaItemDto> đã sort theo sort_order.
 * - galleryUrls()     : mảng URL gốc (hoặc conversion).
 * - galleryCount()    : số media trong gallery.
 * - addToGallery()    : thêm media vào cuối gallery (append sort_order).
 * - setGallery()      : thay toàn bộ gallery bằng list id mới.
 * - removeFromGallery(): xóa 1 media khỏi gallery.
 * - clearGallery()    : xóa toàn bộ gallery.
 * - moveGalleryItem() : đổi vị trí 1 media trong sort_order.
 * - hasGallery()      : bool.
 *
 * Override `$gallerySlot` trong model khi slot khác (vd 'post.gallery').
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : MediaAsset|id[] + slot alias + sortOrder.
 * - OUTPUT: Collection|array|int|bool|MediaAssetUsage.
 * =====================================================================
 */
trait HasGallery
{
    use HasMediaSlots;

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy gallery đã sort theo sort_order
     * =====================================================================
     * INPUT: không. OUTPUT: Collection<MediaItemDto>.
     * =====================================================================
     */
    public function gallery(): Collection
    {
        return $this->mediaItems($this->gallerySlotName());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy mảng URL gallery (gốc hoặc conversion)
     * =====================================================================
     * INPUT: $conversion (mặc định null = gốc; 'thumb'|'featured'|'og').
     * OUTPUT: string[] (bỏ qua item không có URL).
     * =====================================================================
     */
    public function galleryUrls(?string $conversion = null): array
    {
        return $this->gallery()
            ->map(function (MediaItemDto $item) use ($conversion) {
                if ($conversion !== null && isset($item->conversions[$conversion])) {
                    return $item->conversions[$conversion];
                }

                return $item->url;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Số media trong gallery
     * =====================================================================
     * INPUT: không. OUTPUT: int.
     * =====================================================================
     */
    public function galleryCount(): int
    {
        return $this->mediaCount($this->gallerySlotName());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Thêm media vào cuối gallery (append sort_order = max+1)
     * =====================================================================
     * INPUT: MediaAsset|id. OUTPUT: MediaAssetUsage.
     * =====================================================================
     */
    public function addToGallery(MediaAsset|int $asset)
    {
        return $this->attachMedia($asset, $this->gallerySlotName(), null, false);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Thay toàn bộ gallery bằng list id mới (giữ thứ tự)
     * =====================================================================
     * INPUT: int[] $assetIds. OUTPUT: Collection<MediaAssetUsage>.
     * =====================================================================
     */
    public function setGallery(array $assetIds): Collection
    {
        return $this->syncMedia($assetIds, $this->gallerySlotName());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Xóa 1 media khỏi gallery
     * =====================================================================
     * INPUT: MediaAsset|id. OUTPUT: bool.
     * =====================================================================
     */
    public function removeFromGallery(MediaAsset|int $asset): bool
    {
        return $this->detachMedia($asset, $this->gallerySlotName());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Xóa toàn bộ gallery
     * =====================================================================
     * INPUT: không. OUTPUT: int.
     * =====================================================================
     */
    public function clearGallery(): int
    {
        return $this->detachAllMedia($this->gallerySlotName());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đổi vị trí 1 media trong gallery (sort_order)
     * =====================================================================
     * INPUT: MediaAsset|id, $newOrder (int >=0).
     * OUTPUT: bool — true khi cập nhật được usage.
     * =====================================================================
     */
    public function moveGalleryItem(MediaAsset|int $asset, int $newOrder): bool
    {
        $assetId = $asset instanceof MediaAsset ? $asset->id : (int) $asset;
        $newOrder = max(0, (int) $newOrder);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($assetId, $newOrder) {
            // Lấy toàn bộ usage của slot theo thứ tự hiện tại — phải dùng
            // slot ĐÃ RESOLVE từ mediaSlots (giống gallery()/mediaItems()).
            $usages = \Modules\Media\Models\MediaAssetUsage::query()
                ->where('linkable_type', $this->getMorphClass())
                ->where('linkable_id', $this->getKey())
                ->where('slot', $this->resolveSlot($this->gallerySlotName()))
                ->orderBy('sort_order')
                ->lockForUpdate()
                ->get();

            if ($usages->isEmpty()) {
                return false;
            }

            // Tách item cần di chuyển khỏi vị trí cũ.
            $moving = $usages->firstWhere('media_asset_id', $assetId);
            if (! $moving) {
                return false;
            }

            $remaining = $usages->reject(fn ($u) => $u->id === $moving->id)->values();

            // Chèn lại item vào vị trí $newOrder (clamp vào khoảng hợp lệ).
            $insertAt = min($newOrder, $remaining->count());
            $remaining->splice($insertAt, 0, [$moving]);

            // Gán lại sort_order tuần tự 0..n-1 cho toàn bộ — bắt buộc ghi
            // mọi item (không chỉ item lệch) vì item chèn có sort_order cũ.
            foreach ($remaining->values() as $index => $usage) {
                $usage->update(['sort_order' => $index]);
            }

            return true;
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Có media trong gallery không
     * =====================================================================
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function hasGallery(): bool
    {
        return $this->galleryCount() > 0;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tên slot gallery — override bằng $gallerySlot trong model
     * =====================================================================
     * INPUT: không. OUTPUT: string slot.
     * =====================================================================
     */
    protected function gallerySlotName(): string
    {
        return property_exists($this, 'gallerySlot')
            ? (string) $this->gallerySlot
            : 'gallery';
    }
}
