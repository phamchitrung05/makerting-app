<?php

namespace Modules\Media\Registries;

use InvalidArgumentException;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Registry trung tâm khai báo các `slot` media của hệ thống.
 * =====================================================================
 *
 * Mỗi module đăng ký slot riêng trong ServiceProvider::boot() —
 * ví dụ 'post.thumbnail', 'social_variant.media', 'video_project.output',
 * 'visual_style.reference'. Slot định nghĩa vai trò media trên model + quy
 * tắc `allowedKinds` + `maxItems` + `visibility` để `HasMediaSlots`/`MediaPicker`
 * và `MediaUsageService` kiểm soát gán đúng loại/số lượng/truy cập.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register($slot, $rules) : đăng ký slot + quy tắc.
 * - has($slot) / resolve($slot) / resolveOrFail($slot)
 * - slots() / all() / describe()
 * - allowedKinds($slot) / maxItems($slot) / visibility($slot)
 * - validateKind($slot, $kind) / validateCount($slot, $count)
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : slot string (vd 'post.thumbnail') + rules array.
 * - OUTPUT: tra cứu slot + kiểm tra quy tắc cho attach/upload.
 * =====================================================================
 */
class MediaSlotRegistry
{
    /**
     * Slot mặc định hệ thống dùng khi model không tự khai báo.
     */
    public const DEFAULT_SLOT = 'default';

    /** @var array<string, array{allowedKinds?:array<int,string>, maxItems?:int, visibility?:string, label?:string}> */
    private array $slots = [];

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký một slot media với quy tắc kiểm soát
     * =====================================================================
     * INPUT:
     * - $slot  : khóa slot duy nhất (vd 'post.thumbnail').
     * - $rules : array{allowedKinds?:string[], maxItems?:int, visibility?:string, label?:string}
     *            - allowedKinds: mảng MediaAsset::KIND_* được phép (rỗng = mọi kind).
     *            - maxItems    : số media tối đa (mặc định 1; 0 = không giới hạn).
     *            - visibility  : 'public'|'private'|null (null = giữ nguyên asset).
     *            - label       : nhãn hiển thị trên UI.
     *
     * OUTPUT: void — slot được ghi vào $slots[$slot].
     * SIDE EFFECT: ghi registry nội bộ; throw khi slot không hợp lệ.
     * EXCEPTION: InvalidArgumentException khi slot rỗng hoặc rules sai kiểu.
     * =====================================================================
     */
    public function register(string $slot, array $rules = []): void
    {
        if ($slot === '' || strlen($slot) > 80) {
            throw new InvalidArgumentException("Slot media [{$slot}] phải từ 1 đến 80 ký tự.");
        }

        $allowedKinds = $rules['allowedKinds'] ?? [];
        if (! is_array($allowedKinds)) {
            throw new InvalidArgumentException("allowedKinds của slot [{$slot}] phải là mảng MediaAsset::KIND_*.");
        }

        foreach ($allowedKinds as $kind) {
            if (! in_array($kind, MediaAsset::KINDS, true)) {
                throw new InvalidArgumentException(
                    "Kind [{$kind}] không hợp lệ cho slot [{$slot}]; phải trong " . implode(',', MediaAsset::KINDS) . '.',
                );
            }
        }

        $maxItems = $rules['maxItems'] ?? 1;
        if (! is_int($maxItems) || $maxItems < 0) {
            throw new InvalidArgumentException("maxItems của slot [{$slot}] phải int >= 0.");
        }

        $visibility = $rules['visibility'] ?? null;
        if ($visibility !== null && ! in_array($visibility, MediaAsset::VISIBILITIES, true)) {
            throw new InvalidArgumentException("visibility của slot [{$slot}] phải public|private|null.");
        }

        $this->slots[$slot] = array_merge([
            'allowedKinds' => $allowedKinds,
            'maxItems' => $maxItems,
            'visibility' => $visibility,
            'label' => $rules['label'] ?? $slot,
        ], array_diff_key($rules, ['allowedKinds' => 0, 'maxItems' => 0, 'visibility' => 0, 'label' => 0]));
    }

