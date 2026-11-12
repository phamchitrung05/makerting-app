<?php

namespace Modules\WritingStyle\Services;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\WritingStyle\DTOs\WritingAnalysisResultDto;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\TaskCenter\Contracts\TaskTrackerContract;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Giải mã DNA (Prompt Pack V3) và quản lý tác vụ phân tích bất đồng bộ.
 * =====================================================================
 *
 * Thực hiện 2 nhiệm vụ cốt lõi trong hệ thống WritingStyle:
 * 1. dispatchAnalysis(): Tiếp nhận văn bản mẫu, dedupe theo source_hash, tạo bản
 *    ghi WritingProfileAnalysis (pending), đăng ký SystemTaskRun và dispatch
 *    AnalyzeWritingProfileJob vào queue để xử lý bất đồng bộ (trả 202 cho FE).
 * 2. analyze(): Thực thi giải mã DNA bằng AI (chạy trong Queue Worker hoặc gọi đồng bộ):
 *    - Tự động nhận diện phân tầng độ dài (Tier: micro | short | medium | long).
 *    - Gọi CoreAiGatewayContract::executeStructured với JSON Schema nghiêm ngặt
 *      của `WritingStyleAnalysisV3` (features/state/evidence có ID).
 *    - Trích xuất Ô 1 (style_instructions) + Ô 2 (voice_texture_instructions +
 *      sliders) + signature_language + source_specific_items + uncertainties.
 *    - Đóng gói DTO và lưu result_json vào writing_profile_analyses.
 *    - KHÔNG tạo WritingProfile: `draft_profile_id` được gán khi người dùng
 *      bấm Lưu trên trang create (POST /writing-profiles kèm analysis_id).
 *
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Khởi tạo thuộc tính hoặc nhận các dependency của WritingStyleAnalyzerService.
 * - analyze(): Giải mã mẫu và lưu kết quả cùng metadata.
 * - dispatchAnalysis(): Dedupe phân tích đang chạy theo source_hash rồi tạo job nếu cần.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Bài mẫu, loại nguồn, URL, người tạo và analysis có thể đã tồn tại.
 * - OUTPUT: WritingProfileAnalysis hoặc cặp analysis/taskRun.
 *
 * BỐI CẢNH SỬ DỤNG: Controller dispatch job; worker hoặc bộ eval gọi analyze(). Phiên bản được ghim trên analysis.
 * =====================================================================
 */
class WritingStyleAnalyzerService
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo thuộc tính hoặc nhận các dependency của WritingStyleAnalyzerService
     * =====================================================================
     *
     * INPUT:
     * - protected readonly CoreAiGatewayContract $ai, protected readonly TaskTrackerContract $tracker,
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
        protected readonly TaskTrackerContract $tracker,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Phân tích mẫu theo phiên bản đã ghim và lưu kết quả cùng metadata
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Thực thi giải mã DNA văn phong đồng bộ qua CoreAiGateway
     * =====================================================================
     *
     * INPUT:
     * - string $referenceText, string $sourceType = WritingProfileAnalysis::SOURCE_PASTE, ?string $sourceUrl = null, ?int $createdBy = null, ?WritingProfileAnalysis $analysis = null,
     *
     * OUTPUT:
     * - WritingProfileAnalysis fresh có trạng thái succeeded/failed hoặc giữ running để retry
     *
     * SIDE EFFECT:
     * - Gọi gateway, ghi analysis và log AI. V3 kiểm tra hoàn tất, normalize bằng chứng và giữ slider null. Chưa tạo WritingProfile.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi transient được ghi rồi ném lại; lỗi khác được chuyển failed. Không tự mở transaction.
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
            'prompt_version'  => 3,
            'status'          => WritingProfileAnalysis::STATUS_PENDING,
            'expires_at'      => now()->addDays((int) config('writingstyle.analysis.storage.retention_days', config('writingstyle.analysis_retention_days', 30))),
        ]);
        $analysis->markRunning();

        try {
            $tier = WritingProfileDefinition::detectTier($referenceText);

            $analysisService = new WritingStyleAnalysisV3($referenceText);
            $resp = $this->ai->executeStructured($analysisService->request());
            $data = $resp->data ?? [];
            PromptPack::requireComplete($resp);
            $data = $analysisService->normalize($data);
            $aiRunId = $this->ai->lastRun()?->id;

            $rawEvidence = is_array($data['evidence_json'] ?? null) ? $data['evidence_json'] : [];
            $evidence = $rawEvidence;
            $rules = is_array($data['rules_json'] ?? null) ? $data['rules_json'] : [];
            $styleInstructions = trim((string) ($data['style_instructions'] ?? ''));
            $voiceTextureInstructions = trim((string) ($data['voice_texture_instructions'] ?? ''));

            // Chuẩn hóa 5 thanh trượt Chất văn (0..100, giữ null khi thiếu).
            $voiceTextureSliders = [];
            foreach (WritingProfile::VOICE_TEXTURE_KEYS as $axisKey) {
                $voiceTextureSliders[$axisKey] = isset($data['voice_texture_sliders_json'][$axisKey])
                    ? $data['voice_texture_sliders_json'][$axisKey]
                    : null;
            }

            // Chuẩn hóa 8 thanh trượt Sắc thái cảm xúc (0..100, giữ null khi thiếu).
            $toneSliders = [];
            foreach (WritingProfile::TONE_AXIS_KEYS as $toneKey) {
                $toneSliders[$toneKey] = isset($data['tone_sliders_json'][$toneKey])
                    ? $data['tone_sliders_json'][$toneKey]
                    : null;
            }

            $metadata = [
                'word_count'     => WritingProfileDefinition::countWords($referenceText),
                'sentence_count' => WritingProfileDefinition::countSentences($referenceText),
                'detected_tier'  => $tier,
                'ai_model_used'  => $resp->remoteModelId,
                'prompt_version' => 3,
                'schema_version' => 3,
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
                styleAnalysisJson: $data['style_analysis_json'] ?? null,
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
     * CHỨC NĂNG: Dedupe phân tích đang chạy theo source_hash và phiên bản rồi tạo job nếu cần
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Tiếp nhận yêu cầu giải mã, xử lý chống trùng lặp (dedupe), tạo task tracking và dispatch job
     * =====================================================================
     *
     * INPUT:
     * - string $referenceText, string $sourceType = WritingProfileAnalysis::SOURCE_PASTE, ?string $sourceUrl = null, ?string $name = null, ?int $createdBy = null,
     *
     * OUTPUT:
     * - array analysis và taskRun, có thể tái sử dụng bản pending/running
     *
     * SIDE EFFECT:
     * - Truy vấn analysis mới nhất theo id; tạo analysis/taskRun và dispatch queue ai-text sau commit của caller.
     *
     * EXCEPTION/TRANSACTION:
     * - Có thể phát sinh lỗi DB/tracker/queue. Không bao transaction cho cả chuỗi, không giữ khóa ghi.
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
            'prompt_version' => 3,
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
