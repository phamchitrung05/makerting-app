<?php

namespace Modules\CoreAi\Drivers;

use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiImageResponse;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\DTOs\AiSpeechRequest;
use Modules\CoreAi\DTOs\AiSpeechResponse;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\DTOs\AiTranscribeRequest;
use Modules\CoreAi\DTOs\AiTranscribeResponse;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Services\JsonSchemaValidator;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Driver Anthropic (api.anthropic.com + tương thích).
 * =====================================================================
 *
 * Triển khai op:
 * - structured(): POST /v1/messages — system block riêng + messages
 *   [{role,content}] với content có thể là parts (text + image base64
 *   cho vision). Khi request có schema → dùng `tools` + `tool_choice`
 *   ép model trả đúng JSON theo schema (structured output chuẩn
 *   Anthropic).
 * - image()/speech()/transcribe(): KHÔNG hỗ trợ (Anthropic không có
 *   image generation/TTS/STT) → ném AiGatewayException::unsupported().
 *
 * Auth: `x-api-key: <key>` + `anthropic-version: 2023-06-01` +
 * `anthropic-beta: tools-2024-05-16` (tool_use structured).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - key()/ops()/headers() : định danh + op + auth headers.
 * - test()                : GET /v1/models ping nhẹ.
 * - structured()          : /v1/messages + tool_use cho schema.
 * - buildMessages()       : tách system ra riêng + map attachments.
 * - buildTool()           : JSON Schema → tool input_schema.
 * - parseOutput()         : response content[] → text + tool_use input.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AiResolvedModelDto + Request DTO.
 * - OUTPUT: Response DTO chuẩn; AiGatewayException khi lỗi/unsupported.
 * =====================================================================
 */
class AnthropicDriver extends AbstractAiDriver
{
    /** INPUT: không. OUTPUT: 'anthropic'. */
    public function key(): string
    {
        return 'anthropic';
    }

    /** INPUT: không. OUTPUT: chỉ structured (Anthropic không làm ảnh/audio). */
    protected function ops(): array
    {
        return ['structured'];
    }

