<?php

namespace Modules\Media\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Settings\AiSettings;
use Modules\Media\Actions\GenerateAiPreviewImagesAction;
use Modules\Media\Actions\SaveAiPreviewToMediaAction;
use Modules\Media\Http\Requests\GenerateAiImageRequest;
use Modules\Media\Http\Requests\SaveGeneratedImageRequest;
use Modules\Media\Http\Resources\MediaAssetResource;
use Modules\Media\Jobs\GenerateAiImageJob;
use Modules\Media\Services\AiImagePreviewStore;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: API điều phối sinh ảnh AI preview và lưu vào Media Library.
 * =====================================================================
 *
 * Phục vụ dialog `AiGenerateImageDialog` thông qua cơ chế bất đồng bộ:
 * - GET  ai-image/models               : lấy danh sách model image_generation khả dụng
 * - POST ai-image/generate             : tiếp nhận yêu cầu (202 Accepted) & dispatch job
 * - GET  ai-image/jobs/{jobId}         : polling trạng thái và kết quả signed preview
 * - GET  ai-image/previews/{previewId} : stream nhị phân ảnh preview qua signed URL
 * - POST ai-image/save                 : lưu ảnh preview được chọn thành MediaAsset
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : HTTP requests quản trị có quyền `media.manage` (hoặc signed URL cho preview).
 * - OUTPUT: JSON theo chuẩn BaseResponse hoặc StreamedResponse ảnh.
 * =====================================================================
 */
class MediaAiImageController extends Controller
{
    public function __construct(
        private readonly AiImagePreviewStore $store,
    ) {}

    /**
     * =====================================================================
     * GET /api/v1/admin/media/ai-image/models
     * =====================================================================
     * CHỨC NĂNG: Trả danh sách model AI tạo ảnh đang bật + model mặc định
     * INPUT: HTTP Request (đã qua permission:media.manage).
     * OUTPUT: BaseResponse::success { models: array, default_model_id: ?int }.
     * =====================================================================
     */
    public function models(): JsonResponse
    {
        $models = AiModel::query()
            ->with('provider:id,key,name,driver')
            ->usable()
            ->withCapability(AiModel::CAP_IMAGE_GENERATION)
            ->orderBy('id')
            ->get();

        $configuredDefaultId = null;
        try {
            $configuredDefaultId = app(AiSettings::class)->default_image_model_id;
        } catch (Throwable) {
            // Giữ null nếu bảng settings chưa seed trong môi trường test tối giản.
            $configuredDefaultId = null;
        }

        $validDefaultId = ($configuredDefaultId !== null && $models->contains('id', $configuredDefaultId))
            ? (int) $configuredDefaultId
            : null;

        $items = $models->map(function (AiModel $model) use ($validDefaultId): array {
            return [
                'id' => $model->id,
                'provider_id' => $model->ai_provider_id,
                'provider_key' => $model->provider?->key,
                'provider_name' => $model->provider?->name,
                'provider_driver' => $model->provider?->driver,
                'remote_model_id' => $model->remote_model_id,
                'name' => $model->label,
                'label' => $model->label,
                'capabilities' => $model->capabilities(),
                'is_default' => $validDefaultId !== null && $model->id === $validDefaultId,
            ];
        })->values()->all();

        return BaseResponse::success([
            'models' => $items,
            'default_model_id' => $validDefaultId,
        ], 'Lấy danh sách model AI tạo ảnh thành công.');
    }

