<?php

namespace Modules\VisualStyle\Services;

use Modules\Media\Contracts\MediaUsageServiceContract;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\VisualStyle\DTOs\ImageDnaDto;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Vision Analyzer — giải mã ảnh tham chiếu thành DNA 5 nhóm.
 * =====================================================================
 *
 * Nhận `media_asset_id` của ảnh tham chiếu:
 *   1. Đọc stream qua `MediaUsageServiceContract::openReadStream` (không
 *      ghép đường dẫn disk — chuẩn giao tiếp liên module §4.1.3).
 *   2. Base64 hóa thành attachment Vision.
 *   3. Gọi `CoreAiGatewayContract::executeStructured` với system prompt từ
 *      `VisualPromptPack::role('analyzer')` + JSON Schema strict của
 *      `VisualStyleDnaDefinition::schema()`.
 *   4. Kiểm tra hoàn tất đầu ra (`requireComplete`), chuẩn hóa DNA
 *      (`normalizeDna`), lọc taxonomy_suggestion theo scope 'visual_style'.
 *   5. Trả `ImageDnaDto` + diagnostics (model, tokens, ai_run_id).
 *
 * Service chạy ĐỒNG BỘ — queue job (T4) mới dispatch nó. KHÔNG tự ghi
 * `visual_styles` (caller quyết định), KHÔNG gọi AI provider trực tiếp.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - analyzeMedia(): Analyze từ media_asset_id → ImageDnaDto + diagnostics.
 * - analyzeDna(): Analyze từ attachment có sẵn (đơn vị test/nội bộ).
 * - buildAttachment(): stream → base64 attachment chuẩn Vision.
 * - filterTaxonomySuggestion(): lọc tag/category thuộc scope visual_style.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : MediaAsset id (hoặc attachment), channel_group_id tùy chọn.
 * - OUTPUT: ImageDnaDto + diagnostics array — không ghi DB.
 *
 * BỐI CẢNH SỬ DỤNG: AnalyzeVisualStyleJob gọi analyzeMedia(); test mock
 * gateway gọi analyzeDna().
 * =====================================================================
 */
