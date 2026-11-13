<?php

namespace Modules\WritingStyle\Pipelines\Steps;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Pipelines\WritingContentPlan;
use Modules\WritingStyle\Services\PromptPack;
use Modules\WritingStyle\Pipelines\Contracts\SandboxStepContract;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Lập kế hoạch nội dung trước bước viết (Prompt Pack V3).
 * =====================================================================
 *
 * Stage 20 (Tầng Dàn ý chủ đạo). Gọi `CoreAiGatewayContract::executeStructured`
 * với JSON Schema nghiêm ngặt trả `central_message`, `sections` có ngân sách
 * độ dài, `fact_ids` và `fingerprint`; heading_level có thể null theo brief,
 * không ép H2/H3.
 *
 * `compileToPromptBlock` biên dịch artifact thành khối chỉ dẫn dàn ý để
 * ArticleWriterStep (step sau, depends_on) nhúng vào User Prompt.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Khởi tạo thuộc tính hoặc nhận các dependency của TopicOutlineStep.
 * - key(): Trả mã định danh bước pipeline.
 * - stage(): Trả thứ tự tầng thực thi để kiểm tra topology.
 * - isTerminal(): Cho biết bước có kết thúc pipeline hay không.
 * - intrinsicDependsOn(): Khai báo các phụ thuộc bắt buộc bên trong step.
 * - meta(): Cấp nhãn và metadata step để API options dựng giao diện tiến trình.
 * - validateArtifact(): Kiểm tra artifact dàn ý trước khi dùng cho bước kế tiếp.
 * - compileToPromptBlock(): Chuyển dàn ý thành chỉ dẫn để writer sử dụng.
 * - execute(): Lập kế hoạch bằng brief + style summary của profile.
 * - executeV3(): Lập dàn ý V3 bằng brief và style summary thực tế của profile.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Profile, topic, tier và runtime overrides.
 * - OUTPUT: Artifact V3 sections/fingerprint/provenance.
 *
 * BỐI CẢNH SỬ DỤNG: Runner gọi topic_outline khi tier có bước lập dàn ý. V3 nhận brief và style summary từ compiler.
 * =====================================================================
 */
