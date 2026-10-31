<?php

namespace Modules\Media\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\Media\Contracts\MediaUsageServiceContract;
use Modules\Media\DTOs\MediaItemDto;
use Modules\Media\DTOs\MediaSlotRule;
use Modules\Media\Models\MediaAsset;
use Modules\Media\Models\MediaAssetUsage;
use Modules\Media\Registries\MediaSlotRegistry;
use RuntimeException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Triển khai quản lý gán/gỡ media theo slot qua bảng
 * `media_asset_usages` — dùng chung cho mọi Domain Model.
 * =====================================================================
 *
 * Inject `MediaSlotRegistry` để đọc quy tắc slot (allowedKinds/maxItems/
 * visibility). Mọi thao tác ghi chạy trong transaction; khi slot
 * `maxItems=1`, attach mới mặc định thay thế media cũ (trừ khi
 * `replaceExisting=false`).
 *
 * `openReadStream()` trả PHP stream — KHÔNG đọc toàn bộ file vào RAM —
 * cho SocialPublisher upload lên Facebook/YouTube, hoặc VideoStudio nạp
 * vào FFmpeg.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - attach()          : attach single + áp sort_order tự tăng.
 * - attachMany()      : lặp attach() theo thứ tự mảng.
 * - detach()          : xóa usage theo (model, asset, slot).
 * - detachAll()       : xóa mọi usage của slot.
 * - sync()            : thay toàn bộ slot bằng danh sách id mới.
 * - usages()          : query usages theo sort_order.
 * - items()           : map usages -> MediaItemDto.
 * - first()           : items()->first().
 * - count()           : đếm usages.
 * - openReadStream()  : Storage::disk(...)->readStream(...).
 * - validateAttach()  : kiểm tra kind + limit trước ghi.
 * - applyVisibility() : ép visibility asset theo slot.
 * - morphKey()        : morph_type alias hoặc class.
 * - nextSortOrder()   : sort_order kế tiếp trong slot.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Model, MediaAsset|id, slot, sortOrder, replaceExisting.
 * - OUTPUT: MediaAssetUsage|Collection|MediaItemDto|stream|bool|int.
 * =====================================================================
 */
