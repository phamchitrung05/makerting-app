<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu vào cho lệnh chuyển âm thanh thành văn bản.
 * =====================================================================
 *
 * Truyền qua `CoreAiGatewayContract::transcribeAudio()`. `audio` là binary
 * nhị phân (hoặc đường dẫn tạm) + `mime` để provider nhận đúng codec;
 * `language` là mã ISO ('vi','en'... — null = auto-detect); `withTimings`
 * = true để lấy WordTimingDto[] phục vụ phụ đề.
 *
 * `audioPath` cho phép truyền file lớn qua đường dẫn thay vì nhồi vào
 * memory — driver đọc trực tiếp khi gọi HTTP.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : audio|audioPath + mime, language?, withTimings, options?,
 *          providerKey?, modelId?, module, operation.
 * - OUTPUT: readonly object → driver chuyển thành request STT.
 * =====================================================================
 */
final readonly class AiTranscribeRequest
{
    /**
     * CHỨC NĂNG: khởi tạo request STT.
     * INPUT: audio binary|audioPath, mime, language?, withTimings,
     *        options?, providerKey?, modelId?, module, operation.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $audio = '',
        public ?string $audioPath = null,
        public string $mime = 'audio/mpeg',
        public ?string $language = null,
        public bool $withTimings = true,
        public array $options = [],
        public ?string $providerKey = null,
        public ?string $modelId = null,
        public string $module = 'core',
        public string $operation = 'stt',
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Nguồn audio là đường dẫn file (không phải binary nhúng).
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function usesFilePath(): bool
    {
        return $this->audioPath !== null && $this->audio === '';
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Capability cần thiết (luôn stt).
     * INPUT: không. OUTPUT: string CAP_*.
     * =====================================================================
     */
    public function requiredCapability(): string
    {
        return \Modules\CoreAi\Models\AiModel::CAP_STT;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize ghi vào ai_runs.input_json (KHÔNG nhúng binary —
     * chỉ lưu meta; audio thật đi qua file/body request).
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toRunInput(): array
    {
        return [
            'mime' => $this->mime,
            'language' => $this->language,
            'audio_bytes' => $this->audio !== '' ? strlen($this->audio) : null,
            'audio_path' => $this->audioPath,
            'with_timings' => $this->withTimings,
            'options' => $this->options,
            'provider_key' => $this->providerKey,
            'model_id' => $this->modelId,
            'module' => $this->module,
            'operation' => $this->operation,
        ];
    }
}