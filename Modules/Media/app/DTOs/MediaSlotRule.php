<?php

namespace Modules\Media\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO mô tả một slot media (quy tắc + ngữ cảnh dùng).
 * =====================================================================
 *
 * `MediaSlotRule` đóng gói quy tắc của một slot khi model attach media:
 * `allowedKinds` (Kind cho phép), `maxItems` (0 = không giới hạn),
 * `visibility` (null = giữ nguyên asset), `label` (UI), `sortable`
 * (cho phép sắp xếp trong slot).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : quy tắc slot từ MediaSlotRegistry.
 * - OUTPUT: readonly object dùng cho validation trước attach.
 * =====================================================================
 */
final readonly class MediaSlotRule
{
    /**
     * CHỨC NĂNG: khởi tạo quy tắc slot.
     * INPUT: slot, allowedKinds, maxItems, visibility, label, sortable.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $slot,
        public array $allowedKinds = [],
        public int $maxItems = 1,
        public ?string $visibility = null,
        public string $label = '',
        public bool $sortable = false,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: tạo DTO từ mảng rules của MediaSlotRegistry
     * =====================================================================
     * INPUT: $slot string, $rules array{allowedKinds,maxItems,visibility,label,sortable?}.
     * OUTPUT: MediaSlotRule.
     * SIDE EFFECT: không.
     * =====================================================================
     */
    public static function fromRegistry(string $slot, array $rules): self
    {
        return new self(
            slot: $slot,
            allowedKinds: $rules['allowedKinds'] ?? [],
            maxItems: $rules['maxItems'] ?? 1,
            visibility: $rules['visibility'] ?? null,
            label: $rules['label'] ?? $slot,
            sortable: (bool) ($rules['sortable'] ?? false),
        );
    }

    /**
     * CHỨC NĂNG: có giới hạn số media trong slot không.
     * INPUT: không. OUTPUT: bool — maxItems > 0.
     */
    public function hasLimit(): bool
    {
        return $this->maxItems > 0;
    }

    /**
     * CHỨC NĂNG: kind có được phép gán vào slot không.
     * INPUT: $kind string. OUTPUT: bool — [] = mọi kind.
     */
    public function allowsKind(string $kind): bool
    {
        return $this->allowedKinds === [] || in_array($kind, $this->allowedKinds, true);
    }

    /**
     * CHỨC NĂNG: số media hiện có + sắp thêm có vượt maxItems không.
     * INPUT: $current int, $incoming int. OUTPUT: bool.
     */
    public function fits(int $current, int $incoming = 0): bool
    {
        return ! $this->hasLimit() || ($current + $incoming) <= $this->maxItems;
    }
}
