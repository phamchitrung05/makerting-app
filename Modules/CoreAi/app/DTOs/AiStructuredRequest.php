<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO đầu vào cho lệnh AI Text/Structured/Vision.
 * =====================================================================
 *
 * Truyền qua `CoreAiGatewayContract::executeStructured()`. Một request gồm
 * một chuỗi tin nhắn (system/user/assistant...) và/hoặc `prompt`, kèm
 * `schema` JSON Schema để kiểm định đầu ra khi cần structured output.
 *
 * `attachments` là mảng ảnh/base64 cho Vision: mỗi phần tử
 * ['mime' => 'image/png', 'data' => '<base64>'|'url' => 'https://...'].
 * `options` chứa tham số riêng driver (temperature, max_tokens, top_p...).
 *
 * `providerKey`/`modelId` cho phép caller chỉ định rõ; để null →
 * ModelResolver tự chọn theo capability cần thiết.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : messages/prompt, schema?, attachments?, options?, provider/model?
 * - OUTPUT: readonly object → driver chuyển thành request HTTP.
 * =====================================================================
 */
final readonly class AiStructuredRequest
{
    /**
     * CHỨC NĂNG: khởi tạo request structured/vision.
     * INPUT: messages (array<array{role,content}>), prompt?, schema?,
     *        attachments?, options?, providerKey?, modelId?, module, operation.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public array $messages = [],
        public ?string $prompt = null,
        public ?array $schema = null,
        public array $attachments = [],
        public array $options = [],
        public ?string $providerKey = null,
        public ?string $modelId = null,
        public string $module = 'core',
        public string $operation = 'structured',
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo request từ prompt đơn giản (system+user tùy chọn).
     * INPUT: string prompt, ?system, ?schema, ?options.
     * OUTPUT: AiStructuredRequest.
     * =====================================================================
     */
    public static function fromPrompt(
        string $prompt,
        ?string $system = null,
        ?array $schema = null,
        array $options = [],
        string $module = 'core',
        string $operation = 'structured',
    ): self {
        $messages = [];
        if ($system !== null && $system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        return new self(
            messages: $messages,
            schema: $schema,
            options: $options,
            module: $module,
            operation: $operation,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Request có yêu cầu structured output không.
     * INPUT: không. OUTPUT: bool — true khi schema được cung cấp.
     * =====================================================================
     */
    public function requiresStructuredOutput(): bool
    {
        return $this->schema !== null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Request có đính kèm ảnh (Vision) không.
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function hasAttachments(): bool
    {
        return $this->attachments !== [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Capability tối thiểu model cần có để chạy request này.
     * =====================================================================
     * INPUT: không. OUTPUT: string — một trong AiModel::CAP_*.
     * =====================================================================
     */
    public function requiredCapability(): string
    {
        if ($this->requiresStructuredOutput()) {
            return \Modules\CoreAi\Models\AiModel::CAP_STRUCTURED_OUTPUT;
        }
        if ($this->hasAttachments()) {
            return \Modules\CoreAi\Models\AiModel::CAP_VISION;
        }

        return \Modules\CoreAi\Models\AiModel::CAP_TEXT_GENERATION;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize thành mảng lưu vào ai_runs.input_json.
     * INPUT: không. OUTPUT: array — không chứa binary/blob.
     * =====================================================================
     */
    public function toRunInput(): array
    {
        return [
            'messages' => $this->messages,
            'prompt' => $this->prompt,
            'schema' => $this->schema,
            'attachments_count' => count($this->attachments),
            'options' => $this->options,
            'provider_key' => $this->providerKey,
            'model_id' => $this->modelId,
            'module' => $this->module,
            'operation' => $this->operation,
        ];
    }
}