    /**
     * =====================================================================
     * POST /api/v1/admin/media/ai-image/generate
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo job nền tạo ảnh preview AI (HTTP 202 Accepted)
     * INPUT: GenerateAiImageRequest (prompt, description?, aspect_ratio, ai_model_id?, count).
     * OUTPUT: BaseResponse::accepted tracker job ban đầu {job_id, status, total, completed, results}.
     * SIDE EFFECT:
     * - ghi Cache tracker `ai_image_job:{jobId}`
     * - dispatch GenerateAiImageJob vào queue `media.ai_image_queue`
     * =====================================================================
     */
    public function generate(
        GenerateAiImageRequest $request,
        GenerateAiPreviewImagesAction $action,
    ): JsonResponse {
        $data = $request->validated();

        try {
            $selectedModel = $action->resolveUsableImageModel(
                isset($data['ai_model_id']) ? (int) $data['ai_model_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return BaseResponse::error($e->getMessage(), 422);
        }

        $jobId = (string) Str::uuid();
        $userId = (int) $request->user()->id;
        $count = (int) $data['count'];

        $payload = [
            'prompt' => $data['prompt'],
            'description' => $data['description'] ?? null,
            'aspect_ratio' => $data['aspect_ratio'],
            'ai_model_id' => $selectedModel?->id,
            'provider_key' => $selectedModel?->provider?->key,
            'remote_model_id' => $selectedModel?->remote_model_id,
            'count' => $count,
        ];

        $initialJob = $this->store->initJob($jobId, $userId, $count, [
            'aspect_ratio' => $data['aspect_ratio'],
            'ai_model_id' => $selectedModel?->id,
        ]);

        GenerateAiImageJob::dispatch($jobId, $userId, $payload);

        return BaseResponse::accepted(
            $this->store->toPublicJobPayload($initialJob),
            'Đã tiếp nhận yêu cầu tạo ảnh AI, đang xử lý nền.',
        );
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/ai-image/jobs/{jobId}
     * =====================================================================
     * CHỨC NĂNG: Polling tiến độ & danh sách ảnh preview của job tạo ảnh AI
     * INPUT: string $jobId (UUID), HTTP Request (kiểm tra user sở hữu job).
     * OUTPUT: BaseResponse::success payload tracker hoặc 404/403 nếu không hợp lệ.
     * =====================================================================
     */
    public function status(string $jobId, Request $request): JsonResponse
    {
        if (! Str::isUuid($jobId)) {
            return BaseResponse::error('Mã job tạo ảnh không hợp lệ.', 404);
        }

        $job = $this->store->getJob($jobId);
        if ($job === null) {
            return BaseResponse::error('Job tạo ảnh không tồn tại hoặc đã hết hạn.', 404);
        }

        if ((int) ($job['user_id'] ?? 0) !== (int) $request->user()->id) {
            return BaseResponse::error('Bạn không có quyền truy cập job tạo ảnh này.', 403);
        }

        return BaseResponse::success(
            $this->store->toPublicJobPayload($job),
            'Lấy trạng thái tạo ảnh AI thành công.',
        );
    }

    /**
     * =====================================================================
     * GET /api/v1/admin/media/ai-image/previews/{previewId}
     * =====================================================================
     * CHỨC NĂNG: Stream nhị phân ảnh preview tạm qua signed URL (?download=1)
     * INPUT: string $previewId (đã kiểm chữ ký qua middleware `signed:relative`).
     * OUTPUT: StreamedResponse ảnh nhị phân an toàn hoặc 404 JSON nếu hết hạn.
     * =====================================================================
     */
    public function preview(string $previewId, Request $request): StreamedResponse|JsonResponse
    {
        if (! Str::isUuid($previewId)) {
            return BaseResponse::error('Mã preview ảnh không hợp lệ.', 404);
        }

        $preview = $this->store->getPreview($previewId);
        if ($preview === null) {
            return BaseResponse::error('Ảnh preview không tồn tại hoặc đã hết hạn.', 404);
        }

        $path = (string) ($preview['path'] ?? '');
        $disk = $this->store->disk();
        if ($path === '' || ! $disk->exists($path)) {
            return BaseResponse::error('File ảnh preview tạm không còn trên hệ thống.', 404);
        }

        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            return BaseResponse::error('Không mở được luồng đọc ảnh preview tạm.', 404);
        }

        $safeMime = $this->store->safeStreamMime((string) ($preview['mime'] ?? 'image/png'));
        $ext = $this->store->extensionForMime($safeMime);
        $download = $request->boolean('download');
        $filename = 'ai-preview-' . substr($previewId, 0, 8) . '.' . $ext;

        $headers = [
            'Content-Type' => $safeMime,
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . addslashes($filename) . '"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'",
        ];

        if (isset($preview['size'])) {
            $headers['Content-Length'] = (string) $preview['size'];
        }

        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }

    /**
     * =====================================================================
     * POST /api/v1/admin/media/ai-image/save
     * =====================================================================
     * CHỨC NĂNG: Lưu một ảnh preview AI tạm thành MediaAsset chính thức
     * INPUT: SaveGeneratedImageRequest, SaveAiPreviewToMediaAction.
     * OUTPUT: BaseResponse::success MediaAssetResource (có idempotency & dedupe SHA-256).
     * =====================================================================
     */
    public function save(
        SaveGeneratedImageRequest $request,
        SaveAiPreviewToMediaAction $action,
    ): JsonResponse {
        $data = $request->validated();

        try {
            $asset = $action->execute(
                $data['preview_id'],
                (int) $request->user()->id,
                $data,
            );
        } catch (NotFoundHttpException $e) {
            return BaseResponse::error($e->getMessage(), 404);
        } catch (AccessDeniedHttpException $e) {
            return BaseResponse::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            return BaseResponse::error($e->getMessage(), 422);
        }

        return BaseResponse::success(
            new MediaAssetResource($asset),
            'Lưu ảnh AI vào thư viện thành công.',
        );
    }
}
