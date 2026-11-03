<?php

namespace Modules\WritingStyle\Tests\Unit;

use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Services\WritingProfileSnapshotService;
use Tests\TestCase;

/**
 * Unit test WritingProfileSnapshotService (Task 5, PLAN.md §310).
 * Chứng minh: (1) strip evidence_json, (2) rules lọc theo tier,
 * (3) prompt chứa tier label + lệnh cấm đạo văn.
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

        foreach (['headings', 'bullets', 'paragraph_rhythm', 'structure_patterns'] as $k) {
            $this->assertArrayNotHasKey($k, $sn->rulesJson, "micro phải bỏ {$k}");
        }
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
    public function test_short_bo_3_khoa_giu_paragraph_rhythm(): void
    {
        $sn = $this->svc()->buildSnapshot($this->profile(), WritingProfile::TIER_SHORT);

        // short: headings/bullets/structure_patterns bị đẩy uncertainties,
        // paragraph_rhythm vẫn giữ.
        foreach (['headings', 'bullets', 'structure_patterns'] as $k) {
            $this->assertArrayNotHasKey($k, $sn->rulesJson, "short phải bỏ {$k}");
        }
        $this->assertArrayHasKey('paragraph_rhythm', $sn->rulesJson);
        $this->assertSame('3–4 câu', $sn->sentenceHint);
    }
}
