<?php

namespace Modules\CoreAi\Settings;

use Modules\Settings\Contracts\ModularSettingsContract;
use Spatie\LaravelSettings\Settings;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Nhóm cài đặt AI (group 'ai') — mô hình mặc định theo
 * từng loại tác vụ và ngôn ngữ mặc định khi sinh nội dung.
 * =====================================================================
 *
 * Lưu ID của ai_models (không lưu tên model) để tránh lệch khi admin đổi
 * label hoặc provider xoay vòng key. ModelResolver/các module nghiệp vụ
 * (Blog, VideoStudio, WritingStyle) đọc nhóm này để biết dùng model nào
 * khi người dùng không chọn thủ công.
 *
 * Giá trị null = chưa cấu hình -> tầng gọi AI tự fallback theo
 * ModelResolver (model đầu tiên có capability tương ứng, is_enabled).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - group()             : khóa nhóm 'ai'.
 * - defaults()          : giá trị mặc định ban đầu (null + locale 'vi').
 * - validationRules()   : rule validate khi PUT (exists:ai_models,id).
 * - secretProperties()  : không có trường bí mật (API key nằm ở ai_providers).
 * - permission()        : tiền tố 'settings.ai'.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : DB bảng settings (group='ai') qua Spatie repository.
 * - OUTPUT: object typed đọc/ghi cấu hình mặc định AI.
 * =====================================================================
 */
class AiSettings extends Settings implements ModularSettingsContract
{
    /** @var int Khóa lạc quan — client gửi expected_version, hệ thống so khớp. */
    public int $version;

    /** @var int|null ai_models.id mặc định cho tác vụ sinh văn bản (LLM). */
    public ?int $default_text_model_id;

    /** @var int|null ai_models.id mặc định cho tác vụ sinh hình ảnh. */
    public ?int $default_image_model_id;

    /** @var int|null ai_models.id mặc định cho tác vụ sinh video. */
    public ?int $default_video_model_id;

    /** @var string Locale mặc định của nội dung AI sinh ra (vi|en|ja). */
    public string $default_language;

    public static function group(): string
    {
        return 'ai';
    }

    public static function defaults(): array
    {
        return [
            'default_text_model_id' => null,
            'default_image_model_id' => null,
            'default_video_model_id' => null,
            'default_language' => 'vi',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'default_text_model_id' => ['nullable', 'integer', 'exists:ai_models,id'],
            'default_image_model_id' => ['nullable', 'integer', 'exists:ai_models,id'],
            'default_video_model_id' => ['nullable', 'integer', 'exists:ai_models,id'],
            'default_language' => ['sometimes', 'required', 'string', 'in:vi,en,ja'],
        ];
    }

    public static function secretProperties(): array
    {
        return [];
    }

    public static function permission(): string
    {
        return 'settings.ai';
    }
}
