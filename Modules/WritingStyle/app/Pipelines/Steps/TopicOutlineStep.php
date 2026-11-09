<?php

namespace Modules\WritingStyle\Pipelines\Steps;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Pipelines\Contracts\SandboxStepContract;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Luồng #1 — Phân tích Chủ đề -> Luận điểm + Dàn ý H2/H3.
 * =====================================================================
 *
 * Stage 20 (Tầng Dàn ý chủ đạo). Gọi `CoreAiGatewayContract::executeStructured`
 * với JSON Schema nghiêm ngặt trả về:
 *   - thesis         (string): Luận điểm cốt lõi xuyên suốt.
 *   - target_angle   (string): Góc tiếp cận độc giả mục tiêu.
 *   - outline        (array):  [{tag:'H2'|'H3', title, key_points[], suggested_evidence}].
 *
 * `compileToPromptBlock` biên dịch artifact thành khối chỉ dẫn dàn ý để
 * ArticleWriterStep (step sau, depends_on) nhúng vào User Prompt.
 * =====================================================================
 */
class TopicOutlineStep implements SandboxStepContract
{
    public function __construct(
        private readonly CoreAiGatewayContract $ai,
    ) {}

    /** CHỨC NĂNG: Mã step. OUTPUT: 'topic_outline'. */
    public function key(): string
    {
        return 'topic_outline';
    }

    /** CHỨC NĂNG: Stage — tầng dàn ý chủ đạo. OUTPUT: 20. */
    public function stage(): int
    {
        return 20;
    }

    /** CHỨC NĂNG: Không phải bước kết thúc (còn bước viết bài đứng sau). */
    public function isTerminal(): bool
    {
        return false;
    }

    /** CHỨC NĂNG: Không phụ thuộc nội tại — là bước phân tích đầu tiên. */
    public function intrinsicDependsOn(): array
    {
        return [];
    }

    /** CHỨC NĂNG: Metadata cho API /options (FE render stepper). */
    public function meta(): array
    {
        return [
            'key'          => $this->key(),
            'label'        => 'Bước 1: Phân tích Chủ đề & Lập Dàn ý',
            'description'  => 'Rút luận điểm cốt lõi, góc tiếp cận độc giả và cây dàn ý H2/H3 thực chiến trước khi viết.',
            'icon'         => 'tabler-binary-tree',
            'stage'        => $this->stage(),
            'is_terminal'  => $this->isTerminal(),
        ];
    }

    /**
     * CHỨC NĂNG: Kiểm tra artifact của step này đã đủ schema để step sau dùng.
     * INPUT: array $artifact. OUTPUT: bool.
     * Điều kiện: thesis không rỗng VÀ outline là mảng có ít nhất 1 mục có title.
     */
    public function validateArtifact(array $artifact): bool
    {
        $thesis = trim((string) ($artifact['thesis'] ?? ''));
        if ($thesis === '') {
            return false;
        }

        $outline = $artifact['outline'] ?? null;
        if (! is_array($outline)) {
            return false;
        }

        foreach ($outline as $item) {
            if (is_array($item) && trim((string) ($item['title'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Biên dịch artifact thành khối chỉ dẫn cho step viết bài.
     * =====================================================================
     * INPUT: array $artifact (đã qua validateArtifact).
     * OUTPUT: string — đoạn Dàn ý + Luận điểm format sẵn nhúng vào prompt.
     * =====================================================================
     */
    public function compileToPromptBlock(array $artifact): string
    {
        $lines = ['PHÂN TÍCH CHỦ ĐỀ & DÀN Ý ĐÃ DUYỆT:'];

        $thesis = trim((string) ($artifact['thesis'] ?? ''));
        if ($thesis !== '') {
            $lines[] = '- Luận điểm cốt lõi: ' . $thesis;
        }

        $angle = trim((string) ($artifact['target_angle'] ?? ''));
        if ($angle !== '') {
            $lines[] = '- Góc tiếp cận độc giả: ' . $angle;
        }

        $outline = (array) ($artifact['outline'] ?? []);
        if ($outline !== []) {
            $lines[] = '- Cây dàn ý:';
            foreach ($outline as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $tag = strtoupper((string) ($item['tag'] ?? 'H2'));
                $title = trim((string) ($item['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $lines[] = "  + [{$tag}] {$title}";

                $points = (array) ($item['key_points'] ?? []);
                foreach ($points as $p) {
                    $t = trim((string) $p);
                    if ($t !== '') {
                        $lines[] = '    · ' . $t;
                    }
                }

                $evidence = trim((string) ($item['suggested_evidence'] ?? ''));
                if ($evidence !== '') {
                    $lines[] = '    ↳ Dẫn chứng gợi ý: ' . $evidence;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Thực thi phân tích chủ đề — gọi AI structured JSON nghiêm ngặt.
     * =====================================================================
     * INPUT: profile, topic, targetTier, upstreamPromptBlocks (bỏ qua — bước đầu),
     *        overrides (temperature tuỳ chọn).
     * OUTPUT: array artifact {thesis, target_angle, outline, ai_run_id}.
     * =====================================================================
     */
    public function execute(
        WritingProfile $profile,
        string $topic,
        string $targetTier,
        array $upstreamPromptBlocks,
        array $overrides = [],
    ): array {
        $cfg = (array) config('writingstyle.prompts.steps.topic_outline', []);
        $tierLabel = (string) (\Modules\WritingStyle\Services\WritingProfileDefinition::tierLabel($targetTier));
        $sentenceHint = $tierLabel;

        $temperature = isset($overrides['temperature'])
            ? (float) $overrides['temperature']
            : (float) ($cfg['temperature'] ?? 0.4);

        $userPrompt = strtr(
            (string) ($cfg['user_prompt_template'] ?? 'Chủ đề: {topic}'),
            [
                '{topic}'         => $topic,
                '{tier_label}'    => $tierLabel,
                '{sentence_hint}' => $sentenceHint,
                '{profile_name}'  => (string) ($profile->name ?? ''),
            ]
        );

        $resp = $this->ai->executeStructured(
            AiStructuredRequest::fromPrompt(
                $userPrompt,
                system: (string) ($cfg['system'] ?? 'Bạn là Giám đốc Chiến lược Nội dung.'),
                schema: $this->outputSchema(),
                options: ['temperature' => $temperature],
                module: 'writingstyle',
                operation: 'sandbox_step_topic_outline',
            )
        );

        $data = $resp->data ?? [];

        return [
            'thesis'        => trim((string) ($data['thesis'] ?? '')),
            'target_angle'  => trim((string) ($data['target_angle'] ?? '')),
            'outline'       => is_array($data['outline'] ?? null) ? $data['outline'] : [],
            'ai_run_id'     => $this->ai->lastRun()?->id,
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: JSON Schema nghiêm ngặt (strict) cho phản hồi phân tích.
     * =====================================================================
     * INPUT: không. OUTPUT: array schema — required đủ 3 khóa chính,
     * additionalProperties:false ở mọi tầng.
     * =====================================================================
     */
    protected function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'thesis'       => ['type' => 'string'],
                'target_angle' => ['type' => 'string'],
                'outline' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'tag' => ['type' => 'string', 'enum' => ['H2', 'H3']],
                            'title' => ['type' => 'string'],
                            'key_points' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'suggested_evidence' => ['type' => 'string'],
                        ],
                        'required' => ['tag', 'title', 'key_points', 'suggested_evidence'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['thesis', 'target_angle', 'outline'],
            'additionalProperties' => false,
        ];
    }
}
