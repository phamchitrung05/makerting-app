<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu vào cho lệnh chuyển văn bản thành giọng nói.
 * =====================================================================
 *
 * Truyền qua `CoreAiGatewayContract::synthesizeSpeech()`. `text` là nội
 * dung đọc; `voice` là giọng (alloy, echo, shimmer... tùy provider);
 * `format` là định dạng audio mong muốn ('mp3','wav','ogg','pcm'...);
 * `speed` là hệ số tốc độ đọc (0.25..4.0, mặc định 1.0).
 *
 * `withTimings` = true khi cần WordTimingDto[] để dựng phụ đề — driver
 * sẽ cố gắng lấy mốc thời gian từng từ/câu từ provider.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : text, voice?, format, speed, withTimings, options?, providerKey?,
 *          modelId?, module, operation.
 * - OUTPUT: readonly object → driver chuyển thành request TTS.
 * =====================================================================
 */
final readonly class AiSpeechRequest
{
    /**
     * CHỨC NĂNG: khởi tạo request TTS.
     * INPUT: text, voice?, format, speed, withTimings, options?, providerKey?,
     *        modelId?, module, operation.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $text,
        public ?string $voice = null,
        public string $format = 'mp3',
        public float $speed = 1.0,
        public bool $withTimings = false,
        public array $options = [],
        public ?string $providerKey = null,
        public ?string $modelId = null,
        public string $module = 'core',
        public string $operation = 'tts',
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Capability cần thiết (luôn tts).
     * INPUT: không. OUTPUT: string CAP_*.
     * =====================================================================
     */
    public function requiredCapability(): string
    {
        return \Modules\CoreAi\Models\AiModel::CAP_TTS;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize ghi vào ai_runs.input_json (giữ text vì cần
     * audit/retry; text không chứa secret).
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toRunInput(): array
    {
        return [
            'text' => $this->text,
            'voice' => $this->voice,
            'format' => $this->format,
            'speed' => $this->speed,
            'with_timings' => $this->withTimings,
            'options' => $this->options,
            'provider_key' => $this->providerKey,
            'model_id' => $this->modelId,
            'module' => $this->module,
            'operation' => $this->operation,
        ];
    }
}