<?php

namespace Modules\WritingStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO kết quả "Phòng thử nghiệm DNA" (Sandbox).
 * =====================================================================
 *
 * Trả về từ `WritingStyleSandboxService::sandbox()` (§311) — văn bản AI
 * viết thử theo DNA của profile + chủ đề + cấp độ độ dài, kèm snapshot đã
 * dùng để caller/UI hiển thị (xem DNA nào đã áp dụng).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : profileId, topic, targetTier, text, snapshot (rulesJson đã
 *           lọc), aiRunId.
 * - OUTPUT: readonly object + toArray().
 * =====================================================================
 */
final readonly class SandboxResultDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO kết quả sandbox.
     * INPUT: các thuộc tính immutable.
     */
    public function __construct(
        public int $profileId,
        public string $topic,
        public string $targetTier,
        public string $text,
        public array $snapshot,
        public ?string $aiRunId = null,
        public ?array $qualityReport = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Mảng JSON-serializable trả về API/UI.
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toArray(): array
    {
        return [
            'profile_id'  => $this->profileId,
            'topic'       => $this->topic,
            'target_tier' => $this->targetTier,
            'text'        => $this->text,
            'snapshot'    => $this->snapshot,
            'ai_run_id'   => $this->aiRunId,
            ...($this->qualityReport === null ? [] : ['quality_report' => $this->qualityReport]),
        ];
    }
}
