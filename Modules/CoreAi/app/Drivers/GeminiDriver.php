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
 * CHỨC NĂNG FILE: Driver Google Gemini (generativelanguage.googleapis.com).
 * =====================================================================
 *
 * Triển khai op:
 * - structured(): POST /models/{id}:generateContent — system_instruction
 *   riêng + contents[{role,parts}] (text + inlineData base64 cho vision);
 *   khi có schema → generationConfig.responseMimeType=application/json
 *   + responseSchema.
 * - image(): POST /models/{id}:predict — Imagen 3+ sinh ảnh, trả
 *   predictions[].bytesBase64Encoded.
 * - speech()/transcribe(): KHÔNG hỗ trợ (Gemini API này không có TTS/STT
 *   chuẩn) → ném unsupported().
 *
 * Auth: `x-goog-api-key: <key>`.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - key()/ops()/headers() : định danh + op + auth headers.
 * - test()                : GET /v1beta/models ping nhẹ.
 * - structured()          : :generateContent + responseSchema.
 * - image()               : :predict Imagen.
 * - buildContents()       : messages + prompt → contents[] Gemini.
 * - aspectFor()           : aspectRatio → kích thước Imagen.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AiResolvedModelDto + Request DTO.
 * - OUTPUT: Response DTO chuẩn; AiGatewayException khi lỗi/unsupported.
 * =====================================================================
 */
class GeminiDriver extends AbstractAiDriver
{
    /** INPUT: không. OUTPUT: 'gemini'. */
    public function key(): string
    {
        return 'gemini';
    }

    /** INPUT: không. OUTPUT: structured + image. */
    protected function ops(): array
    {
        return ['structured', 'image'];
    }

