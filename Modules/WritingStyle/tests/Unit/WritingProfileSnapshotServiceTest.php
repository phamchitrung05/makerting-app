<?php

namespace Modules\WritingStyle\Tests\Unit;

use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Services\WritingProfileSnapshotService;
use Tests\TestCase;

/**
 * Unit test WritingProfileSnapshotService (Task 5, PLAN.md §310).
 * Chứng minh: (1) strip evidence_json, (2) rules lọc theo tier,
 * (3) prompt chứa tier label + lệnh cấm đạo văn + Ô 2 (Chất văn).
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
        $this->assertArrayNotHasKey('evidence', $sn->rulesJson);
        $this->assertStringNotContainsString('Câu chữ mẫu bí mật', $sn->systemPrompt);
    }

    public function test_micro_loc_bo_khoa_van_ban_dai(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), WritingProfile::TIER_MICRO);

        foreach (['headings', 'bullets', 'paragraph_rhythm', 'structure_patterns', 'opening', 'transitions', 'technical_terms', 'examples'] as $k) {
            $this->assertArrayNotHasKey($k, $sn->rulesJson, "micro phải bỏ {$k}");
        }
        $this->assertArrayNotHasKey('uncertainties', $sn->rulesJson, 'uncertainties không được vào prompt sinh bài');
        $this->assertSame('2–3 câu', $sn->sentenceHint);
    }

    public function test_long_giu_du_khoa(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), WritingProfile::TIER_LONG);

        $this->assertArrayHasKey('headings', $sn->rulesJson);
        $this->assertArrayHasKey('bullets', $sn->rulesJson);
        $this->assertSame('bài dài (phân đoạn rõ ràng)', $sn->sentenceHint);
    }

    public function test_prompt_chua_tier_label_va_lenh_cam_dao_van(): void
    {
        $p = $this->profile();
        $sn = $this->svc()->buildSnapshot($p, WritingProfile::TIER_MEDIUM);

        $this->assertStringContainsString('20–30 câu', $sn->systemPrompt);
        $this->assertStringContainsString('không sao chép nguyên văn', $sn->systemPrompt);
        $this->assertStringContainsString($p->name, $sn->systemPrompt);
        $this->assertStringContainsString('Mở bằng câu hỏi.', $sn->systemPrompt);
    }

    public function test_target_sentences_ep_nhan_cau(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), WritingProfile::TIER_MEDIUM, 25);

        $this->assertSame('25 câu', $sn->sentenceHint);
        $this->assertStringContainsString('25 câu', $sn->systemPrompt);
    }

    public function test_tier_rong_fallback_dominant(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), 'weird');

        $this->assertSame(WritingProfile::TIER_LONG, $sn->targetTier);
        $this->assertArrayHasKey('headings', $sn->rulesJson);
    }

    public function test_short_bo_5_khoa_giu_paragraph_rhythm(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), WritingProfile::TIER_SHORT);

        foreach (['headings', 'bullets', 'structure_patterns', 'technical_terms', 'examples'] as $k) {
            $this->assertArrayNotHasKey($k, $sn->rulesJson, "short phải bỏ {$k}");
        }
        $this->assertArrayHasKey('paragraph_rhythm', $sn->rulesJson);
        $this->assertSame('3–4 câu', $sn->sentenceHint);
    }

    public function test_medium_chi_bo_headings(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), WritingProfile::TIER_MEDIUM);

        $this->assertArrayNotHasKey('headings', $sn->rulesJson, 'medium bỏ headings');
        foreach (['bullets', 'paragraph_rhythm', 'structure_patterns'] as $k) {
            $this->assertArrayHasKey($k, $sn->rulesJson, "medium giữ {$k}");
        }
        $this->assertSame('20–30 câu', $sn->sentenceHint);
    }

    public function test_snapshot_khong_chua_uncertainties(): void
    {
        $p = $this->profile();
        $rules = $p->rules_json;
        $rules['uncertainties'] = ['headings: không áp dụng ở văn bản micro'];
        $p->rules_json = $rules;

        foreach ([WritingProfile::TIER_MICRO, WritingProfile::TIER_SHORT, WritingProfile::TIER_MEDIUM, WritingProfile::TIER_LONG] as $tier) {
            $sn = $this->svc()->buildSnapshot($p, $tier);
            $this->assertArrayNotHasKey('uncertainties', $sn->rulesJson, "{$tier} không có uncertainties trong rules");
            $this->assertStringNotContainsString('uncertainties', $sn->systemPrompt, "{$tier} prompt không chứa uncertainties");
            $this->assertStringNotContainsString('không áp dụng ở văn bản', $sn->systemPrompt);
        }
    }

    public function test_snapshot_chua_voice_texture_o2_va_5_thanh_truot(): void
    {
        $p = $this->profile([
            'voice_texture_instructions' => 'Giọng thủ thỉ trầm ấm, lướt nhanh ý phụ và nhấn sâu chi tiết giác quan.',
            'voice_texture_sliders_json' => [
                'voice_timbre'     => 85,
                'vernacular_flow'  => 75,
                'emotional_nuance' => 80,
                'rhythm_variation' => 70,
                'emphasis_glide'   => 90,
            ],
        ]);

        $sn = $this->svc()->buildSnapshot($p, WritingProfile::TIER_MEDIUM);

        $this->assertStringContainsString('Giọng thủ thỉ trầm ấm', $sn->systemPrompt);
        $this->assertStringContainsString('THÔNG SỐ CHẤT VĂN & ĐỘ TỰ NHIÊN (VOICE TEXTURE CALIBRATION):', $sn->systemPrompt);
        $this->assertStringContainsString('Giọng văn: 85%', $sn->systemPrompt);
        $this->assertStringContainsString('Tính nhấn và lướt: 90%', $sn->systemPrompt);
    }

    public function test_snapshot_tu_dong_chua_tone_sliders_json_tu_db(): void
    {
        $p = $this->profile([
            'tone_sliders_json' => [
                'humor'         => 60,
                'empathy'       => 85,
                'assertiveness' => 50,
            ],
        ]);

        $sn = $this->svc()->buildSnapshot($p, WritingProfile::TIER_MEDIUM);

        $this->assertStringContainsString('HIỆU CHỈNH SẮC THÁI CẢM XÚC THỜI GIAN THỰC (TONE CALIBRATION):', $sn->systemPrompt);
        $this->assertStringContainsString('Trân trọng & Đồng cảm: 85% (+35% so với điểm cân bằng)', $sn->systemPrompt);
    }
}
