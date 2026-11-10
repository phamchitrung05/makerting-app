<?php

namespace Modules\WritingStyle\Services;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\SandboxResultDto;
use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: "Phòng thử nghiệm DNA" — viết thử từ văn phong + chủ đề.
 * =====================================================================
 * Dùng trên UI (Task 10): nhập chủ đề + chọn cấp độ độ dài -> AI viết thử
 * ngay theo DNA của profile. KHÔNG lưu vào DB — chỉ trả văn bản + snapshot
 * đã dùng để caller hiển thị (§311).
 * =====================================================================
 */
class WritingStyleSandboxService
{
    public function __construct(
        protected readonly CoreAiGatewayContract $ai,
        protected readonly WritingStyleCompilerContract $compiler,
        protected readonly \Modules\WritingStyle\Pipelines\Steps\ArticleWriterStep $articleWriter,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Viết thử — tương đương pipeline 1 bước `article_writer`.
     * =====================================================================
     * Giữ tương thích ngược với API cũ POST /sandbox (FE gọi 1 lần, không
     * qua bước phân tích). Bên trong gọi `ArticleWriterStep::execute` với
     * `$upstreamPromptBlocks = []` — đúng semantics của terminal step khi
     * không có bước phân tích nào đứng trước (tier micro/short/medium).
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
