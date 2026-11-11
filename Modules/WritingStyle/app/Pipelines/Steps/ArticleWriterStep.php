<?php

namespace Modules\WritingStyle\Pipelines\Steps;

use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\SandboxResultDto;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;
use Modules\WritingStyle\Services\PromptPack;
use Modules\WritingStyle\Services\WritingQualityReport;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Pipelines\Contracts\SandboxStepContract;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bước terminal áp dụng văn phong và kế hoạch để sinh bài hoàn chỉnh.
 * =====================================================================
 *
 * Stage 100 — bước chấp bút kết thúc chuỗi (is_terminal = true).
 * Dùng `WritingProfileSnapshotService::buildSnapshot` để tạo System Prompt
 * DNA (features/Ô 1/Ô 2/Ô 3 + 5 sliders Chất văn + 8 sliders Sắc thái), rồi
 * ghép brief + content_plan (compileToPromptBlock của mọi step đứng trước —
 * VD dàn ý từ `topic_outline`, và sau này là bất kỳ `section_expander`/
 * `hook_analyzer` nào được chèn vào giữa mà không cần sửa code này).
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Khởi tạo thuộc tính hoặc nhận các dependency của ArticleWriterStep.
 * - key(): Trả mã định danh bước pipeline.
 * - stage(): Trả thứ tự tầng thực thi để kiểm tra topology.
 * - isTerminal(): Cho biết bước có kết thúc pipeline hay không.
 * - intrinsicDependsOn(): Khai báo các phụ thuộc bắt buộc bên trong step.
 * - meta(): Cấp nhãn và metadata step để API options dựng giao diện tiến trình.
 * - validateArtifact(): Artifact của bước viết bài (kết quả cuối — FE chỉ hiển thị).
 * - compileToPromptBlock(): Terminal step không compile prompt block cho bước sau.
 * - execute(): Chuẩn hóa brief, biên dịch DNA, ghép kế hoạch và gọi writer.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Profile, topic, tier, upstream prompt blocks và overrides.
 * - OUTPUT: Artifact văn bản, snapshot, AI run ID và báo cáo chất lượng.
 *
 * BỐI CẢNH SỬ DỤNG: Runner gọi ở cuối pipeline; sandbox trực tiếp cũng dùng cùng bước này.
 * =====================================================================
 */
