<?php

namespace Modules\WritingStyle\Services;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\WritingStyle\DTOs\WritingAnalysisResultDto;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\TaskCenter\Contracts\TaskTrackerContract;

class WritingStyleAnalyzerService
{
    public function __construct(
        protected readonly CoreAiGatewayContract $ai,
        protected readonly TaskTrackerContract $tracker,
    ) {}

    public function analyze(
        string $referenceText,
        string $sourceType = WritingProfileAnalysis::SOURCE_PASTE,
        ?string $sourceUrl = null,
        ?int $createdBy = null,
        ?WritingProfileAnalysis $analysis = null,
    ): WritingProfileAnalysis {
        $analysis ??= WritingProfileAnalysis::create([
            'created_by'      => $createdBy,
            'source_type'     => $sourceType,
            'source_url'      => $sourceUrl,
            'reference_text'  => $referenceText,
            'status'          => WritingProfileAnalysis::STATUS_PENDING,
            'expires_at'      => now()->addDays((int) config('writingstyle.analysis_retention_days', 30)),
        ]);
        $analysis->markRunning();

        try {
            $tier = WritingProfileDefinition::detectTier($referenceText);

            $resp = $this->ai->executeStructured(
                AiStructuredRequest::fromPrompt(
                    $this->buildPrompt($referenceText, $tier),
                    system: 'Bạn là chuyên gia phân tích văn phong. Chỉ trả JSON đúng schema, không thêm lời dẫn.',
                    schema: $this->outputSchema($tier),
                    options: ['temperature' => 0.2],
                    module: 'writingstyle',
                    operation: 'analyze',
                )
            );
            $data = $resp->data ?? [];
            $aiRunId = $this->ai->lastRun()?->id;

            $rawEvidence = is_array($data['evidence_json'] ?? null) ? $data['evidence_json'] : [];
            $evidence = WritingProfileDefinition::filterValidEvidence($referenceText, $rawEvidence);
            $rules = is_array($data['rules_json'] ?? null) ? $data['rules_json'] : [];
            $styleInstructions = trim((string) ($data['style_instructions'] ?? ''));

            $metadata = [
                'word_count'     => WritingProfileDefinition::countWords($referenceText),
                'sentence_count' => WritingProfileDefinition::countSentences($referenceText),
                'detected_tier'  => $tier,
                'ai_model_used'  => $resp->remoteModelId,
            ];

            $result = new WritingAnalysisResultDto(
                detectedTier: $tier,
                rulesJson: $rules,
                evidenceJson: $evidence,
                styleInstructions: $styleInstructions,
                metadata: $metadata,
                aiRunId: $aiRunId,
            );

            $draft = $this->createDraft($analysis, $result, $referenceText, $createdBy);

            $analysis->markSucceeded($result->toResultJson(), $tier, $aiRunId, $draft->id);

            return $analysis->fresh();
        } catch (\Throwable $e) {
            // Lỗi tạm thời (network/timeout/rate_limit/provider_error) ->
            // NÉM LẠI để queue retry theo $tries; markFailed trước khi ném để
            // nếu hết lượt vẫn lỗi thì bản ghi phản ánh trạng thái failed.
            $transient = [
                'network',
                'timeout',
                'rate_limit',
                'provider_error',
            ];
            $code = $e instanceof \Modules\CoreAi\Exceptions\AiGatewayException ? $e->errorCode() : 'analyzer_error';
            $analysis->markFailed($code, $e->getMessage());

            if (in_array($code, $transient, true)) {
                throw $e; // để worker retry — queue sẽ chạy lại lần 2.
            }

            return $analysis->fresh();
        }
    }

    protected function buildPrompt(string $referenceText, string $tier): string
    {
        $lines = [
            'Hãy giải mã DNA văn phong của bài mẫu dưới đây.',
            'Trả lời các khía cạnh sau (mỗi khía cạnh trả lời ngắn gọn câu hỏi kèm theo):',
        ];
        foreach (WritingProfileDefinition::applicableKeys($tier) as $key) {
            $lines[] = '- ' . $key . ': ' . WritingProfileDefinition::ruleQuestion($key);
        }
        $lines[] = '';
        $lines[] = 'Với mỗi khía cạnh cũng trích 1 câu NGUYÊN VĂN trong bài mẫu làm bằng chứng (evidence_json[].excerpt) — PHẢI là chữ có thật trong bài, không được suy diễn.';
        $lines[] = '';
        $lines[] = 'BÀI MẪU:';
        $lines[] = $referenceText;

        return implode(PHP_EOL, $lines);
    }