class TopicOutlineStep implements SandboxStepContract
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo thuộc tính hoặc nhận các dependency của TopicOutlineStep
     * =====================================================================
     *
     * INPUT:
     * - private readonly CoreAiGatewayContract $ai,
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
        private readonly CoreAiGatewayContract $ai,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Trả mã định danh bước pipeline
     * =====================================================================
     *
     * Chi tiết triển khai:
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - string topic_outline
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function key(): string
    {
        return 'topic_outline';
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Trả thứ tự tầng thực thi để kiểm tra topology
     * =====================================================================
     *
     * Chi tiết triển khai:
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - int 20
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function stage(): int
    {
        return 20;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Cho biết bước có kết thúc pipeline hay không
     * =====================================================================
     *
     * Chi tiết triển khai:
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool false
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function isTerminal(): bool
    {
        return false;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo các phụ thuộc bắt buộc bên trong step
     * =====================================================================
     *
     * Chi tiết triển khai:
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array rỗng; phụ thuộc còn lại được lấy từ config
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function intrinsicDependsOn(): array
    {
        return [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Cấp nhãn và metadata step để API options dựng giao diện tiến trình
     * =====================================================================
     *
     * Chi tiết triển khai:
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array key/label/description/icon/stage/is_terminal
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
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
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra artifact dàn ý trước khi dùng cho bước kế tiếp
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Kiểm tra artifact của step này đã đủ schema để step sau dùng.
     * INPUT: array $artifact. OUTPUT: bool.
     * Điều kiện V2: thesis không rỗng và outline có mục với title; V3: sections không rỗng.
     *
     * INPUT:
     * - array $artifact
     *
     * OUTPUT:
     * - bool: V3 có sections không rỗng; V2 có thesis và ít nhất một mục outline có title
     *
     * SIDE EFFECT:
     * - Đọc artifact; đây là kiểm tra sơ bộ, Runner kiểm tra sâu kế hoạch V3.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction hoặc gọi AI.
     * =====================================================================
     */
    public function validateArtifact(array $artifact): bool
    {
        return is_array($artifact['sections'] ?? null) && $artifact['sections'] !== [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển dàn ý V2/V3 thành chỉ dẫn để writer sử dụng
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Biên dịch artifact thành khối chỉ dẫn cho step viết bài.
     * =====================================================================
     * INPUT: array $artifact (đã qua validateArtifact).
     * OUTPUT: string — đoạn Dàn ý + Luận điểm format sẵn nhúng vào prompt.
     * =====================================================================
     *
     * INPUT:
     * - array $artifact
     *
     * OUTPUT:
     * - string: V3 chỉ chọn thông điệp/góc nhìn/cấu trúc/sections; V2 dựng đoạn dàn ý
     *
     * SIDE EFFECT:
     * - Không ghi DB hoặc thay artifact.
     *
     * EXCEPTION/TRANSACTION:
     * - JsonException có thể xảy ra khi mã hóa V3. Không mở transaction.
     * =====================================================================
     */
    public function compileToPromptBlock(array $artifact): string
    {
        return WritingContentPlan::promptBlock($artifact);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lập kế hoạch nội dung bằng brief + style summary
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Thực thi phân tích chủ đề — gọi AI structured JSON nghiêm ngặt.
     * =====================================================================
     * INPUT: profile, topic, targetTier, upstreamPromptBlocks (bỏ qua — bước đầu),
     *        overrides (temperature tuỳ chọn).
     * OUTPUT: array artifact {thesis, target_angle, outline, ai_run_id}.
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $profile, string $topic, string $targetTier, array $upstreamPromptBlocks, array $overrides = [],
     *
     * OUTPUT:
     * - array artifact theo phiên bản prompt
     *
     * SIDE EFFECT:
     * - Gọi AI và ghi log qua gateway.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi schema, brief, context và gateway có thể truyền lên. Không mở transaction.
     * =====================================================================
     */
    public function execute(
        WritingProfile $profile,
        string $topic,
        string $targetTier,
        array $upstreamPromptBlocks,
        array $overrides = [],
    ): array {
        return $this->executeV3($profile, $topic, $targetTier, $overrides);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lập dàn ý V3 bằng brief và style summary thực tế của profile
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: JSON Schema nghiêm ngặt (strict) cho phản hồi phân tích.
     * =====================================================================
     * INPUT: không. OUTPUT: array schema — required đủ 3 khóa chính,
     * additionalProperties:false ở mọi tầng.
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $profile, string $topic, string $tier, array $overrides
     *
     * OUTPUT:
     * - array kế hoạch đã validate, schema_version=3, fingerprint và metadata model/options
     *
     * SIDE EFFECT:
     * - Gọi gateway với temperature planner riêng, ghi AiRun/usage qua gateway.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException hoặc InvalidPipelineException từ context/đầu ra/kế hoạch; lỗi gateway truyền lên. Không mở transaction.
     * =====================================================================
     */
    private function executeV3(WritingProfile $profile, string $topic, string $tier, array $overrides): array
    {
        $brief = WritingBrief::fromInput($topic, $tier, $overrides);
        $snapshot = app(WritingStyleCompilerContract::class)->buildSnapshot(
            $profile,
            $tier,
            (int) ($overrides['target_sentences'] ?? 0),
            array_replace($overrides, ['brief' => $brief->data]),
        );
        $system = PromptPack::role('planner', $brief->data['content_type'] ?? null);
        $prompt = PromptPack::json(['brief' => $brief->data, 'style_summary' => $snapshot->styleSummary]);
        PromptPack::checkContext($system, $prompt);
        $options = ['temperature' => PromptPack::temperature('planner'), 'max_tokens' => (int) PromptPack::limit('max_output_tokens', 4096), 'timeout' => (int) config('writingstyle.pipeline.ai.topic_outline_timeout', 180)];
        $response = $this->ai->executeStructured(AiStructuredRequest::fromPrompt(
            $prompt,
            system: $system,
            schema: WritingContentPlan::schema(),
            options: $options,
            module: 'writingstyle',
            operation: 'sandbox_step_topic_outline_v3',
        ));
        PromptPack::requireComplete($response);
        $plan = WritingContentPlan::validate($response->data ?? [], $brief);

        return [
            ...$plan,
            'schema_version' => 3,
            'input_fingerprint' => WritingContentPlan::fingerprint($snapshot, $brief),
            // Đưa system_prompt của snapshot DNA vào artifact để FE/DB hiển thị đầy đủ
            // Ô 1/2/3 + sliders ngay ở bước lập dàn ý (trước đây chỉ trả provenance
            // khiến bản thử topic_outline báo "ô 3 chưa được kèm theo").
            'system_prompt' => $snapshot->systemPrompt,
            'ai_run_id' => $this->ai->lastRun()?->id,
            'provenance' => array_replace($snapshot->provenance, [
                'schema_version' => 3,
                'model' => $response->remoteModelId,
                'options' => $options,
            ]),
        ];
    }

}
