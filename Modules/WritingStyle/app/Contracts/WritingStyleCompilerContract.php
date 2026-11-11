<?php

namespace Modules\WritingStyle\Contracts;

use Modules\WritingStyle\DTOs\WritingProfileSnapshotDto;
use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng biên dịch WritingProfile -> snapshot an toàn.
 * =====================================================================
 * Mọi module sinh chữ (Blog, YoutubeIntelligence, SocialPublisher,
 * Sandbox) lấy "bộ gen văn phong" qua contract này — KHÔNG đọc
 * evidence_json trực tiếp (tránh đạo văn bài mẫu, PLAN.md §310).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - buildSnapshot() : profile + target tier/sentences -> DTO đã strip
 *                     evidence + system_prompt đúng độ dài yêu cầu.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : WritingProfile + target tier (+ optional target sentences).
 * - OUTPUT: WritingProfileSnapshotDto (rules đã lọc tier, không evidence).
 * =====================================================================
 */
interface WritingStyleCompilerContract
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Biên dịch snapshot văn phong cho 1 độ dài đích.
     * =====================================================================
     * - Lọc rules/features theo bộ quy tắc hiệu lực của prompt pack hiện hành,
     *   không đính kèm evidence_json.
     * - Biên dịch system_prompt theo độ dài đích (brief.lengthHint).
     *
     * INPUT: profile + targetTier + targetSentences (0 = theo tier).
     * OUTPUT: WritingProfileSnapshotDto (evidenceStripped=true).
     * =====================================================================
     */
    public function buildSnapshot(
        WritingProfile $profile,
        string $targetTier,
        int $targetSentences = 0,
    ): WritingProfileSnapshotDto;
}