class ArticleWriterStep implements SandboxStepContract
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo thuộc tính hoặc nhận các dependency của ArticleWriterStep
     * =====================================================================
     *
     * INPUT:
     * - private readonly CoreAiGatewayContract $ai, private readonly WritingStyleCompilerContract $compiler,
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
        private readonly WritingStyleCompilerContract $compiler,
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
     * - string article_writer
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
        return 'article_writer';
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
     * - int 100
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
        return 100;
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
     * - bool true
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
        return true;
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
            'label'        => 'Bước 2: Áp dụng DNA & Viết toàn bài',
            'description'  => 'Áp dụng toàn bộ DNA văn phong + kết quả phân tích các bước trước để sinh bài viết hoàn chỉnh.',
            'icon'         => 'tabler-writing',
            'stage'        => $this->stage(),
            'is_terminal'  => $this->isTerminal(),
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Artifact của bước viết bài (kết quả cuối — FE chỉ hiển thị).
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Artifact của bước viết bài (kết quả cuối — FE chỉ hiển thị).
     * INPUT: array $artifact. OUTPUT: bool — hợp lệ khi có 'text' không rỗng.
     *
     * INPUT:
     * - array $artifact
     *
     * OUTPUT:
     * - bool theo hợp đồng của method.
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function validateArtifact(array $artifact): bool
    {
        return trim((string) ($artifact['text'] ?? '')) !== '';
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Terminal step không compile prompt block cho bước sau.
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Terminal step không compile prompt block cho bước sau.
     * INPUT: artifact. OUTPUT: '' — không có bước sau terminal.
     *
     * INPUT:
     * - array $artifact
     *
     * OUTPUT:
     * - string theo hợp đồng của method.
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function compileToPromptBlock(array $artifact): string
    {
        return '';
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa brief, biên dịch DNA, ghép kế hoạch và gọi writer theo V2/V3
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Viết bài hoàn chỉnh theo DNA + PromptBlocks của các bước trước.
     * =====================================================================
     * INPUT: profile, topic, targetTier, upstreamPromptBlocks (đã compile sẵn),
     *        overrides (tone_overrides/style/voice/temperature/target_sentences).
     * OUTPUT: array — {text, target_tier, snapshot, ai_run_id}.
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $profile, string $topic, string $targetTier, array $upstreamPromptBlocks, array $overrides = [],
     *
     * OUTPUT:
     * - array artifact từ SandboxResultDto
     *
     * SIDE EFFECT:
     * - Gọi gateway ghi AiRun/usage; V3 kiểm tra context và lý do hoàn tất, sau đó kiểm tra bài bằng quy tắc.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException cho brief/context; lỗi gateway; InvalidPipelineException khi V3 bị cắt hoặc trả rỗng. Không mở transaction hay tự lưu sample.
     * =====================================================================
     */
    public function execute(
        WritingProfile $profile,
        string $topic,
        string $targetTier,
        array $upstreamPromptBlocks,
        array $overrides = [],
    ): array {
        // Brief luôn bắt buộc trong V3 (không còn nhánh không-brief của V2).
        $brief = WritingBrief::fromInput($topic, $targetTier, $overrides);
        $overrides['brief'] = $brief->data;

        // 1. Snapshot DNA (rules theo tier + Ô 1 + Ô 2 + 5 sliders + 8 sliders).
        $targetSentences = (int) ($overrides['target_sentences'] ?? 0);
        $snapshot = $this->compiler->buildSnapshot(
            $profile,
            $targetTier,
            $targetSentences,
            $overrides,
        );

        // 2. Temperature: runtime override > profile > pack mặc định.
        $temperature = isset($overrides['temperature'])
            ? (float) $overrides['temperature']
            : ($profile->temperature !== null
                ? (float) $profile->temperature
                : PromptPack::temperature('writer'));

        // 3. Ghép prompt writer: system từ pack + brief + content_plan của các
        //    step trước (compileToPromptBlock của topic_outline).
        $userPrompt = PromptPack::role('writer', $brief->data['content_type'] ?? null)
            ."\n\n".PromptPack::json([
                'brief' => $brief->data,
                'content_plan' => array_column($upstreamPromptBlocks, 'block'),
            ]);
        $options = ['temperature' => $temperature];
        $options['max_tokens'] = (int) PromptPack::limit('max_output_tokens', 4096);
        PromptPack::checkContext($snapshot->systemPrompt, $userPrompt);

        $resp = $this->ai->executeStructured(
            AiStructuredRequest::fromPrompt(
                $userPrompt,
                system: $snapshot->systemPrompt,
                schema: null,
                options: $options,
                module: 'writingstyle',
                operation: 'sandbox_step_article_writer_v3',
            )
        );

        $text = trim($resp->text);
        PromptPack::requireComplete($resp);
        if ($text === '') {
            throw new InvalidPipelineException('AI chưa trả nội dung bài viết. Hãy chạy lại bước viết.');
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
                'provenance'    => array_replace($snapshot->provenance, [
                    'prompt_version' => 3,
                    'model' => $resp->remoteModelId,
                    'provider' => $resp->providerKey,
                    'options' => $options,
                    'brief_fingerprint' => hash('sha256', PromptPack::json($brief->data)),
                    'plan_fingerprint' => $upstreamPromptBlocks === [] ? null : hash('sha256', PromptPack::json($upstreamPromptBlocks)),
                ]),
            ],
            aiRunId: $this->ai->lastRun()?->id,
            qualityReport: WritingQualityReport::inspect($text, $brief, $profile->style_analysis_json['source_specific_items'] ?? []),
        );

        return $dto->toArray();
    }

}
