<?php

namespace Modules\WritingStyle\Services;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\WritingStyle\DTOs\WritingAnalysisResultDto;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\TaskCenter\Contracts\TaskTrackerContract;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Service phân tích, giải mã DNA văn phong qua AI và quản lý tác vụ ngầm.
 * =====================================================================
 *
 * Thực hiện 2 nhiệm vụ cốt lõi trong hệ thống WritingStyle:
 * 1. dispatchAnalysis(): Tiếp nhận văn bản mẫu, dedupe theo source_hash, tạo bản
 *    ghi WritingProfileAnalysis (pending), đăng ký SystemTaskRun và dispatch
 *    AnalyzeWritingProfileJob vào queue để xử lý bất đồng bộ (trả 202 cho FE).
 * 2. analyze(): Thực thi giải mã DNA bằng AI (chạy trong Queue Worker hoặc gọi đồng bộ):
 *    - Tự động nhận diện phân tầng độ dài (Tier: micro | short | medium | long).
 *    - Biên dịch prompt trắc nghiệm bộ quy tắc tương ứng qua WritingProfileDefinition.
 *    - Gọi CoreAiGatewayContract::executeStructured với JSON Schema nghiêm ngặt.
 *    - Lọc kiểm chứng bằng chứng nguyên văn (evidence_json) có thật trong bài mẫu.
 *    - Trích xuất cả Ô 1 (style_instructions) và Ô 2 (voice_texture_instructions +
 *      5 thanh trượt voice_texture_sliders_json).
 *    - Đóng gói DTO và lưu result_json vào writing_profile_analyses.
 *    - KHÔNG tạo WritingProfile: `draft_profile_id` được gán khi người dùng
 *      bấm Lưu trên trang create (POST /writing-profiles kèm analysis_id).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(CoreAiGatewayContract $ai, TaskTrackerContract $tracker): Khởi tạo service
 * - analyze(...): Giải mã DNA văn phong qua AI và lưu kết quả vào analysis
 * - buildPrompt(string $referenceText, string $tier): Ghép prompt phân tích kèm câu hỏi quy tắc
 * - outputSchema(string $tier): Tạo JSON Schema nghiêm ngặt cho phản hồi AI
 * - dispatchAnalysis(...): Khởi tạo bản ghi phân tích, SystemTaskRun và đẩy job vào hàng đợi
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : reference_text (văn bản mẫu), source_type, source_url, name, created_by
 * - OUTPUT: WritingProfileAnalysis (kèm result_json) hoặc array{analysis, taskRun}
 * =====================================================================
 */
class WritingStyleAnalyzerService
{
    public function __construct(
        protected readonly CoreAiGatewayContract $ai,
        protected readonly TaskTrackerContract $tracker,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Thực thi giải mã DNA văn phong đồng bộ qua CoreAiGateway
     * =====================================================================
     */
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
                    system: (string) config(
                        'writingstyle.prompts.analyzer.system',
                        'Bạn là chuyên gia phân tích văn phong. Chỉ trả JSON đúng schema, không thêm lời dẫn.'
                    ),
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
            $voiceTextureInstructions = trim((string) ($data['voice_texture_instructions'] ?? ''));

            // Chuẩn hóa 5 thanh trượt Chất văn (0..100).
            $rawSliders = is_array($data['voice_texture_sliders_json'] ?? null)
                ? $data['voice_texture_sliders_json']
                : [];
            $axesConfig = config('writingstyle.voice_texture_axes', []);
            $voiceTextureSliders = [];
            foreach (WritingProfile::VOICE_TEXTURE_KEYS as $axisKey) {
                $defaultVal = (int) ($axesConfig[$axisKey]['default'] ?? 70);
                $val = isset($rawSliders[$axisKey]) ? (int) $rawSliders[$axisKey] : $defaultVal;
                $voiceTextureSliders[$axisKey] = max(0, min(100, $val));
            }

            // Chuẩn hóa 8 thanh trượt Sandbox sắc thái cảm xúc (0..100, mặc định 50).
            $rawToneSliders = is_array($data['tone_sliders_json'] ?? null)
                ? $data['tone_sliders_json']
                : [];
            $toneSliders = [];
            foreach (WritingProfile::TONE_AXIS_KEYS as $toneKey) {
                $val = isset($rawToneSliders[$toneKey]) ? (int) $rawToneSliders[$toneKey] : 50;
                $toneSliders[$toneKey] = max(0, min(100, $val));
            }

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
                voiceTextureInstructions: $voiceTextureInstructions,
                voiceTextureSlidersJson: $voiceTextureSliders,
                toneSlidersJson: $toneSliders,
            );

