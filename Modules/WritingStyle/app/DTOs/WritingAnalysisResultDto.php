<?php

namespace Modules\WritingStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO kết quả giải mã DNA văn phong từ 1 nguồn mẫu.
 * =====================================================================
 *
 * Trả về từ `WritingStyleAnalyzerService::analyze()` (Task 6) và lưu vào
 * `writing_profile_analyses.result_json`.
 *
 * `evidence_json` đã được `WritingProfileDefinition::filterValidEvidence()`
 * lọc — chỉ excerpt `str_contains` thật từ reference_text (§309).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : detectedTier, rulesJson, evidenceJson, styleInstructions,
 *           metadata (word/sentence count + ai_model_used), aiRunId.
 * - OUTPUT: readonly object + toResultJson().
 * =====================================================================
 */
final readonly class WritingAnalysisResultDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO kết quả phân tích.
     * INPUT: các thuộc tính immutable.
     */
    public function __construct(
        public string $detectedTier,
        public array $rulesJson,
        public array $evidenceJson,
        public string $styleInstructions,
        public array $metadata,
        public ?string $aiRunId = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Mảng JSON-serializable ghi vào writing_profile_analyses.
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toResultJson(): array
    {
        return [
            'detected_tier'       => $this->detectedTier,
            'rules_json'          => $this->rulesJson,
            'evidence_json'       => $this->evidenceJson,
            'style_instructions'  => $this->styleInstructions,
            'metadata'            => $this->metadata,
            'ai_run_id'           => $this->aiRunId,
        ];
    }
}
