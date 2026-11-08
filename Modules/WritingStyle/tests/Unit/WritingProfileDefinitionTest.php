<?php

namespace Modules\WritingStyle\Tests\Unit;

use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Services\WritingProfileDefinition;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Unit test WritingProfileDefinition (P2-02 Task 3+4).
 * =====================================================================
 * Kiểm chứng: 16 RULE_KEYS, detectTier 4 cấp độ, applicableKeys theo tier
 * (micro đẩy long-only vào uncertainties), tierLabel, validateEvidence
 * (str_contains sau chuẩn hóa whitespace/HTML + loại excerpt giả).
 * =====================================================================
 */
class WritingProfileDefinitionTest extends TestCase
{
    /* =================================================================
     * RULE_KEYS + tiers từ config.
     * ================================================================= */

    public function test_rule_keys_co_dung_16_khoa(): void
    {
        $keys = WritingProfileDefinition::ruleKeys();
        $this->assertCount(16, $keys);
        $this->assertSame(WritingProfile::RULE_KEYS, $keys);
        $this->assertContains('uncertainties', $keys);
    }

    /* =================================================================
     * detectTier — 4 cấp độ độ dài.
     * ================================================================= */

    public function test_detect_tier_micro(): void
    {
        $this->assertSame(
            WritingProfile::TIER_MICRO,
            WritingProfileDefinition::detectTier('Cà phê sáng thơm quá.')
        );
    }

    public function test_detect_tier_short(): void
    {
        $text = implode(' ', array_fill(0, 6, 'Câu ngắn.')); // 6 câu
        $this->assertSame(
            WritingProfile::TIER_SHORT,
            WritingProfileDefinition::detectTier($text)
        );
    }

    public function test_detect_tier_medium(): void
    {
        $text = implode(' ', array_fill(0, 20, 'Câu trung bình.')); // 20 câu
        $this->assertSame(
            WritingProfile::TIER_MEDIUM,
            WritingProfileDefinition::detectTier($text)
        );
    }

    public function test_detect_tier_long(): void
    {
        $text = implode(' ', array_fill(0, 40, 'Câu rất dài.')); // 40 câu
        $this->assertSame(
            WritingProfile::TIER_LONG,
            WritingProfileDefinition::detectTier($text)
        );
    }

    public function test_detect_tier_theo_so_tu_khi_khong_dau_cau(): void
    {
        // Không có dấu câu nhưng > 600 từ -> long (fallback theo từ).
        $text = str_repeat('từ ', 700);
        $this->assertSame(
            WritingProfile::TIER_LONG,
            WritingProfileDefinition::detectTier($text)
        );
    }

    /* =================================================================
     * applicableKeys — lọc theo tier_drop_rules (PLAN-V2 §3):
     * micro 8 / short 11 / medium 15 / long 16.
     * ================================================================= */

    public function test_applicable_keys_micro_giu_8_khoa(): void
    {
        $keys = WritingProfileDefinition::applicableKeys(WritingProfile::TIER_MICRO);
        $this->assertCount(8, $keys); // 16 - 8 drop.
        foreach (['headings', 'bullets', 'paragraph_rhythm', 'structure_patterns', 'opening', 'transitions', 'technical_terms', 'examples'] as $dropped) {
            $this->assertNotContains($dropped, $keys, "micro phải bỏ {$dropped}");
        }
        foreach (['tone', 'pronouns', 'emotion', 'sentence_rhythm', 'vocabulary', 'ending', 'avoid', 'uncertainties'] as $kept) {
            $this->assertContains($kept, $keys, "micro phải giữ {$kept}");
        }
    }

    public function test_applicable_keys_short_giu_11_khoa(): void
    {
        $keys = WritingProfileDefinition::applicableKeys(WritingProfile::TIER_SHORT);
        $this->assertCount(11, $keys); // 16 - 5 drop.
        foreach (['headings', 'bullets', 'structure_patterns', 'technical_terms', 'examples'] as $dropped) {
            $this->assertNotContains($dropped, $keys, "short phải bỏ {$dropped}");
        }
        $this->assertContains('opening', $keys);
        $this->assertContains('transitions', $keys);
        $this->assertContains('paragraph_rhythm', $keys);
    }

