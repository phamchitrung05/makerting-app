<?php

namespace Modules\WritingStyle\Services;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\SandboxResultDto;
use Modules\WritingStyle\DTOs\WritingProfileSnapshotDto;
use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: "Phòng thử nghiệm DNA" — viết thử từ văn phong + chủ đề.
 * =====================================================================
 * Dùng trên UI (Task 10): nhập chủ đề + chọn cấp độ độ dài -> AI viết thử
 * ngay theo DNA của profile. KHÔNG lưu vào DB — chỉ trả văn bản + snapshot
 * đã dùng để caller hiển thị (§311).
 *
 * Pipeline:
 *   1. WritingProfileSnapshotService::buildSnapshot($profile, $targetTier)
 *      -> snapshot đã STRIP evidence + system_prompt theo tier.
 *   2. CoreAi::executeStructured(system=snapshot->systemPrompt, user=topic)
 *      -> văn bản thử.
 *   3. Trả SandboxResultDto(text, tier, snapshot, aiRunId).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - sandbox()        : pipeline trên.
 * - buildUserPrompt(): user prompt (chủ đề + gợi ý số câu).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : WritingProfile + topic + targetTier (+ optional sentences).
 * - OUTPUT: SandboxResultDto.
 * =====================================================================
 */
class WritingStyleSandboxService
{
    /**
     * INPUT: CoreAiGatewayContract + WritingStyleCompilerContract.
     */
    public function __construct(
        protected readonly CoreAiGatewayContract $ai,
        protected readonly WritingStyleCompilerContract $compiler,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Viết thử 1 đoạn theo DNA profile + chủ đề + độ dài.
     * =====================================================================
     * INPUT: profile + topic + targetTier + targetSentences (0=theo tier).
     * OUTPUT: SandboxResultDto.
     * EXCEPTION: AiGatewayException khi provider lỗi (để controller xử lý).
     * =====================================================================
     */
    public function sandbox(
        WritingProfile $profile,
        string $topic,
        string $targetTier = 'medium',
        int $targetSentences = 0,
    ): SandboxResultDto {
        // 1. Snapshot an toàn (đã strip evidence + prompt theo tier).
        $snapshot = $this->compiler->buildSnapshot($profile, $targetTier, $targetSentences);

        // 2. Gọi AI viết thử — KHÔNG schema (muốn văn bản tự do, không JSON).
        $resp = $this->ai->executeStructured(
            AiStructuredRequest::fromPrompt(
                $this->buildUserPrompt($topic, $snapshot),
                system: $snapshot->systemPrompt,
                schema: null,
                options: ['temperature' => 0.7],
                module: 'writingstyle',
                operation: 'sandbox',
            )
        );

        return new SandboxResultDto(
            profileId: (int) $profile->id,
            topic: $topic,
            targetTier: $snapshot->targetTier,
            text: trim($resp->text ?? ''),
            snapshot: [
                'system_prompt' => $snapshot->systemPrompt,
                'rules'         => $snapshot->rulesJson,
                'sentence_hint' => $snapshot->sentenceHint,
            ],
            aiRunId: $this->ai->lastRun()?->id,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: User prompt — chủ đề + gợi ý số câu (snapshot hint).
     * =====================================================================
     * INPUT: topic + snapshot. OUTPUT: user prompt string.
     * =====================================================================
     */
    protected function buildUserPrompt(string $topic, WritingProfileSnapshotDto $snapshot): string
    {
        return "Chủ đề: {$topic}\n\n"
            . "Hãy viết {$snapshot->sentenceHint} về chủ đề trên, đúng khẩu khí "
            . "và cách hành văn đã mô tả trong hướng dẫn hệ thống. Chỉ trả văn "
            . "bản, không thêm lời dẫn hay chú thích.";
    }
}
