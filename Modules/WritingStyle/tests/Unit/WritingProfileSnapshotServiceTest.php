<?php

namespace Modules\WritingStyle\Tests\Unit;

use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Services\WritingProfileSnapshotService;
use Tests\TestCase;

/**
 * Unit test WritingProfileSnapshotService (Prompt Pack V3).
 * Chứng minh: (1) strip evidence_json, (2) rules giữ theo tier/format,
 * (3) prompt chứa features/Ô 1–3 + sliders với provenance.
 */
class WritingProfileSnapshotServiceTest extends TestCase
{
    private function profile(array $overrides = []): WritingProfile
    {
        $p = new WritingProfile;
        $p->id = 42;
        $p->name = 'Giọng Chuyên Gia';
        $p->dominant_tier = WritingProfile::TIER_LONG;
        $p->rules_json = [
            'tone' => 'trang trọng',
            'pronouns' => 'tôi/bạn',
            'headings' => 'H2 số thứ tự',
            'bullets' => 'gạch đầu dòng',
            'paragraph_rhythm' => 'đoạn 2–3 câu',
            'structure_patterns' => 'Hook->CTA',
            'uncertainties' => [],
        ];
        $p->evidence_json = [
            ['rule_key' => 'tone', 'excerpt' => 'Câu chữ mẫu bí mật', 'note' => 'x'],
        ];
        $p->style_instructions = 'Mở bằng câu hỏi.';
        foreach ($overrides as $k => $v) {
            $p->{$k} = $v;
        }

        return $p;
    }

    private function svc(): WritingProfileSnapshotService
    {
        return app(WritingStyleCompilerContract::class);
    }

    public function test_contract_resolve_service(): void
    {
        $this->assertInstanceOf(WritingProfileSnapshotService::class, $this->svc());
    }

    public function test_snapshot_khong_chua_evidence(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), WritingProfile::TIER_LONG);

        $this->assertTrue($sn->evidenceStripped);
        $this->assertArrayNotHasKey('evidence_json', $sn->rulesJson);
        $this->assertStringNotContainsString('Câu chữ mẫu bí mật', $sn->systemPrompt);
    }

    public function test_format_headings_bullets_bi_brief_cam(): void
    {
        $p = $this->profile();
        // Brief cấm headings + bullets -> 2 key này không được vào rules/features.
        $sn = $this->svc()->buildSnapshot($p, WritingProfile::TIER_LONG, 0, [
            'brief' => ['format' => ['headings' => false, 'bullets' => false]],
        ]);

        $this->assertArrayNotHasKey('headings', $sn->rulesJson);
        $this->assertArrayNotHasKey('bullets', $sn->rulesJson);
        $this->assertArrayHasKey('tone', $sn->rulesJson);
    }

    public function test_uncertainties_khong_vao_features(): void
    {
        $p = $this->profile();
        $rules = $p->rules_json;
        $rules['uncertainties'] = ['headings: không áp dụng ở văn bản micro'];
        $p->rules_json = $rules;

        $sn = $this->svc()->buildSnapshot($p, WritingProfile::TIER_MEDIUM);

        $this->assertArrayNotHasKey('uncertainties', $sn->rulesJson, 'uncertainties không được vào rules');
        $this->assertStringNotContainsString('uncertainties', $sn->systemPrompt);
    }

    public function test_snapshot_chua_o1_o2_o3_va_sliders(): void
    {
        $p = $this->profile([
            'style_instructions'         => 'Chân dung người kể chuyện: Người bạn đồng hành sâu sắc.',
            'voice_texture_instructions' => 'Giọng thủ thỉ trầm ấm, lướt nhanh ý phụ và nhấn sâu chi tiết giác quan.',
            'tone_instructions'          => 'Luôn giữ sắc thái trân trọng thấu hiểu.',
            'voice_texture_sliders_json' => ['voice_timbre' => 85, 'emphasis_glide' => 90],
            'tone_sliders_json'          => ['humor' => 60, 'empathy' => 85],
        ]);

        $sn = $this->svc()->buildSnapshot($p, WritingProfile::TIER_MEDIUM);

        // Ô 1: style_instructions
        $this->assertStringContainsString('Chân dung người kể chuyện: Người bạn đồng hành sâu sắc.', $sn->systemPrompt);
        // Ô 2: voice_texture_instructions
        $this->assertStringContainsString('Giọng thủ thỉ trầm ấm', $sn->systemPrompt);
        // Ô 3: tone_instructions
        $this->assertStringContainsString('Luôn giữ sắc thái trân trọng thấu hiểu.', $sn->systemPrompt);
        // 5 Sliders Chất văn
        $this->assertStringContainsString('"voice_timbre"', $sn->systemPrompt);
        $this->assertStringContainsString('"emphasis_glide"', $sn->systemPrompt);
        // 8 Sliders Sắc thái
        $this->assertStringContainsString('"humor"', $sn->systemPrompt);
        $this->assertStringContainsString('"empathy"', $sn->systemPrompt);
    }

    public function test_provenance_va_length_hint(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), WritingProfile::TIER_LONG);

        $this->assertSame('2000–3000 từ', $sn->sentenceHint);
        $this->assertSame(3, $sn->provenance['prompt_version']);
        $this->assertSame(3, $sn->provenance['compiler_version']);
        $this->assertSame(42, $sn->provenance['profile_id']);
    }

    public function test_tier_rong_fallback_dominant(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), 'weird');

        $this->assertSame(WritingProfile::TIER_LONG, $sn->targetTier);
        $this->assertArrayHasKey('headings', $sn->rulesJson);
    }
}
