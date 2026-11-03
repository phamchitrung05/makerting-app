<?php

namespace Modules\WritingStyle\Services;

use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\WritingProfileSnapshotDto;
use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Biên dịch WritingProfile -> WritingProfileSnapshotDto.
 * =====================================================================
 * Đây là 'compiler' duy nhất cấp DNA văn phong cho AI (PLAN.md §310).
 * Mọi module sinh chữ đi qua service này thay vì đọc profile trực tiếp:
 *
 *  1. Lọc rules theo cấp độ độ dài đích (applicableKeys / applicableRules).
 *  2. LOẠI BỎ hoàn toàn evidence_json — AI không bao giờ thấy câu chữ mẫu
 *     thật nên không thể đạo văn.
 *  3. Biên dịch system_prompt theo tierLabel(targetTier) để AI bắt chước
 *     đúng khẩu khí/nhịp câu ở đúng độ dài yêu cầu.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - buildSnapshot() : profile + targetTier + ?targetSentences -> DTO.
 * - compilePrompt() : rules đã lọc + tier label -> system_prompt text.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : WritingProfile + tier đích (+ optional số câu ép).
 * - OUTPUT: WritingProfileSnapshotDto (evidenceStripped=true).
 * =====================================================================
 */
class WritingProfileSnapshotService implements WritingStyleCompilerContract
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Biên dịch snapshot văn phong cho độ dài đích.
     * =====================================================================
     * INPUT: profile + targetTier + targetSentences (0=theo tier).
     * OUTPUT: WritingProfileSnapshotDto — rules đã lọc, KHÔNG có evidence.
     * =====================================================================
     */
    public function buildSnapshot(
        WritingProfile $profile,
        string $targetTier,
        int $targetSentences = 0,
    ): WritingProfileSnapshotDto {
        // Chuẩn hóa tier: rỗng/sai -> dùng dominant_tier của profile.
        $tier = in_array($targetTier, WritingProfile::TIERS, true)
            ? $targetTier
            : ($profile->dominant_tier ?? WritingProfile::TIER_MEDIUM);

        // Lọc rules theo tier + loại bỏ evidence (model helper đã xử lý
        // việc đẩy khóa văn-bản-dài vào uncertainties khi tier=micro).
        $rules = $profile->snapshotRules($tier);

        $sentenceHint = WritingProfileDefinition::tierLabel($tier);
        if ($targetSentences > 0) {
            $sentenceHint = $targetSentences . ' câu';
        }

        return new WritingProfileSnapshotDto(
            profileId: (int) $profile->id,
            name: (string) $profile->name,
            targetTier: $tier,
            systemPrompt: $this->compilePrompt($profile, $rules, $tier, $sentenceHint),
            rulesJson: $rules,
            sentenceHint: $sentenceHint,
            evidenceStripped: true,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghép system_prompt từ rules đã lọc + nhãn độ dài.
     * =====================================================================
     * Dạng: 'Bạn là người viết có văn phong <name>. Hãy viết <hint>'
     * + liệt kê từng rule (chỉ key theo tier) + style_instructions nếu có.
     * KHÔNG nhúng evidence_json — chỉ dùng giá trị rules (đã là tóm tắt).
     * INPUT: profile + rules đã lọc + tier + sentenceHint. OUTPUT: string.
     * =====================================================================
     */
    protected function compilePrompt(
        WritingProfile $profile,
        array $rules,
        string $tier,
        string $sentenceHint,
    ): string {
        $lines = [];
        $lines[] = 'Bạn là người viết nội dung có văn phong "' . $profile->name . '".';
        $lines[] = 'Hãy viết ' . $sentenceHint . ' đúng 100% khẩu khí, nhịp câu và cách hành văn của văn phong này.';
        $lines[] = 'TUYỆT ĐỐI không sao chép nguyên văn bất kỳ câu nào từ bài mẫu — chỉ bắt chước đặc trưng văn phong.';
        $lines[] = '';

        // Liệt kê từng rule áp dụng cho tier (bỏ qua rule rỗng/null).
        $lines[] = 'Đặc trưng văn phong cần tuân thủ:';
        foreach ($rules as $key => $value) {
            $text = is_array($value)
                ? implode('; ', array_filter(array_map('strval', $value)))
                : trim((string) $value);
            if ($text === '') {
                continue;
            }
            $lines[] = '- ' . $key . ': ' . $text;
        }

        // Hướng dẫn hành văn tự do của admin (style_instructions).
        $extra = trim((string) ($profile->style_instructions ?? ''));
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = 'Hướng dẫn bổ sung:' . $extra;
        }

        return implode("\n", $lines);
    }
}
