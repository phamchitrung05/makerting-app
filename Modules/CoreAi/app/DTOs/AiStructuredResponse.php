<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu ra của lệnh AI Text/Structured/Vision.
 * =====================================================================
 *
 * Trả về từ `CoreAiGatewayContract::executeStructured()`. `text` là nội
 * dung thô model sinh; `data` là mảng đã json_decode + kiểm định schema
 * (chỉ có khi request yêu cầu structured output và output hợp lệ).
 * `usage`/`raw` mang thông tin token + response gốc phục vụ diagnostics.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : text/data từ driver + usage + raw + model/provider đã dùng.
 * - OUTPUT: readonly object → gateway/caller đọc kết quả.
 * =====================================================================
 */
final readonly class AiStructuredResponse
{
    /**
     * CHỨC NĂNG: khởi tạo response structured.
     * INPUT: text, ?data, usage, raw, remoteModelId, providerKey, latencyMs.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $text,
        public ?array $data = null,
        public array $usage = [],
        public array $raw = [],
        public ?string $remoteModelId = null,
        public ?string $providerKey = null,
        public ?int $latencyMs = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Response có dữ liệu structured đã parse không.
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function hasStructuredData(): bool
    {
        return $this->data !== null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tổng số token đã dùng (input+output) — từ usage.
     * INPUT: không. OUTPUT: int.
     * =====================================================================
     */
    public function totalTokens(): int
    {
        $in = (int) ($this->usage['input_tokens'] ?? $this->usage['prompt_tokens'] ?? 0);
        $out = (int) ($this->usage['output_tokens'] ?? $this->usage['completion_tokens'] ?? 0);

        return $in + $out;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize ghi vào ai_runs.output_json.
     * INPUT: không. OUTPUT: array — không chứa raw blob nặng.
     * =====================================================================
     */
    public function toRunOutput(): array
    {
        return [
            'text' => $this->text,
            'data' => $this->data,
            'usage' => $this->usage,
            'remote_model_id' => $this->remoteModelId,
            'provider_key' => $this->providerKey,
            'latency_ms' => $this->latencyMs,
        ];
    }
}