    /** INPUT: apiKey. OUTPUT: headers x-api-key + version + beta tools. */
    protected function headers(string $apiKey): array
    {
        return $this->client()->buildAuthHeaders('x-api-key', $apiKey, '', [
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'tools-2024-05-16',
        ]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ping kết nối — GET /v1/models.
     * =====================================================================
     * INPUT: resolved. OUTPUT: array{ok,latency_ms,message,models?}.
     * =====================================================================
     */
    public function test(AiResolvedModelDto $resolved): array
    {
        $start = microtime(true);
        try {
            $res = $this->client()->request(
                $resolved->provider,
                'GET',
                $this->url($resolved->provider, 'v1/models'),
                $this->headers($resolved->apiKey),
                null,
                ['timeout' => 15]
            );
            $data = json_decode($res->body, true);
            $count = is_array($data['data'] ?? null) ? count($data['data']) : null;
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'ok' => true,
                'latency_ms' => $ms,
                'message' => 'Kết nối thành công' . ($count !== null ? " — {$count} models" : ''),
                'models' => is_array($data['data'] ?? null) ? array_column($data['data'], 'id') : [],
            ];
        } catch (AiGatewayException $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'ok' => false,
                'latency_ms' => $ms,
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode(),
            ];
        }
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Text/Vision/Structured qua /v1/messages.
     * =====================================================================
     * INPUT: resolved + request. OUTPUT: AiStructuredResponse.
     * =====================================================================
     */
    public function structured(AiResolvedModelDto $resolved, AiStructuredRequest $request): AiStructuredResponse
    {
        return $this->withKeyRotation($resolved, function (AiResolvedModelDto $r) use ($request) {
            [$system, $messages] = $this->buildMessages($request);

            $payload = [
                'model' => $r->model->remote_model_id,
                'max_tokens' => (int) ($request->options['max_tokens'] ?? 4096),
                'messages' => $messages,
            ];
            if ($system !== null && $system !== '') {
                $payload['system'] = $system;
            }

            $toolName = null;
            if ($request->requiresStructuredOutput()) {
                $toolName = 'structured_output';
                $payload['tools'] = [$this->buildTool($toolName, $request->schema)];
                $payload['tool_choice'] = ['type' => 'tool', 'name' => $toolName];
            }

            foreach ($request->options as $k => $v) {
                if (! in_array($k, ['model', 'messages', 'system', 'tools', 'tool_choice', 'max_tokens'], true)) {
                    $payload[$k] = $v;
                }
            }

            $data = $this->client()->requestJson(
                $r->provider,
                $this->url($r->provider, 'v1/messages'),
                $payload,
                $this->headers($r->apiKey),
                ['keyIndex' => $r->keyIndex]
            );

            [$text, $parsed] = $this->parseOutput($data, $toolName);

            if ($request->requiresStructuredOutput()) {
                if (! is_array($parsed)) {
                    throw AiGatewayException::validation(
                        'model khong tra ve structured output',
                        ['provider' => $r->provider->key, 'model' => $r->model->remote_model_id]
                    );
                }
                app(JsonSchemaValidator::class)->validate($parsed, $request->schema);
            }

            return new AiStructuredResponse(
                text: $text,
                data: $parsed,
                usage: $data['usage'] ?? [],
                raw: $data,
                remoteModelId: $r->model->remote_model_id,
                providerKey: $r->provider->key,
            );
        });
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Tách system ra riêng + map messages + vision parts.
     * INPUT: request. OUTPUT: [?string system, array messages].
     * =====================================================================
     */
    protected function buildMessages(AiStructuredRequest $request): array
    {
        $system = null;
        $messages = [];

        if ($request->messages !== []) {
            foreach ($request->messages as $msg) {
                $role = $msg['role'] ?? 'user';
                $content = $msg['content'] ?? '';
                if ($role === 'system') {
                    $system = ($system ?? '') . (is_string($content) ? $content : json_encode($content));
                    continue;
                }
                $parts = $this->buildContentParts($content, $request->attachments);
                $messages[] = ['role' => $role === 'assistant' ? 'assistant' : 'user', 'content' => $parts];
            }
        } else {
            $parts = $this->buildContentParts($request->prompt ?? '', $request->attachments);
            $messages[] = ['role' => 'user', 'content' => $parts];
        }

        return [$system, $messages];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Build content[] Anthropic — text + image parts.
     * INPUT: string|array content + attachments. OUTPUT: array parts.
     * =====================================================================
     */
    protected function buildContentParts(string|array $content, array $attachments): array
    {
        $parts = [];
        $text = is_string($content) ? $content : (is_array($content) ? ($content['text'] ?? json_encode($content)) : '');
        if ($text !== '') {
            $parts[] = ['type' => 'text', 'text' => $text];
        }

        foreach ($attachments as $att) {
            if (isset($att['data'])) {
                $parts[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $att['mime'] ?? 'image/png',
                        'data' => is_string($att['data']) && str_starts_with($att['data'], 'data:')
                            ? substr($att['data'], strpos($att['data'], ',') + 1)
                            : base64_encode($att['data']),
                    ],
                ];
            }
        }

        return $parts;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Build tool Anthropic cho structured output.
     * INPUT: tool name + JSON Schema. OUTPUT: array tool definition.
     * =====================================================================
     */
    protected function buildTool(string $name, ?array $schema): array
    {
        return [
            'name' => $name,
            'description' => 'Tra ve ket qua duoi dang JSON theo schema.',
            'input_schema' => $schema ?? ['type' => 'object'],
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Parse response content[] thanh text + tool_use input.
     * INPUT: array response decode + ?toolName. OUTPUT: [text, ?parsed].
     * =====================================================================
     */
    protected function parseOutput(array $data, ?string $toolName): array
    {
        $text = '';
        $parsed = null;

        foreach ((array) ($data['content'] ?? []) as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'text') {
                $text .= (string) ($block['text'] ?? '');
            } elseif ($type === 'tool_use' && $toolName !== null && ($block['name'] ?? '') === $toolName) {
                $parsed = is_array($block['input'] ?? null) ? $block['input'] : null;
            }
        }

        if ($parsed === null && $toolName !== null && $text !== '') {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
            }
        }

        return [$text, $parsed];
    }

    /** INPUT: resolved+request. OUTPUT: never — unsupported. */
    public function image(AiResolvedModelDto $resolved, AiImageRequest $request): AiImageResponse
    {
        $this->unsupported('image');
    }

    /** INPUT: resolved+request. OUTPUT: never — unsupported. */
    public function speech(AiResolvedModelDto $resolved, AiSpeechRequest $request): AiSpeechResponse
    {
        $this->unsupported('speech');
    }

    /** INPUT: resolved+request. OUTPUT: never — unsupported. */
    public function transcribe(AiResolvedModelDto $resolved, AiTranscribeRequest $request): AiTranscribeResponse
    {
        $this->unsupported('transcribe');
    }
}