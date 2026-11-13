<?php

namespace Modules\VisualStyle\Services;

use Modules\VisualStyle\DTOs\CompiledImagePromptDto;
use Modules\VisualStyle\Models\VisualStyle;
use Modules\VisualStyle\Models\VisualStyleShot;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: VisualPromptBuilder — ghép prompt sinh ảnh 7 lớp cố định.
 * =====================================================================
 *
 * Thứ tự lớp BẮT BUỘC (spec P2-03 Task 5):
 *   1. SubjectIdentity       — chủ thể + hành động người dùng mô tả
 *   2. StyleDna              — 5 nhóm DNA (composition, camera, lighting,
 *                              environment, color_mood) sau khi đã áp
 *                              override của shot
 *   3. CategoryAdaptation    — gợi ý ngữ cảnh từ taxonomy (scope visual_style)
 *   4. ShotRecipe            — code/name/suffix của góc chụp đã chọn
 *   5. UserInstructions      — chỉ dẫn tự do của người dùng
 *   6. PreservationConstraint— subject_rules + reference_media_id (khi cap
 *                              'reference_image' bật) để giữ đặc trưng
 *   7. NegativeLayer         — negative_prompt của style + suffix shot +
 *                              hậu tố '--ar <aspect>'
 *
 * Đây là backend thuần: KHÔNG gọi AI thêm. System/instruction khung mở đầu
 * lấy từ `VisualPromptPack::role('prompt_builder', $purpose)` (có thể
 * overlay `specialties[purpose]`). Prompt ghép lưu vào
 * `compiled_prompt_preview` của visual_styles để preview UI.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - build(): ghép 7 lớp → CompiledImagePromptDto + lưu preview.
 * - applyShotOverrides(): ghi đè composition/camera của DNA bằng override.
 * - styleDnaLayer(): render 5 nhóm DNA thành câu mô tả.
 * - categoryAdaptationLayer(): render taxonomy thành dòng gợi ý.
 * - negativeLayer(): tổng hợp negative + aspect suffix.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : VisualStyle + ?VisualStyleShot + userVars (subject, instructions,
 *          aspect_ratio, category_context) + ?purpose.
 * - OUTPUT: CompiledImagePromptDto — prompt + negative + aspectRatio +
 *          referenceImages + layers (từng lớp rời cho preview).
 * =====================================================================
 */
