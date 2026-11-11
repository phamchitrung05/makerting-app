<?php

namespace Modules\WritingStyle\Tests\Unit;

use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Services\WritingProfileDefinition;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Unit test WritingProfileDefinition (giữ lại phần đếm/độ dài).
 * =====================================================================
 * Kiểm chứng: ruleKeys, detectTier 4 cấp độ (theo số câu/số từ), và
 * countSentences/countWords giữ newline giữa các block HTML.
 * =====================================================================
 */
class WritingProfileDefinitionTest extends TestCase
{
    /* =================================================================
     * ruleKeys — danh sách khóa DNA từ config chung.
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
}
