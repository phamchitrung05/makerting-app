<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu ra của lệnh chuyển âm thanh thành văn bản.
 * =====================================================================
 *
 * Trả về từ `CoreAiGatewayContract::transcribeAudio()`. `text` là toàn
 * bộ bản ghi; `timings` là mảng WordTimingDto[] có mốc thời gian;
 * `language` là mã ISO provider detect được (có thể khác request khi
 * để auto).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : text + timings + language + durationMs + usage + raw.
 * - OUTPUT: readonly object → caller lưu transcript / phụ đề.
 * =====================================================================
 */
final readonly class AiTranscribeResponse
{
    /**
     * CHỨC NĂNG: khởi tạo response STT.
     * INPUT: text, timings (WordTimingDto[]), language?, durationMs?,
     *        usage, raw, remoteModelId, providerKey, latencyMs.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $text = '',
        public array $timings = [],
        public ?string $language = null,
        public ?int $durationMs = null,
        public array $usage = [],
        public array $raw = [],
        public ?string $remoteModelId = null,
        public ?string $providerKey = null,
        public ?int $latencyMs = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Có dữ liệu timing không.
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function hasTimings(): bool
    {
        return $this->timings !== [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize ghi vào ai_runs.output_json.
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toRunOutput(): array
    {
        return [
            'text' => $this->text,
            'language' => $this->language,
            'duration_ms' => $this->durationMs,
            'timings' => array_map(
                static fn (WordTimingDto $t): array => $t->toArray(),
                $this->timings
            ),
            'usage' => $this->usage,
            'remote_model_id' => $this->remoteModelId,
            'provider_key' => $this->providerKey,
            'latency_ms' => $this->latencyMs,
        ];
    }
}