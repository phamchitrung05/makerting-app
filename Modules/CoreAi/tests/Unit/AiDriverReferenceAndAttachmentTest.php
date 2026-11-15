<?php

namespace Modules\CoreAi\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\DTOs\RemoteFetchResultDto;
use Modules\Core\Services\CredentialVault;
use Modules\CoreAi\Drivers\AnthropicDriver;
use Modules\CoreAi\Drivers\GeminiDriver;
use Modules\CoreAi\Drivers\OpenAiDriver;
use Modules\CoreAi\Drivers\XpikiDriver;
use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvider;
use Modules\CoreAi\Services\AiProviderClient;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Unit test chuẩn hóa attachment & referenceImages ở Driver.
 * =====================================================================
 *
 * Kiểm chứng:
 * - AnthropicDriver / OpenAiDriver / GeminiDriver không bị double-base64 khi
 *   caller truyền bare base64, data URI hoặc binary thô.
 * - OpenAiDriver::image() chuyển sang multipart POST /images/edits khi có
 *   referenceImages, map size cho gpt-image-* và hỗ trợ fallback response `url`.
 * - XpikiDriver::image() đính kèm `references` (data URI) vào JSON body.
 * - AbstractAiDriver::testModel() với model `['image_generation', 'vision']`
 *   sẽ ping op `image()` thay vì ép qua `structured()`.
 * =====================================================================
 */
class AiDriverReferenceAndAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $key, string $driver, string $baseUrl): AiProvider
    {
        return AiProvider::create([
            'key' => $key,
            'name' => strtoupper($key),
            'kind' => AiProvider::KIND_OFFICIAL,
            'driver' => $driver,
            'base_url' => $baseUrl,
            'api_key_encrypted' => app(CredentialVault::class)->encrypt('secret-key'),
            'request_timeout' => 60,
            'is_active' => true,
        ]);
    }

    private function makeModel(AiProvider $provider, string $remoteId, array $caps): AiModel
    {
        return AiModel::create([
            'ai_provider_id' => $provider->id,
            'remote_model_id' => $remoteId,
            'label' => $remoteId,
            'capabilities_json' => $caps,
            'is_enabled' => true,
            'is_available' => true,
        ]);
    }

    public function test_anthropic_driver_does_not_double_encode_bare_base64_attachment(): void
    {
        $raw = str_repeat('PNGDATA_', 4);
        $bareBase64 = base64_encode($raw);

        $captured = null;
        $mock = new class($captured) extends AiProviderClient {
            public function __construct(private &$capturedRef) {}

            public function requestJson(
                AiProvider $provider,
                string $url,
                array $payload = [],
                array $headers = [],
                array $options = [],
                string $method = 'POST',
            ): array {
                $this->capturedRef = $payload;

                return [
                    'model' => 'claude-test',
                    'content' => [['type' => 'text', 'text' => '{"prompt":"ok"}']],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ];
            }
        };
        $this->app->instance(AiProviderClient::class, $mock);

        $provider = $this->makeProvider('anthropic-test', 'anthropic', 'https://api.anthropic.test/v1');
        $model = $this->makeModel($provider, 'claude-test', [AiModel::CAP_TEXT_GENERATION, AiModel::CAP_STRUCTURED_OUTPUT, AiModel::CAP_VISION]);
        $resolved = new AiResolvedModelDto($provider, $model, 'secret-key', 0);

        (new AnthropicDriver())->structured($resolved, new AiStructuredRequest(
            prompt: 'Mo ta anh nay',
            schema: ['type' => 'object', 'properties' => ['prompt' => ['type' => 'string']], 'required' => ['prompt']],
            attachments: [['mime' => 'image/jpeg', 'data' => $bareBase64]],
        ));

        $content = $captured['messages'][0]['content'] ?? [];
        $imagePart = collect($content)->firstWhere('type', 'image');
        $this->assertIsArray($imagePart);
        $this->assertSame('image', $imagePart['type'] ?? null);
        $this->assertSame('image/jpeg', $imagePart['source']['media_type'] ?? null);
        $this->assertSame($bareBase64, $imagePart['source']['data'] ?? null);
    }

    public function test_openai_and_gemini_drivers_preserve_bare_base64_and_raw_binary_attachments(): void
    {
        $raw = str_repeat('JPEGDATA', 4);
        $bareBase64 = base64_encode($raw);

        $capturedBodies = [];
        $mock = new class($capturedBodies) extends AiProviderClient {
            public function __construct(private &$capturedRef) {}

            public function requestJson(
                AiProvider $provider,
                string $url,
                array $payload = [],
                array $headers = [],
                array $options = [],
                string $method = 'POST',
            ): array {
                $this->capturedRef[] = ['url' => $url, 'payload' => $payload];

                if (str_contains($url, 'generativelanguage') || str_contains($url, 'generateContent')) {
                    return [
                        'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
                        'usageMetadata' => [],
                    ];
                }

                return [
                    'model' => 'gpt-test',
                    'choices' => [['message' => ['content' => 'ok']]],
                    'usage' => [],
                ];
            }
        };
        $this->app->instance(AiProviderClient::class, $mock);

        $openAiProvider = $this->makeProvider('openai-test', 'openai', 'https://api.openai.test/v1');
        $openAiModel = $this->makeModel($openAiProvider, 'gpt-test', [AiModel::CAP_VISION]);
        (new OpenAiDriver())->structured(
            new AiResolvedModelDto($openAiProvider, $openAiModel, 'secret-key', 0),
            new AiStructuredRequest(
                prompt: 'Describe',
                attachments: [['mime' => 'image/webp', 'data' => $bareBase64]],
            )
        );

        $openAiImgUrl = $capturedBodies[0]['payload']['messages'][0]['content'][1]['image_url']['url'] ?? null;
        $this->assertSame('data:image/webp;base64,' . $bareBase64, $openAiImgUrl);

        $geminiProvider = $this->makeProvider('gemini-test', 'gemini', 'https://generativelanguage.googleapis.com/v1beta');
        $geminiModel = $this->makeModel($geminiProvider, 'gemini-2.5-flash', [AiModel::CAP_VISION]);
        (new GeminiDriver())->structured(
            new AiResolvedModelDto($geminiProvider, $geminiModel, 'secret-key', 0),
            new AiStructuredRequest(
                prompt: 'Describe',
                attachments: [['mime' => 'image/png', 'data' => "BIN\x89\x50"]],
            )
        );

        $geminiInline = $capturedBodies[1]['payload']['contents'][0]['parts'][1]['inlineData'] ?? null;
        $this->assertSame('image/png', $geminiInline['mimeType'] ?? null);
        $this->assertSame(base64_encode("BIN\x89\x50"), $geminiInline['data'] ?? null);
    }

    public function test_openai_driver_uses_multipart_images_edits_when_reference_present_and_handles_url_fallback(): void
    {
        $calls = [];
        $mock = new class($calls) extends AiProviderClient {
            public function __construct(private &$callsRef) {}

            public function request(
                AiProvider $provider,
                string $method,
                string $url,
                array $headers = [],
                string|array|null $body = null,
                array $options = [],
            ): RemoteFetchResultDto {
                $this->callsRef[] = compact('method', 'url', 'body', 'options');

                if ($method === 'GET') {
                    return new RemoteFetchResultDto(
                        status: 200,
                        body: 'GENERATED_PNG_BYTES',
                        headers: [],
                        finalUrl: $url,
                        contentType: 'image/png',
                    );
                }

                return new RemoteFetchResultDto(
                    status: 200,
                    body: json_encode([
                        'data' => [['url' => 'https://cdn.example.test/generated.png']],
                    ]),
                    headers: [],
                    finalUrl: $url,
                    contentType: 'application/json',
                );
            }
        };
        $this->app->instance(AiProviderClient::class, $mock);

        $provider = $this->makeProvider('apikey-img', 'openai_compatible', 'https://api.apikey.fan/v1');
        $model = $this->makeModel($provider, 'gpt-image-2', [AiModel::CAP_IMAGE_GENERATION, AiModel::CAP_VISION]);
        $resolved = new AiResolvedModelDto($provider, $model, 'secret-key', 0);

        $rawRef = "PNG\x89REF_BINARY";
        $response = (new OpenAiDriver())->image($resolved, new AiImageRequest(
            prompt: 'A handmade mug matching reference style',
            aspectRatio: '16:9',
            referenceImages: [['mime' => 'image/jpeg', 'data' => base64_encode($rawRef)]],
        ));

        $this->assertCount(2, $calls);
        $this->assertSame('POST', $calls[0]['method']);
        $this->assertSame('https://api.apikey.fan/v1/images/edits', $calls[0]['url']);
        $this->assertSame('1536x1024', $calls[0]['body']['size']);
        $this->assertSame('gpt-image-2', $calls[0]['body']['model']);
        $this->assertSame($rawRef, $calls[0]['body']['image']['contents']);
        $this->assertSame('image/jpeg', $calls[0]['body']['image']['mime']);
        $this->assertSame('reference.jpg', $calls[0]['body']['image']['filename']);

        $this->assertSame('GET', $calls[1]['method']);
        $this->assertSame('https://cdn.example.test/generated.png', $calls[1]['url']);
        $this->assertSame('GENERATED_PNG_BYTES', $response->images[0]['data']);
    }

    public function test_xpiki_driver_includes_references_data_uri_array(): void
    {
        $calls = [];
        $mock = new class($calls) extends AiProviderClient {
            public function __construct(private &$callsRef) {}

            public function request(
                AiProvider $provider,
                string $method,
                string $url,
                array $headers = [],
                string|array|null $body = null,
                array $options = [],
            ): RemoteFetchResultDto {
                $this->callsRef[] = compact('method', 'url', 'body');

                if (strtoupper($method) === 'POST') {
                    return new RemoteFetchResultDto(
                        202,
                        json_encode(['task_id' => 'task_ref_1', 'status' => 'queued']),
                        [],
                        $url,
                        'application/json'
                    );
                }

                if (str_contains($url, 'generation-tasks/')) {
                    return new RemoteFetchResultDto(
                        200,
                        json_encode([
                            'task_id' => 'task_ref_1',
                            'status' => 'succeeded',
                            'outputs' => [['url' => 'https://cdn.xpiki.test/out.png', 'mime_type' => 'image/png']],
                        ]),
                        [],
                        $url,
                        'application/json'
                    );
                }

                return new RemoteFetchResultDto(200, 'XPIKI_IMG_BIN', [], $url, 'image/png');
            }
        };
        $this->app->instance(AiProviderClient::class, $mock);

        $provider = $this->makeProvider('xpiki-test', 'xpiki', 'https://api.xpiki.com/v1');
        $model = $this->makeModel($provider, 'seedream-4-5-edit-image', [AiModel::CAP_IMAGE_GENERATION]);
        $resolved = new AiResolvedModelDto($provider, $model, 'secret-key', 0);

        $raw = "\x89PNG\r\n\x1a\nXREF_BIN_" . str_repeat('X', 8);
        $response = (new XpikiDriver())->image($resolved, new AiImageRequest(
            prompt: 'Warm editorial portrait',
            aspectRatio: '4:3',
            referenceImages: [['mime' => 'image/png', 'data' => $raw]],
        ));

        $submitBody = $calls[0]['body'] ?? '';
        $submit = is_string($submitBody) ? json_decode($submitBody, true) : (array) $submitBody;
        $this->assertSame('seedream-4-5-edit-image', $submit['model'] ?? null);
        $this->assertSame('4:3', $submit['aspect_ratio'] ?? null);
        $this->assertSame(['data:image/png;base64,' . base64_encode($raw)], $submit['references'] ?? null);
        $this->assertSame('XPIKI_IMG_BIN', $response->images[0]['data']);
    }

    public function test_test_model_probes_image_endpoint_for_image_generation_with_vision_capability(): void
    {
        $calledUrl = null;
        $mock = new class($calledUrl) extends AiProviderClient {
            public function __construct(private &$urlRef) {}

            public function requestJson(
                AiProvider $provider,
                string $url,
                array $payload = [],
                array $headers = [],
                array $options = [],
                string $method = 'POST',
            ): array {
                $this->urlRef = $url;

                return [
                    'data' => [['b64_json' => base64_encode('PNG_PROBE')]],
                ];
            }
        };
        $this->app->instance(AiProviderClient::class, $mock);

        $provider = $this->makeProvider('apikey-img-probe', 'openai_compatible', 'https://api.apikey.fan/v1');
        $model = $this->makeModel($provider, 'gpt-image-2', [AiModel::CAP_IMAGE_GENERATION, AiModel::CAP_VISION]);
        $resolved = new AiResolvedModelDto($provider, $model, 'secret-key', 0);

        $res = (new OpenAiDriver())->testModel($resolved);

        $this->assertTrue($res['ok']);
        $this->assertSame('https://api.apikey.fan/v1/images/generations', $calledUrl);
    }
}