    /** INPUT: apiKey. OUTPUT: header x-goog-api-key. */
    protected function headers(string $apiKey): array
    {
        return $this->client()->buildAuthHeaders('x-goog-api-key', $apiKey, '');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ping kết nối — GET /v1beta/models.
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
                $this->url($resolved->provider, 'v1beta/models'),
                $this->headers($resolved->apiKey),
                null,
                ['timeout' => 15]
            );
            $data = json_decode($res->body, true);
            $count = is_array($data['models'] ?? null) ? count($data['models']) : null;
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'ok' => true,
                'latency_ms' => $ms,
                'message' => 'Kết nối thành công' . ($count !== null ? " — {$count} models" : ''),
                // Gemini trả name dạng 'models/<id>' — strip tiền tố để
                // remote_model_id sạch, tránh 'models/models/<id>' khi ghép URL.
                'models' => is_array($data['models'] ?? null)
                    ? array_map(static fn ($m) => preg_replace('#^models/#', '', (string) ($m['name'] ?? '')), $data['models'])
                    : [],
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
     * CHỨC NĂNG: Text/Vision/Structured qua :generateContent.
     * =====================================================================
     * INPUT: resolved + request. OUTPUT: AiStructuredResponse.
     * =====================================================================
     */
    public function structured(AiResolvedModelDto $resolved, AiStructuredRequest $request): AiStructuredResponse
    {
        return $this->withKeyRotation($resolved, function (AiResolvedModelDto $r) use ($request) {
            [$systemText, $contents] = $this->buildContents($request);

            $payload = [
                'contents' => $contents,
            ];
            if ($systemText !== null && $systemText !== '') {
                $payload['system_instruction'] = ['parts' => [['text' => $systemText]]];
            }

            // Structured output qua responseSchema.
            $genConfig = [];
            if ($request->requiresStructuredOutput()) {
                $genConfig['response_mime_type'] = 'application/json';
                $genConfig['response_schema'] = $request->schema;
            }
            foreach ($request->options as $k => $v) {
                $key = match ($k) {
                    'temperature' => 'temperature',
                    'top_p' => 'topP',
                    'top_k' => 'topK',
                    'max_tokens', 'max_output_tokens' => 'maxOutputTokens',
                    default => null,
                };
                if ($key !== null) {
                    $genConfig[$key] = $v;
                }
            }
            if ($genConfig !== []) {
                $payload['generationConfig'] = $genConfig;
            }

            $data = $this->client()->requestJson(
                $r->provider,
                $this->url($r->provider, "v1beta/models/" . $this->bareModelId($r->model->remote_model_id) . ":generateContent"),
                $payload,
                $this->headers($r->apiKey),
                ['keyIndex' => $r->keyIndex]
            );

            $text = '';
            foreach ((array) ($data['candidates'][0]['content']['parts'] ?? []) as $part) {
                $text .= (string) ($part['text'] ?? '');
            }

            $parsed = null;
            if ($request->requiresStructuredOutput()) {
                $parsed = json_decode($text, true);
                if (! is_array($parsed)) {
                    throw AiGatewayException::validation(
                        'model tra ve text khong phai JSON hop le',
                        ['provider' => $r->provider->key, 'model' => $r->model->remote_model_id]
                    );
                }
                app(JsonSchemaValidator::class)->validate($parsed, $request->schema);
            }

            return new AiStructuredResponse(
                text: $text,
                data: $parsed,
                usage: $data['usageMetadata'] ?? $data['usage'] ?? [],
                raw: $data,
                remoteModelId: $r->model->remote_model_id,
                providerKey: $r->provider->key,
            );
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Sinh ảnh qua Imagen :predict.
     * =====================================================================
     * INPUT: resolved + request. OUTPUT: AiImageResponse — images[] binary.
     * =====================================================================
     */
    public function image(AiResolvedModelDto $resolved, AiImageRequest $request): AiImageResponse
    {
        return $this->withKeyRotation($resolved, function (AiResolvedModelDto $r) use ($request) {
            $payload = [
                'instances' => [['prompt' => $request->prompt]],
                'parameters' => [
                    'sampleCount' => 1,
                    'aspectRatio' => $this->aspectFor($request->aspectRatio),
                ],
            ];
            if ($request->negativePrompt !== null && $request->negativePrompt !== '') {
                $payload['parameters']['negativePrompt'] = $request->negativePrompt;
            }
            foreach ($request->options as $k => $v) {
                $payload['parameters'][$k] = $v;
            }

            $data = $this->client()->requestJson(
                $r->provider,
                $this->url($r->provider, "v1beta/models/" . $this->bareModelId($r->model->remote_model_id) . ":predict"),
                $payload,
                $this->headers($r->apiKey),
                ['keyIndex' => $r->keyIndex, 'maxBytes' => 64 * 1024 * 1024]
            );

            $images = [];
            foreach ((array) ($data['predictions'] ?? []) as $pred) {
                $b64 = $pred['bytesBase64Encoded'] ?? $pred['image'] ?? null;
                if ($b64 === null) {
                    continue;
                }
                $bin = base64_decode($b64, true);
                if ($bin === false) {
                    continue;
                }
                $images[] = [
                    'mime' => $pred['mimeType'] ?? 'image/png',
                    'data' => $bin,
                    'width' => null,
                    'height' => null,
                ];
            }

            if ($images === []) {
                throw AiGatewayException::providerError('provider khong tra ve anh', ['provider' => $r->provider->key]);
            }

            return new AiImageResponse(
                images: $images,
                usage: $data['usageMetadata'] ?? [],
                raw: $data,
                remoteModelId: $r->model->remote_model_id,
                providerKey: $r->provider->key,
            );
        });
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Build contents[] Gemini từ request chuẩn.
     * =====================================================================
     * INPUT: request. OUTPUT: [?string systemText, array contents].
     *         contents[] = [{role:'user'|'model', parts:[{text}|{inlineData}]}].
     * =====================================================================
     */
    protected function buildContents(AiStructuredRequest $request): array
    {
        $systemText = null;
        $contents = [];

        if ($request->messages !== []) {
            foreach ($request->messages as $msg) {
                $role = $msg['role'] ?? 'user';
                $content = $msg['content'] ?? '';
                if ($role === 'system') {
                    $systemText = ($systemText ?? '') . (is_string($content) ? $content : json_encode($content));
                    continue;
                }
                $parts = $this->buildParts($content, $request->attachments);
                $contents[] = [
                    'role' => $role === 'assistant' ? 'model' : 'user',
                    'parts' => $parts,
                ];
            }
        } else {
            $contents[] = [
                'role' => 'user',
                'parts' => $this->buildParts($request->prompt ?? '', $request->attachments),
            ];
        }

        return [$systemText, $contents];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Build parts[] Gemini — text + inlineData (image).
     * INPUT: string|array content + attachments. OUTPUT: array parts.
     * =====================================================================
     */
    protected function buildParts(string|array $content, array $attachments): array
    {
        $parts = [];
        $text = is_string($content) ? $content : (is_array($content) ? ($content['text'] ?? json_encode($content)) : '');
        if ($text !== '') {
            $parts[] = ['text' => $text];
        }

        foreach ($attachments as $att) {
            if (isset($att['data'])) {
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $att['mime'] ?? 'image/png',
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
     * CHỨC NĂNG: aspectRatio request → aspectRatio Imagen.
     * INPUT: 'W:H'. OUTPUT: 'W:H' hợp lệ cho Imagen.
     * =====================================================================
     */
    /**
     * =====================================================================
     * CHỨC NĂNG: Strip tiền tố 'models/' khỏi remote_model_id (nếu có).
     * =====================================================================
     * Gemini /v1beta/models trả name 'models/<id>'; model cũ trong DB hoặc
     * model nhập tay có thể đã chứa sẵn tiền tố -> tránh 'models/models/<id>'.
     * INPUT: remote_model_id. OUTPUT: id sạch.
     * =====================================================================
     */
    protected function bareModelId(string $id): string
    {
        return (string) preg_replace('#^models/#', '', $id);
    }

    protected function aspectFor(string $aspectRatio): string
    {
        return match ($aspectRatio) {
            '16:9', '9:16', '1:1', '4:3', '3:4' => $aspectRatio,
            default => '1:1',
        };
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