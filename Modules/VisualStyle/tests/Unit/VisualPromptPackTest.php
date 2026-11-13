<?php

namespace Modules\VisualStyle\Tests\Unit;

use Illuminate\Validation\ValidationException;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\VisualStyle\DTOs\CompiledImagePromptDto;
use Modules\VisualStyle\DTOs\GenerationResultDto;
use Modules\VisualStyle\DTOs\ImageDnaDto;
use Modules\VisualStyle\Services\VisualStyleDnaDefinition;
use Modules\VisualStyle\Services\VisualPromptPack;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ kiểm thử Prompt Pack V1 + DNA Definition VisualStyle.
 * =====================================================================
 *
 * PHPUnit chạy các ca hồi quy với fixture/mock trong môi trường kiểm thử.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - setUp(): Khởi tạo test và bật cấu hình prompt V1 trong ứng dụng kiểm thử.
 * - test_pack_resolves_current_version_and_caps(): Pack resolve đúng v1 và
 *   4 capabilities đọc qua cap() đúng.
 * - test_role_reads_analyzer_and_builder_prompts(): 2 vai trò system prompt
 *   không rỗng, role lạ trả rỗng.
 * - test_specialties_overlay_deep_merges_for_purpose(): Overlay specialty
 *   đè prompt_builder và giữ nguyên phần còn lại của pack.
 * - test_dna_sections_and_aspect_ratios_match_pack(): dnaSections +
 *   aspectRatios mirror pack đúng thứ tự.
 * - test_schema_builds_strict_object_with_five_sections(): JSON Schema strict
 *   đủ 5 nhóm + required + additionalProperties false + enum + palette_hex.
 * - test_validate_dna_accepts_full_dna(): DNA đủ 5 nhóm hợp lệ → 0 lỗi.
 * - test_validate_dna_rejects_bad_enum_and_missing_group(): Sai enum, thiếu
 *   nhóm, palette hex sai, confidence ngoài khoảng → báo đúng lỗi.
 * - test_normalize_dna_strips_unknown_and_bad_values(): Chuẩn hóa lọc key lạ,
 *   giá trị ngoài enum → null, palette lọc hex, confidence kẹp 0..1.
 * - test_empty_dna_provides_null_groups_for_manual_form(): emptyDna đủ nhóm
 *   và mọi field null.
 * - test_image_dna_dto_round_trips(): ImageDnaDto fromArray → toArray khớp.
 * - test_context_guard_throws_when_over_limit(): checkContext vượt 40000
 *   ký tự ném ValidationException.
 * - test_require_complete_rejects_truncated_output(): finish_reason=length
 *   ném AiGatewayException; finish_reason=stop đi qua.
 * - test_compiled_and_generation_dtos_serialize(): CompiledImagePromptDto +
 *   GenerationResultDto serialize key snake_case.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Config visualstyle + fixtures DNA trong bộ nhớ.
 * - OUTPUT: Assertions thành công hoặc lỗi kiểm thử.
 *
 * BỐI CẢNH SỬ DỤNG: PHPUnit chạy các ca hồi quy; không gọi AI, không ghi DB.
 * =====================================================================
 */