    /**
     * CHỨC NĂNG: Kiểm tra slot đã đăng ký chưa.
     * INPUT: $slot string. OUTPUT: bool. SIDE EFFECT: không.
     */
    public function has(string $slot): bool
    {
        return isset($this->slots[$slot]);
    }

    /**
     * CHỨC NĂNG: Lấy quy tắc của slot, trả null khi chưa đăng ký.
     * INPUT: $slot string. OUTPUT: array|null. SIDE EFFECT: không.
     */
    public function resolve(string $slot): ?array
    {
        return $this->slots[$slot] ?? null;
    }

    /**
     * CHỨC NĂNG: Lấy quy tắc của slot, throw khi chưa đăng ký.
     * INPUT: $slot string.
     * OUTPUT: array{allowedKinds:string[],maxItems:int,visibility:?string,label:string}.
     * EXCEPTION: InvalidArgumentException khi slot chưa đăng ký.
     */
    public function resolveOrFail(string $slot): array
    {
        return $this->resolve($slot)
            ?? throw new InvalidArgumentException("Slot media [{$slot}] chưa được đăng ký.");
    }

    /**
     * CHỨC NĂNG: Danh sách khóa slot đã đăng ký.
     * INPUT: không. OUTPUT: string[]. SIDE EFFECT: không.
     */
    public function slots(): array
    {
        return array_keys($this->slots);
    }

    /**
     * CHỨC NĂNG: Map slot => rules của toàn bộ registry.
     * INPUT: không. OUTPUT: array<string,array>. SIDE EFFECT: không.
     */
    public function all(): array
    {
        return $this->slots;
    }

    /**
     * CHỨC NĂNG: Metadata các slot để UI dựng tab/lọc (label + rules).
     * INPUT: không. OUTPUT: array<int,array{slot,allowedKinds,maxItems,visibility,label}>.
     * SIDE EFFECT: không.
     */
    public function describe(): array
    {
        return collect($this->slots)
            ->map(fn (array $rules, string $slot) => [
                'slot' => $slot,
                'allowedKinds' => $rules['allowedKinds'],
                'maxItems' => $rules['maxItems'],
                'visibility' => $rules['visibility'],
                'label' => $rules['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * CHỨC NĂNG: Kinds được phép của slot; trả [] khi slot chưa đăng ký.
     * INPUT: $slot string. OUTPUT: array<int,string>. SIDE EFFECT: không.
     */
    public function allowedKinds(string $slot): array
    {
        return $this->resolve($slot)['allowedKinds'] ?? [];
    }

    /**
     * CHỨC NĂNG: maxItems của slot; trả 0 (không giới hạn) khi chưa đăng ký.
     * INPUT: $slot string. OUTPUT: int. SIDE EFFECT: không.
     */
    public function maxItems(string $slot): int
    {
        return $this->resolve($slot)['maxItems'] ?? 0;
    }

    /**
     * CHỨC NĂNG: visibility bắt buộc của slot; null = giữ nguyên asset.
     * INPUT: $slot string. OUTPUT: string|null. SIDE EFFECT: không.
     */
    public function visibility(string $slot): ?string
    {
        return $this->resolve($slot)['visibility'] ?? null;
    }

    /**
     * CHỨC NĂNG: kind của media có được phép gán vào slot không.
     * INPUT: $slot, $kind (MediaAsset::KIND_*). OUTPUT: bool.
     * SIDE EFFECT: không.
     */
    public function validateKind(string $slot, string $kind): bool
    {
        $allowed = $this->allowedKinds($slot);

        return $allowed === [] || in_array($kind, $allowed, true);
    }

    /**
     * CHỨC NĂNG: số media hiện có + sắp thêm có vượt maxItems không.
     * INPUT: $slot, $currentCount (đang gán), $incoming (sắp gán thêm).
     * OUTPUT: bool — true nếu currentCount+incoming <= maxItems.
     * SIDE EFFECT: không.
     */
    public function validateCount(string $slot, int $currentCount, int $incoming = 0): bool
    {
        $max = $this->maxItems($slot);

        return $max === 0 || ($currentCount + $incoming) <= $max;
    }
}
