<?php

namespace Modules\Media\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiImageResponse;
use Modules\CoreAi\DTOs\AiSpeechRequest;
use Modules\CoreAi\DTOs\AiSpeechResponse;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\DTOs\AiTranscribeRequest;
use Modules\CoreAi\DTOs\AiTranscribeResponse;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvenance;
use Modules\CoreAi\Models\AiProvider;
use Modules\CoreAi\Models\AiRun;
use Modules\CoreAi\Settings\AiSettings;
use Modules\Identity\Models\User;
use Modules\Media\Jobs\GenerateAiImageJob;
use Modules\Media\Models\MediaAsset;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Feature test API tạo ảnh preview AI & lưu MediaAsset.
 * =====================================================================
 *
 * Kiểm chứng toàn bộ Task 1 (Backend Modules/Media):
 * - GET ai-image/models: lọc usable + image_generation, default_model_id, quyền 403
 * - POST ai-image/generate: validate payload/model, queue 202 Accepted + queue config
 * - GET ai-image/jobs/{jobId}: polling progress, ownership isolation 403/404, lỗi CoreAi
 * - GET ai-image/previews/{previewId}: relative signed URL, ?download=1, forged 403
 * - POST ai-image/save: lưu StoreGeneratedMediaAction, ai_generation meta, idempotent
 * - GET assets?quick=ai: chỉ trả về MediaAsset sinh từ AI
 * =====================================================================
 */
class MediaAiImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media_public');
        Storage::fake('media_private');
        Storage::fake('temp_processing');
        Cache::flush();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['media.view', 'media.manage'] as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }
    }

    /**
     * CHỨC NĂNG: Tạo user thử nghiệm với tập permission chỉ định.
     * INPUT: array<int, string> $permissions.
     * OUTPUT: User.
     */
    private function createUserWithPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'Media Admin',
            'email' => 'media-' . uniqid() . '@test.local',
            'password' => Hash::make('secret1234'),
        ]);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    /**
     * CHỨC NĂNG: Tạo header Authorization Bearer token Sanctum cho user test.
     * INPUT: User $user.
     * OUTPUT: array<string, string>.
     */
    private function bearerHeader(User $user): array
    {
        // Xóa cache guard Sanctum giữa nhiều user khác nhau trong cùng 1 test method.
        app('auth')->forgetGuards();

        return [
            'Authorization' => 'Bearer ' . $user->createToken('media-test')->plainTextToken,
        ];
    }

    /**
     * CHỨC NĂNG: Tạo bản ghi AiProvider phục vụ test danh sách & chọn model AI.
     * INPUT: array $overrides.
     * OUTPUT: AiProvider.
     */
    private function createProvider(array $overrides = []): AiProvider
    {
        return AiProvider::create(array_merge([
            'key' => 'prov-' . uniqid(),
            'name' => 'Gemini Provider',
            'kind' => 'official',
            'driver' => 'gemini',
            'base_url' => 'https://generativelanguage.googleapis.com',
            'api_key_encrypted' => 'encrypted-dummy-key',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * CHỨC NĂNG: Tạo ảnh PNG nhị phân thật bằng GD với màu RGB khác nhau để test checksum & size.
     * INPUT: int $width, int $height, int $red, int $green, int $blue.
     * OUTPUT: string binary PNG.
     */
    private function pngBinary(int $width = 16, int $height = 12, int $red = 120, int $green = 40, int $blue = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, $red, $green, $blue);
        imagefill($image, 0, 0, $color);

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    /**
     * CHỨC NĂNG: Bind mock CoreAiGatewayContract vào Laravel container trong phạm vi test case.
     * INPUT: callable(AiImageRequest, int): AiImageResponse $handler, bool $withLastRun.
     * OUTPUT: void.
     */
    private function bindFakeImageGateway(callable $handler, bool $withLastRun = true): void
    {
        $fake = $withLastRun
            ? new class($handler) implements CoreAiGatewayContract
            {
                private int $calls = 0;

                public function __construct(private readonly mixed $handler) {}

                public function executeStructured(AiStructuredRequest $req): AiStructuredResponse
                {
                    throw new RuntimeException('Not implemented in image test');
                }

                public function generateImage(AiImageRequest $req): AiImageResponse
                {
                    $this->calls++;

                    return ($this->handler)($req, $this->calls);
                }

                public function synthesizeSpeech(AiSpeechRequest $req): AiSpeechResponse
                {
                    throw new RuntimeException('Not implemented in image test');
                }

                public function transcribeAudio(AiTranscribeRequest $req): AiTranscribeResponse
                {
                    throw new RuntimeException('Not implemented in image test');
                }

                public function lastRun(): object
                {
                    return (object) ['id' => 'run-test-' . $this->calls];
                }
            }
            : new class($handler) implements CoreAiGatewayContract
            {
                private int $calls = 0;

                public function __construct(private readonly mixed $handler) {}

                public function executeStructured(AiStructuredRequest $req): AiStructuredResponse
                {
                    throw new RuntimeException('Not implemented in image test');
                }

                public function generateImage(AiImageRequest $req): AiImageResponse
                {
                    $this->calls++;

                    return ($this->handler)($req, $this->calls);
                }

                public function synthesizeSpeech(AiSpeechRequest $req): AiSpeechResponse
                {
                    throw new RuntimeException('Not implemented in image test');
                }

                public function transcribeAudio(AiTranscribeRequest $req): AiTranscribeResponse
                {
                    throw new RuntimeException('Not implemented in image test');
                }
            };

        $this->app->instance(CoreAiGatewayContract::class, $fake);
    }

    public function test_models_endpoint_requires_manage_permission_and_filters_usable_image_models(): void
    {
        $viewOnly = $this->createUserWithPermissions(['media.view']);
        $manager = $this->createUserWithPermissions(['media.view', 'media.manage']);

        $this->withHeaders($this->bearerHeader($viewOnly))
            ->getJson('/api/v1/admin/media/ai-image/models')
            ->assertStatus(403);

        $activeProvider = $this->createProvider(['key' => 'gemini-main', 'name' => 'Gemini Chính']);
        $inactiveProvider = $this->createProvider(['key' => 'openai-off', 'is_active' => false]);

        $usableModel = AiModel::create([
            'ai_provider_id' => $activeProvider->id,
            'remote_model_id' => 'imagen-3.0',
            'label' => 'Imagen 3 Pro',
            'capabilities_json' => [AiModel::CAP_IMAGE_GENERATION],
            'is_enabled' => true,
            'is_available' => true,
        ]);

        // Text-only, disabled và thuộc provider tắt không được phép lọt vào danh sách.
        AiModel::create([
            'ai_provider_id' => $activeProvider->id,
            'remote_model_id' => 'gemini-2.5-flash',
            'label' => 'Gemini Text Only',
            'capabilities_json' => [AiModel::CAP_TEXT_GENERATION],
            'is_enabled' => true,
            'is_available' => true,
        ]);
        AiModel::create([
            'ai_provider_id' => $activeProvider->id,
            'remote_model_id' => 'imagen-disabled',
            'label' => 'Imagen Disabled',
            'capabilities_json' => [AiModel::CAP_IMAGE_GENERATION],
            'is_enabled' => false,
            'is_available' => true,
        ]);
        AiModel::create([
            'ai_provider_id' => $inactiveProvider->id,
            'remote_model_id' => 'gpt-image-1',
            'label' => 'Inactive Provider Model',
            'capabilities_json' => [AiModel::CAP_IMAGE_GENERATION],
            'is_enabled' => true,
            'is_available' => true,
        ]);

        $settings = app(AiSettings::class);
        $settings->default_image_model_id = $usableModel->id;
        $settings->save();

        $response = $this->withHeaders($this->bearerHeader($manager))
            ->getJson('/api/v1/admin/media/ai-image/models');

        $response->assertOk()
            ->assertJsonPath('data.default_model_id', $usableModel->id)
            ->assertJsonCount(1, 'data.models')
            ->assertJsonPath('data.models.0.id', $usableModel->id)
            ->assertJsonPath('data.models.0.label', 'Imagen 3 Pro')
            ->assertJsonPath('data.models.0.provider_key', 'gemini-main')
            ->assertJsonPath('data.models.0.is_default', true);
    }

    public function test_generate_validates_payload_and_rejects_non_image_or_disabled_model(): void
    {
        $manager = $this->createUserWithPermissions(['media.view', 'media.manage']);
        $provider = $this->createProvider();

        $textOnlyModel = AiModel::create([
            'ai_provider_id' => $provider->id,
            'remote_model_id' => 'text-model',
            'label' => 'Text Only',
            'capabilities_json' => [AiModel::CAP_TEXT_GENERATION],
            'is_enabled' => true,
            'is_available' => true,
        ]);

        $disabledImageModel = AiModel::create([
            'ai_provider_id' => $provider->id,
            'remote_model_id' => 'disabled-image-model',
            'label' => 'Disabled Image',
            'capabilities_json' => [AiModel::CAP_IMAGE_GENERATION],
            'is_enabled' => false,
            'is_available' => true,
        ]);

        // Thiếu prompt, sai aspect_ratio và sai count -> Laravel 422.
        $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => '   ',
                'aspect_ratio' => '2:3',
                'count' => 3,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt', 'aspect_ratio', 'count']);

        // Chọn model không hỗ trợ image_generation -> 422.
        $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Banner quảng cáo',
                'aspect_ratio' => '1:1',
                'ai_model_id' => $textOnlyModel->id,
                'count' => 1,
            ])
            ->assertStatus(422);

        // Chọn model đang tắt -> 422.
        $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Banner quảng cáo',
                'aspect_ratio' => '1:1',
                'ai_model_id' => $disabledImageModel->id,
                'count' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_generate_dispatches_job_on_configured_queue_and_returns_202(): void
    {
        Queue::fake();
        config([
            'media.ai_image_queue' => 'ai-images',
            'media.ai_image_timeout' => 480,
        ]);

        $manager = $this->createUserWithPermissions(['media.view', 'media.manage']);

        $response = $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Cốc cà phê buổi sáng',
                'aspect_ratio' => '16:9',
                'count' => 2,
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.completed', 0);

        $jobId = $response->json('data.job_id');
        $this->assertNotEmpty($jobId);

        Queue::assertPushedOn('ai-images', function (GenerateAiImageJob $job) use ($jobId, $manager): bool {
            return $job->jobId === $jobId
                && $job->userId === $manager->id
                && $job->timeout === 480
                && ($job->payload['count'] ?? null) === 2;
        });

        config(['media.ai_image_timeout' => 0]);
        $fallbackJob = new GenerateAiImageJob($jobId, $manager->id, ['count' => 1]);
        $this->assertSame(360, $fallbackJob->timeout);
    }

    public function test_async_flow_generates_multiple_previews_streams_signed_urls_and_isolates_owner(): void
    {
        $owner = $this->createUserWithPermissions(['media.view', 'media.manage']);
        $otherManager = $this->createUserWithPermissions(['media.view', 'media.manage']);
        $provider = $this->createProvider(['key' => 'gemini-img']);

        $model = AiModel::create([
            'ai_provider_id' => $provider->id,
            'remote_model_id' => 'imagen-3-fast',
            'label' => 'Imagen 3 Fast',
            'capabilities_json' => [AiModel::CAP_IMAGE_GENERATION],
            'is_enabled' => true,
            'is_available' => true,
        ]);

        $capturedRequests = [];
        $this->bindFakeImageGateway(function (AiImageRequest $req, int $callIndex) use (&$capturedRequests): AiImageResponse {
            $capturedRequests[] = $req;

            return new AiImageResponse(
                images: [[
                    'mime' => 'image/png',
                    'data' => $this->pngBinary(24, 18, 10 + ($callIndex * 40), 80, 160),
                    'width' => null,
                    'height' => null,
                ]],
                revisedPrompt: 'Revised: ' . $req->prompt,
                remoteModelId: $req->modelId,
                providerKey: $req->providerKey,
            );
        });

        $generateResponse = $this->withHeaders($this->bearerHeader($owner))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Poster sự kiện mùa hè',
                'description' => 'Chiến dịch ra mắt sản phẩm',
                'aspect_ratio' => '16:9',
                'ai_model_id' => $model->id,
                'count' => 4,
            ]);

        $generateResponse->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.total', 4);

        $jobId = $generateResponse->json('data.job_id');
        $this->assertCount(4, $capturedRequests);
        $this->assertSame('gemini-img', $capturedRequests[0]->providerKey);
        $this->assertSame('imagen-3-fast', $capturedRequests[0]->modelId);
        $this->assertSame('16:9', $capturedRequests[0]->aspectRatio);

        // User khác không có quyền poll job của owner.
        $this->withHeaders($this->bearerHeader($otherManager))
            ->getJson('/api/v1/admin/media/ai-image/jobs/' . $jobId)
            ->assertStatus(403);

        // UUID không tồn tại trả 404.
        $this->withHeaders($this->bearerHeader($owner))
            ->getJson('/api/v1/admin/media/ai-image/jobs/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);

        $pollResponse = $this->withHeaders($this->bearerHeader($owner))
            ->getJson('/api/v1/admin/media/ai-image/jobs/' . $jobId);

        $pollResponse->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.completed', 4)
            ->assertJsonCount(4, 'data.results')
            ->assertJsonPath('data.results.0.width', 24)
            ->assertJsonPath('data.results.0.height', 18)
            ->assertJsonPath('data.results.0.mime', 'image/png')
            ->assertJsonPath('data.results.0.saved_asset_id', null);

        $firstResult = $pollResponse->json('data.results.0');
        $previewPathOnDisk = 'ai-previews/' . $firstResult['preview_id'] . '.png';
        Storage::disk('temp_processing')->assertExists($previewPathOnDisk);

        // Signed preview URL xem inline hoạt động không cần Bearer token.
        $previewStreamResponse = $this->get($firstResult['preview_url']);
        $previewStreamResponse->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline;', (string) $previewStreamResponse->headers->get('Content-Disposition'));

        // Signed download URL ép tải file attachment.
        $downloadResponse = $this->get($firstResult['download_url']);
        $downloadResponse->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $downloadResponse->headers->get('Content-Disposition'));

        // Chữ ký giả mạo hoặc thiếu chữ ký bị từ chối 403.
        $this->get('/api/v1/admin/media/ai-image/previews/' . $firstResult['preview_id'] . '?expires=9999999999&signature=invalid')
            ->assertStatus(403);
    }

    public function test_save_preview_creates_media_asset_with_ai_meta_is_idempotent_and_checks_owner(): void
    {
        $owner = $this->createUserWithPermissions(['media.view', 'media.manage']);
        $otherManager = $this->createUserWithPermissions(['media.view', 'media.manage']);

        // Đồng thời test contract không cài phương thức lastRun().
        $this->bindFakeImageGateway(function (AiImageRequest $req): AiImageResponse {
            return new AiImageResponse(
                images: [[
                    'mime' => 'image/png',
                    'data' => $this->pngBinary(32, 32, 220, 120, 30),
                ]],
                revisedPrompt: 'Golden coffee cup photo',
                remoteModelId: 'gpt-image-1',
                providerKey: 'openai-main',
            );
        }, withLastRun: false);

        $generateResponse = $this->withHeaders($this->bearerHeader($owner))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Ly cà phê buổi sáng',
                'description' => 'Ảnh minh họa blog',
                'aspect_ratio' => '1:1',
                'count' => 1,
            ])
            ->assertStatus(202);

        $jobId = $generateResponse->json('data.job_id');
        $pollData = $this->withHeaders($this->bearerHeader($owner))
            ->getJson('/api/v1/admin/media/ai-image/jobs/' . $jobId)
            ->assertOk()
            ->json('data');

        $previewId = $pollData['results'][0]['preview_id'];

        // User khác không thể lưu preview của owner.
        $this->withHeaders($this->bearerHeader($otherManager))
            ->postJson('/api/v1/admin/media/ai-image/save', [
                'preview_id' => $previewId,
            ])
            ->assertStatus(403);

        // Preview không tồn tại -> 404.
        $this->withHeaders($this->bearerHeader($owner))
            ->postJson('/api/v1/admin/media/ai-image/save', [
                'preview_id' => '00000000-0000-0000-0000-000000000000',
            ])
            ->assertStatus(404);

        // Owner lưu preview thành MediaAsset.
        $saveResponse = $this->withHeaders($this->bearerHeader($owner))
            ->postJson('/api/v1/admin/media/ai-image/save', [
                'preview_id' => $previewId,
                'folder' => 'ai-banners',
                'title' => 'Banner Cà Phê Sáng',
                'alt_text' => 'Ly cà phê nóng trên bàn gỗ',
                'visibility' => 'public',
            ]);

        $saveResponse->assertOk()
            ->assertJsonPath('data.folder', 'ai-banners')
            ->assertJsonPath('data.title', 'Banner Cà Phê Sáng')
            ->assertJsonPath('data.alt_text', 'Ly cà phê nóng trên bàn gỗ')
            ->assertJsonPath('data.ai_generation.source', 'ai_dialog')
            ->assertJsonPath('data.ai_generation.prompt', 'Ly cà phê buổi sáng')
            ->assertJsonPath('data.ai_generation.aspect_ratio', '1:1')
            ->assertJsonPath('data.ai_generation.preview_id', $previewId);

        $assetId = $saveResponse->json('data.id');
        $this->assertNotNull($assetId);

        // Job tracker được đồng bộ saved_asset_id.
        $this->withHeaders($this->bearerHeader($owner))
            ->getJson('/api/v1/admin/media/ai-image/jobs/' . $jobId)
            ->assertOk()
            ->assertJsonPath('data.results.0.saved_asset_id', $assetId);

        // Lưu lại lần thứ 2 cho cùng preview_id là idempotent (không tạo bản ghi thứ 2).
        $repeatResponse = $this->withHeaders($this->bearerHeader($owner))
            ->postJson('/api/v1/admin/media/ai-image/save', [
                'preview_id' => $previewId,
                'folder' => 'ai-banners',
            ]);

        $repeatResponse->assertOk()
            ->assertJsonPath('data.id', $assetId);
        $this->assertSame(1, MediaAsset::query()->count());
        $this->assertSame(1, AiProvenance::query()->count());
    }

    public function test_assets_index_quick_ai_filter_returns_only_ai_generated_assets(): void
    {
        $manager = $this->createUserWithPermissions(['media.view', 'media.manage']);

        // Upload 1 ảnh thường (không có ai_generation).
        $tmp = tempnam(sys_get_temp_dir(), 'normal_') . '.png';
        file_put_contents($tmp, $this->pngBinary(20, 20, 10, 200, 10));
        $uploadFile = new UploadedFile($tmp, 'normal.png', 'image/png', null, true);

        $this->withHeaders($this->bearerHeader($manager))
            ->post('/api/v1/admin/media/assets', [
                'file' => $uploadFile,
                'title' => 'Ảnh upload thường',
            ])
            ->assertCreated();

        // Tạo & lưu 1 ảnh từ luồng AI.
        $this->bindFakeImageGateway(fn (): AiImageResponse => new AiImageResponse(
            images: [[
                'mime' => 'image/png',
                'data' => $this->pngBinary(28, 28, 240, 60, 80),
            ]],
        ));

        $gen = $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Ảnh quảng cáo sản phẩm',
                'aspect_ratio' => '1:1',
                'count' => 1,
            ])
            ->assertStatus(202)
            ->json('data');

        $poll = $this->withHeaders($this->bearerHeader($manager))
            ->getJson('/api/v1/admin/media/ai-image/jobs/' . $gen['job_id'])
            ->assertOk()
            ->json('data');

        $aiSave = $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/save', [
                'preview_id' => $poll['results'][0]['preview_id'],
            ])
            ->assertOk()
            ->json('data');

        // Filter ?quick=ai chỉ trả về đúng ảnh AI vừa lưu.
        $listResponse = $this->withHeaders($this->bearerHeader($manager))
            ->getJson('/api/v1/admin/media/assets?quick=ai');

        $listResponse->assertOk()
            ->assertJsonPath('data.itemsLength', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $aiSave['id']);
    }

    public function test_job_marks_failed_when_core_ai_gateway_throws_exception(): void
    {
        $manager = $this->createUserWithPermissions(['media.view', 'media.manage']);

        $this->bindFakeImageGateway(function (): AiImageResponse {
            throw AiGatewayException::rateLimit('Vượt giới hạn gọi API từ nhà cung cấp.');
        });

        $gen = $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Banner lỗi rate limit',
                'aspect_ratio' => '4:3',
                'count' => 1,
            ])
            ->assertStatus(202)
            ->json('data');

        $this->withHeaders($this->bearerHeader($manager))
            ->getJson('/api/v1/admin/media/ai-image/jobs/' . $gen['job_id'])
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error', 'Vượt giới hạn gọi API từ nhà cung cấp.')
            ->assertJsonPath('data.error_code', 'rate_limit');
    }

    public function test_save_preview_records_has_ai_provenance_linked_to_ai_run(): void
    {
        $manager = $this->createUserWithPermissions(['media.view', 'media.manage']);

        $aiRun = AiRun::create([
            'module' => 'media',
            'operation' => 'ai_image_dialog',
            'input_json' => ['prompt' => 'Ảnh trà sen'],
            'status' => AiRun::STATUS_READY,
            'initiated_by' => $manager->id,
        ]);

        $png = $this->pngBinary(40, 40, 15, 135, 95);

        $fakeGateway = new class($aiRun, $png) implements CoreAiGatewayContract
        {
            public function __construct(
                private readonly AiRun $run,
                private readonly string $binary,
            ) {}

            public function executeStructured(AiStructuredRequest $req): AiStructuredResponse
            {
                throw new RuntimeException('Not implemented');
            }

            public function generateImage(AiImageRequest $req): AiImageResponse
            {
                return new AiImageResponse(
                    images: [[
                        'mime' => 'image/png',
                        'data' => $this->binary,
                    ]],
                    remoteModelId: 'imagen-3.0',
                    providerKey: 'gemini-main',
                );
            }

            public function synthesizeSpeech(AiSpeechRequest $req): AiSpeechResponse
            {
                throw new RuntimeException('Not implemented');
            }

            public function transcribeAudio(AiTranscribeRequest $req): AiTranscribeResponse
            {
                throw new RuntimeException('Not implemented');
            }

            public function lastRun(): AiRun
            {
                return $this->run;
            }
        };

        $this->app->instance(CoreAiGatewayContract::class, $fakeGateway);

        $gen = $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Ảnh trà sen',
                'aspect_ratio' => '1:1',
                'count' => 1,
            ])
            ->assertStatus(202)
            ->json('data');

        $poll = $this->withHeaders($this->bearerHeader($manager))
            ->getJson('/api/v1/admin/media/ai-image/jobs/' . $gen['job_id'])
            ->assertOk()
            ->json('data');

        $saved = $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/save', [
                'preview_id' => $poll['results'][0]['preview_id'],
                'folder' => 'ai-sen',
            ])
            ->assertOk()
            ->json('data');

        $asset = MediaAsset::query()->findOrFail($saved['id']);

        $this->assertTrue($asset->isAiGenerated('checksum_sha256'));
        $this->assertFalse($asset->aiValueEdited('checksum_sha256'));

        $prov = $asset->aiProvenanceFor('checksum_sha256');
        $this->assertNotNull($prov);
        $this->assertSame($aiRun->id, $prov->ai_run_id);
        $this->assertSame($manager->id, (int) $prov->applied_by);
        $this->assertSame(AiProvenance::hashValue($asset->checksum_sha256), $prov->value_hash);
    }

    public function test_suggest_prompt_requires_media_manage_permission(): void
    {
        $viewer = $this->createUserWithPermissions(['media.view']);

        $this->withHeaders($this->bearerHeader($viewer))
            ->postJson('/api/v1/admin/media/ai-image/suggest-prompt', [
                'description' => 'Cốc trà sen phong cách vẽ tay',
            ])
            ->assertStatus(403);
    }

    public function test_suggest_prompt_requires_description_or_reference_image(): void
    {
        $manager = $this->createUserWithPermissions(['media.manage']);

        $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/suggest-prompt', [
                'description' => '   ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_suggest_prompt_with_description_and_reference_image_calls_structured_gateway(): void
    {
        $manager = $this->createUserWithPermissions(['media.manage']);
        $capturedRequest = null;

        $fakeGateway = new class($capturedRequest) implements CoreAiGatewayContract
        {
            public function __construct(private mixed &$capturedRef) {}

            public function executeStructured(AiStructuredRequest $req): AiStructuredResponse
            {
                $this->capturedRef = $req;

                return new AiStructuredResponse(
                    text: '{"prompt":"Warm artisanal ceramic cup of lotus tea matching the pastel palette, watercolor editorial illustration"}',
                    data: [
                        'prompt' => 'Warm artisanal ceramic cup of lotus tea matching the pastel palette, watercolor editorial illustration',
                    ],
                    remoteModelId: 'gpt-6.1-sol',
                    providerKey: 'apikey-fan',
                );
            }

            public function generateImage(AiImageRequest $req): AiImageResponse
            {
                throw new RuntimeException('Not implemented');
            }

            public function synthesizeSpeech(AiSpeechRequest $req): AiSpeechResponse
            {
                throw new RuntimeException('Not implemented');
            }

            public function transcribeAudio(AiTranscribeRequest $req): AiTranscribeResponse
            {
                throw new RuntimeException('Not implemented');
            }
        };

        $this->app->instance(CoreAiGatewayContract::class, $fakeGateway);

        $refPng = $this->pngBinary(120, 80, 210, 150, 90);
        $uploadedRef = UploadedFile::fake()->createWithContent('reference.png', $refPng);

        $res = $this->withHeaders($this->bearerHeader($manager))
            ->post('/api/v1/admin/media/ai-image/suggest-prompt', [
                'description' => 'Giữ dáng cốc trong ảnh mẫu, đổi sang nền kem ấm',
                'reference_image' => $uploadedRef,
                'style' => 'watercolor',
                'aspect_ratio' => '4:3',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data');

        $this->assertNotNull($capturedRequest);
        $this->assertInstanceOf(AiStructuredRequest::class, $capturedRequest);
        $this->assertSame('vision', $capturedRequest->requiredCapability());
        $this->assertCount(1, $capturedRequest->attachments);
        $this->assertSame('image/png', $capturedRequest->attachments[0]['mime']);
        $this->assertSame(base64_encode($refPng), $capturedRequest->attachments[0]['data']);
        $this->assertSame('system', $capturedRequest->messages[0]['role']);
        $this->assertSame(trim(str_replace(["\r\n", "\r"], "\n", (string) config('media.prompt.ai_image_suggest.system'))), $capturedRequest->messages[0]['content']);
        $decodedUser = json_decode((string) $capturedRequest->messages[1]['content'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'task' => 'suggest_image_generation_prompt',
            'has_reference_image' => true,
            'description' => 'Giữ dáng cốc trong ảnh mẫu, đổi sang nền kem ấm',
            'style' => 'watercolor',
            'style_guidance' => config('media.prompt.ai_image_suggest.styles.watercolor'),
            'aspect_ratio' => '4:3',
        ], $decodedUser);
        $this->assertSame('gpt-6.1-sol', $res['model']);
        $this->assertSame('apikey-fan', $res['provider']);
        $this->assertTrue($res['has_reference_image']);
        $this->assertStringContainsString('ceramic cup of lotus tea', $res['prompt']);
    }

    public function test_suggest_prompt_with_description_only_works_without_attachment(): void
    {
        $manager = $this->createUserWithPermissions(['media.manage']);
        $capturedRequest = null;

        $fakeGateway = new class($capturedRequest) implements CoreAiGatewayContract
        {
            public function __construct(private mixed &$capturedRef) {}

            public function executeStructured(AiStructuredRequest $req): AiStructuredResponse
            {
                $this->capturedRef = $req;

                return new AiStructuredResponse(
                    text: '{"prompt":"Minimalist matcha drink poster with crisp studio daylight"}',
                    data: ['prompt' => 'Minimalist matcha drink poster with crisp studio daylight'],
                    remoteModelId: 'gpt-6.1-sol',
                    providerKey: 'apikey-fan',
                );
            }

            public function generateImage(AiImageRequest $req): AiImageResponse
            {
                throw new RuntimeException('Not implemented');
            }

            public function synthesizeSpeech(AiSpeechRequest $req): AiSpeechResponse
            {
                throw new RuntimeException('Not implemented');
            }

            public function transcribeAudio(AiTranscribeRequest $req): AiTranscribeResponse
            {
                throw new RuntimeException('Not implemented');
            }
        };

        $this->app->instance(CoreAiGatewayContract::class, $fakeGateway);

        $res = $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/suggest-prompt', [
                'description' => 'Poster trà matcha tối giản',
                'style' => 'minimal',
                'aspect_ratio' => '1:1',
            ])
            ->assertOk()
            ->json('data');

        $this->assertInstanceOf(AiStructuredRequest::class, $capturedRequest);
        $this->assertSame('structured_output', $capturedRequest->requiredCapability());
        $this->assertSame([], $capturedRequest->attachments);
        $this->assertFalse($res['has_reference_image']);
        $this->assertSame('Minimalist matcha drink poster with crisp studio daylight', $res['prompt']);
    }

    public function test_suggest_prompt_reads_base_prompts_and_styles_from_media_prompt_config(): void
    {
        $manager = $this->createUserWithPermissions(['media.manage']);
        $capturedRequest = null;

        config()->set('media.prompt.ai_image_suggest.system', <<<'PROMPT'
CUSTOM_SYSTEM_NOWDOC_FOR_TEST
Chỉ trả JSON đúng schema.
PROMPT);
        config()->set('media.prompt.ai_image_suggest.styles.watercolor', 'Custom Watercolor Style');

        $fakeGateway = new class($capturedRequest) implements CoreAiGatewayContract
        {
            public function __construct(private mixed &$capturedRef) {}

            public function executeStructured(AiStructuredRequest $req): AiStructuredResponse
            {
                $this->capturedRef = $req;

                return new AiStructuredResponse(
                    text: '{"prompt":"Custom configured editorial watercolor drink scene"}',
                    data: ['prompt' => 'Custom configured editorial watercolor drink scene'],
                    remoteModelId: 'gpt-6.1-sol',
                    providerKey: 'apikey-fan',
                );
            }

            public function generateImage(AiImageRequest $req): AiImageResponse
            {
                throw new RuntimeException('Not implemented');
            }

            public function synthesizeSpeech(AiSpeechRequest $req): AiSpeechResponse
            {
                throw new RuntimeException('Not implemented');
            }

            public function transcribeAudio(AiTranscribeRequest $req): AiTranscribeResponse
            {
                throw new RuntimeException('Not implemented');
            }
        };

        $this->app->instance(CoreAiGatewayContract::class, $fakeGateway);

        $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/suggest-prompt', [
                'description' => 'Trà vải hoa hồng',
                'style' => 'watercolor',
                'aspect_ratio' => '4:3',
            ])
            ->assertOk();

        $this->assertInstanceOf(AiStructuredRequest::class, $capturedRequest);
        $this->assertSame("CUSTOM_SYSTEM_NOWDOC_FOR_TEST\nChỉ trả JSON đúng schema.", $capturedRequest->messages[0]['content']);
        $decodedUser = json_decode((string) $capturedRequest->messages[1]['content'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'task' => 'suggest_image_generation_prompt',
            'has_reference_image' => false,
            'description' => 'Trà vải hoa hồng',
            'style' => 'watercolor',
            'style_guidance' => 'Custom Watercolor Style',
            'aspect_ratio' => '4:3',
        ], $decodedUser);
    }

    public function test_generate_with_reference_image_forwards_reference_to_gateway_and_cleans_up_temp_file(): void
    {
        $manager = $this->createUserWithPermissions(['media.manage']);
        $capturedImageRequest = null;
        $refPng = $this->pngBinary(64, 64, 180, 90, 60);
        $outPng = $this->pngBinary(64, 64, 40, 120, 200);

        $this->bindFakeImageGateway(function (AiImageRequest $req) use (&$capturedImageRequest, $outPng): AiImageResponse {
            $capturedImageRequest = $req;

            return new AiImageResponse(
                images: [['mime' => 'image/png', 'data' => $outPng]],
                remoteModelId: 'gpt-image-2',
                providerKey: 'apikey-image',
            );
        });

        $uploadedRef = UploadedFile::fake()->createWithContent('ref-subject.png', $refPng);

        $job = $this->withHeaders($this->bearerHeader($manager))
            ->post('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Keep the cup design on a sunlit wooden table',
                'aspect_ratio' => '16:9',
                'count' => 1,
                'reference_image' => $uploadedRef,
            ], ['Accept' => 'application/json'])
            ->assertStatus(202)
            ->json('data');

        $this->assertInstanceOf(AiImageRequest::class, $capturedImageRequest);
        $this->assertTrue($capturedImageRequest->hasReferences());
        $this->assertCount(1, $capturedImageRequest->referenceImages);
        $this->assertSame('image/png', $capturedImageRequest->referenceImages[0]['mime']);
        $this->assertSame($refPng, $capturedImageRequest->referenceImages[0]['data']);
        $this->assertSame([], Storage::disk('temp_processing')->files('ai-references'));

        $this->withHeaders($this->bearerHeader($manager))
            ->getJson('/api/v1/admin/media/ai-image/jobs/' . $job['job_id'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_generate_and_save_persist_style_model_label_description_and_tags(): void
    {
        $manager = $this->createUserWithPermissions(['media.view', 'media.manage']);
        $provider = $this->createProvider(['key' => 'gemini-main', 'name' => 'Gemini Chính']);
        $model = AiModel::create([
            'ai_provider_id' => $provider->id,
            'remote_model_id' => 'imagen-3.0',
            'label' => 'Imagen 3 Studio',
            'capabilities_json' => [AiModel::CAP_IMAGE_GENERATION],
            'is_enabled' => true,
            'is_available' => true,
        ]);

        $outPng = $this->pngBinary(64, 64, 40, 120, 200);
        $this->bindFakeImageGateway(fn (): AiImageResponse => new AiImageResponse(
            images: [['mime' => 'image/png', 'data' => $outPng]],
            remoteModelId: 'imagen-3.0',
            providerKey: 'gemini-main',
        ));

        $job = $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/generate', [
                'prompt' => 'Minimal studio ceramic vase in warm daylight',
                'description' => '  Ảnh banner mùa hè tối giản  ',
                'style' => 'minimal',
                'aspect_ratio' => '16:9',
                'ai_model_id' => $model->id,
                'count' => 1,
            ])
            ->assertStatus(202)
            ->json('data');

        $previewId = $this->withHeaders($this->bearerHeader($manager))
            ->getJson('/api/v1/admin/media/ai-image/jobs/' . $job['job_id'])
            ->assertOk()
            ->json('data.results.0.preview_id');

        $saved = $this->withHeaders($this->bearerHeader($manager))
            ->postJson('/api/v1/admin/media/ai-image/save', [
                'preview_id' => $previewId,
                'title' => 'Ceramic Vase Minimal',
                'tags' => [' mùa hè ', 'Banner', 'banner'],
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Ảnh banner mùa hè tối giản')
            ->assertJsonPath('data.tags', ['mùa hè', 'Banner'])
            ->assertJsonPath('data.ai_generation.style', 'minimal')
            ->assertJsonPath('data.ai_generation.model_label', 'Imagen 3 Studio')
            ->assertJsonPath('data.ai_generation.model', 'imagen-3.0')
            ->json('data');

        $this->withHeaders($this->bearerHeader($manager))
            ->getJson('/api/v1/admin/media/assets/' . $saved['id'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Ảnh banner mùa hè tối giản')
            ->assertJsonPath('data.tags', ['mùa hè', 'Banner'])
            ->assertJsonPath('data.ai_generation.style', 'minimal')
            ->assertJsonPath('data.ai_generation.model_label', 'Imagen 3 Studio');
    }
}
