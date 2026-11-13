<?php

namespace Modules\VisualStyle\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VisualStyle\DTOs\CompiledImagePromptDto;
use Modules\VisualStyle\Models\VisualStyle;
use Modules\VisualStyle\Models\VisualStyleShot;
use Modules\VisualStyle\Services\VisualPromptBuilder;
use Modules\VisualStyle\Services\VisualPromptPack;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ kiểm thử VisualPromptBuilder (Task 5).
 * =====================================================================
 *
 * Refresh DB để tạo VisualStyle + shots + taxonomy; mock MediaAsset bằng
 * model DB khi cần reference. Không gọi AI.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - makeStyle(): Tạo VisualStyle với dna_json + reference_media_id.
 * - dna(): Fixture DNA đầy đủ.
 * - test_build_produces_seven_layers_in_order(): Prompt chứa đủ 7 lớp và
 *   theo thứ tự cố định 1→7.
 * - test_shot_overrides_apply_to_dna_layers(): Shot override composition/
     *   camera ghi đè DNA gốc trong lớp 2.
 * - test_aspect_ratio_from_shot_overrides_default(): Aspect dùng
 *   shot.suggested_aspect_ratio khi userVars không chỉ định.
 * - test_specialties_overlay_applies_to_prompt_builder_frame(): Purpose=
 *   'thumbnail' đọc prompt_builder riêng từ specialties.
 * - test_compiled_prompt_preview_is_saved_to_style(): Preview lưu vào
 *   compiled_prompt_preview.
 * - test_reference_image_only_attached_when_cap_enabled(): referenceImages
 *   chỉ xuất hiện khi cap('reference_image') và có media.
 * - test_build_rejects_unusable_style_and_missing_subject(): Lỗi rõ ràng khi
 *   style chưa analyzed/active hoặc subject rỗng.
 * - test_negative_layer_combines_style_and_shot(): Negative prompt nối
 *   style + aspect suffix đúng.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : VisualStyle + shot + userVars trong DB test.
 * - OUTPUT: CompiledImagePromptDto + assertions.
 *
 * BỐI CẢNH SỬ DỤNG: PHPUnit; không gọi AI.
 * =====================================================================
 */
class VisualPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo VisualStyle usable (analyzed + enabled + dna_json)
     * =====================================================================
     *
     * INPUT:
     * - array $overrides — ghi đè attrs.
     *
     * OUTPUT:
     * - VisualStyle đã lưu DB.
     *
     * SIDE EFFECT:
     * - INSERT visual_styles (rollback sau test).
     * =====================================================================
     */
    private function makeStyle(array $overrides = []): VisualStyle
    {
        // reference_media_id phải tồn tại trong media_assets (FK).
        $asset = \Modules\Media\Models\MediaAsset::create([
            'kind' => \Modules\Media\Models\MediaAsset::KIND_IMAGE,
            'title' => 'Ref',
            'visibility' => 'public',
            'checksum_sha256' => hash('sha256', (string) microtime(true).rand()),
        ]);

        return VisualStyle::create(array_replace([
            'name' => 'Test Style',
            'status' => 'analyzed',
            'is_enabled' => true,
            'dna_json' => $this->dna(),
            'negative_prompt' => 'watermark, deformed_hands',
            'reference_media_id' => $asset->id,
        ], $overrides));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Fixture DNA đầy đủ
     * =====================================================================
     *
     * OUTPUT:
     * - array dna_json.
     * =====================================================================
     */
    private function dna(): array
    {
        return [
            'composition' => ['camera_angle' => 'low_angle', 'framing_rule' => 'rule_of_thirds', 'depth_of_field' => 'shallow_dof'],
            'camera'      => ['lens_type' => '85mm', 'shot_medium' => 'photograph', 'perspective' => 'medium'],
            'lighting'    => ['tone' => 'warm_golden', 'key_light' => 'rim_light'],
            'environment' => ['setting' => 'studio', 'backdrop' => 'nền xám nhạt', 'atmosphere' => 'clean'],
            'color_mood'  => ['palette_hex' => ['#1A1A2E', '#E94560'], 'temperature' => 'warm', 'contrast' => 'high', 'grade' => 'teal_orange'],
            'subject_rules' => ['preserve' => ['logo', 'product_shape'], 'forbidden' => ['watermark', 'extra_fingers']],
            'style_constraints' => 'Chất ảnh điện ảnh ấm áp.',
            'confidence' => 0.85,
            'taxonomy_suggestion' => [],
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Prompt chứa đủ 7 lớp theo thứ tự cố định 1→7
     * =====================================================================
     *
     * INPUT:
     * - Style usable + userVars subject + instructions.
     *
     * OUTPUT:
     * - void; assertions từng lớp xuất hiện trong prompt đúng thứ tự.
     *
     * SIDE EFFECT:
     * - UPDATE compiled_prompt_preview.
     * =====================================================================
     */
    public function test_build_produces_seven_layers_in_order(): void
    {
        $style = $this->makeStyle();
        $shot = VisualStyleShot::create([
            'visual_style_id' => $style->id,
            'code' => 'hero', 'name' => 'Hero — Ảnh chủ đạo',
            'prompt_suffix' => 'Focus on depth.',
            'suggested_aspect_ratio' => '16:9',
        ]);

        $out = app(VisualPromptBuilder::class)->build($style, $shot, [
            'subject' => 'a ceramic coffee mug on a wooden table',
            'instructions' => 'keep it minimal and clean',
        ]);

        $layers = $out->layers;

        // Kiểm tra 7 key lớp có mặt.
        $this->assertArrayHasKey('1_subject_identity', $layers);
        $this->assertArrayHasKey('2_style_dna', $layers);
        $this->assertArrayHasKey('3_category_adaptation', $layers);
        $this->assertArrayHasKey('4_shot_recipe', $layers);
        $this->assertArrayHasKey('5_user_instructions', $layers);
        $this->assertArrayHasKey('6_preservation_constraint', $layers);
        $this->assertArrayHasKey('7_negative_layer', $layers);

        // Lớp 1: subject + frame prompt_builder.
        $this->assertStringContainsString('ceramic coffee mug', $layers['1_subject_identity']);
        $this->assertStringContainsString('professional photograph', $layers['1_subject_identity']);

        // Lớp 2: DNA sau shot override.
        $this->assertStringContainsString('composition: low angle', $layers['2_style_dna']);
        $this->assertStringContainsString('camera: 85mm', $layers['2_style_dna']);
        $this->assertStringContainsString('lighting: warm golden', $layers['2_style_dna']);
        $this->assertStringContainsString('environment: studio', $layers['2_style_dna']);
        $this->assertStringContainsString('color_mood: #1A1A2E', $layers['2_style_dna']);
        $this->assertStringContainsString('teal orange', $layers['2_style_dna']);
        $this->assertStringContainsString('style: Chất ảnh điện ảnh ấm áp', $layers['2_style_dna']);

        // Lớp 3: taxonomy — style này không gắn nên rỗng.
        $this->assertSame('', $layers['3_category_adaptation']);

        // Thêm case taxonomy có gắn: categories + tags → lớp 3 hiện.
        $style->setRelation('categories', collect([
            (object) ['id' => 1, 'name' => 'Sản phẩm'],
        ]));
        $style->setRelation('tags', collect([
            (object) ['id' => 1, 'name' => 'Giờ vàng'],
        ]));
        $cat = app(VisualPromptBuilder::class)->build($style, $shot, ['subject' => 'a mug']);
        $this->assertStringContainsString('context: Sản phẩm, Giờ vàng', $cat->layers['3_category_adaptation']);

        // Lớp 4: shot.
        $this->assertStringContainsString('shot: hero', $layers['4_shot_recipe']);
        $this->assertStringContainsString('Focus on depth', $layers['4_shot_recipe']);

        // Lớp 5: user.
        $this->assertStringContainsString('minimal and clean', $layers['5_user_instructions']);

        // Lớp 6: preservation — subject_rules giữ nguyên văn key.
        $this->assertStringContainsString('preserve: logo, product_shape', $layers['6_preservation_constraint']);
        $this->assertStringContainsString('avoid: watermark, extra_fingers', $layers['6_preservation_constraint']);
        $this->assertStringContainsString('reference', $layers['6_preservation_constraint']);

        // Lớp 7: negative + aspect.
        $this->assertStringContainsString('watermark, deformed_hands', $layers['7_negative_layer']);
        $this->assertStringContainsString('--ar 16:9', $layers['7_negative_layer']);

        // Thứ tự lớp trong prompt: 1 < 2 < 4 < 5 < 6 < 7.
        $prompt = $out->prompt;
        $pos = fn ($s) => strpos($prompt, $s);
        $this->assertLessThan($pos('composition'), $pos('ceramic coffee mug'));
        $this->assertLessThan($pos('shot: hero'), $pos('composition'));
        $this->assertLessThan($pos('minimal and clean'), $pos('shot: hero'));
        $this->assertLessThan($pos('preserve:'), $pos('minimal and clean'));
        $this->assertLessThan($pos('--ar'), $pos('preserve:'));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Shot override ghi đè composition/camera trong lớp 2
     * =====================================================================
     *
     * INPUT:
     * - Shot có composition_override (camera_angle=macro, depth_of_field=deep)
     *   + camera_override (lens_type=macro, perspective=close_up).
     *
     * OUTPUT:
     * - void; assertions lớp 2 hiển thị giá trị override, không còn gốc.
     *
     * SIDE EFFECT:
     * - Gọi builder.
     * =====================================================================
     */
    public function test_shot_overrides_apply_to_dna_layers(): void
    {
        $style = $this->makeStyle();
        $shot = VisualStyleShot::create([
            'visual_style_id' => $style->id,
            'code' => 'detail', 'name' => 'Detail',
            'composition_override_json' => [
                'camera_angle' => 'macro',
                'framing_rule' => 'centered',
            ],
            'camera_override_json' => [
                'lens_type' => 'macro',
                'perspective' => 'close_up',
            ],
            'suggested_aspect_ratio' => '1:1',
        ]);

        $out = app(VisualPromptBuilder::class)->build($style, $shot, ['subject' => 'a watch']);

        $layer2 = $out->layers['2_style_dna'];
        $this->assertStringContainsString('composition: macro', $layer2);
        $this->assertStringContainsString('centered', $layer2);
        $this->assertStringNotContainsString('low angle', $layer2);
        $this->assertStringContainsString('camera: macro', $layer2);
        $this->assertStringContainsString('close up', $layer2);
        $this->assertStringNotContainsString('85mm', $layer2);

        // aspect_ratio theo shot.
        $this->assertSame('1:1', $out->aspectRatio);
        $this->assertStringContainsString('--ar 1:1', $out->layers['7_negative_layer']);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Aspect ratio dùng shot.suggested_aspect_ratio khi userVars trống
     * =====================================================================
     *
     * INPUT:
     * - Shot suggested_aspect_ratio='9:16', userVars không có aspect_ratio.
     *
     * OUTPUT:
     * - void; assertions aspectRatio='9:16' và negative có '--ar 9:16'.
     *
     * SIDE EFFECT:
     * - Gọi builder.
     * =====================================================================
     */
    public function test_aspect_ratio_from_shot_overrides_default(): void
    {
        $style = $this->makeStyle();
        $shot = VisualStyleShot::create([
            'visual_style_id' => $style->id,
            'code' => 'story', 'name' => 'Story',
            'suggested_aspect_ratio' => '9:16',
        ]);

        $out = app(VisualPromptBuilder::class)->build($style, $shot, ['subject' => 'a shoe']);

        $this->assertSame('9:16', $out->aspectRatio);
        $this->assertStringContainsString('--ar 9:16', $out->layers['7_negative_layer']);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Specialties overlay áp đúng prompt_builder khi purpose khớp
     * =====================================================================
     *
     * INPUT:
     * - config runtime specialties.thumbnail.prompt_builder riêng.
     *
     * OUTPUT:
     * - void; assertions lớp 1 dùng frame của thumbnail, purpose khác giữ base.
     *
     * SIDE EFFECT:
     * - Ghi config runtime.
     * =====================================================================
     */
    public function test_specialties_overlay_applies_to_prompt_builder_frame(): void
    {
        config([
            'visualstyle.v1.specialties.thumbnail' => [
                'prompts' => ['prompt_builder' => 'A high-impact thumbnail photograph'],
            ],
        ]);
        VisualPromptPack::flush();

        $style = $this->makeStyle();
        $shot = null;

        $out = app(VisualPromptBuilder::class)->build($style, $shot, [
            'subject' => 'a gaming headset',
        ], 'thumbnail');

        $this->assertStringContainsString('high-impact thumbnail photograph', $out->layers['1_subject_identity']);

        // Purpose khác → frame base.
        VisualPromptPack::flush();
        $out2 = app(VisualPromptBuilder::class)->build($style, $shot, ['subject' => 'a shoe'], 'hero');
        $this->assertStringNotContainsString('high-impact thumbnail photograph', $out2->layers['1_subject_identity']);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Preview prompt được lưu vào compiled_prompt_preview
     * =====================================================================
     *
     * INPUT:
     * - Style usable + subject.
     *
     * OUTPUT:
     * - void; assertions style.compiled_prompt_preview được cập nhật.
     *
     * SIDE EFFECT:
     * - UPDATE visual_styles.
     * =====================================================================
     */
    public function test_compiled_prompt_preview_is_saved_to_style(): void
    {
        $style = $this->makeStyle();

        $out = app(VisualPromptBuilder::class)->build($style, null, ['subject' => 'a lamp']);

        $style->refresh();
        $this->assertSame($out->prompt, $style->compiled_prompt_preview);
        $this->assertStringContainsString('a lamp', $style->compiled_prompt_preview);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: referenceImages chỉ xuất hiện khi cap('reference_image') + có media
     * =====================================================================
     *
     * INPUT:
     * - Style có reference_media_id.
     *
     * OUTPUT:
     * - void; assertions referenceImages có 1 phần tử khi cap on.
     *
     * SIDE EFFECT:
     * - Gọi builder.
     * =====================================================================
     */
    public function test_reference_image_only_attached_when_cap_enabled(): void
    {
        $style = $this->makeStyle();

        $out = app(VisualPromptBuilder::class)->build($style, null, ['subject' => 'a bottle']);

        $this->assertCount(1, $out->referenceImages);
        $this->assertSame($style->reference_media_id, $out->referenceImages[0]['media_asset_id']);

        // Tắt cap → không còn reference.
        config(['visualstyle.v1.capabilities.reference_image' => false]);
        VisualPromptPack::flush();

        $out2 = app(VisualPromptBuilder::class)->build($style, null, ['subject' => 'a bottle']);
        $this->assertSame([], $out2->referenceImages);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lỗi rõ khi style chưa usable hoặc subject rỗng
     * =====================================================================
     *
     * INPUT:
     * - Style status=draft (không usable) + subject rỗng.
     *
     * OUTPUT:
     * - void; assertions InvalidArgumentException đúng message.
     *
     * SIDE EFFECT:
     * - Gọi builder.
     * =====================================================================
     */
    public function test_build_rejects_unusable_style_and_missing_subject(): void
    {
        $draft = VisualStyle::create(['name' => 'Draft', 'status' => 'draft', 'is_enabled' => true, 'dna_json' => null]);

        try {
            app(VisualPromptBuilder::class)->build($draft, null, ['subject' => 'a cup']);
            $this->fail('Kỳ vọng InvalidArgumentException style chưa usable.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('chưa đủ điều kiện', $e->getMessage());
        }

        $usable = $this->makeStyle();
        try {
            app(VisualPromptBuilder::class)->build($usable, null, ['subject' => '']);
            $this->fail('Kỳ vọng InvalidArgumentException subject rỗng.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('subject', $e->getMessage());
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Negative layer nối style + aspect đúng
     * =====================================================================
     *
     * INPUT:
     * - Style negative_prompt='watermark, deformed_hands', aspect 4:5.
     *
     * OUTPUT:
     * - void; assertions negative đúng chuỗi.
     *
     * SIDE EFFECT:
     * - Gọi builder.
     * =====================================================================
     */
    public function test_negative_layer_combines_style_and_shot(): void
    {
        $style = $this->makeStyle(['negative_prompt' => 'watermark, deformed_hands']);

        $out = app(VisualPromptBuilder::class)->build($style, null, [
            'subject' => 'a mug',
            'aspect_ratio' => '4:5',
        ]);

        $neg = $out->negativePrompt;
        $this->assertStringContainsString('watermark, deformed_hands', $neg);
        $this->assertStringContainsString('--ar 4:5', $neg);
    }
}