    public function test_applicable_keys_medium_long(): void
    {
        $keys = WritingProfileDefinition::applicableKeys(WritingProfile::TIER_MEDIUM);
        $this->assertCount(15, $keys); // 16 - headings.
        $this->assertNotContains('headings', $keys);
        $this->assertContains('bullets', $keys);
        $this->assertContains('structure_patterns', $keys);

        $this->assertCount(16, WritingProfileDefinition::applicableKeys(WritingProfile::TIER_LONG));
    }

    /* =================================================================
     * tierLabel — nhãn cho System Prompt.
     * ================================================================= */

    public function test_tier_label(): void
    {
        $this->assertSame('2–3 câu', WritingProfileDefinition::tierLabel(WritingProfile::TIER_MICRO));
        $this->assertSame('3–4 câu', WritingProfileDefinition::tierLabel(WritingProfile::TIER_SHORT));
        $this->assertSame('20–30 câu', WritingProfileDefinition::tierLabel(WritingProfile::TIER_MEDIUM));
        $this->assertStringContainsString('bài dài', WritingProfileDefinition::tierLabel(WritingProfile::TIER_LONG));
    }

    /* =================================================================
     * validateEvidence — excerpt phải str_contains nguyên văn.
     * ================================================================= */

    public function test_validate_evidence_pass_khi_excerpt_that(): void
    {
        $reference = 'Cà phê sáng thơm quá. Mình thích mùi này. Bạn cũng thử nhé.';
        $evidence = [
            ['rule_key' => 'tone', 'excerpt' => 'Cà phê sáng thơm quá', 'note' => 'mở đầu'],
            ['rule_key' => 'pronouns', 'excerpt' => 'Bạn cũng thử', 'note' => 'xưng hô'],
        ];

        $res = WritingProfileDefinition::validateEvidence($reference, $evidence);
        $this->assertTrue($res['valid']);
        $this->assertSame(2, $res['checked']);
        $this->assertSame(['tone' => 1, 'pronouns' => 1], $res['by_rule']);
    }

    public function test_validate_evidence_fail_khi_excerpt_bia(): void
    {
        $reference = 'Hôm nay trời đẹp. Tôi đi dạo công viên.';
        $evidence = [
            ['rule_key' => 'tone', 'excerpt' => 'Trời hôm nay đẹp', 'note' => 'AI viết lại'],
            ['rule_key' => 'emotion', 'excerpt' => 'Tôi rất vui', 'note' => 'không có trong text'],
        ];

        $res = WritingProfileDefinition::validateEvidence($reference, $evidence);
        $this->assertFalse($res['valid']);
        $this->assertCount(2, $res['failed']);
    }

    public function test_validate_evidence_chuan_hoa_whitespace_va_html(): void
    {
        // reference có HTML + newline + NBSP; excerpt chuẩn hóa vẫn khớp.
        $reference = "<p>Cà&nbsp;phê sáng\nthơm   quá.</p>";
        $evidence = [
            ['rule_key' => 'tone', 'excerpt' => 'Cà phê sáng thơm quá', 'note' => 'x'],
        ];

        $res = WritingProfileDefinition::validateEvidence($reference, $evidence);
        $this->assertTrue($res['valid']);
    }

    /* =================================================================
     * filterValidEvidence — loại excerpt không có thật.
     * ================================================================= */

    public function test_filter_valid_evidence_giu_chi_excerpt_that(): void
    {
        $reference = 'Mình uống cà phê. Bạn thích loại nào?';
        $evidence = [
            ['rule_key' => 'pronouns', 'excerpt' => 'Mình uống cà phê', 'note' => 'có'],
            ['rule_key' => 'tone', 'excerpt' => 'Giọng vui vẻ', 'note' => 'bịa'],
            ['rule_key' => 'pronouns', 'excerpt' => 'Bạn thích loại nào', 'note' => 'có'],
        ];

        $kept = WritingProfileDefinition::filterValidEvidence($reference, $evidence);
        $this->assertCount(2, $kept);
        $this->assertSame('pronouns', $kept[0]['rule_key']);
        $this->assertSame('pronouns', $kept[1]['rule_key']);
    }
}
