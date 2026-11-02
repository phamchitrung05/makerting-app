<?php

namespace Modules\WritingStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO System Prompt đã biên dịch từ DNA văn phong.
 * =====================================================================
 *
 * Trả về từ `WritingProfileSnapshotService::buildSnapshot()` —
 * gói system_prompt sạch (không chứa `evidence_json`) + metadata để caller
 * (Blog/YoutubeIntelligence/SocialPublisher/Sandbox) inject vào
 * `CoreAi::executeStructured`.
 *
 * `system_prompt` đã biên dịch đúng `target_tier` (PLAN.md §310) —
 * chỉ chứa `applicable_keys` của tier đó (micro không ép `headings`/`bullets`).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : profileId, targetTier, systemPrompt, rulesJson đã lọc,
 *           applicableKeys, sentenceHint, excludedEvidence.
 * - OUTPUT: readonly object.
 * =====================================================================
 */
final readonly class CompiledStylePromptDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO prompt biên dịch.
     * INPUT: các thuộc tính immutable.
     */
    public function __construct(
        public int $profileId,
        public string $targetTier,
        public string $systemPrompt,
        public array $rulesJson,
        public array $applicableKeys,
        public string $sentenceHint,
        public bool $evidenceStripped,
    ) {}
}
