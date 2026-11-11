<?php

namespace Modules\WritingStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Đóng gói bài viết thử, snapshot và báo cáo chất lượng.
 * =====================================================================
 *
 * Trả về từ `WritingStyleSandboxService::sandbox()` (§311) — văn bản AI
 * viết thử theo DNA của profile + chủ đề + cấp độ độ dài, kèm snapshot đã
 * dùng để caller/UI hiển thị (xem DNA nào đã áp dụng).
 *
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Khởi tạo thuộc tính hoặc nhận các dependency của SandboxResultDto.
 * - toArray(): Chuyển bài thử và báo cáo chất lượng sang payload API.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : profileId, topic, targetTier, text, snapshot, aiRunId, qualityReport tùy chọn.
 * - OUTPUT: Object readonly hoặc mảng response.
 *
 * BỐI CẢNH SỬ DỤNG: Writer tạo DTO; API sandbox trực tiếp serialize DTO này.
 * =====================================================================
 */
final readonly class SandboxResultDto
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo thuộc tính hoặc nhận các dependency của SandboxResultDto
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: khởi tạo DTO kết quả sandbox.
     * INPUT: các thuộc tính immutable.
     *
     * INPUT:
     * - public int $profileId, public string $topic, public string $targetTier, public string $text, public array $snapshot, public ?string $aiRunId = null, public ?array $qualityReport = null,
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
        public string $topic,
        public string $targetTier,
        public string $text,
        public array $snapshot,
        public ?string $aiRunId = null,
        public ?array $qualityReport = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển bài thử và báo cáo chất lượng sang payload API
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Mảng JSON-serializable trả về API/UI.
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array profile_id/topic/target_tier/text/snapshot/ai_run_id/quality_report
     *
     * SIDE EFFECT:
     * - Đọc thuộc tính DTO; không ghi DB hoặc gọi AI.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction; chỉ serialize dữ liệu sẵn có.
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
