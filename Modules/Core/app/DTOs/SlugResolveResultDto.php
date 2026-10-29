<?php

namespace Modules\Core\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO kết quả tra cứu slug (bất biến).
 * =====================================================================
 *
 * Trả về từ SlugServiceContract::resolve(): cho biết slug có tồn tại,
 * đích đến (sluggable_type/id) và có phải slug cũ cần Redirect 301 hay không.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : slug tìm thấy + flag found/isPrimary + slug primary hiện hành
 * - OUTPUT: readonly object phục vụ controller/route model binding
 * =====================================================================
 */
final readonly class SlugResolveResultDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO kết quả resolve slug.
     * INPUT: found, sluggableType, sluggableId, isPrimary, primarySlug, locale.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public bool $found,
        public ?string $sluggableType = null,
        public ?int $sluggableId = null,
        public bool $isPrimary = false,
        public ?string $primarySlug = null,
        public string $locale = 'vi',
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: tạo DTO "không tìm thấy"
     * =====================================================================
     *
     * INPUT: locale (mặc định 'vi').
     * OUTPUT: SlugResolveResultDto với found=false.
     * SIDE EFFECT: không có.
     * =====================================================================
     */
    public static function notFound(string $locale = 'vi'): self
    {
        return new self(found: false, locale: $locale);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: slug này có phải slug cũ cần Redirect 301 sang primary không
     * =====================================================================
     *
     * INPUT: không có tham số.
     * OUTPUT: bool — true khi found và isPrimary=false.
     * SIDE EFFECT: không có.
     * =====================================================================
     */
    public function needsRedirect(): bool
    {
        return $this->found && ! $this->isPrimary && $this->primarySlug !== null;
    }
}
