<?php

namespace Modules\VisualStyle\Services;

use Modules\VisualStyle\Enums\ShotAspectRatio;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Định nghĩa & tiện ích DNA ảnh 5 nhóm (VisualStyleDnaDefinition).
 * =====================================================================
 *
 * - `sections()`      — 5 nhóm DNA bắt buộc theo thứ tự pack.
 * - `schema()`        — JSON Schema strict cho `executeStructured` (analyzer
 *   trả 5 nhóm + subject_rules + style_constraints + confidence +
 *   taxonomy_suggestion). Build bằng `VisualPromptPack::objectSchema`.
 * - `validateDna()`   — kiểm tra DNA đầu vào: đủ 5 nhóm, đúng enum giá trị
 *   cho phép; trả mảng lỗi (rỗng = hợp lệ).
 * - `emptyDna()`      — DNA rỗng chuẩn (từng nhóm null) cho form tạo tay.
 * - `fieldValues()`   — enum giá trị cho phép của 1 field trong nhóm.
 *
 * Câu chữ/mô tả nhóm đọc từ prompt pack (`v1.dna_fields`) — pack là nguồn
 * duy nhất của định nghĩa enum; class này chỉ lắp schema + validate.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : mảng dna_json (từ AI hoặc form người dùng).
 * - OUTPUT: schema/DTO/validate result — không gọi AI, không ghi DB.
 * =====================================================================
 */
