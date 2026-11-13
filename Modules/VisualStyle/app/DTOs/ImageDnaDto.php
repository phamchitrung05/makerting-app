<?php

namespace Modules\VisualStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO DNA ảnh 5 nhóm — đầu ra chuẩn của Vision Analyzer.
 * =====================================================================
 *
 * Đóng gói `dna_json` đã chuẩn hóa (5 nhóm enum + subject_rules +
 * style_constraints + confidence + taxonomy_suggestion) sau khi
 * `VisualStyleDnaDefinition::normalizeDna()`. Dùng để:
 * - Lưu vào `visual_styles.dna_json` (toArray).
 * - Đưa vào prompt builder và snapshot (đọc thuộc tính).
 * - Round-trip fromArray/toArray cho test + serialize.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : mảng dna đã chuẩn hóa.
 * - OUTPUT: readonly object + fromArray()/toArray().
 * =====================================================================
 */
final readonly class ImageDnaDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO DNA ảnh.
     * INPUT: 5 nhóm (composition/camera/lighting/environment/color_mood),
     *        subjectRules, styleConstraints, confidence, taxonomySuggestion.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public ?array $composition = null,
        public ?array $camera = null,
        public ?array $lighting = null,
        public ?array $environment = null,
        public ?array $colorMood = null,
        public array $subjectRules = [],
        public string $styleConstraints = '',
        public ?float $confidence = null,
        public array $taxonomySuggestion = [],
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Dựng DTO từ mảng dna_json đã chuẩn hóa.
     * =====================================================================
     * INPUT: array $dna — mảng dna_json (key snake_case).
     * OUTPUT: self.
     * =====================================================================
     */
    public static function fromArray(array $dna): self
    {
        return new self(
            composition:        is_array($dna['composition'] ?? null) ? $dna['composition'] : null,
            camera:             is_array($dna['camera'] ?? null) ? $dna['camera'] : null,
            lighting:           is_array($dna['lighting'] ?? null) ? $dna['lighting'] : null,
            environment:        is_array($dna['environment'] ?? null) ? $dna['environment'] : null,
            colorMood:          is_array($dna['color_mood'] ?? null) ? $dna['color_mood'] : null,
            subjectRules:       is_array($dna['subject_rules'] ?? null) ? $dna['subject_rules'] : [],
            styleConstraints:   (string) ($dna['style_constraints'] ?? ''),
            confidence:         isset($dna['confidence']) && is_numeric($dna['confidence'])
                                    ? (float) $dna['confidence']
                                    : null,
            taxonomySuggestion: is_array($dna['taxonomy_suggestion'] ?? null)
                                    ? $dna['taxonomy_suggestion']
                                    : [],
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize về mảng dna_json chuẩn để lưu visual_styles.
     * =====================================================================
     * INPUT: không. OUTPUT: array key snake_case khớp schema DNA.
     * =====================================================================
     */
    public function toArray(): array
    {
        return [
            'composition'         => $this->composition,
            'camera'              => $this->camera,
            'lighting'            => $this->lighting,
            'environment'         => $this->environment,
            'color_mood'          => $this->colorMood,
            'subject_rules'       => $this->subjectRules,
            'style_constraints'   => $this->styleConstraints,
            'confidence'          => $this->confidence,
            'taxonomy_suggestion' => $this->taxonomySuggestion,
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Nhóm DNA cụ thể (rỗng nếu thiếu).
     * =====================================================================
     * INPUT: string $group — 'composition'|'camera'|'lighting'|'environment'|'color_mood'.
     * OUTPUT: array.
     * =====================================================================
     */
    public function group(string $group): array
    {
        $map = [
            'composition' => $this->composition,
            'camera'      => $this->camera,
            'lighting'    => $this->lighting,
            'environment' => $this->environment,
            'color_mood'  => $this->colorMood,
        ];

        return is_array($map[$group] ?? null) ? $map[$group] : [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đã đủ 5 nhóm DNA chưa (không null).
     * =====================================================================
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function isComplete(): bool
    {
        return $this->composition !== null
            && $this->camera !== null
            && $this->lighting !== null
            && $this->environment !== null
            && $this->colorMood !== null;
    }
}
