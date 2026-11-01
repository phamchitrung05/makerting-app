<?php

namespace Modules\Seo\Registries;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Registry khai báo các Domain Model được phép SEO.
 * =====================================================================
 *
 * Module nghiệp vụ gọi register() trong ServiceProvider::boot() để mở
 * rộng whitelist model cần SEO (Post, Category, VisualStyle, ChannelGroup,
 * MediaAsset...). SeoController::findModel() dùng registry này kết hợp
 * với kiểm tra HasSeoMeta để chặn cả model không phải SEO-enabled
 * (BadMethodCallException) lẫn IDOR (enum id model bất kỳ).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(string $class) : thêm model vào whitelist.
 * - has(string $class)      : kiểm tra model đã đăng ký.
 * - models()                : danh sách FQCN đã đăng ký.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : FQCN model (dùng HasSeoMeta).
 * - OUTPUT: whitelist class được phép SEO.
 * =====================================================================
 */
class SeoModelRegistry
{
    /** @var array<string, class-string> */
    private array $models = [];

    /**
     * CHỨC NĂNG: Đăng ký model được phép SEO (dùng HasSeoMeta).
     * INPUT: $class FQCN. OUTPUT: void. SIDE EFFECT: ghi whitelist.
     */
    public function register(string $class): void
    {
        $this->models[$class] = $class;
    }

    /**
     * CHỨC NĂNG: Kiểm tra model đã đăng ký whitelist chưa.
     * INPUT: $class FQCN. OUTPUT: bool. SIDE EFFECT: không.
     */
    public function has(string $class): bool
    {
        return isset($this->models[$class]);
    }

    /**
     * CHỨC NĂNG: Danh sách FQCN model được phép SEO.
     * INPUT: không. OUTPUT: string[]. SIDE EFFECT: không.
     */
    public function models(): array
    {
        return array_values($this->models);
    }
}
