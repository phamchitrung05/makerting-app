<?php

namespace Modules\Media\Traits;

use Illuminate\Support\Collection;
use Modules\Media\Contracts\MediaUsageServiceContract;
use Modules\Media\DTOs\MediaItemDto;
use Modules\Media\Models\MediaAsset;
use Modules\Media\Models\MediaAssetUsage;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Trait gốc gán media vào Domain Model theo `slot`.
 * =====================================================================
 *
 * Model `use HasMediaSlots` nhận bộ hàm attach/detach/sync/items qua
 * `MediaUsageServiceContract` — slot được định nghĩa trong `mediaSlots()`
 * (override trong model) hoặc mặc định `default`.
 *
 * Các trait chuyên biệt `HasThumbnail`/`HasGallery`/`HasVideoAsset`
 * mở rộng trait này với slot + kind mặc định tương ứng.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - mediaSlots()          : map alias -> slot string (override trong model).
 * - resolveSlot()         : nhận alias -> slot (fallback 'default').
 * - attachMedia()         : gán 1 asset vào slot.
 * - attachMediaMany()     : gán nhiều vào slot.
 * - detachMedia()         : gỡ 1 asset khỏi slot.
 * - detachAllMedia()      : gỡ toàn bộ slot.
 * - syncMedia()           : đồng bộ slot với list id.
 * - mediaUsages()         : usages của slot (sort_order ASC).
 * - mediaItems()          : MediaItemDto[] của slot.
 * - mediaItem()           : item đầu slot.
 * - mediaCount()          : số media trong slot.
 * - mediaService()        : resolve MediaUsageServiceContract.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : model có trait + MediaAsset|id + slot/alias.
 * - OUTPUT: MediaAssetUsage|Collection|MediaItemDto|bool|int.
 * =====================================================================
 */
trait HasMediaSlots
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Map alias -> slot name thật trong MediaSlotRegistry
     * =====================================================================
     * Model override để đặt slot riêng, vd:
     *   protected array $mediaSlots = [
     *       'thumbnail' => 'post.thumbnail',
     *       'gallery'   => 'post.gallery',
     *       'video'     => 'post.video',
     *   ];
     * INPUT: không. OUTPUT: array<string,string>.
     * =====================================================================
     */
    protected function mediaSlots(): array
    {
        return property_exists($this, 'mediaSlots')
            ? (array) $this->mediaSlots
            : [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve alias -> slot thật trong registry
     * =====================================================================
     * - Alias khớp mediaSlots() -> slot tương ứng.
     * - Alias là slot trực tiếp -> giữ nguyên.
     * - Mặc định -> 'default'.
     *
     * INPUT: $alias (vd 'thumbnail'|'post.thumbnail'|'default').
     * OUTPUT: slot string.
     * =====================================================================
     */
    protected function resolveSlot(?string $alias): string
    {
        if ($alias === null || $alias === '') {
            return 'default';
        }

        $slots = $this->mediaSlots();
        if (isset($slots[$alias])) {
            return $slots[$alias];
        }

        return $alias;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gán một MediaAsset vào slot
     * =====================================================================
     * INPUT: $asset (MediaAsset|id), $slotAlias, $sortOrder?, $replaceExisting?
     * OUTPUT: MediaAssetUsage.
     * =====================================================================
     */
    public function attachMedia(
        MediaAsset|int $asset,
        ?string $slotAlias = null,
        ?int $sortOrder = null,
        bool $replaceExisting = true,
    ): MediaAssetUsage {
        return $this->mediaService()->attach(
            $this, $asset, $this->resolveSlot($slotAlias), $sortOrder, $replaceExisting,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gán nhiều media vào slot (giữ thứ tự)
     * =====================================================================
     * INPUT: array<MediaAsset|int>, $slotAlias, $replaceExisting.
     * OUTPUT: Collection<MediaAssetUsage>.
     * =====================================================================
     */
    public function attachMediaMany(
        array $assets,
        ?string $slotAlias = null,
        bool $replaceExisting = true,
    ): Collection {
        return $this->mediaService()->attachMany(
            $this, $assets, $this->resolveSlot($slotAlias), $replaceExisting,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gỡ một media khỏi slot
     * =====================================================================
     * INPUT: $asset, $slotAlias. OUTPUT: bool.
     * =====================================================================
     */
    public function detachMedia(MediaAsset|int $asset, ?string $slotAlias = null): bool
    {
        return $this->mediaService()->detach($this, $asset, $this->resolveSlot($slotAlias));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gỡ toàn bộ media khỏi slot
     * =====================================================================
     * INPUT: $slotAlias. OUTPUT: int (số usage đã xóa).
     * =====================================================================
     */
    public function detachAllMedia(?string $slotAlias = null): int
    {
        return $this->mediaService()->detachAll($this, $this->resolveSlot($slotAlias));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đồng bộ slot với danh sách id mới (giữ thứ tự)
     * =====================================================================
     * INPUT: int[] $assetIds, $slotAlias. OUTPUT: Collection<MediaAssetUsage>.
     * =====================================================================
     */
    public function syncMedia(array $assetIds, ?string $slotAlias = null): Collection
    {
        return $this->mediaService()->sync($this, $assetIds, $this->resolveSlot($slotAlias));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy usages của slot (sort_order ASC)
     * =====================================================================
     * INPUT: $slotAlias. OUTPUT: Collection<MediaAssetUsage>.
     * =====================================================================
     */
    public function mediaUsages(?string $slotAlias = null): Collection
    {
        return $this->mediaService()->usages($this, $this->resolveSlot($slotAlias));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy media của slot dưới dạng DTO (kèm URL conversions)
     * =====================================================================
     * INPUT: $slotAlias. OUTPUT: Collection<MediaItemDto>.
     * =====================================================================
     */
    public function mediaItems(?string $slotAlias = null): Collection
    {
        return $this->mediaService()->items($this, $this->resolveSlot($slotAlias));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy media đầu slot (thumbnail, cover, video chính)
     * =====================================================================
     * INPUT: $slotAlias. OUTPUT: ?MediaItemDto.
     * =====================================================================
     */
    public function mediaItem(?string $slotAlias = null): ?MediaItemDto
    {
        return $this->mediaService()->first($this, $this->resolveSlot($slotAlias));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đếm số media đang gán vào slot
     * =====================================================================
     * INPUT: $slotAlias. OUTPUT: int.
     * =====================================================================
     */
    public function mediaCount(?string $slotAlias = null): int
    {
        return $this->mediaService()->count($this, $this->resolveSlot($slotAlias));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve MediaUsageServiceContract từ container
     * =====================================================================
     * INPUT: không. OUTPUT: MediaUsageServiceContract.
     * =====================================================================
     */
    protected function mediaService(): MediaUsageServiceContract
    {
        return app(MediaUsageServiceContract::class);
    }
}
