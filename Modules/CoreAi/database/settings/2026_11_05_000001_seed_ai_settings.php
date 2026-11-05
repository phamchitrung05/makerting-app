<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * =====================================================================
 * MIGRATION (Spatie settings): Seed giá trị mặc định nhóm 'ai' vào bảng
 * settings — chạy qua settings:migrate.
 * =====================================================================
 * Nhóm 'ai' giữ mô hình mặc định theo loại tác vụ (text/image/video) dưới
 * dạng ai_models.id và ngôn ngữ mặc định của nội dung AI sinh ra.
 *
 * Khởi tạo null cho cả 3 model: lúc cài mới chưa có provider/model nào,
 * tầng gọi AI tự fallback qua ModelResolver cho tới khi admin chọn.
 * version=1 khởi tạo khóa lạc quan cho Optimistic Locking.
 * =====================================================================
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ai.version', 1);
        $this->migrator->add('ai.default_text_model_id', null);
        $this->migrator->add('ai.default_image_model_id', null);
        $this->migrator->add('ai.default_video_model_id', null);
        $this->migrator->add('ai.default_language', 'vi');
    }

    /**
     * CHỨC NĂNG: Gỡ các row nhóm 'ai' khi rollback.
     * INPUT: không. OUTPUT: void.
     */
    public function down(): void
    {
        foreach ([
            'version',
            'default_text_model_id',
            'default_image_model_id',
            'default_video_model_id',
            'default_language',
        ] as $name) {
            $this->migrator->delete("ai.{$name}");
        }
    }
};
