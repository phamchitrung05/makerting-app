<?php

namespace Modules\WritingStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Mang system prompt, style summary và thông tin phiên bản của snapshot.
 * =====================================================================
 *
 * Trả về từ `WritingProfileSnapshotService::buildSnapshot()` (§310).
 * Snapshot không đính `evidence_json`; việc này giảm rủi ro sao chép mẫu.
 * Nội dung Ô 1/2 và rules cần được chuẩn hóa ở bước phân tích.
 *
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Khởi tạo thuộc tính hoặc nhận các dependency của WritingProfileSnapshotDto.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Profile ID, tên, tier, prompt, rules, length hint, cờ evidenceStripped, styleSummary, provenance.
 * - OUTPUT: Object readonly dùng trong lần sinh nội dung.
 *
 * BỐI CẢNH SỬ DỤNG: Compiler tạo DTO; planner/writer và fingerprint sử dụng.
 * =====================================================================
 */
final readonly class WritingProfileSnapshotDto
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo thuộc tính hoặc nhận các dependency của WritingProfileSnapshotDto
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: khởi tạo DTO snapshot.
     * INPUT: các thuộc tính immutable.
     *
     * INPUT:
     * - public int $profileId, public string $name, public string $targetTier, public string $systemPrompt, public array $rulesJson, public string $sentenceHint, public bool $evidenceStripped, public array $styleSummary = [], public array $provenance = [],
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
        public int $profileId,
        public string $name,
        public string $targetTier,
        public string $systemPrompt,
        public array $rulesJson,
        public string $sentenceHint,
        public bool $evidenceStripped,
        public array $styleSummary = [],
        public array $provenance = [],
    ) {}
}
