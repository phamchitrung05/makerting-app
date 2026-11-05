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
 *    - Đóng gói DTO, tự động tạo WritingProfile nháp (status: draft, origin: reference).
 *    - Đánh dấu succeeded và lưu kết quả vào writing_profile_analyses.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(CoreAiGatewayContract $ai, TaskTrackerContract $tracker): Khởi tạo service
 * - analyze(...): Giải mã DNA văn phong qua AI và lưu WritingProfile bản nháp
 * - buildPrompt(string $referenceText, string $tier): Ghép prompt phân tích kèm câu hỏi quy tắc
 * - outputSchema(string $tier): Tạo JSON Schema nghiêm ngặt cho phản hồi AI
 * - createDraft(...): Tạo bản ghi WritingProfile nháp từ kết quả phân tích
 * - dispatchAnalysis(...): Khởi tạo bản ghi phân tích, SystemTaskRun và đẩy job vào hàng đợi
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : reference_text (văn bản mẫu), source_type, source_url, name, created_by
 * - OUTPUT: WritingProfileAnalysis (kèm draft_profile_id và result_json) hoặc array{analysis, taskRun}
 * =====================================================================
 */
class WritingStyleAnalyzerService
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo service phân tích văn phong với AI Gateway và Task Tracker
     * =====================================================================
     *
     * INPUT:
     * - CoreAiGatewayContract $ai: Gateway điều phối các mô hình AI (OpenAI, Gemini, Anthropic)
     * - TaskTrackerContract $tracker: Quản trị vòng đời tác vụ hệ thống (SystemTaskRun)
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Tiêm phụ thuộc CoreAiGatewayContract và TaskTrackerContract
     *
     * EXCEPTION/TRANSACTION:
     * - Không có
     * =====================================================================
     */
    public function __construct(
        protected readonly CoreAiGatewayContract $ai,
        protected readonly TaskTrackerContract $tracker,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Thực thi giải mã DNA văn phong đồng bộ qua CoreAiGateway và lưu bản nháp WritingProfile
     * =====================================================================
     *
     * Luồng xử lý chi tiết:
     * 1. Khởi tạo/nhận bản ghi WritingProfileAnalysis và chuyển trạng thái sang running.
     * 2. Phát hiện phân tầng độ dài bài viết mẫu (detected_tier).
     * 3. Ghép prompt và JSON Schema nghiêm ngặt theo các quy tắc áp dụng cho tier đó.
     * 4. Gọi AI Gateway xử lý trích xuất có cấu trúc (structured JSON).
     * 5. Xác thực evidence_json nguyên văn từ văn bản mẫu để chống ảo giác (hallucination).
     * 6. Tạo WritingProfile bản nháp (status: draft, origin: reference) và gắn ID vào analysis.
     * 7. Chuyển trạng thái analysis sang succeeded kèm toàn bộ payload result_json.
     *
     * INPUT:
     * - string $referenceText: Toàn bộ văn bản mẫu cần bóc tách văn phong
     * - string $sourceType: Loại nguồn (paste | url | file)
     * - ?string $sourceUrl: Đường dẫn nguồn nếu phân tích từ trang web
     * - ?int $createdBy: ID người dùng yêu cầu phân tích
     * - ?WritingProfileAnalysis $analysis: Bản ghi phân tích đã tạo trước (từ queue job)
     *
     * OUTPUT:
     * - WritingProfileAnalysis: Bản ghi phân tích sau khi đã xử lý xong (kèm result_json và draft_profile_id)
     *
     * SIDE EFFECT:
     * - Cập nhật trạng thái và kết quả bảng writing_profile_analyses
     * - Thêm 1 bản ghi mới vào bảng writing_profiles (bản nháp)
     * - Ghi nhận aiRunId cho tác vụ AI trong CoreAi
     *
     * EXCEPTION/TRANSACTION:
     * - Throwable: Bắt mọi lỗi; với các lỗi tạm thời (network, timeout, rate_limit, provider_error),
     *   đánh dấu failed tạm và ném lại (rethrow) để Queue Job retry tự động
     * - Không mở DB transaction để bảo toàn trạng thái lỗi khi có sự cố
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

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghép nối câu lệnh prompt phân tích văn phong theo các quy tắc áp dụng cho tier độ dài
     * =====================================================================
     *
     * Duyệt qua các khóa quy tắc phù hợp với tier của bài mẫu từ WritingProfileDefinition,
     * yêu cầu AI trả lời ngắn gọn từng câu hỏi và trích xuất câu văn nguyên văn làm bằng chứng.
     *
     * INPUT:
     * - string $referenceText: Văn bản mẫu cần phân tích
     * - string $tier: Phân tầng độ dài (micro | short | medium | long)
     *
     * OUTPUT:
     * - string: Toàn bộ chuỗi prompt hoàn chỉnh gửi tới AI Gateway
     *
     * SIDE EFFECT:
     * - Không có (hàm thuần túy)
     *
     * EXCEPTION/TRANSACTION:
     * - Không ném exception, không mở transaction
     * =====================================================================
     */
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

    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo cấu trúc JSON Schema nghiêm ngặt cho kết quả phản hồi của AI
     * =====================================================================
     *
     * Tuân thủ chuẩn OpenAI strict json_schema: mọi object bắt buộc có
     * additionalProperties: false và required phải khai báo đủ 100% properties.
     *
     * INPUT:
     * - string $tier: Phân tầng độ dài để lấy danh sách rule_keys áp dụng
     *
     * OUTPUT:
     * - array: Cấu trúc JSON Schema dùng cho AiStructuredRequest
     *
     * SIDE EFFECT:
     * - Không có (hàm thuần túy)
     *
     * EXCEPTION/TRANSACTION:
     * - Không ném exception, không mở transaction
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

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo bản ghi hồ sơ văn phong nháp (Draft WritingProfile) từ kết quả giải mã
     * =====================================================================
     *
     * Lưu kết quả giải mã vào bảng writing_profiles với status 'draft' và origin 'reference'
     * để người dùng có thể xem lại, chỉnh sửa hoặc dùng ngay trên giao diện mà không bị mất dữ liệu.
     *
     * INPUT:
     * - WritingProfileAnalysis $analysis: Bản ghi phiên phân tích liên quan
     * - WritingAnalysisResultDto $result: DTO chứa kết quả giải mã (rules, evidence, style instructions, metadata)
     * - string $referenceText: Văn bản mẫu dùng để tính source_hash
     * - ?int $createdBy: ID người dùng sở hữu hồ sơ
     *
     * OUTPUT:
     * - WritingProfile: Bản ghi hồ sơ văn phong nháp vừa tạo
     *
     * SIDE EFFECT:
     * - Thêm 1 dòng mới vào bảng writing_profiles
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction
     * =====================================================================
     */
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
     * CHỨC NĂNG: Tiếp nhận yêu cầu giải mã, xử lý chống trùng lặp (dedupe), tạo task tracking và dispatch job
     * =====================================================================
     *
     * Dùng bởi WritingAnalyzeController@store (Giai đoạn 3) và các dịch vụ gọi bất đồng bộ:
     * - Dedupe 2 tầng: Nếu cùng source_hash đang pending/running thì trả về bản ghi hiện tại, tránh tạo job trùng.
     * - Nếu chưa có hoặc lần trước đã kết thúc (succeeded/failed): Tạo WritingProfileAnalysis mới,
     *   đăng ký SystemTaskRun và dispatch AnalyzeWritingProfileJob vào queue.
     *
     * INPUT:
     * - string $referenceText: Nội dung bài viết mẫu
     * - string $sourceType: Loại nguồn (paste | url | file), mặc định là paste
     * - ?string $sourceUrl: URL bài viết (nếu có)
     * - ?string $name: Tên gợi nhớ của hồ sơ
     * - ?int $createdBy: ID người dùng gửi yêu cầu
     *
     * OUTPUT:
     * - array{analysis: WritingProfileAnalysis, taskRun: ?SystemTaskRun}: Cặp model phân tích và tác vụ hệ thống
     *   (taskRun có thể null ở nhánh dedupe khi dữ liệu taskRun cũ không khớp)
     *
     * SIDE EFFECT:
     * - Tạo bản ghi mới trong writing_profile_analyses và system_task_runs
     * - Đẩy AnalyzeWritingProfileJob vào hàng đợi xử lý ngầm
     *
     * EXCEPTION/TRANSACTION:
     * - Không ném exception, không mở transaction
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