            // KHÔNG tạo WritingProfile ở đây — phân tích bao nhiêu lần cũng
            // chỉ ghi run log; profile chỉ được tạo khi người dùng bấm Lưu
            // (POST /writing-profiles kèm analysis_id). `draft_profile_id`
            // giữ null và được gán lúc Lưu (xem WritingProfileController@store).
            $analysis->markSucceeded($result->toResultJson(), $tier, $aiRunId);

            return $analysis->fresh();
        } catch (\Throwable $e) {
            $transient = [
                'network',
                'timeout',
                'rate_limit',
                'provider_error',
            ];
            $code = $e instanceof \Modules\CoreAi\Exceptions\AiGatewayException ? $e->errorCode() : 'analyzer_error';

            if (in_array($code, $transient, true)) {
                $analysis->markTransientError($code, $e->getMessage());

                throw $e;
            }

            $analysis->markFailed($code, $e->getMessage());

            return $analysis->fresh();
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghép nối câu lệnh prompt phân tích văn phong theo các quy tắc áp dụng cho tier độ dài
     * =====================================================================
     */
    protected function buildPrompt(string $referenceText, string $tier): string
    {
        $cfg = config('writingstyle.prompts.analyzer', []);

        $lines = (array) ($cfg['intro'] ?? [
            'Hãy giải mã DNA văn phong của bài mẫu dưới đây.',
            'Trả lời các khía cạnh sau (mỗi khía cạnh trả lời ngắn gọn câu hỏi kèm theo):',
        ]);

        foreach (WritingProfileDefinition::applicableKeys($tier) as $key) {
            $lines[] = '- ' . $key . ': ' . WritingProfileDefinition::ruleQuestion($key);
        }

        $lines[] = '';
        $lines[] = (string) ($cfg['evidence_instruction'] ?? 'Với mỗi khía cạnh cũng trích 1 câu NGUYÊN VĂN trong bài mẫu làm bằng chứng (evidence_json[].excerpt) — PHẢI là chữ có thật trong bài, không được suy diễn.');
        $guide = trim((string) ($cfg['style_instructions_guide'] ?? ''));
        if ($guide !== '') {
            $lines[] = '';
            $lines[] = $guide;
        }
        $voiceGuide = trim((string) ($cfg['voice_texture_guide'] ?? ''));
        if ($voiceGuide !== '') {
            $lines[] = '';
            $lines[] = $voiceGuide;
        }
        $lines[] = '';
        $lines[] = 'BÀI MẪU:';
        $lines[] = $referenceText;

        return implode(PHP_EOL, $lines);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo cấu trúc JSON Schema nghiêm ngặt cho kết quả phản hồi của AI
     * =====================================================================
     */
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

        $sliderProps = [];
        foreach (WritingProfile::VOICE_TEXTURE_KEYS as $axisKey) {
            $sliderProps[$axisKey] = ['type' => 'integer'];
        }

        $toneSliderProps = [];
        foreach (WritingProfile::TONE_AXIS_KEYS as $toneKey) {
            $toneSliderProps[$toneKey] = ['type' => 'integer'];
        }

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
                'voice_texture_instructions' => ['type' => 'string'],
                'voice_texture_sliders_json' => [
                    'type' => 'object',
                    'properties' => $sliderProps,
                    'required' => array_keys($sliderProps),
                    'additionalProperties' => false,
                ],
                'tone_sliders_json' => [
                    'type' => 'object',
                    'properties' => $toneSliderProps,
                    'required' => array_keys($toneSliderProps),
                    'additionalProperties' => false,
                ],
            ],
            'required' => [
                'rules_json',
                'evidence_json',
                'style_instructions',
                'voice_texture_instructions',
                'voice_texture_sliders_json',
                'tone_sliders_json',
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tiếp nhận yêu cầu giải mã, xử lý chống trùng lặp (dedupe), tạo task tracking và dispatch job
     * =====================================================================
     */
    public function dispatchAnalysis(
        string $referenceText,
        string $sourceType = WritingProfileAnalysis::SOURCE_PASTE,
        ?string $sourceUrl = null,
        ?string $name = null,
        ?int $createdBy = null,
    ): array {
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
