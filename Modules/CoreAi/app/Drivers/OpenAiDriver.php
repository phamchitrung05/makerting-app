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
use Modules\CoreAi\DTOs\AiUsageDto;
use Modules\CoreAi\DTOs\WordTimingDto;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Services\JsonSchemaValidator;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Driver chuẩn OpenAI (api.openai.com + tương thích).
 * =====================================================================
 *
 * Triển khai đủ 4 capability:
 * - structured(): POST /chat/completions — messages + response_format
 *   (json_schema khi có schema) + vision attachments (image_url base64/
 *   url). Parse choices[0].message.content → text; nếu schema → decode
 *   JSON + kiểm định bằng JsonSchemaValidator.
 * - image(): POST /images/generations — prompt/size/quality/style/negative
 *   (qua extra options) → b64_json[] decode binary.
 * - speech(): POST /audio/speech — model+voice+input+response_format+
 *   speed → binary audio. Word timing: OpenAI chưa trả → timings=[].
 * - transcribe(): POST /audio/transcriptions — multipart file + model +
 *   response_format=verbose_json + timestamp_granularities=word →
 *   segments/words → WordTimingDto[].
 *
 * Auth: `Authorization: Bearer <key>`; tổ chức qua `OpenAI-Organization`
 * nếu config_json.organization.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - key()/ops()/headers() : định danh + op hỗ trợ + auth headers.
 * - test()                : GET /models ping nhẹ + đếm model.
 * - structured()          : chat/completions + schema validate.
 * - image()/speech()/transcribe() : 3 op còn lại.
 * - mapAttachments()      : ảnh base64/url → image_url content parts.
 * - parseWordTimings()    : verbose_json words → WordTimingDto[].
 * - sizeFor()             : aspectRatio → size API OpenAI.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AiResolvedModelDto + Request DTO.
 * - OUTPUT: Response DTO chuẩn; AiGatewayException khi provider trả lỗi.
 * =====================================================================
 */
class OpenAiDriver extends AbstractAiDriver
{
    /** INPUT: không. OUTPUT: 'openai'. */
    public function key(): string
    {
        return 'openai';
    }

    /** INPUT: không. OUTPUT: đủ 4 op. */
    protected function ops(): array
    {
        return ['structured', 'image', 'speech', 'transcribe'];
    }

