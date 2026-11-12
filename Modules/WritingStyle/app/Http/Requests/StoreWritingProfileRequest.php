<?php

namespace Modules\WritingStyle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate dữ liệu tạo/cập nhật profile và cấu hình slider.
 * =====================================================================
 *
 * Kiểm tra tính hợp lệ của payload gửi lên:
 * - POST /v1/admin/writing-style/writing-profiles     (Tạo thủ công origin=manual)
 * - PUT  /v1/admin/writing-style/writing-profiles/{id} (Cập nhật hồ sơ hoặc hoàn tất bản nháp)
 *
 * Hỗ trợ đồng bộ Taxonomy (categories, tags, new_tags) qua HasTaxonomy trait.
 *
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - authorize(): Cho phép FormRequest tiếp tục sau middleware xác thực và phân quyền.
 * - rules(): Khai báo kiểu dữ liệu, giới hạn và validation cho payload request.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Tên, DNA, Ô 1–3, slider preferences, analysis_id, slug và taxonomy.
 * - OUTPUT: validated() cung cấp dữ liệu hợp lệ.
 *
 * BỐI CẢNH SỬ DỤNG: Controller chỉ lưu các trường được cho phép; quyền do route middleware kiểm tra.
 * =====================================================================
 */
class StoreWritingProfileRequest extends FormRequest
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Cho phép FormRequest tiếp tục; quyền cụ thể được kiểm tra qua middleware route
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool true; xác thực và permission do route xử lý.
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đọc cấu hình từ config/profile.php
     * =====================================================================
     */
    protected function profileConfig(string $key, mixed $default = null): mixed
    {
        return config('writingstyle.profile.'.$key, $default);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo tập validation cho các trường đầu vào của request
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array theo hợp đồng của method.
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function rules(): array
    {
        $nameMax = (int) $this->profileConfig('name.max', 160);
        $descMax = (int) $this->profileConfig('description.max', 2000);
        $slugMax = (int) $this->profileConfig('slug.max', 250);
        $slugRegex = (string) $this->profileConfig('slug.regex', '/^[a-z0-9]+(?:-[a-z0-9]+)*$/');

        $tempMin = $this->profileConfig('temperature.min', 0);
        $tempMax = $this->profileConfig('temperature.max', 2);

        $dominantTiers = (array) $this->profileConfig('dominant_tiers', ['micro', 'short', 'medium', 'long']);
        $statuses = (array) $this->profileConfig('statuses', ['draft', 'active']);

        $instructions = (array) $this->profileConfig('instructions', []);
        $styleMin = (int) ($instructions['style_min'] ?? 1);
        $styleMax = (int) ($instructions['style_max'] ?? 10000);
        $voiceTextureMax = (int) ($instructions['voice_texture_max'] ?? 10000);
        $toneMax = (int) ($instructions['tone_max'] ?? 10000);

        $rulesCfg = (array) $this->profileConfig('rules', []);
        $ruleItemMin = (int) ($rulesCfg['item_min'] ?? 1);
        $ruleItemMax = (int) ($rulesCfg['item_max'] ?? 500);
        $uncertMin = (int) ($rulesCfg['uncertainties_item_min'] ?? 1);
        $uncertMax = (int) ($rulesCfg['uncertainties_item_max'] ?? 500);

        $evidenceCfg = (array) $this->profileConfig('evidence', []);
        $ruleKeyMax = (int) ($evidenceCfg['rule_key_max'] ?? 50);
        $excerptMin = (int) ($evidenceCfg['excerpt_min'] ?? 1);
        $excerptMax = (int) ($evidenceCfg['excerpt_max'] ?? 2000);
        $noteMax = (int) ($evidenceCfg['note_max'] ?? 500);

        $slidersCfg = (array) $this->profileConfig('sliders', []);
        $sliderMin = (int) ($slidersCfg['min_score'] ?? 0);
        $sliderMax = (int) ($slidersCfg['max_score'] ?? 100);
        $voiceAxesMax = (int) ($slidersCfg['voice_max_axes'] ?? 5);
        $toneAxesMax = (int) ($slidersCfg['tone_max_axes'] ?? 8);
        $voicePresetsMax = (int) ($slidersCfg['voice_presets_max_items'] ?? 8);
        $tonePresetsMax = (int) ($slidersCfg['tone_presets_max_items'] ?? 8);
        $presetNameMax = (int) ($slidersCfg['preset_name_max'] ?? 60);

        $taxonomyCfg = (array) $this->profileConfig('taxonomy', []);
        $newCategoryMax = (int) ($taxonomyCfg['new_category_name_max'] ?? 160);
        $newTagMax = (int) ($taxonomyCfg['new_tag_name_max'] ?? 120);

        $ruleItemValidation = ['nullable', 'string', "min:{$ruleItemMin}", "max:{$ruleItemMax}"];

        return [
            'name'                  => [$this->method() === 'POST' ? 'required' : 'sometimes', 'string', "max:{$nameMax}"],
            // UUID phiên phân tích đã succeeded — gắn profile vào run log và
            // suy ra origin=reference (không tin client gửi origin).
            'analysis_id'           => ['nullable', 'string', 'uuid', Rule::exists('writing_profile_analyses', 'id')],
            'description'           => ['nullable', 'string', "max:{$descMax}"],
            'dominant_tier'         => ['nullable', Rule::in($dominantTiers)],
            'temperature'           => ['nullable', 'numeric', "min:{$tempMin}", "max:{$tempMax}"],
            // rules_json chặt theo 16 khóa DNA thật từ WritingProfileDefinition;
            // mỗi giá trị khóa có độ dài tối thiểu để chặn dữ liệu giả/trống.
            // 15 khóa văn bản là string; uncertainties là mảng chuỗi (doc GĐ1).
            'rules_json'            => ['nullable', 'array'],
            'rules_json.tone'       => $ruleItemValidation,
            'rules_json.pronouns'   => $ruleItemValidation,
            'rules_json.emotion'    => $ruleItemValidation,
            'rules_json.opening'    => $ruleItemValidation,
            'rules_json.sentence_rhythm' => $ruleItemValidation,
            'rules_json.paragraph_rhythm' => $ruleItemValidation,
            'rules_json.transitions' => $ruleItemValidation,
            'rules_json.vocabulary' => $ruleItemValidation,
            'rules_json.technical_terms' => $ruleItemValidation,
            'rules_json.structure_patterns' => $ruleItemValidation,
            'rules_json.headings'   => $ruleItemValidation,
            'rules_json.bullets'    => $ruleItemValidation,
            'rules_json.examples'   => $ruleItemValidation,
            'rules_json.ending'     => $ruleItemValidation,
            'rules_json.avoid'      => $ruleItemValidation,
            'rules_json.uncertainties'   => ['nullable', 'array'],
            'rules_json.uncertainties.*' => ['string', "min:{$uncertMin}", "max:{$uncertMax}"],
            'evidence_json'         => ['nullable', 'array'],
            'evidence_json.*'       => ['array'],
            'evidence_json.*.rule_key' => ['required', 'string', "max:{$ruleKeyMax}"],
            'evidence_json.*.excerpt' => ['required', 'string', "min:{$excerptMin}", "max:{$excerptMax}"],
            'evidence_json.*.note'  => ['nullable', 'string', "max:{$noteMax}"],
            'style_instructions'    => ['nullable', 'string', "min:{$styleMin}", "max:{$styleMax}"],
            'voice_texture_instructions' => ['nullable', 'string', "max:{$voiceTextureMax}"],
            'voice_texture_sliders_json' => ['nullable', 'array'],
            'voice_texture_sliders_json.voice_timbre' => ['nullable', 'integer', "min:{$sliderMin}", "max:{$sliderMax}"],
            'voice_texture_sliders_json.vernacular_flow' => ['nullable', 'integer', "min:{$sliderMin}", "max:{$sliderMax}"],
            'voice_texture_sliders_json.emotional_nuance' => ['nullable', 'integer', "min:{$sliderMin}", "max:{$sliderMax}"],
            'voice_texture_sliders_json.rhythm_variation' => ['nullable', 'integer', "min:{$sliderMin}", "max:{$sliderMax}"],
            'voice_texture_sliders_json.emphasis_glide' => ['nullable', 'integer', "min:{$sliderMin}", "max:{$sliderMax}"],
            'tone_sliders_json'     => ['nullable', 'array'],
            'tone_sliders_json.*'   => ['nullable', 'integer', "min:{$sliderMin}", "max:{$sliderMax}"],
            'slider_preferences_json' => ['nullable', 'array:voice,tone,voice_presets,tone_presets'],
            'slider_preferences_json.voice' => ['nullable', 'array', "max:{$voiceAxesMax}"],
            'slider_preferences_json.tone' => ['nullable', 'array', "max:{$toneAxesMax}"],
            'slider_preferences_json.voice.*' => ['nullable', 'integer', "min:{$sliderMin}", "max:{$sliderMax}"],
            'slider_preferences_json.tone.*' => ['nullable', 'integer', "min:{$sliderMin}", "max:{$sliderMax}"],
            'slider_preferences_json.voice_presets' => ['nullable', 'array', "max:{$voicePresetsMax}"],
            'slider_preferences_json.tone_presets' => ['nullable', 'array', "max:{$tonePresetsMax}"],
            'slider_preferences_json.voice_presets.*' => ['string', "max:{$presetNameMax}", 'distinct'],
            'slider_preferences_json.tone_presets.*' => ['string', "max:{$presetNameMax}", 'distinct'],
            'tone_instructions'     => ['nullable', 'string', "max:{$toneMax}"],
            'status'                => ['nullable', Rule::in($statuses)],
            'is_enabled'            => ['nullable', 'boolean'],

            // Slug tùy biến — tự sinh từ name nếu để trống; khớp chuẩn
            // HasSlug toàn cục. unique bảng slugs cột slug.
            'slug'                  => ['nullable', 'string', "max:{$slugMax}", "regex:{$slugRegex}"],

            // Taxonomy (Categories & Tags) hỗ trợ trait HasTaxonomy (Gap G11)
            'categories'            => ['nullable', 'array'],
            'categories.*'          => [
                'integer',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'new_categories'        => ['nullable', 'array'],
            'new_categories.*'      => ['string', "max:{$newCategoryMax}"],
            'tags'                  => ['nullable', 'array'],
            'tags.*'                => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'new_tags'              => ['nullable', 'array'],
            'new_tags.*'            => ['string', "max:{$newTagMax}"],
        ];
    }
}
