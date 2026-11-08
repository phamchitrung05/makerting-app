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
 * =====================================================================
 */
class WritingStyleSandboxService
{
    public function __construct(
        protected readonly CoreAiGatewayContract $ai,
        protected readonly WritingStyleCompilerContract $compiler,
    ) {}

    public function sandbox(
        WritingProfile $profile,
        string $topic,
        string $targetTier = 'medium',
        int $targetSentences = 0,
        array $overrides = [],
    ): SandboxResultDto {
        // 1. Snapshot an toàn (đã strip evidence + prompt theo tier +
        //    tone_overrides/style_instructions/voice_texture runtime nếu có).
        $snapshot = $this->compiler->buildSnapshot(
            $profile,
            $targetTier,
            $targetSentences,
            $overrides,
        );

        // 2. Gọi AI viết thử — ưu tiên temperature runtime > profile > config mặc định.
        $temperature = isset($overrides['temperature'])
            ? (float) $overrides['temperature']
            : ($profile->temperature !== null
                ? (float) $profile->temperature
                : (float) config('writingstyle.prompts.sandbox.temperature', 0.85));

        $resp = $this->ai->executeStructured(
            AiStructuredRequest::fromPrompt(
                $this->buildUserPrompt($topic, $snapshot),
                system: $snapshot->systemPrompt,
                schema: null,
                options: ['temperature' => $temperature],
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

    protected function buildUserPrompt(string $topic, WritingProfileSnapshotDto $snapshot): string
    {
        $template = (string) config(
            'writingstyle.prompts.sandbox.user_prompt_template',
            "Chủ đề: {topic}\n\nHãy viết {sentence_hint} về chủ đề trên, đúng khẩu khí và cách hành văn đã mô tả trong hướng dẫn hệ thống. Chỉ trả văn bản, không thêm lời dẫn hay chú thích."
        );

        return strtr($template, [
            '{topic}' => $topic,
            '{sentence_hint}' => $snapshot->sentenceHint,
        ]);
    }
}