class VisualPromptPackTest extends TestCase
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo test và bật cấu hình prompt V1 trong ứng dụng kiểm thử
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Gọi parent setUp, thay config của ứng dụng kiểm thử + xóa cache pack.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi khởi tạo được truyền lên; không tự mở transaction.
     * =====================================================================
     */
    protected function setUp(): void
    {
        parent::setUp();
        config(['visualstyle.prompt_version' => 1]);
        VisualPromptPack::flush();
    }

    /* =====================================================================
     * PACK RESOLVE
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Pack resolve đúng v1 và capabilities đọc qua cap()
     * =====================================================================
     *
     * INPUT:
     * - Config visualstyle.prompt_version = 1 (setUp).
     *
     * OUTPUT:
     * - void; assertions pack_version = 1, 4 capabilities đúng, limit/temperature.
     *
     * SIDE EFFECT:
     * - Đọc config + cache pack trong process.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_pack_resolves_current_version_and_caps(): void
    {
        $pack = VisualPromptPack::resolve();

        $this->assertSame(1, $pack['pack_version']);
        $this->assertTrue(VisualPromptPack::cap('structured_analysis'));
        $this->assertTrue(VisualPromptPack::cap('seven_layer_prompt'));
        $this->assertTrue(VisualPromptPack::cap('shot_overrides'));
        $this->assertTrue(VisualPromptPack::cap('reference_image'));
        $this->assertFalse(VisualPromptPack::cap('nonexistent'));
        $this->assertSame(1, VisualPromptPack::version());
        $this->assertSame(40000, VisualPromptPack::limit('max_context_chars'));
        $this->assertSame(0.2, VisualPromptPack::temperature('analyzer'));
        $this->assertSame(0.4, VisualPromptPack::temperature('prompt_builder'));
        $this->assertSame(0.7, VisualPromptPack::temperature('image'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: 2 vai trò system prompt không rỗng, role lạ trả rỗng
     * =====================================================================
     *
     * INPUT:
     * - Config pack v1 với prompts.analyzer + prompts.prompt_builder.
     *
     * OUTPUT:
     * - void; assertions role() đọc đúng, role lạ rỗng.
     *
     * SIDE EFFECT:
     * - Đọc config trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_role_reads_analyzer_and_builder_prompts(): void
    {
        $analyzer = VisualPromptPack::role('analyzer');
        $builder = VisualPromptPack::role('prompt_builder');

        $this->assertNotSame('', $analyzer);
        $this->assertNotSame('', $builder);
        $this->assertStringContainsString('DNA nhiếp ảnh', $analyzer);
        // prompt_builder là frame instruction ngắn cho prompt ảnh (tiếng Anh).
        $this->assertStringContainsString('photograph', $builder);
        $this->assertSame('', VisualPromptPack::role('writer'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Overlay specialty đè prompt_builder và giữ phần còn lại
     * =====================================================================
     *
     * INPUT:
     * - Config runtime specialties.thumbnail với prompts.prompt_builder riêng.
     *
     * OUTPUT:
     * - void; assertions purpose='thumbnail' đọc prompt đè + analyzer giữ nguyên
     *   + purpose=null đọc prompt base.
     *
     * SIDE EFFECT:
     * - Ghi config runtime + xóa cache pack (flush trong setUp).
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_specialties_overlay_deep_merges_for_purpose(): void
    {
        config([
            'visualstyle.v1.specialties.thumbnail' => [
                'prompts' => ['prompt_builder' => 'PROMPT THUMBNAIL RIÊNG'],
            ],
        ]);
        VisualPromptPack::flush();

        // Có purpose → prompt_builder bị đè, analyzer giữ nguyên.
        $this->assertSame('PROMPT THUMBNAIL RIÊNG', VisualPromptPack::role('prompt_builder', 'thumbnail'));
        $this->assertSame(VisualPromptPack::role('analyzer'), VisualPromptPack::role('analyzer', 'thumbnail'));

        // Không purpose → prompt base.
        $this->assertStringNotContainsString('PROMPT THUMBNAIL RIÊNG', VisualPromptPack::role('prompt_builder'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: dnaSections + aspectRatios mirror pack đúng thứ tự
     * =====================================================================
     *
     * INPUT:
     * - Config pack v1 với dna_sections + aspect_ratios.
     *
     * OUTPUT:
     * - void; assertions khớp danh sách spec.
     *
     * SIDE EFFECT:
     * - Đọc config trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_dna_sections_and_aspect_ratios_match_pack(): void
    {
        $this->assertSame(
            ['composition', 'camera', 'lighting', 'environment', 'color_mood'],
            VisualPromptPack::dnaSections(),
        );
        $this->assertSame(['16:9', '9:16', '1:1', '4:5'], VisualPromptPack::aspectRatios());
    }

    /* =====================================================================
     * SCHEMA & VALIDATE
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: JSON Schema strict đủ 5 nhóm + enum + palette_hex
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; dùng pack v1 dna_fields.
     *
     * OUTPUT:
     * - void; assertions schema object strict, 5 nhóm required, enum có null,
     *   palette_hex pattern hex, có subject_rules/confidence.
     *
     * SIDE EFFECT:
     * - Dựng schema trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_schema_builds_strict_object_with_five_sections(): void
    {
        $schema = VisualStyleDnaDefinition::schema();

        $this->assertSame('object', $schema['type']);
        $this->assertSame(
            ['composition', 'camera', 'lighting', 'environment', 'color_mood',
                'subject_rules', 'style_constraints', 'confidence', 'taxonomy_suggestion'],
            $schema['required'],
        );
        $this->assertFalse($schema['additionalProperties']);

        // composition.camera_angle là enum có null.
        $angle = $schema['properties']['composition']['properties']['camera_angle'];
        $this->assertContains('low_angle', $angle['enum']);
        $this->assertContains(null, $angle['enum']);

        // color_mood.palette_hex là mảng hex pattern.
        $palette = $schema['properties']['color_mood']['properties']['palette_hex'];
        $this->assertSame('array', $palette['type']);
        $this->assertSame('#[0-9A-Fa-f]{6}', str_replace(['^', '$'], '', $palette['items']['pattern']));

        // confidence number 0..1.
        $confidence = $schema['properties']['confidence'];
        $this->assertSame('number', $confidence['type']);
        $this->assertSame(0, $confidence['minimum']);
        $this->assertSame(1, $confidence['maximum']);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: DNA đủ 5 nhóm hợp lệ → validate 0 lỗi
     * =====================================================================
     *
     * INPUT:
     * - Fixture DNA đầy đủ đúng enum.
     *
     * OUTPUT:
     * - void; assertions validateDna trả [] và isComplete true.
     *
     * SIDE EFFECT:
     * - Dựng fixture trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_validate_dna_accepts_full_dna(): void
    {
        $dna = [
            'composition' => ['camera_angle' => 'low_angle', 'framing_rule' => 'rule_of_thirds', 'depth_of_field' => 'shallow_dof'],
            'camera'      => ['lens_type' => '85mm', 'shot_medium' => 'photograph', 'perspective' => 'medium'],
            'lighting'    => ['tone' => 'warm_golden', 'key_light' => 'rim_light'],
            'environment' => ['setting' => 'studio', 'backdrop' => 'nền xám nhạt', 'atmosphere' => 'clean'],
            'color_mood'  => ['palette_hex' => ['#1A1A2E', '#E94560'], 'temperature' => 'warm', 'contrast' => 'high', 'grade' => 'teal_orange'],
            'subject_rules'      => ['preserve' => ['logo'], 'forbidden' => ['watermark']],
            'style_constraints'  => 'Chất ảnh điện ảnh ấm áp.',
            'confidence'         => 0.85,
            'taxonomy_suggestion'=> ['Sản phẩm', 'Giờ vàng'],
        ];

        $this->assertSame([], VisualStyleDnaDefinition::validateDna($dna));

        $dto = ImageDnaDto::fromArray($dna);
        $this->assertTrue($dto->isComplete());
        $this->assertSame(0.85, $dto->confidence);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Sai enum, thiếu nhóm, hex sai, confidence sai → báo lỗi đúng
     * =====================================================================
     *
     * INPUT:
     * - Fixture DNA lỗi: camera_angle lạ, thiếu lighting, palette sai format,
     *   confidence 1.5.
     *
     * OUTPUT:
     * - void; assertions các lỗi xuất hiện đúng nhóm/field.
     *
     * SIDE EFFECT:
     * - Dựng fixture trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_validate_dna_rejects_bad_enum_and_missing_group(): void
    {
        $dna = [
            'composition' => ['camera_angle' => 'flying_angle'], // sai enum
            'camera'      => ['lens_type' => '85mm', 'shot_medium' => 'photograph', 'perspective' => 'medium'],
            // thiếu lighting
            'environment' => ['setting' => 'studio', 'backdrop' => null, 'atmosphere' => 'clean'],
            'color_mood'  => ['palette_hex' => ['#GGGGGG'], 'temperature' => 'warm', 'contrast' => 'high', 'grade' => 'vivid'],
            'confidence'  => 1.5, // ngoài 0..1
        ];

        $errors = VisualStyleDnaDefinition::validateDna($dna);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('composition.camera_angle', implode(' ', $errors));
        $this->assertStringContainsString('lighting', implode(' ', $errors));
        $this->assertStringContainsString('palette_hex', implode(' ', $errors));
        $this->assertStringContainsString('confidence', implode(' ', $errors));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa lọc key lạ, giá trị ngoài enum → null, kẹp confidence
     * =====================================================================
     *
     * INPUT:
     * - Fixture DNA thô có key lạ, enum sai, hex lỗi, confidence 1.8.
     *
     * OUTPUT:
     * - void; assertions normalize chỉ giữ field khai báo, giá trị sai thành
     *   null, confidence = 1.0, validate sau normalize 0 lỗi.
     *
     * SIDE EFFECT:
     * - Dựng fixture trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_normalize_dna_strips_unknown_and_bad_values(): void
    {
        $raw = [
            'composition' => ['camera_angle' => 'flying_angle', 'unknown_key' => 'x', 'framing_rule' => 'centered', 'depth_of_field' => 'deep'],
            'camera'      => ['lens_type' => '50mm', 'shot_medium' => 'film', 'perspective' => 'wide'],
            'lighting'    => ['tone' => 'neon', 'key_light' => 'backlight'],
            'environment' => ['setting' => 'urban', 'backdrop' => 'nhà cao tầng', 'atmosphere' => 'haze'],
            'color_mood'  => ['palette_hex' => ['#AABBCC', 'bad', '#DDEEFF'], 'temperature' => 'cool', 'contrast' => 'low', 'grade' => 'desaturated'],
            'subject_rules' => ['preserve' => ['face', 123], 'forbidden' => []],
            'style_constraints' => str_repeat('mô tả dài ', 80),
            'confidence' => 1.8,
            'taxonomy_suggestion' => ['Tag 1', '', 'Tag 2'],
        ];

        $clean = VisualStyleDnaDefinition::normalizeDna($raw);

        // camera_angle ngoài enum → null; unknown_key bị strip.
        $this->assertNull($clean['composition']['camera_angle']);
        $this->assertArrayNotHasKey('unknown_key', $clean['composition']);
        $this->assertSame('centered', $clean['composition']['framing_rule']);

        // palette lọc hex hợp lệ, uppercase.
        $this->assertSame(['#AABBCC', '#DDEEFF'], $clean['color_mood']['palette_hex']);

        // confidence kẹp 1.0; style_constraints cắt 500 ký tự; suggestion lọc rỗng.
        $this->assertSame(1.0, $clean['confidence']);
        $this->assertLessThanOrEqual(500, mb_strlen($clean['style_constraints']));
        $this->assertSame(['Tag 1', 'Tag 2'], $clean['taxonomy_suggestion']);
        // preserve lọc giá trị không phải string.
        $this->assertSame(['face'], $clean['subject_rules']['preserve']);

        // Sau normalize validate phải sạch.
        $this->assertSame([], VisualStyleDnaDefinition::validateDna($clean));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: emptyDna đủ nhóm và mọi field null (form tạo tay)
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số.
     *
     * OUTPUT:
     * - void; assertions 5 nhóm null-field + subject_rules rỗng.
     *
     * SIDE EFFECT:
     * - Dựng trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_empty_dna_provides_null_groups_for_manual_form(): void
    {
        $dna = VisualStyleDnaDefinition::emptyDna();

        foreach (VisualStyleDnaDefinition::sections() as $section) {
            $this->assertArrayHasKey($section, $dna);
            foreach ($dna[$section] as $value) {
                $this->assertNull($value);
            }
        }
        $this->assertSame([], $dna['subject_rules']['preserve']);
        $this->assertSame([], $dna['taxonomy_suggestion']);
    }

    /* =====================================================================
     * DTO
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: ImageDnaDto fromArray → toArray round-trip khớp
     * =====================================================================
     *
     * INPUT:
     * - Fixture DNA đầy đủ.
     *
     * OUTPUT:
     * - void; assertions toArray khớp input key snake_case + group() helper.
     *
     * SIDE EFFECT:
     * - Dựng DTO trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_image_dna_dto_round_trips(): void
    {
        $dna = [
            'composition' => ['camera_angle' => 'eye_level', 'framing_rule' => 'centered', 'depth_of_field' => 'deep'],
            'camera'      => ['lens_type' => '35mm', 'shot_medium' => 'digital', 'perspective' => 'wide'],
            'lighting'    => ['tone' => 'studio_clean', 'key_light' => 'softbox'],
            'environment' => ['setting' => 'studio', 'backdrop' => 'trắng', 'atmosphere' => 'clean'],
            'color_mood'  => ['palette_hex' => ['#FFFFFF'], 'temperature' => 'neutral', 'contrast' => 'muted', 'grade' => 'pastel'],
            'subject_rules'      => ['preserve' => ['product_shape'], 'forbidden' => ['extra_fingers']],
            'style_constraints'  => 'Sạch, sáng, tối giản.',
            'confidence'         => 0.9,
            'taxonomy_suggestion'=> ['Bán lẻ'],
        ];

        $dto = ImageDnaDto::fromArray($dna);
        $out = $dto->toArray();

        $this->assertSame($dna['composition'], $out['composition']);
        $this->assertSame($dna['color_mood'], $out['color_mood']);
        $this->assertSame($dna['subject_rules'], $out['subject_rules']);
        $this->assertSame(0.9, $out['confidence']);
        $this->assertSame($dna['taxonomy_suggestion'], $out['taxonomy_suggestion']);
        $this->assertSame($dna['composition'], $dto->group('composition'));
        $this->assertSame([], $dto->group('nonexistent'));
    }

    /* =====================================================================
     * GUARD
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: checkContext vượt 40000 ký tự ném ValidationException
     * =====================================================================
     *
     * INPUT:
     * - 2 chuỗi dài hơn limits.max_context_chars.
     *
     * OUTPUT:
     * - void; assertions ném ValidationException khi vượt, pass khi dưới.
     *
     * SIDE EFFECT:
     * - Dựng chuỗi trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException là kết quả mong đợi. Không mở transaction.
     * =====================================================================
     */
    public function test_context_guard_throws_when_over_limit(): void
    {
        VisualPromptPack::checkContext('ngắn');

        $this->expectException(ValidationException::class);
        VisualPromptPack::checkContext(
            str_repeat('a', 25000),
            str_repeat('b', 25000),
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: finish_reason=length ném AiGatewayException; stop đi qua
     * =====================================================================
     *
     * INPUT:
     * - 2 AiStructuredResponse raw với finish_reason khác nhau.
     *
     * OUTPUT:
     * - void; assertions truncated → exception, stop → void.
     *
     * SIDE EFFECT:
     * - Dựng DTO trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AiGatewayException là kết quả mong đợi. Không mở transaction.
     * =====================================================================
     */
    public function test_require_complete_rejects_truncated_output(): void
    {
        // finish_reason=stop — đi qua không ném.
        VisualPromptPack::requireComplete(new AiStructuredResponse(
            text: '',
            data: [],
            raw: ['choices' => [['finish_reason' => 'stop']]],
        ));

        // finish_reason=length — bị chặn.
        try {
            VisualPromptPack::requireComplete(new AiStructuredResponse(
                text: '',
                data: [],
                raw: ['choices' => [['finish_reason' => 'length']]],
            ));
            $this->fail('Kỳ vọng AiGatewayException khi output bị cắt.');
        } catch (AiGatewayException $e) {
            $this->assertSame('truncated_output', $e->errorCode());
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: CompiledImagePromptDto + GenerationResultDto serialize đúng
     * =====================================================================
     *
     * INPUT:
     * - 2 DTO mẫu.
     *
     * OUTPUT:
     * - void; assertions key snake_case, binary không xuất hiện trong serialize.
     *
     * SIDE EFFECT:
     * - Dựng DTO trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - AssertionFailedError nếu sai. Không mở transaction.
     * =====================================================================
     */
    public function test_compiled_and_generation_dtos_serialize(): void
    {
        $compiled = new CompiledImagePromptDto(
            prompt: 'A product on a table, low angle...',
            negativePrompt: 'watermark, deformed_hands',
            aspectRatio: '16:9',
            referenceImages: [['mime' => 'image/png', 'data' => 'BASE64-X']],
            layers: ['1_subject' => 'product', '2_style' => 'cinematic'],
            purpose: 'thumbnail',
        );

        $arr = $compiled->toArray();
        $this->assertSame('16:9', $arr['aspect_ratio']);
        $this->assertSame(1, $arr['reference_count']); // binary không xuất hiện
        $this->assertArrayNotHasKey('referenceImages', $arr);
        $this->assertSame('thumbnail', $arr['purpose']);

        $result = new GenerationResultDto(
            mediaAssetId: 42,
            previewUrl: 'http://localhost/media/42',
            promptUsed: 'A product...',
            aspectRatio: '16:9',
            aiRunId: 'run-uuid-1',
        );

        $res = $result->toArray();
        $this->assertSame(42, $res['media_asset_id']);
        $this->assertSame('run-uuid-1', $res['ai_run_id']);
    }
}
