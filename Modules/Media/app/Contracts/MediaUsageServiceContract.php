<?php

namespace Modules\Media\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Media\DTOs\MediaItemDto;
use Modules\Media\Models\MediaAsset;
use Modules\Media\Models\MediaAssetUsage;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng quản lý gán/gỡ media vào Domain Model theo slot.
 * =====================================================================
 *
 * Mọi module cần gắn media (Post.thumbnail, SocialVariant.media,
 * VideoProject.output, VisualStyle.reference...) inject
 * `MediaUsageServiceContract` (bind tới `MediaUsageService`) thay vì tự
 * thao tác bảng `media_asset_usages`.
 *
 * Slot được khai báo trong `MediaSlotRegistry` — service kiểm tra:
 * - `allowedKinds`: kind của asset có hợp lệ cho slot.
 * - `maxItems`    : số media tối đa (0 = không giới hạn).
 * - `visibility`  : ép visibility bắt buộc (null = giữ nguyên asset).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - attach()          : gán media vào model theo slot (cập nhật sort_order).
 * - attachMany()      : gán nhiều media một lượt (thay thế toàn bộ slot).
 * - detach()          : gỡ một media khỏi slot.
 * - detachAll()       : gỡ toàn bộ media khỏi slot.
 * - sync()            : đồng bộ slot với danh sách media id + sort.
 * - usages()          : lấy usages của model theo slot.
 * - items()           : lấy MediaItemDto[] của model theo slot.
 * - first()           : lấy media đầu tiên của slot.
 * - count()           : đếm số media trong slot.
 * - openReadStream()  : mở stream file vật lý của asset (cho FFmpeg/upload).
 * - validateAttach()  : kiểm tra trước khi attach (kind/count/visibility).
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Model (Post/…), MediaAsset|id, slot string, options.
 * - OUTPUT: MediaAssetUsage|MediaItemDto|stream|bool|int.
 * =====================================================================
 */
interface MediaUsageServiceContract
{
    /**
     * CHỨC NĂNG: Gán một MediaAsset vào model theo slot.
     * INPUT: $model (morph target), $asset (MediaAsset|id), $slot,
     *        $sortOrder (tùy chọn), $replaceExisting (mặc định true khi
     *        slot maxItems=1 — thay thế media cũ).
     * OUTPUT: MediaAssetUsage vừa tạo/cập nhật.
     * EXCEPTION: khi slot không hợp lệ / kind bị chặn / vượt maxItems.
     */
    public function attach(
        Model $model,
        MediaAsset|int $asset,
        string $slot,
        ?int $sortOrder = null,
        bool $replaceExisting = true,
    ): MediaAssetUsage;

    /**
     * CHỨC NĂNG: Gán nhiều media một lượt vào slot (theo thứ tự mảng).
     * INPUT: $model, array<int,MediaAsset|int> $assets, $slot,
     *        $replaceExisting (mặc định true = thay toàn bộ).
     * OUTPUT: Collection<MediaAssetUsage>.
     */
    public function attachMany(
        Model $model,
        array $assets,
        string $slot,
        bool $replaceExisting = true,
    ): Collection;

    /**
     * CHỨC NĂNG: Gỡ một media khỏi slot của model.
     * INPUT: $model, $asset (MediaAsset|id), $slot.
     * OUTPUT: bool — true khi có usage bị xóa.
     */
    public function detach(Model $model, MediaAsset|int $asset, string $slot): bool;

    /**
     * CHỨC NĂNG: Gỡ toàn bộ media khỏi slot của model.
     * INPUT: $model, $slot. OUTPUT: int — số usage đã xóa.
     */
    public function detachAll(Model $model, string $slot): int;

    /**
     * CHỨC NĂNG: Đồng bộ slot với danh sách id (giữ thứ tự, set sort_order).
     * INPUT: $model, array<int> $assetIds, $slot.
     * OUTPUT: Collection<MediaAssetUsage> — usage cuối cùng sau sync.
     */
    public function sync(Model $model, array $assetIds, string $slot): Collection;

    /**
     * CHỨC NĂNG: Lấy usages của model theo slot (mặc định sort_order ASC).
     * INPUT: $model, $slot. OUTPUT: Collection<MediaAssetUsage>.
     */
    public function usages(Model $model, string $slot): Collection;

    /**
     * CHỨC NĂNG: Lấy media của slot dưới dạng DTO (kèm URL conversions).
     * INPUT: $model, $slot. OUTPUT: Collection<MediaItemDto>.
     */
    public function items(Model $model, string $slot): Collection;

    /**
     * CHỨC NĂNG: Lấy media đầu tiên của slot (dùng cho thumbnail...).
     * INPUT: $model, $slot. OUTPUT: ?MediaItemDto.
     */
    public function first(Model $model, string $slot): ?MediaItemDto;

    /**
     * CHỨC NĂNG: Đếm số media đang gán vào slot.
     * INPUT: $model, $slot. OUTPUT: int.
     */
    public function count(Model $model, string $slot): int;

    /**
     * CHỨC NĂNG: Mở stream đọc file vật lý của asset (cho FFmpeg/upload).
     * INPUT: $asset (MediaAsset|id). OUTPUT: resource PHP stream.
     * EXCEPTION: khi asset không có file Spatie hoặc thiếu quyền đọc.
     */
    public function openReadStream(MediaAsset|int $asset);

    /**
     * CHỨC NĂNG: Kiểm tra trước khi attach (kind/count) — không ghi DB.
     * INPUT: $model, $asset, $slot, $incoming (mặc định 1).
     * OUTPUT: void. EXCEPTION: InvalidArgumentException khi vi phạm slot.
     */
    public function validateAttach(
        Model $model,
        MediaAsset|int $asset,
        string $slot,
        int $incoming = 1,
    ): void;
}
