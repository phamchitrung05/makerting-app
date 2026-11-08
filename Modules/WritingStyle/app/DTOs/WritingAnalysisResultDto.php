<?php

namespace Modules\WritingStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO kết quả giải mã DNA văn phong từ 1 nguồn mẫu.
 * =====================================================================
 *
 * Trả về từ `WritingStyleAnalyzerService::analyze()` (Task 6) và lưu vào
 * `writing_profile_analyses.result_json`.
 * =====================================================================
 */
final readonly class WritingAnalysisResultDto
{
    public function __construct(
        public string $detectedTier,
        public array $rulesJson,
        public array $evidenceJson,
        public string $styleInstructions,
        public array $metadata,
        public ?string $aiRunId = null,
        public string $voiceTextureInstructions = '',
        public array $voiceTextureSlidersJson = [],
        public array $toneSlidersJson = [],
    ) {}

    public function toResultJson(): array
    {
        return [
            'detected_tier'              => $this->detectedTier,
            'rules_json'                 => $this->rulesJson,
            'evidence_json'              => $this->evidenceJson,
            'style_instructions'         => $this->styleInstructions,
            'voice_texture_instructions' => $this->voiceTextureInstructions,
            'voice_texture_sliders_json' => $this->voiceTextureSlidersJson,
            'tone_sliders_json'          => $this->toneSlidersJson,
            'metadata'                   => $this->metadata,
            'ai_run_id'                  => $this->aiRunId,
        ];
    }
}
