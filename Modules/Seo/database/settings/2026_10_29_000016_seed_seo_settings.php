<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * =====================================================================
 * MIGRATION (Spatie settings): Seed giá trị mặc định nhóm 'seo' vào bảng
 * settings — chạy qua settings:migrate.
 * =====================================================================
 * Idempotent ở mức ứng dụng: ->add cùng (group,name) upsert lại giá trị.
 * version=1 khởi tạo khóa lạc quan cho Optimistic Locking.
 * =====================================================================
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.version', 1);
        $this->migrator->add('seo.title_template', '%title% | %site_name%');
        $this->migrator->add('seo.default_description', null);
        $this->migrator->add('seo.default_og_image_url', null);
        $this->migrator->add('seo.twitter_site', null);
        $this->migrator->add('seo.global_index', true);
        $this->migrator->add('seo.global_follow', true);
        $this->migrator->add('seo.canonical_base_url', null);
    }

    /**
     * CHỨC NĂNG: Gỡ các row nhóm 'seo' khi rollback.
     * INPUT: không. OUTPUT: void.
     */
    public function down(): void
    {
        foreach (['version','title_template','default_description','default_og_image_url','twitter_site','global_index','global_follow','canonical_base_url'] as $name) {
            $this->migrator->delete("seo.{$name}");
        }
    }
};
