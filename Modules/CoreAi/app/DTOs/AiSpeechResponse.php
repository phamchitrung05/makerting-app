<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu ra của lệnh chuyển văn bản thành giọng nói.
 * =====================================================================
 *
 * Trả về từ `CoreAiGatewayContract::synthesizeSpeech()`. `audio` là
 * binary nhị phân định dạng `mime` (vd audio/mpeg, audio/wav);
 * `timings` là mảng WordTimingDto[] để dựng phụ đề động .ass;
 * `durationMs` là độ dài thực tế của audio sinh ra.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : audio binary + mime + timings + durationMs + usage + raw.
 * - OUTPUT: readonly object → caller lưu file / render video.
 * =====================================================================
 */
final readonly class AiSpeechResponse
{
    /**
     * CHỨC NĂNG: khởi tạo response TTS.
     * INPUT: audio binary, mime, timings (WordTimingDto[]), durationMs,
     *        usage, raw, remoteModelId, providerKey, latencyMs.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $audio = '',
        public string $mime = 'audio/mpeg',
        public array $timings = [],
        public ?int $durationMs = null,
        public array $usage = [],
        public array $raw = [],
        public ?string $remoteModelId = null,
        public ?string $providerKey = null,
        public ?int $latencyMs = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Kích thước audio (bytes).
     * INPUT: không. OUTPUT: int.
     * =====================================================================
     */
    public function sizeBytes(): int
    {
        return strlen($this->audio);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize ghi vào ai_runs.output_json (BỎ binary audio —
     * chỉ giữ meta + timings; audio thật đã lưu MediaAsset/file).
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toRunOutput(): array
    {
        return [
            'mime' => $this->mime,
            'duration_ms' => $this->durationMs,
            'audio_bytes' => $this->sizeBytes(),
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