class VisualStyleAnalyzerService
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo service với gateway AI và media usage service
     * =====================================================================
     *
     * INPUT:
     * - CoreAiGatewayContract $ai — cổng gọi AI duy nhất (module 8).
     * - MediaUsageServiceContract $media — đọc stream file tham chiếu.
     *
     * OUTPUT:
     * - Instance giữ các dependency theo chữ ký.
     *
     * SIDE EFFECT:
     * - Gán thuộc tính trong bộ nhớ; không gọi AI hoặc ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không có.
     * =====================================================================
     */
    public function __construct(
        protected readonly CoreAiGatewayContract $ai,
        protected readonly MediaUsageServiceContract $media,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Giải mã ảnh tham chiếu (media_asset_id) thành DNA 5 nhóm
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * 1. Đọc stream file ảnh qua openReadStream → base64 attachment.
     * 2. Gọi analyzeDna() — phần lõi dùng chung với test.
     * 3. Đọc max_reference_bytes từ config('visualstyle.analysis') để chặn
     *    ảnh quá lớn trước khi base64 (bảo vệ context + chi phí AI).
     * =====================================================================
     *
     * INPUT:
     * - int $mediaAssetId — MediaAsset ảnh tham chiếu (slot visual_style.reference).
     * - ?int $channelGroupId — ngữ cảnh nhóm kênh (chỉ dùng cho audit).
     *
     * OUTPUT:
     * - array{dna: ImageDnaDto, diagnostics: array{ai_run_id, model, tokens, provider}}
     *
     * SIDE EFFECT:
     * - Đọc file media; gọi gateway AI (ghi AiRun/AiUsageLog phía CoreAi).
     *
     * EXCEPTION/TRANSACTION:
     * - RuntimeException khi asset không có file/disk đọc lỗi.
     * - AiGatewayException/AiValidationException khi provider lỗi hoặc JSON
     *   không khớp schema. Không mở transaction.
     * =====================================================================
     */
    public function analyzeMedia(int $mediaAssetId, ?int $channelGroupId = null): array
    {
        $attachment = $this->buildAttachment($mediaAssetId);

        return $this->analyzeDna($attachment, $channelGroupId);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lõi giải mã — gọi Vision AI qua gateway với schema strict
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * - System prompt: VisualPromptPack::role('analyzer') (temperature 0.2).
     * - User prompt: JSON-encoded task description kèm channelGroupId ngữ cảnh.
     * - Schema: VisualStyleDnaDefinition::schema() — 5 nhóm + phụ trợ.
     * - requireComplete: chặn output bị cắt coi như kết quả.
     * - normalizeDna: lọc key lạ/giá trị sai enum → chỉ lưu phần hợp lệ.
     * - filterTaxonomySuggestion: chỉ nhận tag/category có thật thuộc scope
     *   'visual_style' (không tự tạo taxonomy).
     * =====================================================================
     *
     * INPUT:
     * - array $attachment — ['mime' => ..., 'data' => base64].
     * - ?int $channelGroupId — ngữ cảnh nhóm kênh.
     *
     * OUTPUT:
     * - array{dna: ImageDnaDto, diagnostics: array} như analyzeMedia.
     *
     * SIDE EFFECT:
     * - Gọi gateway (ghi AiRun + AiUsageLog phía CoreAi).
     *
     * EXCEPTION/TRANSACTION:
     * - AiGatewayException khi bị cắt/truncated_output; AiValidationException
     *   khi JSON không khớp schema. Không mở transaction.
     * =====================================================================
     */
    public function analyzeDna(array $attachment, ?int $channelGroupId = null): array
    {
        $system = VisualPromptPack::role('analyzer');
        $temperature = VisualPromptPack::temperature('analyzer');

        $userPayload = [
            'task'            => 'analyze_reference_image',
            'channel_group_id'=> $channelGroupId,
            'instructions'    => 'Điền đầy đủ 5 nhóm DNA của ảnh này theo schema. Trường không đoán được trả null.',
        ];
        $userPrompt = VisualPromptPack::json($userPayload);

        VisualPromptPack::checkContext($system, $userPrompt);

        $request = new AiStructuredRequest(
            messages: [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            schema: VisualStyleDnaDefinition::schema(),
            attachments: [$attachment],
            options: ['temperature' => $temperature],
            module: 'visual-style',
            operation: 'analyze-dna',
        );

        /** @var AiStructuredResponse $response */
        $response = $this->ai->executeStructured($request);
        VisualPromptPack::requireComplete($response);

        $raw = is_array($response->data ?? null) ? $response->data : [];
        $normalized = VisualStyleDnaDefinition::normalizeDna($raw);
        $normalized['taxonomy_suggestion'] = $this->filterTaxonomySuggestion(
            is_array($normalized['taxonomy_suggestion'] ?? null) ? $normalized['taxonomy_suggestion'] : [],
        );

        $diagnostics = [
            'ai_run_id' => $this->ai->lastRun()?->id,
            'model'     => $response->remoteModelId,
            'provider'  => $response->providerKey,
            'tokens'    => [
                'input'  => $response->usage['input_tokens'] ?? $response->usage['prompt_tokens'] ?? null,
                'output' => $response->usage['output_tokens'] ?? $response->usage['completion_tokens'] ?? null,
            ],
            'latency_ms' => $response->latencyMs,
        ];

        return [
            'dna'         => ImageDnaDto::fromArray($normalized),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đọc stream MediaAsset → base64 attachment chuẩn Vision
     * =====================================================================
     *
     * Chi tiết triển khai:
     * - openReadStream trả PHP stream (caller phải fclose) — đọc bằng
     *   stream_get_contents, kiểm tra max_reference_bytes từ config để chặn
     *   ảnh quá lớn TRƯỚC khi base64.
     * - Mime lấy từ cột `type` của Spatie Media (mime thật của file).
     * =====================================================================
     *
     * INPUT:
     * - int $mediaAssetId.
     *
     * OUTPUT:
     * - array{mime: string, data: string} — attachment base64.
     *
     * SIDE EFFECT:
     * - Đọc file vật lý qua Storage (mở + đóng stream).
     *
     * EXCEPTION/TRANSACTION:
     * - RuntimeException khi asset không có file / vượt max bytes / đọc lỗi.
     * - Không mở transaction.
     * =====================================================================
     */
    protected function buildAttachment(int $mediaAssetId): array
    {
        $maxBytes = (int) config('visualstyle.analysis.max_reference_bytes', 8 * 1024 * 1024);

        $stream = $this->media->openReadStream($mediaAssetId);
        $binary = '';

        try {
            $binary = (string) stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        if ($binary === '') {
            throw new \RuntimeException("MediaAsset [{$mediaAssetId}] rỗng hoặc không đọc được nội dung.");
        }

        if (strlen($binary) > $maxBytes) {
            throw new \RuntimeException(sprintf(
                'Ảnh tham chiếu [%d] nặng %s, vượt giới hạn %s. Hãy nén lại trước khi giải mã.',
                $mediaAssetId,
                number_format(strlen($binary) / 1024, 0).' KB',
                number_format($maxBytes / 1024 / 1024, 1).' MB',
            ));
        }

        // Mime thật của file — Spatie media row đính kèm asset.
        $mime = 'image/png';
        try {
            $asset = \Modules\Media\Models\MediaAsset::findOrFail($mediaAssetId);
            $media = $asset->mediaFile();
            if ($media && ! empty($media->mime_type)) {
                $mime = $media->mime_type;
            }
        } catch (\Throwable) {
            // fallback giữ image/png — driver tự xử lý mime lạ.
        }

        return [
            'mime' => $mime,
            'data' => base64_encode($binary),
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lọc taxonomy_suggestion — chỉ giữ tag/category có thật
     * thuộc scope 'visual_style' hoặc dùng chung.
     * =====================================================================
     *
     * Chi tiết triển khai:
     * - AI có thể đề xuất tên chưa tồn tại — KHÔNG tự tạo; chỉ giữ tên khớp
     *   categories.name hoặc tags.name (scope='visual_style' hoặc scope NULL).
     * - So sánh lowercase + trim để khớp mềm.
     * =====================================================================
     *
     * INPUT:
     * - array $suggestions — mảng tên do AI đề xuất.
     *
     * OUTPUT:
     * - string[] — tối đa 5 tên hợp lệ (nguyên văn trong DB).
     *
     * SIDE EFFECT:
     * - SELECT categories + tags (2 truy vấn nhỏ).
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    protected function filterTaxonomySuggestion(array $suggestions): array
    {
        $suggestions = array_values(array_filter(
            array_map(static fn ($t) => is_string($t) ? trim($t) : '', $suggestions),
            static fn ($t) => $t !== '',
        ));

        if ($suggestions === []) {
            return [];
        }

        $lower = array_map('mb_strtolower', $suggestions);

        // Category: scope 'visual_style' + scope dùng chung (NULL).
        $categoryNames = \Modules\Taxonomy\Models\Category::query()
            ->where(function ($q) {
                $q->whereNull('scope')->orWhere('scope', 'visual_style');
            })
            ->get(['name'])
            ->pluck('name')
            ->map(static fn ($n) => mb_strtolower($n))
            ->all();

        // Tag: scope 'visual_style' + scope dùng chung (NULL).
        $tagNames = \Modules\Taxonomy\Models\Tag::query()
            ->where(function ($q) {
                $q->whereNull('scope')->orWhere('scope', 'visual_style');
            })
            ->get(['name'])
            ->pluck('name')
            ->map(static fn ($n) => mb_strtolower($n))
            ->all();

        $known = array_flip(array_merge($categoryNames, $tagNames));

        $kept = [];
        foreach ($lower as $i => $low) {
            if (isset($known[$low]) && ! in_array($suggestions[$i], $kept, true)) {
                $kept[] = $suggestions[$i];
            }
            if (count($kept) >= 5) {
                break;
            }
        }

        return $kept;
    }
}