class VisualStyleDnaDefinition
{
    /* =====================================================================
     * DANH MỤC NHÓM & FIELD
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: 5 nhóm DNA bắt buộc theo thứ tự pack hiện hành.
     * =====================================================================
     * INPUT: không. OUTPUT: string[] (composition, camera, lighting,
     * environment, color_mood).
     * =====================================================================
     */
    public static function sections(): array
    {
        return VisualPromptPack::dnaSections();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Định nghĩa chi tiết 1 nhóm DNA (label + fields enum).
     * =====================================================================
     * INPUT: string $section. OUTPUT: ?array (null nếu nhóm không khai báo).
     * =====================================================================
     */
    public static function sectionDefinition(string $section): ?array
    {
        $fields = VisualPromptPack::dict('dna_fields');

        return is_array($fields[$section] ?? null) ? $fields[$section] : null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Enum giá trị cho phép của 1 field trong 1 nhóm.
     * =====================================================================
     * INPUT: string $section (vd 'composition'), string $field (vd 'camera_angle').
     * OUTPUT: string[] — rỗng = field tự do (chuỗi/mảng, không enum).
     * =====================================================================
     */
    public static function fieldValues(string $section, string $field): array
    {
        $def = self::sectionDefinition($section);

        return (array) ($def['fields'][$field]['values'] ?? []);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tỷ lệ khung hình hỗ trợ của pack hiện hành.
     * =====================================================================
     * INPUT: không. OUTPUT: string[].
     * =====================================================================
     */
    public static function aspectRatios(): array
    {
        return VisualPromptPack::aspectRatios();
    }

    /* =====================================================================
     * JSON SCHEMA CHO ANALYZER
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Dựng JSON Schema strict cho output của Vision Analyzer.
     * =====================================================================
     * Chi tiết triển khai:
     * - 5 nhóm DNA là object có các field enum/tự do theo pack dna_fields.
     * - subject_rules: object {preserve: string[], forbidden: string[]}.
     * - style_constraints: string (<= 500 ký tự).
     * - confidence: number 0..1.
     * - taxonomy_suggestion: string[] tối đa 5 phần tử.
     * - Mọi property required + additionalProperties:false (OpenAI strict).
     *
     * INPUT: không. OUTPUT: array JSON Schema object gốc.
     * =====================================================================
     */
    public static function schema(): array
    {
        $sections = [];

        foreach (self::sections() as $section) {
            $def = self::sectionDefinition($section) ?? ['fields' => []];
            $props = [];

            foreach ($def['fields'] ?? [] as $name => $meta) {
                $values = (array) ($meta['values'] ?? []);

                if ($name === 'palette_hex') {
                    // Mảng mã hex — items pattern #RRGGBB, tối đa 6.
                    $props[$name] = [
                        'type'  => 'array',
                        'items' => ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$'],
                        'minItems' => 0,
                        'maxItems' => 6,
                    ];

                    continue;
                }

                if ($values === []) {
                    // Field tự do — chuỗi hoặc null.
                    $props[$name] = ['type' => ['string', 'null']];

                    continue;
                }

                // Field enum — string trong values hoặc null (không đoán).
                $props[$name] = [
                    'type' => ['string', 'null'],
                    'enum' => array_merge($values, [null]),
                ];
            }

            $sections[$section] = self::buildSchemaObject($props);
        }

        return self::buildSchemaObject(array_merge($sections, [
            'subject_rules'       => self::buildSchemaObject([
                'preserve'  => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 12],
                'forbidden' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 12],
            ]),
            'style_constraints'   => ['type' => 'string'],
            'confidence'          => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            'taxonomy_suggestion' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 5],
        ]));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Dựng 1 object schema strict (helper nội bộ).
     * =====================================================================
     * INPUT: array $fields — map tên → property schema.
     * OUTPUT: array schema object + allOf minProperties để ép đủ key.
     * =====================================================================
     */
    private static function buildSchemaObject(array $fields): array
    {
        return [
            'type'                 => 'object',
            'properties'           => $fields,
            'required'             => array_keys($fields),
            'additionalProperties' => false,
        ];
    }

    /* =====================================================================
     * VALIDATE & DNA RỖNG
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra DNA đầu vào — đủ 5 nhóm + đúng enum giá trị.
     * =====================================================================
     * Chi tiết triển khai:
     * - Nhóm thiếu hoặc không phải object → lỗi '<section>_missing'.
     * - Field enum có giá trị ngoài danh sách pack → lỗi '<section>.<field>'.
     * - palette_hex sai format hex → lỗi 'palette_hex'.
     * - confidence ngoài 0..1 → lỗi 'confidence'.
     * - Trả mảng message lỗi; RỖNG = hợp lệ.
     *
     * INPUT: array $dna — dna_json cần kiểm tra (từ AI hoặc form).
     * OUTPUT: array<string> — lỗi theo dạng 'nhóm.field: mô tả'.
     * =====================================================================
     */
    public static function validateDna(array $dna): array
    {
        $errors = [];

        foreach (self::sections() as $section) {
            $group = $dna[$section] ?? null;

            if (! is_array($group)) {
                $errors[] = "{$section}: thiếu nhóm DNA bắt buộc.";

                continue;
            }

            $def = self::sectionDefinition($section);
            foreach ((array) ($def['fields'] ?? []) as $name => $meta) {
                $value = $group[$name] ?? null;

                if ($value === null) {
                    continue; // null = không đoán — hợp lệ
                }

                if ($name === 'palette_hex') {
                    if (! is_array($value)) {
                        $errors[] = "{$section}.palette_hex: phải là mảng mã hex.";

                        continue;
                    }
                    foreach ($value as $hex) {
                        if (! is_string($hex) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
                            $errors[] = "{$section}.palette_hex: '{$hex}' không đúng định dạng #RRGGBB.";
                        }
                    }

                    continue;
                }

                $values = (array) ($meta['values'] ?? []);
                if ($values !== [] && ! in_array($value, $values, true)) {
                    $errors[] = "{$section}.{$name}: '{$value}' không thuộc enum cho phép ("
                        .implode('|', $values).').';
                }
            }
        }

        // confidence 0..1 (nếu có).
        $confidence = $dna['confidence'] ?? null;
        if ($confidence !== null && (! is_numeric($confidence) || $confidence < 0 || $confidence > 1)) {
            $errors[] = 'confidence: phải là số trong khoảng 0.0–1.0.';
        }

        return $errors;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: DNA rỗng chuẩn — từng nhóm null (cho form tạo tay).
     * =====================================================================
     * INPUT: không.
     * OUTPUT: array<string,mixed> — 5 nhóm với mọi field null.
     * =====================================================================
     */
    public static function emptyDna(): array
    {
        $dna = [];

        foreach (self::sections() as $section) {
            $def = self::sectionDefinition($section) ?? ['fields' => []];
            $group = [];

            foreach (array_keys((array) ($def['fields'] ?? [])) as $name) {
                $group[$name] = null;
            }

            $dna[$section] = $group;
        }

        $dna['subject_rules'] = ['preserve' => [], 'forbidden' => []];
        $dna['style_constraints'] = '';
        $dna['confidence'] = null;
        $dna['taxonomy_suggestion'] = [];

        return $dna;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa DNA trước khi lưu — lọc key lạ, bỏ giá trị sai.
     * =====================================================================
     * Chi tiết triển khai:
     * - Chỉ giữ 5 nhóm + 4 phụ trợ (subject_rules, style_constraints,
     *   confidence, taxonomy_suggestion).
     * - Trong mỗi nhóm chỉ giữ field khai báo trong pack; giá trị ngoài enum
     *   → null (không ném lỗi — lưu phần tốt nhất AI trả về).
     * - palette_hex lọc chỉ giữ mã hex hợp lệ, tối đa 6.
     *
     * INPUT: array $dna — dna_json thô từ AI.
     * OUTPUT: array dna đã chuẩn hóa (an toàn để ghi dna_json).
     * =====================================================================
     */
    public static function normalizeDna(array $dna): array
    {
        $clean = [];

        foreach (self::sections() as $section) {
            $group = $dna[$section] ?? null;
            if (! is_array($group)) {
                $clean[$section] = null;

                continue;
            }

            $def = self::sectionDefinition($section) ?? ['fields' => []];
            $cleanGroup = [];

            foreach (array_keys((array) ($def['fields'] ?? [])) as $name) {
                $value = $group[$name] ?? null;

                if ($value === null) {
                    $cleanGroup[$name] = null;

                    continue;
                }

                if ($name === 'palette_hex') {
                    $hexes = [];
                    foreach ((array) $value as $hex) {
                        if (is_string($hex) && preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
                            $hexes[] = strtoupper($hex);
                        }
                        if (count($hexes) >= 6) {
                            break;
                        }
                    }
                    $cleanGroup[$name] = $hexes ?: null;

                    continue;
                }

                $values = (array) ($def['fields'][$name]['values'] ?? []);
                if ($values !== [] && ! in_array($value, $values, true)) {
                    $cleanGroup[$name] = null;

                    continue;
                }

                $cleanGroup[$name] = $value;
            }

            $clean[$section] = $cleanGroup;
        }

        // Phụ trợ.
        $subjectRules = $dna['subject_rules'] ?? [];
        $clean['subject_rules'] = [
            'preserve'  => array_slice(array_filter(
                is_array($subjectRules['preserve'] ?? null) ? $subjectRules['preserve'] : [],
                'is_string',
            ), 0, 12),
            'forbidden' => array_slice(array_filter(
                is_array($subjectRules['forbidden'] ?? null) ? $subjectRules['forbidden'] : [],
                'is_string',
            ), 0, 12),
        ];

        $constraints = $dna['style_constraints'] ?? '';
        $clean['style_constraints'] = is_string($constraints)
            ? mb_substr(trim($constraints), 0, 500)
            : '';

        $confidence = $dna['confidence'] ?? null;
        $clean['confidence'] = is_numeric($confidence)
            ? round((float) min(max((float) $confidence, 0.0), 1.0), 2)
            : null;

        $suggestion = $dna['taxonomy_suggestion'] ?? [];
        $clean['taxonomy_suggestion'] = array_slice(array_filter(
            is_array($suggestion) ? $suggestion : [],
            static fn ($t) => is_string($t) && trim($t) !== '',
        ), 0, 5);

        return $clean;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra tỷ lệ khung hình có thuộc hỗ trợ không.
     * =====================================================================
     * INPUT: string $ratio. OUTPUT: bool.
     * =====================================================================
     */
    public static function isValidAspectRatio(string $ratio): bool
    {
        // Ưu tiên enum cố định; pack có thể mở rộng thêm về sau.
        return ShotAspectRatio::isValid($ratio)
            || in_array($ratio, self::aspectRatios(), true);
    }
}
