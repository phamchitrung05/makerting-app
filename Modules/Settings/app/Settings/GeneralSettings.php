<?php

namespace Modules\Settings\Settings;

use Modules\Settings\Contracts\ModularSettingsContract;
use Spatie\LaravelSettings\Settings;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Nhóm cài đặt Chung (group 'site') — thông tin nhận diện
 * và hành vi mặc định của toàn ứng dụng.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - group()             : khóa nhóm 'site'.
 * - defaults()          : giá trị mặc định ban đầu.
 * - validationRules()   : rule validate khi PUT.
 * - secretProperties()  : không có trường bí mật.
 * - permission()        : tiền tố 'settings.site'.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : DB bảng settings (group='site') qua Spatie repository.
 * - OUTPUT: object typed đọc/ghi cấu hình site.
 * =====================================================================
 */
class GeneralSettings extends Settings implements ModularSettingsContract
{
    /** @var int Khóa lạc quan — client gửi expected_version, hệ thống so khớp. */
    public int $version;

    /** @var string Tên hiển thị của ứng dụng/site. */
    public string $site_name;

    /** @var string|null Mô tả ngắn dùng cho SEO mặc định & meta tag. */
    public ?string $site_description;

    /** @var string Múi giờ mặc định cho scheduler/hiển thị ngày giờ. */
    public string $timezone;

    /** @var string Ngôn ngữ mặc định của nội dung (locale). */
    public string $locale;

    /** @var string|null Email liên hệ hiển thị trên trang public. */
    public ?string $contact_email;

    /** @var string|null Khẩu hiệu / slogan ngắn. */
    public ?string $tagline;

    /** @var string|null Địa chỉ website public. */
    public ?string $website_url;

    /** @var string|null Số điện thoại liên hệ. */
    public ?string $contact_phone;

    /** @var string|null Địa chỉ liên hệ hiển thị. */
    public ?string $contact_address;

    public static function group(): string
    {
        return 'site';
    }

    public static function defaults(): array
    {
        return [
            'site_name' => 'Makerting',
            'site_description' => null,
            'timezone' => 'Asia/Ho_Chi_Minh',
            'locale' => 'vi',
            'contact_email' => null,
            'tagline' => null,
            'website_url' => null,
            'contact_phone' => null,
            'contact_address' => null,
        ];
    }

    public static function validationRules(): array
    {
        return [
            'site_name' => ['sometimes','required', 'string', 'max:160'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'timezone' => ['sometimes','required', 'string', 'timezone:all'],
            'locale' => ['sometimes','required', 'string', 'max:16'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function secretProperties(): array
    {
        return [];
    }

    public static function permission(): string
    {
        return 'settings.site';
    }
}
