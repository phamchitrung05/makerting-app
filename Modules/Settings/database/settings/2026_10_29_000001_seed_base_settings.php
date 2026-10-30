<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Seed giá trị mặc định của 3 nhóm cài đặt nền vào bảng
 * settings (Spatie) — chạy qua settings:migrate.
 * =====================================================================
 *
 * Mỗi ->add tạo 1 row (group,name,payload); version=1 khởi đầu cho khóa
 * lạc quan. Idempotent ở mức ứng dụng: chạy lại sẽ upsert cùng giá trị.
 * =====================================================================
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        // ------------------------- site -------------------------
        $this->migrator->add('site.version', 1);
        $this->migrator->add('site.site_name', 'Makerting');
        $this->migrator->add('site.site_description', null);
        $this->migrator->add('site.timezone', 'Asia/Ho_Chi_Minh');
        $this->migrator->add('site.locale', 'vi');
        $this->migrator->add('site.contact_email', null);
        $this->migrator->add('site.tagline', null);
        $this->migrator->add('site.website_url', null);
        $this->migrator->add('site.contact_phone', null);
        $this->migrator->add('site.contact_address', null);

        // ----------------------- security -----------------------
        $this->migrator->add('security.version', 1);
        $this->migrator->add('security.password_min_length', 8);
        $this->migrator->add('security.password_require_symbols', false);
        $this->migrator->add('security.password_require_mixed', true);
        $this->migrator->add('security.login_max_attempts', 5);
        $this->migrator->add('security.login_lockout_minutes', 15);
        $this->migrator->add('security.session_lifetime_minutes', 120);
        $this->migrator->add('security.allowed_ips', []);
        $this->migrator->add('security.two_factor_required', false);

        // ------------------------- mail -------------------------
        $this->migrator->add('mail.version', 1);
        $this->migrator->add('mail.mail_driver', 'log');
        $this->migrator->add('mail.mail_host', null);
        $this->migrator->add('mail.mail_port', 587);
        $this->migrator->add('mail.mail_encryption', 'tls');
        $this->migrator->add('mail.mail_username', null);
        $this->migrator->addEncrypted('mail.mail_password', null);
        $this->migrator->add('mail.mail_from_address', 'no-reply@example.com');
        $this->migrator->add('mail.mail_from_name', 'Makerting');
        $this->migrator->add('mail.mail_queue', true);
    }
};
