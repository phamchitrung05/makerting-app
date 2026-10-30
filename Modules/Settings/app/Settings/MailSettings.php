<?php

namespace Modules\Settings\Settings;

use Modules\Settings\Contracts\ModularSettingsContract;
use Spatie\LaravelSettings\Settings;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Nhóm cài đặt Email (group 'mail') — cấu hình gửi mail
 * SMTP/queue của hệ thống.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - group()             : khóa nhóm 'mail'.
 * - defaults()          : giá trị mặc định.
 * - validationRules()   : rule validate khi PUT.
 * - secretProperties()  : che 'mail_password'.
 * - permission()        : tiền tố 'settings.mail'.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : DB bảng settings (group='mail').
 * - OUTPUT: object typed cấu hình mailer.
 * =====================================================================
 */
class MailSettings extends Settings implements ModularSettingsContract
{
    /** @var int Khóa lạc quan. */
    public int $version;

    /** @var string Driver mail (smtp|sendmail|log|ses...). */
    public string $mail_driver;

    /** @var string|null Máy chủ SMTP. */
    public ?string $mail_host;

    /** @var int Cổng SMTP. */
    public int $mail_port;

    /** @var string|null Encryption (tls|ssl|null). */
    public ?string $mail_encryption;

    /** @var string|null Username đăng nhập SMTP. */
    public ?string $mail_username;

    /** @var string|null Mật khẩu SMTP — BÍ MẬT, che khi trả API. */
    public ?string $mail_password;

    /** @var string Địa chỉ From mặc định. */
    public string $mail_from_address;

    /** @var string|null Tên hiển thị From. */
    public ?string $mail_from_name;

    /** @var bool Gửi mail qua queue thay vì đồng bộ. */
    public bool $mail_queue;

    public static function group(): string
    {
        return 'mail';
    }

    public static function defaults(): array
    {
        return [
            'mail_driver' => 'log',
            'mail_host' => null,
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => null,
            'mail_password' => null,
            'mail_from_address' => 'no-reply@example.com',
            'mail_from_name' => 'Makerting',
            'mail_queue' => true,
        ];
    }

    public static function validationRules(): array
    {
        return [
            'mail_driver' => ['sometimes','required', 'string', 'max:40'],
            'mail_host' => ['nullable', 'string', 'max:190'],
            'mail_port' => ['sometimes','required', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['nullable', 'string', 'max:10'],
            'mail_username' => ['nullable', 'string', 'max:190'],
            // Nhận giá trị mới chỉ khi client cố tình gửi; bỏ trống = giữ cũ.
            'mail_password' => ['nullable', 'string', 'max:190'],
            'mail_from_address' => ['sometimes','required', 'email', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:160'],
            'mail_queue' => ['boolean'],
        ];
    }

    public static function secretProperties(): array
    {
        return ['mail_password'];
    }

    public static function permission(): string
    {
        return 'settings.mail';
    }
}
