<?php

namespace Modules\Seo\Settings;

use Modules\Settings\Contracts\ModularSettingsContract;
use Spatie\LaravelSettings\Settings;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Nhóm cài đặt SEO (group 'seo') — mẫu thẻ meta mặc định
 * và luật fallback toàn site.
 * =====================================================================
 *
 * `title_template` chứa placeholder `%title%` + `%site_name%`, được
 * SeoMetadataService dùng để render thẻ <title> khi model không tự ghi đè
 * seo_title. Các trường còn lại là giá trị meta mặc định cho OG/Twitter.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - group()             : khóa nhóm 'seo'.
 * - defaults()          : giá trị mặc định ban đầu.
 * - validationRules()   : rule validate khi PUT.
 * - secretProperties()  : không có trường bí mật.
 * - permission()        : tiền tố 'settings.seo'.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : DB bảng settings (group='seo') qua Spatie repository.
 * - OUTPUT: object typed đọc/ghi cấu hình SEO.
 * =====================================================================
 */
class SeoSettings extends Settings implements ModularSettingsContract
{
    /** @var int Khóa lạc quan — client gửi expected_version, hệ thống so khớp. */
    public int $version;

    /** @var string Mẫu thẻ title mặc định, có %title% và %site_name%. */
    public string $title_template;

    /** @var string|null Mô tả mặc định dùng khi model thiếu seo_description. */
    public ?string $default_description;

    /** @var string|null URL ảnh OG mặc định toàn site khi không có og_image. */
    public ?string $default_og_image_url;

    /** @var string Handle Twitter/X (có @ hay không đều được). */
    public ?string $twitter_site;

    /** @var bool Cho phép index toàn cục (noindex site-wide khi tắt). */
    public bool $global_index;

    /** @var bool Cho phép follow link toàn cục. */
    public bool $global_follow;

    /** @var string|null Canonical base URL (vd https://example.com) nối slug. */
    public ?string $canonical_base_url;

    public static function group(): string
    {
        return 'seo';
    }

    public static function defaults(): array
    {
        return [
            'title_template' => '%title% | %site_name%',
            'default_description' => null,
            'default_og_image_url' => null,
            'twitter_site' => null,
            'global_index' => true,
            'global_follow' => true,
            'canonical_base_url' => null,
        ];
    }

    public static function validationRules(): array
    {
        return [
            'title_template' => ['sometimes', 'required', 'string', 'max:255'],
            'default_description' => ['nullable', 'string', 'max:500'],
            'default_og_image_url' => ['nullable', 'url', 'max:2048'],
            'twitter_site' => ['nullable', 'string', 'max:60'],
            'global_index' => ['sometimes', 'boolean'],
            'global_follow' => ['sometimes', 'boolean'],
            'canonical_base_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    public static function secretProperties(): array
    {
        return [];
    }

    public static function permission(): string
    {
        return 'settings.seo';
    }
}
