<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu ra của lệnh sinh ảnh AI.
 * =====================================================================
 *
 * Trả về từ `CoreAiGatewayContract::generateImage()`. `images` là mảng
 * các ảnh đã sinh — mỗi phần tử:
 *   ['mime' => 'image/png', 'data' => '<binary>' , 'width'=>, 'height'=>]
 * (binary raw, chưa lưu Media — caller đẩy qua UploadMediaAssetAction).
 * `revisedPrompt` là prompt provider tự điều chỉnh (DALL-E rewrite...).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : images binary[] + revisedPrompt? + usage + raw + model/provider.
 * - OUTPUT: readonly object → caller lưu MediaAsset hoặc xử lý tiếp.
 * =====================================================================
 */
final readonly class AiImageResponse
{
    /**
     * CHỨC NĂNG: khởi tạo response sinh ảnh.
     * INPUT: images (array<array{mime,data,width?,height?}>) + revisedPrompt?
     *        + usage + raw + remoteModelId + providerKey + latencyMs.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public array $images = [],
        public ?string $revisedPrompt = null,
        public array $usage = [],
        public array $raw = [],
        public ?string $remoteModelId = null,
        public ?string $providerKey = null,
        public ?int $latencyMs = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Số ảnh đã sinh.
     * INPUT: không. OUTPUT: int.
     * =====================================================================
     */
    public function count(): int
    {
        return count($this->images);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize ghi vào ai_runs.output_json (BỎ binary data —
     * chỉ giữ meta để tránh phình DB; ảnh thật đã lưu MediaAsset).
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toRunOutput(): array
    {
        $metas = array_map(
            static fn (array $img): array => [
                'mime' => $img['mime'] ?? null,
                'width' => $img['width'] ?? null,
                'height' => $img['height'] ?? null,
                'bytes' => isset($img['data']) ? strlen((string) $img['data']) : null,
            ],
            $this->images
        );

        return [
            'images' => $metas,
            'revised_prompt' => $this->revisedPrompt,
            'usage' => $this->usage,
            'remote_model_id' => $this->remoteModelId,
            'provider_key' => $this->providerKey,
            'latency_ms' => $this->latencyMs,
        ];
    }
}