    protected function outputSchema(string $tier): array
    {
        $rulesProps = [];
        foreach (WritingProfileDefinition::applicableKeys($tier) as $k) {
            $rulesProps[$k] = ['type' => 'string'];
        }
        // uncertainties luôn là mảng chuỗi.
        $rulesProps['uncertainties'] = ['type' => 'array', 'items' => ['type' => 'string']];

        // OpenAI strict json_schema: additionalProperties:false ở MỌI object
        // + required phải liệt kê toàn bộ properties ở mỗi tầng.
        $rulesRequired = array_keys($rulesProps);
        $evidenceItemProps = [
            'rule_key' => ['type' => 'string'],
            'excerpt'  => ['type' => 'string'],
            'note'     => ['type' => 'string'],
        ];

        return [
            'type' => 'object',
            'properties' => [
                'rules_json' => [
                    'type' => 'object',
                    'properties' => $rulesProps,
                    'required' => $rulesRequired,
                    'additionalProperties' => false,
                ],
                'evidence_json' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => $evidenceItemProps,
                        'required' => array_keys($evidenceItemProps),
                        'additionalProperties' => false,
                    ],
                ],
                'style_instructions' => ['type' => 'string'],
            ],
            'required' => ['rules_json', 'evidence_json', 'style_instructions'],
            'additionalProperties' => false,
        ];
    }

    protected function createDraft(
        WritingProfileAnalysis $analysis,
        WritingAnalysisResultDto $result,
        string $referenceText,
        ?int $createdBy,
    ): WritingProfile {
        return WritingProfile::create([
            'name'             => $analysis->name ?: 'Văn phong giải mã ' . now()->format('d/m H:i'),
            'dominant_tier'    => $result->detectedTier,
            'rules_json'       => $result->rulesJson,
            'evidence_json'    => $result->evidenceJson,
            'style_instructions' => $result->styleInstructions,
            'origin'           => WritingProfile::ORIGIN_REFERENCE,
            'source_hash'      => WritingProfileAnalysis::sourceHashOf($referenceText),
            'analysis_metadata_json' => $result->metadata,
            'status'           => WritingProfile::STATUS_DRAFT,
            'is_enabled'       => true,
            'created_by'       => $createdBy,
        ]);
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo analysis pending + SystemTaskRun + dispatch job.
     * =====================================================================
     * Dùng bởi controller (Task 9) và mọi nơi cần phân tích bất đồng bộ:
     * tạo WritingProfileAnalysis (pending) -> TaskTrackerService::create
     * (task_type='writing_profile.analyze') -> dispatch AnalyzeWritingProfileJob.
     * Trả [analysis, taskRun] để caller lấy id cho response 202.
     *
     * Dedupe 2 tầng: analysis cùng source_hash đang pending/running -> trả
     * bản ghi + taskRun hiện có (không tạo trùng). Lần trước failed/succeeded
     * -> tạo analysis + taskRun MỚI (dedupe_key kèm analysis->id) để retry.
     *
     * INPUT: referenceText + sourceType + ?sourceUrl + ?name + ?createdBy.
     * OUTPUT: array{analysis: WritingProfileAnalysis, taskRun: SystemTaskRun}.
     * =====================================================================
     */
    public function dispatchAnalysis(
        string $referenceText,
        string $sourceType = WritingProfileAnalysis::SOURCE_PASTE,
        ?string $sourceUrl = null,
        ?string $name = null,
        ?int $createdBy = null,
    ): array {
        // Dedupe: đã có analysis cùng source_hash đang pending/running ->
        // trả bản ghi + taskRun hiện có, KHÔNG tạo analysis/job trùng.
        $hash = WritingProfileAnalysis::sourceHashOf($referenceText);
        $existing = WritingProfileAnalysis::query()
            ->where('source_hash', $hash)
            ->whereIn('status', [WritingProfileAnalysis::STATUS_PENDING, WritingProfileAnalysis::STATUS_RUNNING])
            ->latest('id')
            ->first();

        if ($existing !== null) {
            $taskRun = \Modules\TaskCenter\Models\SystemTaskRun::query()
                ->where('taskable_id', (string) $existing->id)
                ->where('task_type', 'writing_profile.analyze')
                ->latest('created_at')
                ->first();

            return ['analysis' => $existing, 'taskRun' => $taskRun];
        }

        // Chưa có / lần trước succeeded|failed -> tạo analysis mới để retry
        // (hoặc lần đầu). dedupe_key gắn analysis->id => mỗi attempt 1
        // taskRun riêng, không trả taskRun failed của lần trước.
        $analysis = WritingProfileAnalysis::create([
            'created_by'     => $createdBy,
            'name'           => $name,
            'source_type'    => $sourceType,
            'source_url'     => $sourceUrl,
            'reference_text' => $referenceText,
            'status'         => WritingProfileAnalysis::STATUS_PENDING,
            'expires_at'     => now()->addDays((int) config('writingstyle.analysis_retention_days', 30)),
        ]);

        $taskRun = $this->tracker->create(new \Modules\TaskCenter\DTOs\CreateTaskRunDto(
            dedupeKey: 'wp-analyze:' . $analysis->source_hash . ':' . $analysis->id,
            module: 'writingstyle',
            taskType: 'writing_profile.analyze',
            title: 'Giải mã văn phong' . ($name ? " — {$name}" : ''),
            taskableType: WritingProfileAnalysis::class,
            taskableId: (string) $analysis->id,
            initiatedBy: $createdBy,
            meta: ['analysis_id' => $analysis->id],
        ));

        \Modules\WritingStyle\Jobs\AnalyzeWritingProfileJob::dispatch($analysis->id, $taskRun->id);

        return ['analysis' => $analysis, 'taskRun' => $taskRun];
    }

}
