<?php

namespace Modules\WritingStyle\Pipelines\Steps;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\SandboxResultDto;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;
use Modules\WritingStyle\Services\PromptV3;
use Modules\WritingStyle\Services\WritingQualityReport;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Pipelines\Contracts\SandboxStepContract;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Luồng #2 (Terminal) — Áp dụng DNA Văn phong + Viết bài.
 * =====================================================================
 *
 * Stage 100 — bước chấp bút kết thúc chuỗi (is_terminal = true).
 * Dùng `WritingProfileSnapshotService::buildSnapshot` để tạo System Prompt
 * DNA (rules đã lọc theo tier + Ô 1 + Ô 2 + 5 sliders Chất văn + 8 sliders
 * Sắc thái), rồi ghép toàn bộ `$upstreamPromptBlocks` (khối chỉ dẫn của mọi
 * step đứng trước — VD dàn ý từ `topic_outline`, và sau này là bất kỳ
 * `section_expander`/`hook_analyzer` nào được chèn vào giữa mà không cần
 * sửa code này).
 * =====================================================================
 */
class ArticleWriterStep implements SandboxStepContract
{
    public function __construct(
        private readonly CoreAiGatewayContract $ai,
        private readonly WritingStyleCompilerContract $compiler,
    ) {}

    /** CHỨC NĂNG: Mã step. OUTPUT: 'article_writer'. */
    public function key(): string
    {
        return 'article_writer';
    }

    /** CHỨC NĂNG: Stage — tầng chấp bút viết bài (cuối chuỗi). OUTPUT: 100. */
    public function stage(): int
    {
        return 100;
    }

    /** CHỨC NĂNG: Là bước kết thúc — phải đứng cuối mọi pipeline. */
    public function isTerminal(): bool
    {
        return true;
    }

    /** CHỨC NĂNG: Không phụ thuộc nội tại — depends_on khai báo trong config. */
    public function intrinsicDependsOn(): array
    {
        return [];
    }

    /** CHỨC NĂNG: Metadata cho API /options (FE render stepper). */
    public function meta(): array
    {
        return [
            'key'          => $this->key(),
            'label'        => 'Bước 2: Áp dụng DNA & Viết toàn bài',
            'description'  => 'Áp dụng toàn bộ DNA văn phong + kết quả phân tích các bước trước để sinh bài viết hoàn chỉnh.',
            'icon'         => 'tabler-writing',
            'stage'        => $this->stage(),
            'is_terminal'  => $this->isTerminal(),
        ];
    }

    /**
     * CHỨC NĂNG: Artifact của bước viết bài (kết quả cuối — FE chỉ hiển thị).
     * INPUT: array $artifact. OUTPUT: bool — hợp lệ khi có 'text' không rỗng.
     */
    public function validateArtifact(array $artifact): bool
    {
        return trim((string) ($artifact['text'] ?? '')) !== '';
    }

