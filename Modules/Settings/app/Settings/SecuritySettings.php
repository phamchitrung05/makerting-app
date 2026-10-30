<?php

namespace Modules\Settings\Settings;

use Modules\Settings\Contracts\ModularSettingsContract;
use Spatie\LaravelSettings\Settings;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Nhóm cài đặt Bảo mật (group 'security') — chính sách
 * đăng nhập, session và giới hạn truy cập admin.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - group()             : khóa nhóm 'security'.
 * - defaults()          : giá trị mặc định.
 * - validationRules()   : rule validate khi PUT.
 * - secretProperties()  : không có trường bí mật.
 * - permission()        : tiền tố 'settings.security'.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : DB bảng settings (group='security').
 * - OUTPUT: object typed chính sách bảo mật.
 * =====================================================================
 */
class SecuritySettings extends Settings implements ModularSettingsContract
{
    /** @var int Khóa lạc quan. */
    public int $version;

    /** @var int Độ dài mật khẩu tối thiểu cho tài khoản admin. */
    public int $password_min_length;

    /** @var bool Bắt buộc ký tự đặc biệt trong mật khẩu. */
    public bool $password_require_symbols;

    /** @var bool Bắt buộc chữ hoa + số trong mật khẩu. */
    public bool $password_require_mixed;

    /** @var int Số lần sai mật khẩu liên tiếp trước khi khóa (0 = tắt). */
    public int $login_max_attempts;

    /** @var int Thời gian khóa tài khoản (phút) sau khi sai quá số lần. */
    public int $login_lockout_minutes;

    /** @var int Thời hạn session/token (phút) trước khi bắt đăng nhập lại. */
    public int $session_lifetime_minutes;

    /** @var array<int,string> Danh sách IP được phép vào admin (rỗng = mọi IP). */
    public array $allowed_ips;

    /** @var bool Bật yêu cầu xác thực 2 lớp cho vai trò quản trị. */
    public bool $two_factor_required;

    public static function group(): string
    {
        return 'security';
    }

    public static function defaults(): array
    {
        return [
            'password_min_length' => 8,
            'password_require_symbols' => false,
            'password_require_mixed' => true,
            'login_max_attempts' => 5,
            'login_lockout_minutes' => 15,
            'session_lifetime_minutes' => 120,
            'allowed_ips' => [],
            'two_factor_required' => false,
        ];
    }

    public static function validationRules(): array
    {
        return [
            'password_min_length' => ['sometimes','required', 'integer', 'min:6', 'max:64'],
            'password_require_symbols' => ['boolean'],
            'password_require_mixed' => ['boolean'],
            'login_max_attempts' => ['sometimes','required', 'integer', 'min:0', 'max:20'],
            'login_lockout_minutes' => ['sometimes','required', 'integer', 'min:0', 'max:1440'],
            'session_lifetime_minutes' => ['sometimes','required', 'integer', 'min:5', 'max:43200'],
            'allowed_ips' => ['array'],
            'allowed_ips.*' => ['string', 'max:64'],
            'two_factor_required' => ['boolean'],
        ];
    }

    public static function secretProperties(): array
    {
        return [];
    }

    public static function permission(): string
    {
        return 'settings.security';
    }
}
