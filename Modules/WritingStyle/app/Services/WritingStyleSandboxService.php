<?php

namespace Modules\WritingStyle\Services;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\SandboxResultDto;
use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Duy trì API viết thử trực tiếp qua article_writer.
 * =====================================================================
 * Dùng trên UI (Task 10): nhập chủ đề + chọn cấp độ độ dài -> AI viết thử
 * ngay theo DNA của profile. Không lưu bài vào sandbox_samples_json; gateway
 * vẫn ghi AiRun/usage. Trả văn bản, snapshot và qualityReport để hiển thị (§311).
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Khởi tạo thuộc tính hoặc nhận các dependency của WritingStyleSandboxService.
 * - sandbox(): Viết thử trực tiếp và chuyển đầy đủ báo cáo chất lượng vào DTO.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Profile, topic, tier, số câu và runtime overrides.
 * - OUTPUT: SandboxResultDto có text, snapshot, aiRunId và qualityReport nếu được tạo.
 *
 * BỐI CẢNH SỬ DỤNG: Endpoint /sandbox gọi một bước writer và đóng gói kết quả để hiển thị.
 * =====================================================================
 */
class WritingStyleSandboxService
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo thuộc tính hoặc nhận các dependency của WritingStyleSandboxService
     * =====================================================================
     *
     * INPUT:
     * - protected readonly CoreAiGatewayContract $ai, protected readonly WritingStyleCompilerContract $compiler, protected readonly \Modules\WritingStyle\Pipelines\Steps\ArticleWriterStep $articleWriter,
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
        protected readonly CoreAiGatewayContract $ai,
        protected readonly WritingStyleCompilerContract $compiler,
        protected readonly \Modules\WritingStyle\Pipelines\Steps\ArticleWriterStep $articleWriter,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Viết thử trực tiếp và chuyển đầy đủ báo cáo chất lượng vào DTO
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Viết thử — tương đương pipeline 1 bước `article_writer`.
     * =====================================================================
     * Giữ tương thích ngược với API cũ POST /sandbox (FE gọi 1 lần, không
     * qua bước phân tích). Bên trong gọi `ArticleWriterStep::execute` với
     * `$upstreamPromptBlocks = []` — đúng semantics của terminal step khi
     * không có bước phân tích nào đứng trước (tier micro/short/medium).
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $profile, string $topic, string $targetTier = 'medium', int $targetSentences = 0, array $overrides = [],
     *
     * OUTPUT:
     * - SandboxResultDto
     *
     * SIDE EFFECT:
     * - ArticleWriterStep gọi AI và ghi log qua gateway; service không lưu sample vào profile.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi brief, compiler và gateway truyền lên. Không mở transaction hoặc chạy bước dàn ý.
     * =====================================================================
     */
    public function sandbox(
        WritingProfile $profile,
        string $topic,
        string $targetTier = 'medium',
        int $targetSentences = 0,
        array $overrides = [],
    ): SandboxResultDto {
        $result = $this->articleWriter->execute(
            $profile,
            $topic,
            $targetTier,
            [], // upstreamPromptBlocks rỗng — không có step phân tích trước
            array_merge($overrides, ['target_sentences' => $targetSentences]),
        );

        return new SandboxResultDto(
            profileId: (int) $result['profile_id'],
            topic: (string) $result['topic'],
            targetTier: (string) $result['target_tier'],
            text: (string) $result['text'],
            snapshot: (array) ($result['snapshot'] ?? []),
            aiRunId: $result['ai_run_id'] ?? null,
            qualityReport: $result['quality_report'] ?? null,
        );
    }
}