    /**
     * CHỨC NĂNG: Terminal step không compile prompt block cho bước sau.
     * INPUT: artifact. OUTPUT: '' — không có bước sau terminal.
     */
    public function compileToPromptBlock(array $artifact): string
    {
        return '';
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Viết bài hoàn chỉnh theo DNA + PromptBlocks của các bước trước.
     * =====================================================================
     * INPUT: profile, topic, targetTier, upstreamPromptBlocks (đã compile sẵn),
     *        overrides (tone_overrides/style/voice/temperature/target_sentences).
     * OUTPUT: array — {text, target_tier, snapshot, ai_run_id}.
     * =====================================================================
     */
    public function execute(
        WritingProfile $profile,
        string $topic,
        string $targetTier,
        array $upstreamPromptBlocks,
        array $overrides = [],
    ): array {
        $brief = PromptV3::enabled() || isset($overrides['brief']) ? WritingBrief::fromInput($topic, $targetTier, $overrides) : null;
        if ($brief !== null) {
            $overrides['brief'] = $brief->data;
        }

        // 1. Snapshot DNA (rules theo tier + Ô 1 + Ô 2 + 5 sliders + 8 sliders).
        $targetSentences = (int) ($overrides['target_sentences'] ?? 0);
        $snapshot = $this->compiler->buildSnapshot(
            $profile,
            $targetTier,
            $targetSentences,
            $overrides,
        );

        // 2. Temperature: runtime override > profile > config mặc định.
        $temperature = isset($overrides['temperature'])
            ? (float) $overrides['temperature']
            : ($profile->temperature !== null
                ? (float) $profile->temperature
                : (float) config('writingstyle.prompts.sandbox.temperature', 0.85));

        // 3. Ghép PromptBlocks của các step trước vào User Prompt.
        $upstreamSection = $this->buildUpstreamSection($upstreamPromptBlocks);
        $userPrompt = $this->buildUserPrompt($topic, $snapshot->sentenceHint, $upstreamSection);

        $options = ['temperature' => $temperature];
        if (PromptV3::enabled()) {
            $userPrompt = config('writingstyle.v3.writer')."\n\n".PromptV3::json([
                'brief' => $brief->data,
                'content_plan' => array_column($upstreamPromptBlocks, 'block'),
            ]);
            $options['max_tokens'] = (int) config('writingstyle.v3.max_output_tokens', 4096);
            PromptV3::checkContext($snapshot->systemPrompt, $userPrompt);
        } elseif ($brief !== null) {
            $userPrompt .= "\n\nBrief:\n".PromptV3::json($brief->data);
        }

        $resp = $this->ai->executeStructured(
            AiStructuredRequest::fromPrompt(
                $userPrompt,
                system: $snapshot->systemPrompt,
                schema: null,
                options: $options,
                module: 'writingstyle',
                operation: PromptV3::enabled() ? 'sandbox_step_article_writer_v3' : 'sandbox_step_article_writer',
            )
        );

        $text = trim($resp->text);
        if (PromptV3::enabled()) {
            PromptV3::requireComplete($resp);
            if ($text === '') {
                throw new InvalidPipelineException('AI chưa trả nội dung bài viết. Hãy chạy lại bước viết.');
            }
        }

        $dto = new SandboxResultDto(
            profileId: (int) $profile->id,
            topic: $topic,
            targetTier: $snapshot->targetTier,
            text: $text,
            snapshot: [
                'system_prompt' => $snapshot->systemPrompt,
                'rules'         => $snapshot->rulesJson,
                'sentence_hint' => $snapshot->sentenceHint,
                ...($brief === null ? [] : ['provenance' => array_replace($snapshot->provenance, [
                    'prompt_version' => PromptV3::enabled() ? 3 : 2,
                    'model' => $resp->remoteModelId,
                    'provider' => $resp->providerKey,
                    'options' => $options,
                    'brief_fingerprint' => hash('sha256', PromptV3::json($brief->data)),
                    'plan_fingerprint' => $upstreamPromptBlocks === [] ? null : hash('sha256', PromptV3::json($upstreamPromptBlocks)),
                ])]),
            ],
            aiRunId: $this->ai->lastRun()?->id,
            qualityReport: $brief === null ? null : WritingQualityReport::inspect($text, $brief, $profile->style_analysis_json['source_specific_items'] ?? []),
        );

        return $dto->toArray();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gom toàn bộ upstream PromptBlocks thành 1 khối chỉ dẫn.
     * =====================================================================
     * INPUT: array $upstreamPromptBlocks — [{step, block}] theo thứ tự pipeline.
     * OUTPUT: string — khối chỉ dẫn ghép (rỗng nếu không có bước trước).
     * =====================================================================
     */
    protected function buildUpstreamSection(array $upstreamPromptBlocks): string
    {
        if ($upstreamPromptBlocks === []) {
            return '';
        }

        $header = (string) config(
            'writingstyle.prompts.sandbox.upstream_blocks_header',
            'Kết quả phân tích các bước trước (đã được người dùng duyệt/chỉnh sửa trên UI):'
        );

        $lines = [$header];
        foreach ($upstreamPromptBlocks as $item) {
            $block = trim((string) ($item['block'] ?? ''));
            if ($block !== '') {
                $lines[] = $block;
            }
        }

        return implode("\n\n", $lines);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghép User Prompt cuối cùng — chủ đề + upstream blocks + chỉ dẫn.
     * =====================================================================
     * INPUT: topic, sentenceHint, upstreamSection (đã build).
     * OUTPUT: string — user prompt đã ghép.
     * =====================================================================
     */
    protected function buildUserPrompt(string $topic, string $sentenceHint, string $upstreamSection): string
    {
        $template = (string) config(
            'writingstyle.prompts.sandbox.user_prompt_template',
            "Chủ đề: {topic}\n\nHãy viết {sentence_hint} về chủ đề trên theo đúng chất liệu văn phong và tâm thế trong hướng dẫn hệ thống.\nChỉ trả văn bản hoàn chỉnh, không thêm lời dẫn hay chú thích."
        );

        $base = strtr($template, [
            '{topic}' => $topic,
            '{sentence_hint}' => $sentenceHint,
        ]);

        if ($upstreamSection === '') {
            return $base;
        }

        return $upstreamSection . "\n\n" . $base;
    }
}
