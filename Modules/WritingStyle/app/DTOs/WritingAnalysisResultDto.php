<?php

namespace Modules\WritingStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Đóng gói kết quả phân tích V2/V3 trước khi lưu analysis.
 * =====================================================================
 *
 * Được tạo trong `WritingStyleAnalyzerService::analyze()` (Task 6);
 * toResultJson() cấp payload lưu vào `writing_profile_analyses.result_json`.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Khởi tạo thuộc tính hoặc nhận các dependency của WritingAnalysisResultDto.
 * - toResultJson(): Chuyển kết quả giải mã thành cấu trúc JSON để lưu analysis.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Detected tier, rules/evidence, chỉ dẫn, slider nullable, metadata và styleAnalysisJson.
 * - OUTPUT: Object readonly hoặc result_json.
 *
 * BỐI CẢNH SỬ DỤNG: Analyzer dùng DTO để thống nhất Ô 1/2, bằng chứng, slider và dữ liệu V3.
 * =====================================================================
 */
final readonly class WritingAnalysisResultDto
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo thuộc tính hoặc nhận các dependency của WritingAnalysisResultDto
     * =====================================================================
     *
     * INPUT:
     * - public string $detectedTier, public array $rulesJson, public array $evidenceJson, public string $styleInstructions, public array $metadata, public ?string $aiRunId = null, public string $voiceTextureInstructions = '', public array $voiceTextureSlidersJson = [], public array $toneSlidersJson = [], public ?array $styleAnalysisJson = null,
     *
     * OUTPUT:
     * - Instance giữ các thuộc tính/dependency theo chữ ký
     *
     * SIDE EFFECT:
     * - Gán thuộc tính trong bộ nhớ; không gọi AI hoặc ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi kiểu dữ liệu truyền về caller; không mở transaction.
     * =====================================================================
     */
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
        public ?array $styleAnalysisJson = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển kết quả giải mã thành cấu trúc JSON để lưu analysis
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array chứa DNA, evidence, Ô 1/2, sliders, metadata và style_analysis_json
     *
     * SIDE EFFECT:
     * - Đọc thuộc tính DTO, không mã hóa JSON hoặc ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction; chỉ tạo mảng kết quả.
     * =====================================================================
     */
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
            'style_analysis_json'        => $this->styleAnalysisJson,
            'metadata'                   => $this->metadata,
            'ai_run_id'                  => $this->aiRunId,
        ];
    }
}