    /** INPUT: apiKey. OUTPUT: headers Bearer + optional organization. */
    protected function headers(string $apiKey): array
    {
        return $this->client()->buildAuthHeaders('Authorization', $apiKey, 'Bearer');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ping kết nối — GET /models (nhẹ) trả ok/latency/message.
     * =====================================================================
     * INPUT: resolved. OUTPUT: array{ok,latency_ms,message,models?}.
     * SIDE EFFECT: gọi HTTP thật.
     * =====================================================================
     */
    public function test(AiResolvedModelDto $resolved): array
    {
        $start = microtime(true);
        try {
            $res = $this->client()->request(
                $resolved->provider,
                'GET',
                $this->url($resolved->provider, 'models'),
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
     * CHỨC NĂNG: Text/Vision/Structured qua /chat/completions.
     * =====================================================================
     * INPUT: resolved + request (messages|prompt, schema?, attachments?).
     * OUTPUT: AiStructuredResponse — text + ?data đã validate schema.
     * EXCEPTION: AiGatewayException + AiValidationException (schema lệch).
     * =====================================================================
     */
    public function structured(AiResolvedModelDto $resolved, AiStructuredRequest $request): AiStructuredResponse
    {
        return $this->withKeyRotation($resolved, function (AiResolvedModelDto $r) use ($request) {
            $messages = $this->buildMessages($request);
            $payload = [
                'model' => $r->model->remote_model_id,
                'messages' => $messages,
            ];

            // response_format json_schema khi request có schema.
            if ($request->requiresStructuredOutput()) {
                $payload['response_format'] = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'structured_output',
                        'strict' => true,
                        'schema' => $request->schema,
                    ],
                ];
            }

            // Merge options driver (temperature, max_tokens, top_p...).
            // 'timeout' là cài đặt HTTP client, KHÔNG phải tham số API —
            // không nhét vào body để tránh 400 invalid_request.
            foreach ($request->options as $k => $v) {
                if (! in_array($k, ['model', 'messages', 'response_format', 'timeout'], true)) {
                    $payload[$k] = $v;
                }
            }

            // Timeout HTTP lấy từ options của request (ví dụ 180 cho analyzer
            // dài) nếu được truyền; provider->request_timeout mặc định 120s là
            // không đủ cho max_tokens lớn.
            $httpTimeout = (int) ($request->options['timeout'] ?? 0);
            $data = $this->client()->requestJson(
                $r->provider,
                $this->url($r->provider, 'chat/completions'),
                $payload,
                $this->headers($r->apiKey),
                ['keyIndex' => $r->keyIndex, ...($httpTimeout > 0 ? ['timeout' => $httpTimeout] : [])]
            );

            $choice = $data['choices'][0] ?? null;
            $text = (string) ($choice['message']['content'] ?? '');
            $parsed = null;

            if ($request->requiresStructuredOutput()) {
                $parsed = json_decode($text, true);
                if (! is_array($parsed)) {
                    throw AiGatewayException::validation(
                        'model tra ve text khong phai JSON hop le',
                        ['provider' => $r->provider->key, 'model' => $r->model->remote_model_id]
                    );
                }
                // Kiểm định đầu ra khớp schema 100%.
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
     * CHỨC NĂNG: Sinh ảnh qua /images/generations.
     * =====================================================================
     * INPUT: resolved + request (prompt/negative?/aspectRatio/references?).
     * OUTPUT: AiImageResponse — images[] binary đã decode.
     * =====================================================================
     */
    public function image(AiResolvedModelDto $resolved, AiImageRequest $request): AiImageResponse
    {
        return $this->withKeyRotation($resolved, function (AiResolvedModelDto $r) use ($request) {
            $prompt = $request->prompt;
            if ($request->negativePrompt !== null && $request->negativePrompt !== '') {
                // OpenAI không có negative_prompt riêng — nhét vào prompt với
                // chỉ dẫn loại trừ để model tự tránh.
                $prompt .= "\n\nAvoid: {$request->negativePrompt}";
            }

            $size = $this->sizeFor($request->aspectRatio, (string) $r->model->remote_model_id);

            $data = $request->hasReferences()
                ? $this->requestImageEdits($r, $prompt, $size, $request)
                : $this->requestImageGenerations($r, $prompt, $size, $request);

            $images = [];
            foreach ((array) ($data['data'] ?? []) as $item) {
                $b64 = $item['b64_json'] ?? null;
                if (is_string($b64) && $b64 !== '') {
                    $bin = base64_decode($b64, true);
                    if ($bin !== false) {
                        $images[] = [
                            'mime' => 'image/png',
                            'data' => $bin,
                            'width' => null,
                            'height' => null,
                        ];
                    }

                    continue;
                }

                $url = $item['url'] ?? null;
                if (is_string($url) && $url !== '') {
                    $dl = $this->client()->request(
                        $r->provider,
                        'GET',
                        $url,
                        [],
                        null,
                        ['maxBytes' => 64 * 1024 * 1024]
                    );
                    if ($dl->body !== '') {
                        $images[] = [
                            'mime' => $dl->contentType !== '' ? trim(explode(';', $dl->contentType)[0]) : 'image/png',
                            'data' => $dl->body,
                            'width' => null,
                            'height' => null,
                        ];
                    }
                }
            }

            if ($images === []) {
                throw AiGatewayException::providerError('provider khong tra ve anh', ['provider' => $r->provider->key]);
            }

            return new AiImageResponse(
                images: $images,
                revisedPrompt: $data['data'][0]['revised_prompt'] ?? null,
                usage: $data['usage'] ?? [],
                raw: $data,
                remoteModelId: $r->model->remote_model_id,
                providerKey: $r->provider->key,
            );
        });
    }

    /**
     * CHỨC NĂNG: Gọi POST /images/generations cho sinh ảnh từ văn bản thuần.
     * INPUT: resolved, prompt, size, request. OUTPUT: array JSON decoded.
     */
    protected function requestImageGenerations(
        AiResolvedModelDto $r,
        string $prompt,
        string $size,
        AiImageRequest $request,
    ): array {
        $payload = [
            'model' => $r->model->remote_model_id,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'response_format' => 'b64_json',
        ];

        foreach ($request->options as $k => $v) {
            if (! array_key_exists($k, $payload)) {
                $payload[$k] = $v;
            }
        }

        return $this->client()->requestJson(
            $r->provider,
            $this->url($r->provider, 'images/generations'),
            $payload,
            $this->headers($r->apiKey),
            ['keyIndex' => $r->keyIndex, 'maxBytes' => 64 * 1024 * 1024]
        );
    }

    /**
     * CHỨC NĂNG: Gọi multipart POST /images/edits khi có ảnh tham chiếu.
     * INPUT: resolved, prompt, size, request. OUTPUT: array JSON decoded.
     */
    protected function requestImageEdits(
        AiResolvedModelDto $r,
        string $prompt,
        string $size,
        AiImageRequest $request,
    ): array {
        $ref = $request->referenceImages[0] ?? [];
        $norm = $this->normalizeAttachment(is_array($ref) ? $ref : []);
        $binary = $norm['binary'];

        if ($binary === '' && $norm['url'] !== null) {
            $dl = $this->client()->request(
                $r->provider,
                'GET',
                $norm['url'],
                [],
                null,
                ['maxBytes' => 25 * 1024 * 1024]
            );
            $binary = $dl->body;
            if ($dl->contentType !== '') {
                $norm['mime'] = trim(explode(';', $dl->contentType)[0]);
            }
        }

        if ($binary === '') {
            throw AiGatewayException::validation('Không đọc được dữ liệu ảnh tham chiếu để gửi cho model tạo ảnh.', [
                'provider' => $r->provider->key,
                'model' => $r->model->remote_model_id,
            ]);
        }

        $ext = $this->mimeExtension($norm['mime']);
        $body = [
            'model' => $r->model->remote_model_id,
            'prompt' => $prompt,
            'n' => '1',
            'size' => $size,
            'image' => [
                'contents' => $binary,
                'filename' => "reference.{$ext}",
                'mime' => $norm['mime'],
            ],
        ];

        foreach ($request->options as $k => $v) {
            if (! array_key_exists($k, $body) && (is_scalar($v) || is_null($v))) {
                $body[$k] = (string) $v;
            }
        }

        $res = $this->client()->request(
            $r->provider,
            'POST',
            $this->url($r->provider, 'images/edits'),
            $this->headers($r->apiKey),
            $body,
            ['keyIndex' => $r->keyIndex, 'maxBytes' => 64 * 1024 * 1024]
        );

        $decoded = json_decode($res->body, true);
        if (! is_array($decoded)) {
            throw AiGatewayException::providerError(
                'provider không trả JSON hợp lệ cho images/edits',
                ['provider' => $r->provider->key]
            );
        }

        return $decoded;
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: TTS qua /audio/speech.
     * =====================================================================
     * INPUT: resolved + request (text/voice/format/speed/withTimings?).
     * OUTPUT: AiSpeechResponse — audio binary + timings=[] (OpenAI chưa
     *         trả word timing qua endpoint này — caller dùng model khác
     *         hoặc P4-03 adapter khi cần).
     * =====================================================================
     */
    public function speech(AiResolvedModelDto $resolved, AiSpeechRequest $request): AiSpeechResponse
    {
        return $this->withKeyRotation($resolved, function (AiResolvedModelDto $r) use ($request) {
            $payload = [
                'model' => $r->model->remote_model_id,
                'input' => $request->text,
                'voice' => $request->voice ?? 'alloy',
                'response_format' => $request->format,
                'speed' => $request->speed,
            ];
            foreach ($request->options as $k => $v) {
                if (! array_key_exists($k, $payload)) {
                    $payload[$k] = $v;
                }
            }

            $res = $this->client()->request(
                $r->provider,
                'POST',
                $this->url($r->provider, 'audio/speech'),
                array_merge($this->headers($r->apiKey), ['Content-Type: application/json']),
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                ['keyIndex' => $r->keyIndex, 'maxBytes' => 64 * 1024 * 1024]
            );

            return new AiSpeechResponse(
                audio: $res->body,
                mime: $res->contentType ?? 'audio/mpeg',
                timings: [],
                durationMs: null,
                usage: [],
                raw: [],
                remoteModelId: $r->model->remote_model_id,
                providerKey: $r->provider->key,
            );
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: STT qua /audio/transcriptions (verbose_json + word timing).
     * =====================================================================
     * INPUT: resolved + request (audio|audioPath + mime + language? +
     *        withTimings). OUTPUT: AiTranscribeResponse — text +
     *        WordTimingDto[] khi provider trả words/segments.
     * =====================================================================
     */
    public function transcribe(AiResolvedModelDto $resolved, AiTranscribeRequest $request): AiTranscribeResponse
    {
        return $this->withKeyRotation($resolved, function (AiResolvedModelDto $r) use ($request) {
            $fileField = [
                'contents' => $request->audio !== ''
                    ? $request->audio
                    : ($request->audioPath && is_file($request->audioPath) ? file_get_contents($request->audioPath) : ''),
                'filename' => $request->audioPath ? basename($request->audioPath) : 'audio.' . $this->extFor($request->mime),
                'mime' => $request->mime,
            ];
            if (($fileField['contents'] ?? '') === '') {
                throw AiGatewayException::validation('khong co audio de transcribe', ['provider' => $r->provider->key]);
            }

            $fields = [
                'file' => $fileField,
                'model' => $r->model->remote_model_id,
                'response_format' => 'verbose_json',
            ];
            if ($request->language !== null) {
                $fields['language'] = $request->language;
            }
            if ($request->withTimings) {
                $fields['timestamp_granularities[]'] = 'word';
            }
            foreach ($request->options as $k => $v) {
                $fields[$k] = $v;
            }

            $res = $this->client()->request(
                $r->provider,
                'POST',
                $this->url($r->provider, 'audio/transcriptions'),
                $this->headers($r->apiKey),
                $fields,
                ['keyIndex' => $r->keyIndex, 'maxBytes' => 64 * 1024 * 1024]
            );

            $data = json_decode($res->body, true) ?: [];
            $timings = $request->withTimings ? $this->parseWordTimings($data) : [];

            return new AiTranscribeResponse(
                text: (string) ($data['text'] ?? ''),
                timings: $timings,
                language: $data['language'] ?? $request->language,
                durationMs: isset($data['duration']) ? (int) round((float) $data['duration'] * 1000) : null,
                usage: $data['usage'] ?? [],
                raw: $data,
                remoteModelId: $r->model->remote_model_id,
                providerKey: $r->provider->key,
            );
        });
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Build messages cho /chat/completions từ request chuẩn.
     * =====================================================================
     * INPUT: request. OUTPUT: array messages — nếu prompt đơn + không có
     *         messages sẵn → wrap thành user message; nếu có attachments
     *         → content là mảng parts [text, image_url, image_url...].
     * =====================================================================
     */
    protected function buildMessages(AiStructuredRequest $request): array
    {
        if ($request->messages !== []) {
            // Attachments gắn vào message cuối cùng (user) — đủ cho đa số use case.
            $messages = $request->messages;
            if ($request->hasAttachments()) {
                $last = count($messages) - 1;
                $content = $messages[$last]['content'] ?? '';
                $parts = is_array($content) ? $content : [['type' => 'text', 'text' => (string) $content]];
                foreach ($this->mapAttachments($request->attachments) as $part) {
                    $parts[] = $part;
                }
                $messages[$last]['content'] = $parts;
            }

            return $messages;
        }

        // Prompt đơn → user message (+ attachments nếu có).
        if ($request->hasAttachments()) {
            $parts = [['type' => 'text', 'text' => $request->prompt ?? '']];
            foreach ($this->mapAttachments($request->attachments) as $part) {
                $parts[] = $part;
            }

            return [['role' => 'user', 'content' => $parts]];
        }

        return [['role' => 'user', 'content' => $request->prompt ?? '']];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Map attachments (mime+data|url) → image_url parts OpenAI.
     * =====================================================================
     * INPUT: array attachments. OUTPUT: array parts cho content[] message.
     * =====================================================================
     */
    protected function mapAttachments(array $attachments): array
    {
        $parts = [];
        foreach ($attachments as $att) {
            if (isset($att['url'])) {
                $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $att['url']]];
            } elseif (isset($att['data'])) {
                $norm = $this->normalizeAttachment($att);
                if ($norm['data_uri'] !== '') {
                    $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $norm['data_uri']]];
                }
            }
        }

        return $parts;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Parse verbose_json OpenAI thành WordTimingDto[].
     * =====================================================================
     * INPUT: array response decode. OUTPUT: WordTimingDto[].
     * =====================================================================
     */
    protected function parseWordTimings(array $data): array
    {
        $timings = [];
        $idx = 0;

        // 1. words[] — timestamp_granularities=word.
        foreach ((array) ($data['words'] ?? []) as $w) {
            $timings[] = WordTimingDto::fromArray([
                'text' => $w['word'] ?? $w['text'] ?? '',
                'start' => isset($w['start']) ? (float) $w['start'] * 1000 : 0,
                'end' => isset($w['end']) ? (float) $w['end'] * 1000 : 0,
                'confidence' => $w['confidence'] ?? null,
            ], $idx++);
        }
        if ($timings !== []) {
            return $timings;
        }

        // 2. segments[] — fallback khi provider chỉ trả segment-level.
        foreach ((array) ($data['segments'] ?? []) as $seg) {
            $timings[] = WordTimingDto::fromArray([
                'text' => trim((string) ($seg['text'] ?? '')),
                'start' => isset($seg['start']) ? (float) $seg['start'] * 1000 : 0,
                'end' => isset($seg['end']) ? (float) $seg['end'] * 1000 : 0,
                'confidence' => $seg['avg_logprob'] ?? $seg['confidence'] ?? null,
            ], $idx++);
        }

        return $timings;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: aspectRatio → size API OpenAI (DALL-E / gpt-image-*).
     * INPUT: 'W:H', optional remote model id. OUTPUT: 'WxH' hợp lệ.
     * =====================================================================
     */
    protected function sizeFor(string $aspectRatio, string $modelId = ''): string
    {
        if (str_starts_with(strtolower($modelId), 'gpt-image')) {
            return match ($aspectRatio) {
                '16:9', '4:3' => '1536x1024',
                '9:16', '3:4' => '1024x1536',
                '1:1' => '1024x1024',
                default => '1024x1024',
            };
        }

        return match ($aspectRatio) {
            '16:9' => '1792x1024',
            '9:16' => '1024x1792',
            '1:1' => '1024x1024',
            default => '1024x1024',
        };
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: MIME → extension cho filename upload transcribe.
     * INPUT: mime. OUTPUT: ext.
     * =====================================================================
     */
    protected function extFor(string $mime): string
    {
        return match (strtolower($mime)) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/mp4', 'audio/m4a' => 'm4a',
            'audio/ogg', 'audio/opus' => 'ogg',
            'audio/webm' => 'webm',
            'video/mp4' => 'mp4',
            default => 'bin',
        };
    }
}