class VisualPromptBuilder
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Ghép prompt 7 lớp theo thứ tự cố định
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * 1. Shot overrides áp lên DNA trước khi render lớp 2.
     * 2. Lớp 2 dùng DNA đã áp override (nếu cap('shot_overrides') off thì bỏ).
     * 3. Lớp 3 taxonomy lấy categories+tags name của style (scope visual_style).
     * 4. Lớp 6 chỉ gắn reference khi cap('reference_image') và style có
     *    reference_media_id.
     * 5. Aspect ratio: userVars.aspect_ratio → shot.suggested_aspect_ratio →
     *    config('visualstyle.generation.default_aspect_ratio').
     * 6. Lưu compiled_prompt_preview (không chứa binary reference).
     * =====================================================================
     *
     * INPUT:
     * - VisualStyle $style — style đã usable (dna_json có thể null nếu
     *   manual: render emptyDna).
     * - ?VisualStyleShot $shot — góc chụp (null = lớp 4 chỉ dùng mặc định).
     * - array $userVars — ['subject','instructions','aspect_ratio',
     *   'category_context' => [ids]].
     * - ?string $purpose — 'thumbnail'|'hero'|'b_roll'|'portrait' (overlay
     *   specialties cho prompt_builder).
     *
     * OUTPUT:
     * - CompiledImagePromptDto
     *
     * SIDE EFFECT:
     * - UPDATE compiled_prompt_preview trên visual_styles.
     *
     * EXCEPTION/TRANSACTION:
     * - InvalidArgumentException khi style không dùng được hoặc thiếu subject.
     * =====================================================================
     */
    public function build(
        VisualStyle $style,
        ?VisualStyleShot $shot = null,
        array $userVars = [],
        ?string $purpose = null,
    ): CompiledImagePromptDto {
        if (! $style->isUsable()) {
            throw new \InvalidArgumentException('Visual Style chưa đủ điều kiện sinh ảnh (chưa có DNA hoặc đang tắt).');
        }

        $subject = trim((string) ($userVars['subject'] ?? ''));
        if ($subject === '') {
            throw new \InvalidArgumentException('Thiếu chủ thể (subject) để ghép prompt.');
        }

        // 1) DNA gốc → áp override của shot (nếu cap bật).
        $dna = is_array($style->dna_json) ? $style->dna_json : [];
        if ($dna === [] ) {
            $dna = \Modules\VisualStyle\Services\VisualStyleDnaDefinition::emptyDna();
        }
        $effective = $dna;
        if ($shot !== null && VisualPromptPack::cap('shot_overrides')) {
            $effective = $this->applyShotOverrides($effective, $shot);
        }

        // 2) Lớp định hướng: system/instruction của prompt_builder (có overlay
        //    specialty theo purpose) + chủ thể.
        $frame = trim(VisualPromptPack::role('prompt_builder', $purpose));
        $subjectLayer = trim(($frame !== '' ? $frame . ' of ' : '') . $subject);

        // 3) Lớp DNA — render 5 nhóm sau override.
        $styleLayer = $this->styleDnaLayer($effective);

        // 4) Lớp taxonomy — categories + tags của style.
        $categoryLayer = $this->categoryAdaptationLayer($style, $userVars);

        // 5) Lớp shot — code/name + prompt_suffix.
        $shotLayer = $this->shotRecipeLayer($shot);

        // 6) Lớp user instructions.
        $userLayer = trim((string) ($userVars['instructions'] ?? ''));

        // 7) Lớp preservation — subject_rules + reference (nếu cap on).
        $preservationLayer = $this->preservationLayer($effective, $style);

        // Aspect ratio: userVars → shot → config default.
        $aspect = trim((string) ($userVars['aspect_ratio'] ?? ''));
        if ($aspect === '' && $shot !== null) {
            $aspect = trim((string) $shot->suggested_aspect_ratio);
        }
        if ($aspect === '' || ! \Modules\VisualStyle\Services\VisualStyleDnaDefinition::isValidAspectRatio($aspect)) {
            $aspect = (string) config('visualstyle.generation.default_aspect_ratio', '16:9');
        }

        // 8) Negative layer — negative_prompt style + shot + suffix '--ar'.
        $negative = $this->negativeLayer($style, $shot, $aspect);

        // 9) Ghép 7 lớp theo thứ tự — mỗi lớp 1 đoạn, giữ thứ tự cố định.
        $layers = [
            '1_subject_identity'        => $subjectLayer,
            '2_style_dna'               => $styleLayer,
            '3_category_adaptation'     => $categoryLayer,
            '4_shot_recipe'             => $shotLayer,
            '5_user_instructions'       => $userLayer,
            '6_preservation_constraint' => $preservationLayer,
            '7_negative_layer'          => $negative,
        ];

        $prompt = implode("\n\n", array_filter($layers, static fn ($v) => is_string($v) && $v !== ''));

        // Reference images cho subject preservation (chỉ khi cap on).
        $referenceImages = [];
        if (VisualPromptPack::cap('reference_image') && $style->reference_media_id) {
            $referenceImages[] = [
                'mime' => 'image/*',
                'media_asset_id' => $style->reference_media_id,
            ];
        }

        // Lưu preview (không chứa binary) — phục vụ UI preview trước khi sinh.
        $style->forceFill(['compiled_prompt_preview' => $prompt])->save();

        return new CompiledImagePromptDto(
            prompt:           $prompt,
            negativePrompt:   $negative,
            aspectRatio:      $aspect,
            referenceImages:  $referenceImages,
            layers:           $layers,
            purpose:          $purpose,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Áp override của shot lên DNA gốc (composition + camera)
     * =====================================================================
     *
     * INPUT:
     * - array $dna — dna_json gốc.
     * - VisualStyleShot $shot — shot có composition_override_json /
     *   camera_override_json.
     *
     * OUTPUT:
     * - array DNA sau khi áp override (field null của shot không ghi đè).
     *
     * SIDE EFFECT:
     * - Không ghi DB.
     * =====================================================================
     */
    protected function applyShotOverrides(array $dna, VisualStyleShot $shot): array
    {
        $comp = is_array($shot->composition_override_json) ? $shot->composition_override_json : [];
        $cam  = is_array($shot->camera_override_json) ? $shot->camera_override_json : [];

        foreach ($comp as $key => $value) {
            if ($value !== null) {
                $dna['composition'][$key] = $value;
            }
        }
        foreach ($cam as $key => $value) {
            if ($value !== null) {
                $dna['camera'][$key] = $value;
            }
        }

        return $dna;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Render 5 nhóm DNA thành 1 câu mô tả (lớp 2)
     * =====================================================================
     * Ghép từng nhóm thành cụm có nhãn, giữ nguyên văn enum (gạch dưới →
     * khoảng trắng để dễ đọc). Chỉ rải field có giá trị (không null/rỗng).
     *
     * INPUT: array $dna — DNA đã áp override.
     * OUTPUT: string — 'composition: low angle, rule of thirds; camera: 85mm; ...'
     * =====================================================================
     */
    protected function styleDnaLayer(array $dna): string
    {
        $parts = [];

        foreach (\Modules\VisualStyle\Services\VisualStyleDnaDefinition::sections() as $section) {
            $group = is_array($dna[$section] ?? null) ? $dna[$section] : [];
            $fragments = [];

            foreach ($group as $field => $value) {
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                if ($field === 'palette_hex' && is_array($value)) {
                    $fragments[] = implode(', ', $value);

                    continue;
                }

                $fragments[] = is_string($value) ? str_replace('_', ' ', $value) : $value;
            }

            if ($fragments !== []) {
                $parts[] = $section . ': ' . implode(', ', $fragments);
            }
        }

        // style_constraints thêm vào cuối lớp (mô tả tổng thể).
        $constraints = trim((string) ($dna['style_constraints'] ?? ''));
        if ($constraints !== '') {
            $parts[] = 'style: ' . $constraints;
        }

        return implode('; ', $parts);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Render taxonomy (categories + tags) thành dòng gợi ý (lớp 3)
     * =====================================================================
     * Lấy name từ categories/tags đã eager-load của style; nếu userVars
     * truyền 'category_context' thì ưu tiên đó.
     *
     * INPUT: VisualStyle $style + array $userVars.
     * OUTPUT: string — rỗng nếu không có taxonomy.
     * =====================================================================
     */
    protected function categoryAdaptationLayer(VisualStyle $style, array $userVars): string
    {
        // userVars.category_context cho phép caller truyền taxonomy override.
        $custom = $userVars['category_context'] ?? null;
        if (is_array($custom) && $custom !== []) {
            $names = array_filter(array_map('trim', $custom));
            return $names === [] ? '' : 'context: ' . implode(', ', $names);
        }

        $names = [];
        if ($style->relationLoaded('categories')) {
            $names = array_merge($names, $style->categories->pluck('name')->all());
        }
        if ($style->relationLoaded('tags')) {
            $names = array_merge($names, $style->tags->pluck('name')->all());
        }

        return $names === [] ? '' : 'context: ' . implode(', ', $names);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Render shot thành dòng gợi ý (lớp 4)
     * =====================================================================
     * code + name + prompt_suffix của shot (ví dụ 'hero: Hero — Ảnh chủ đạo.
     * Focus on depth and grandeur.').
     *
     * INPUT: ?VisualStyleShot $shot.
     * OUTPUT: string — rỗng khi không có shot.
     * =====================================================================
     */
    protected function shotRecipeLayer(?VisualStyleShot $shot): string
    {
        if ($shot === null) {
            return '';
        }

        $parts = [];
        if ($shot->code !== '') {
            $parts[] = 'shot: ' . $shot->code;
        }
        if ($shot->name !== '') {
            $parts[] = $shot->name;
        }
        $suffix = trim((string) ($shot->prompt_suffix ?? ''));
        if ($suffix !== '') {
            $parts[] = $suffix;
        }

        return implode(' — ', $parts);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Render subject preservation (lớp 6)
     * =====================================================================
     * Ghép subject_rules preserve/forbidden + nhắc có reference image.
     *
     * INPUT: array $dna, VisualStyle $style.
     * OUTPUT: string — 'preserve: logo, face; avoid: watermark; reference: #42'.
     * =====================================================================
     */
    protected function preservationLayer(array $dna, VisualStyle $style): string
    {
        $rules = is_array($dna['subject_rules'] ?? null) ? $dna['subject_rules'] : [];
        $preserve = is_array($rules['preserve'] ?? null) ? $rules['preserve'] : [];
        $forbidden = is_array($rules['forbidden'] ?? null) ? $rules['forbidden'] : [];

        $parts = [];
        if ($preserve !== []) {
            $parts[] = 'preserve: ' . implode(', ', $preserve);
        }
        if ($forbidden !== []) {
            $parts[] = 'avoid: ' . implode(', ', $forbidden);
        }
        if ($style->reference_media_id) {
            $parts[] = 'reference: use the provided reference image to keep subject identity';
        }

        return implode('; ', $parts);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghép negative prompt (lớp 7)
     * =====================================================================
     * negative_prompt của style + suffix shot + '--ar <aspect>'.
     *
     * INPUT: VisualStyle $style, ?VisualStyleShot $shot, string $aspect.
     * OUTPUT: string — 'watermark, deformed_hands --ar 16:9'.
     * =====================================================================
     */
    protected function negativeLayer(VisualStyle $style, ?VisualStyleShot $shot, string $aspect): string
    {
        $parts = [];
        $neg = trim((string) ($style->negative_prompt ?? ''));
        if ($neg !== '') {
            $parts[] = $neg;
        }
        // Shot có thể có negative riêng trong prompt_suffix dạng 'no: x, y'
        // — spec P2-03 chỉ yêu cầu prompt_suffix cho shot nên giữ phần đó
        // vào lớp 4, lớp 7 chỉ nối '--ar'.
        $parts[] = '--ar ' . $aspect;

        return implode(' ', $parts);
    }
}