class MediaUsageService implements MediaUsageServiceContract
{
    public function __construct(
        private readonly MediaSlotRegistry $slots,
    ) {}
    /**
     * =====================================================================
     * CHỨC NĂNG: Gán một MediaAsset vào model theo slot
     * =====================================================================
     * - Validate kind + maxItems theo slot.
     * - Khi maxItems=1 và replaceExisting=true: xóa usage cũ trước.
     * - Ép visibility của slot lên asset nếu slot khai báo visibility.
     * - sortOrder null -> tự động gán = max(sort_order)+1.
     *
     * INPUT: $model, $asset, $slot, $sortOrder, $replaceExisting.
     * OUTPUT: MediaAssetUsage.
     * EXCEPTION: InvalidArgumentException khi vi phạm quy tắc slot.
     * SIDE EFFECT: DB transaction ghi media_asset_usages (+ cập nhật
     *             visibility trên media_assets nếu slot ép).
     * =====================================================================
     */
    public function attach(
        Model $model,
        MediaAsset|int $asset,
        string $slot,
        ?int $sortOrder = null,
        bool $replaceExisting = true,
    ): MediaAssetUsage {
        $this->assertModelHasMediaSlots($model);
        $asset = $this->resolveAsset($asset);
        $rule = $this->resolveRule($slot);

        return DB::transaction(function () use ($model, $asset, $slot, $sortOrder, $replaceExisting, $rule) {
            $existing = $this->usagesQuery($model, $slot)->lockForUpdate()->count();

            // Khi maxItems=1 và replaceExisting -> xóa usage cũ trước.
            if ($replaceExisting && $rule->hasLimit() && $rule->maxItems === 1 && $existing > 0) {
                $this->usagesQuery($model, $slot)->delete();
                $existing = 0;
            }

            $this->assertAttachable($model, $asset, $rule, $existing, 1);
            $this->applyVisibility($asset, $rule);

            /** @var MediaAssetUsage $usage */
            $usage = MediaAssetUsage::updateOrCreate(
                [
                    'media_asset_id' => $asset->id,
                    'linkable_type' => $this->morphKey($model),
                    'linkable_id' => $model->getKey(),
                    'slot' => $slot,
                ],
                [
                    'sort_order' => $sortOrder ?? $this->nextSortOrder($model, $slot),
                ],
            );

            return $usage;
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gán nhiều media vào slot (giữ thứ tự mảng)
     * =====================================================================
     * INPUT: $model, array<int,MediaAsset|int>, $slot, $replaceExisting.
     * OUTPUT: Collection<MediaAssetUsage>.
     * SIDE EFFECT: DB transaction; replaceExisting=true -> clear slot trước.
     * =====================================================================
     */
    public function attachMany(
        Model $model,
        array $assets,
        string $slot,
        bool $replaceExisting = true,
    ): Collection {
        $this->assertModelHasMediaSlots($model);
        $rule = $this->resolveRule($slot);

        return DB::transaction(function () use ($model, $assets, $slot, $replaceExisting, $rule) {
            if ($replaceExisting) {
                $this->usagesQuery($model, $slot)->delete();
            }

            $existing = $this->usagesQuery($model, $slot)->count();
            $this->assertAttachable($model, null, $rule, $existing, count($assets));

            $out = new Collection();
            $seen = [];

            foreach (array_values($assets) as $index => $asset) {
                $asset = $this->resolveAsset($asset);

                // Bỏ qua id trùng trong cùng request — unique index trên
                // (media_asset_id, linkable_type, linkable_id, slot) sẽ nổ
                // Duplicate entry 500 nếu create() lặp.
                if (isset($seen[$asset->id])) {
                    continue;
                }
                $seen[$asset->id] = true;

                $this->assertAttachable($model, $asset, $rule, $existing, 1);
                $this->applyVisibility($asset, $rule);

                // updateOrCreate thay create: khi replaceExisting=false
                // và asset đã gán, cập nhật sort_order thay vì nổ unique.
                $out->push(MediaAssetUsage::updateOrCreate(
                    [
                        'media_asset_id' => $asset->id,
                        'linkable_type' => $this->morphKey($model),
                        'linkable_id' => $model->getKey(),
                        'slot' => $slot,
                    ],
                    ['sort_order' => $index],
                ));
                $existing++;
            }

            return $out;
        });
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Gỡ một media khỏi slot của model
     * =====================================================================
     * INPUT: $model, $asset, $slot. OUTPUT: bool.
     * SIDE EFFECT: DELETE media_asset_usages.
     * =====================================================================
     */
    public function detach(Model $model, MediaAsset|int $asset, string $slot): bool
    {
        $assetId = $asset instanceof MediaAsset ? $asset->id : (int) $asset;

        return $this->usagesQuery($model, $slot)
            ->where('media_asset_id', $assetId)
            ->delete() > 0;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gỡ toàn bộ media khỏi slot của model
     * =====================================================================
     * INPUT: $model, $slot. OUTPUT: int (số usage đã xóa).
     * SIDE EFFECT: DELETE media_asset_usages.
     * =====================================================================
     */
    public function detachAll(Model $model, string $slot): int
    {
        return $this->usagesQuery($model, $slot)->delete();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đồng bộ slot với danh sách id mới (giữ thứ tự -> sort_order)
     * =====================================================================
     * INPUT: $model, int[] $assetIds, $slot.
     * OUTPUT: Collection<MediaAssetUsage>.
     * EXCEPTION: InvalidArgumentException khi vượt maxItems.
     * SIDE EFFECT: transaction xóa + chèn lại usages theo thứ tự.
     * =====================================================================
     */
    public function sync(Model $model, array $assetIds, string $slot): Collection
    {
        $this->assertModelHasMediaSlots($model);
        $rule = $this->resolveRule($slot);
        $assetIds = array_values(array_unique(array_map('intval', $assetIds)));

        return DB::transaction(function () use ($model, $assetIds, $slot, $rule) {
            $this->assertAttachable($model, null, $rule, 0, count($assetIds));

            $this->usagesQuery($model, $slot)->delete();

            $out = new Collection();
            foreach ($assetIds as $index => $assetId) {
                $asset = $this->resolveAsset($assetId);
                $this->assertAttachable($model, $asset, $rule, 0, 1);
                $this->applyVisibility($asset, $rule);

                $out->push(MediaAssetUsage::create([
                    'media_asset_id' => $asset->id,
                    'linkable_type' => $this->morphKey($model),
                    'linkable_id' => $model->getKey(),
                    'slot' => $slot,
                    'sort_order' => $index,
                ]));
            }

            return $out;
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy usages của model theo slot (sort_order ASC)
     * =====================================================================
     * INPUT: $model, $slot. OUTPUT: Collection<MediaAssetUsage>.
     * SIDE EFFECT: SELECT media_asset_usages.
     * =====================================================================
     */
    public function usages(Model $model, string $slot): Collection
    {
        return $this->usagesQuery($model, $slot)
            ->with('mediaAsset')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy media của slot dưới dạng DTO (kèm URL conversions)
     * =====================================================================
     * INPUT: $model, $slot. OUTPUT: Collection<MediaItemDto>.
     * SIDE EFFECT: SELECT usages + getFirstMedia/getFullUrl từng item.
     * =====================================================================
     */
    public function items(Model $model, string $slot): Collection
    {
        return $this->usages($model, $slot)
            ->map(fn (MediaAssetUsage $usage) => $usage->mediaAsset
                ? MediaItemDto::fromModel($usage->mediaAsset)
                : null)
            ->filter()
            ->values();
    }

    /**
     * CHỨC NĂNG: Lấy media đầu tiên của slot (thumbnail, cover...).
     * INPUT: $model, $slot. OUTPUT: ?MediaItemDto.
     */
    public function first(Model $model, string $slot): ?MediaItemDto
    {
        return $this->items($model, $slot)->first();
    }

    /**
     * CHỨC NĂNG: Đếm số media đang gán vào slot.
     * INPUT: $model, $slot. OUTPUT: int.
     */
    public function count(Model $model, string $slot): int
    {
        return $this->usagesQuery($model, $slot)->count();
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Mở stream đọc file vật lý của asset (FFmpeg/upload)
     * =====================================================================
     * INPUT: MediaAsset|int. OUTPUT: resource PHP stream.
     * EXCEPTION: RuntimeException khi asset không có file Spatie
     *            hoặc storage không đọc được.
     * SIDE EFFECT: mở file handle — caller phải fclose() khi xong.
     * =====================================================================
     */
    public function openReadStream(MediaAsset|int $asset)
    {
        $asset = $this->resolveAsset($asset);
        $media = $asset->mediaFile();

        if (! $media) {
            throw new RuntimeException("MediaAsset [{$asset->id}] chưa có file Spatie đính kèm.");
        }

        $disk = $media->disk;
        $path = $media->getPathRelativeToRoot();

        $stream = Storage::disk($disk)->readStream($path);
        if ($stream === false || $stream === null) {
            throw new RuntimeException("Không mở được stream cho file [{$path}] trên disk [{$disk}].");
        }

        return $stream;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra trước khi attach (không ghi DB)
     * =====================================================================
     * INPUT: $model, $asset, $slot, $incoming.
     * OUTPUT: void. EXCEPTION: InvalidArgumentException khi vi phạm.
     * SIDE EFFECT: SELECT count usages.
     * =====================================================================
     */
    public function validateAttach(
        Model $model,
        MediaAsset|int $asset,
        string $slot,
        int $incoming = 1,
    ): void {
        $this->assertModelHasMediaSlots($model);
        $asset = $this->resolveAsset($asset);
        $rule = $this->resolveRule($slot);
        $existing = $this->usagesQuery($model, $slot)->count();

        $this->assertAttachable($model, $asset, $rule, $existing, $incoming);
    }

    /* =====================================================================
     * Helpers nội bộ
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Kiểm tra model đích có trait HasMediaSlots — bảo vệ
     * chống gán media vào model tùy ý khi gọi service trực tiếp.
     * INPUT: Model. OUTPUT: void. EXCEPTION: InvalidArgumentException.
     */
    private function assertModelHasMediaSlots(Model $model): void
    {
        if (! in_array(\Modules\Media\Traits\HasMediaSlots::class, class_uses_recursive($model), true)) {
            throw new InvalidArgumentException(
                'Model ' . get_class($model) . ' không dùng trait HasMediaSlots — không được gán media.',
            );
        }
    }

    /**
     * CHỨC NĂNG: Nạp MediaAsset từ id; throw khi không tồn tại.
     * INPUT: MediaAsset|int. OUTPUT: MediaAsset.
     * EXCEPTION: InvalidArgumentException khi id không tồn tại.
     */
    private function resolveAsset(MediaAsset|int $asset): MediaAsset
    {
        if ($asset instanceof MediaAsset) {
            return $asset;
        }

        $model = MediaAsset::find($asset);
        if (! $model) {
            throw new InvalidArgumentException("MediaAsset [{$asset}] không tồn tại.");
        }

        return $model;
    }

    /**
     * CHỨC NĂNG: Lấy MediaSlotRule của slot; slot chưa đăng ký -> fallback
     * rule "mở" (mọi kind, không giới hạn, không ép visibility).
     * INPUT: $slot string. OUTPUT: MediaSlotRule.
     */
    private function resolveRule(string $slot): MediaSlotRule
    {
        $rules = $this->slots->resolve($slot);

        return $rules
            ? MediaSlotRule::fromRegistry($slot, $rules)
            : new MediaSlotRule(slot: $slot, maxItems: 0, label: $slot);
    }

    /**
     * CHỨC NĂNG: Kiểm tra kind + số lượng attach (ném exception khi vi phạm).
     * INPUT: $model, $asset (nullable khi chỉ check count), $rule,
     *        $existing, $incoming.
     * OUTPUT: void. EXCEPTION: InvalidArgumentException.
     */
    private function assertAttachable(
        Model $model,
        ?MediaAsset $asset,
        MediaSlotRule $rule,
        int $existing,
        int $incoming,
    ): void {
        if ($asset && ! $rule->allowsKind($asset->kind)) {
            throw new InvalidArgumentException(
                "Media kind [{$asset->kind}] không được phép gán vào slot [{$rule->slot}] " .
                '(cho phép: ' . ($rule->allowedKinds ? implode(',', $rule->allowedKinds) : 'mọi kind') . ').',
            );
        }

        if (! $rule->fits($existing, $incoming)) {
            throw new InvalidArgumentException(
                "Slot [{$rule->slot}] chỉ cho phép tối đa {$rule->maxItems} media " .
                "(hiện {$existing}, thêm {$incoming}).",
            );
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ép visibility của asset theo khai báo slot (nếu có)
     * =====================================================================
     * Không chỉ UPDATE cột visibility — còn di chuyển file vật lý giữa
     * `media_public` <-> `media_private` để đồng bộ quyền truy cập trên disk.
     * INPUT: $asset, $rule. OUTPUT: void.
     * SIDE EFFECT: UPDATE media_assets.visibility + move file giữa disks.
     * =====================================================================
     */
    private function applyVisibility(MediaAsset $asset, MediaSlotRule $rule): void
    {
        if ($rule->visibility === null || $asset->visibility === $rule->visibility) {
            return;
        }

        $oldVisibility = $asset->visibility;
        $newVisibility = $rule->visibility;
        $oldDisk = $oldVisibility === MediaAsset::VISIBILITY_PRIVATE ? 'media_private' : 'media_public';
        $newDisk = $newVisibility === MediaAsset::VISIBILITY_PRIVATE ? 'media_private' : 'media_public';

        // Cập nhật cột visibility trước (transaction cùng caller).
        $asset->update(['visibility' => $newVisibility]);

        // Di chuyển file Spatie + conversions giữa hai disk.
        $media = $asset->mediaFile();
        if (! $media || $oldDisk === $newDisk) {
            return;
        }

        try {
            $media->move($asset, MediaAsset::COLLECTION_LIBRARY, $newDisk);
        } catch (\Throwable $e) {
            // Không rollback visibility — file vẫn ở disk cũ nhưng cờ đã đúng;
            // endpoint stream sẽ kiểm quyền trước khi đọc.
            \Illuminate\Support\Facades\Log::warning(
                "Không chuyển được file media {$media->id} sang {$newDisk}: {$e->getMessage()}",
            );
        }
    }

    /**
     * CHỨC NĂNG: Lấy morph_type alias đã đăng ký (Relation::morphMap) hoặc
     * FQCN khi không có alias — đồng nhất với Eloquent morph.
     * INPUT: $model. OUTPUT: string.
     */
    private function morphKey(Model $model): string
    {
        return $model->getMorphClass();
    }

    /**
     * CHỨC NĂNG: Query usages của model theo slot (không order).
     * INPUT: $model, $slot. OUTPUT: query builder MediaAssetUsage.
     */
    private function usagesQuery(Model $model, string $slot)
    {
        return MediaAssetUsage::query()
            ->where('linkable_type', $this->morphKey($model))
            ->where('linkable_id', $model->getKey())
            ->where('slot', $slot);
    }

    /**
     * CHỨC NĂNG: sort_order kế tiếp trong slot (= max + 1).
     * INPUT: $model, $slot. OUTPUT: int.
     */
    private function nextSortOrder(Model $model, string $slot): int
    {
        $max = (int) $this->usagesQuery($model, $slot)->max('sort_order');

        return $max + 1;
    }
}