<?php

namespace Modules\WritingStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO snapshot văn phong an toàn cấp cho AI sinh văn bản.
 * =====================================================================
 *
 * Trả về từ `WritingProfileSnapshotService::buildSnapshot()` (§310).
 * Snapshot KHÔNG chứa `evidence_json` — AI học cách hành văn nhưng không
 * bao giờ nhìn thấy câu chữ mẫu để đạo văn.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : profileId, name, targetTier, systemPrompt, rulesJson (đã lọc
 *           theo tier), sentenceHint, evidenceStripped.
 * - OUTPUT: readonly object.
 * =====================================================================
 */
final readonly class WritingProfileSnapshotDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO snapshot.
     * INPUT: các thuộc tính immutable.
     */
    public function __construct(
        public int $profileId,
        public string $name,
        public string $targetTier,
        public string $systemPrompt,
        public array $rulesJson,
        public string $sentenceHint,
        public bool $evidenceStripped,
    ) {}
}
