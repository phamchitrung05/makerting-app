<?php

namespace Modules\Media\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Models\AiModel;
use Modules\Media\Services\AiImagePreviewStore;
use RuntimeException;
use Throwable;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Gọi CoreAi sinh ảnh preview tạm và cập nhật tiến độ Cache.
 * =====================================================================
 *
 * Toàn bộ lời gọi AI đi qua CoreAiGatewayContract::generateImage() để tái sử dụng
 * ModelResolver, Driver OpenAI/Gemini/Xpiki và AiUsageLog/AiRun của Modules/CoreAi.
 * Ảnh sinh ra được kiểm tra kích thước, lưu xuống disk `temp_processing`, gắn
 * signed URL preview tạm và cập nhật liên tục vào Cache cho luồng polling UI.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - resolveUsableImageModel(?int $aiModelId): kiểm tra model AI tạo ảnh hợp lệ
 * - execute(string $jobId, int $userId, array $payload): gọi AI lặp đủ count ảnh
 * - storeGeneratedBinary(...): lưu 1 ảnh vào temp_processing + Cache preview
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : jobId, userId, payload (prompt, description, aspect_ratio, ai_model_id, count...).
 * - OUTPUT: mảng trạng thái job tracker sau khi hoàn tất (`ready`) hoặc lỗi (`failed`).
 * =====================================================================
 */
class GenerateAiPreviewImagesAction
{
    public function __construct(
        private readonly CoreAiGatewayContract $gateway,
        private readonly AiImagePreviewStore $store,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra model AI do UI chọn có sẵn sàng tạo ảnh hay không
     * =====================================================================
     * INPUT: ?int $aiModelId (null nếu dùng model mặc định của CoreAi).
     * OUTPUT: ?AiModel đã eager load provider.
     * EXCEPTION: InvalidArgumentException nếu model không active hoặc sai capability.
     * =====================================================================
     */
    public function resolveUsableImageModel(?int $aiModelId): ?AiModel
    {
        if ($aiModelId === null) {
            return null;
        }

        $model = AiModel::query()
            ->with('provider')
            ->usable()
            ->withCapability(AiModel::CAP_IMAGE_GENERATION)
            ->find($aiModelId);

        if (! $model) {
            throw new InvalidArgumentException('Model AI đã chọn không khả dụng hoặc không hỗ trợ tạo ảnh.');
        }

        return $model;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Thực thi gọi CoreAiGatewayContract đủ số lượng ảnh và lưu tạm
     * =====================================================================
     * INPUT:
     * - string $jobId: UUID tracker job
     * - int $userId: chủ sở hữu job & preview
     * - array $payload: {prompt, description, aspect_ratio, ai_model_id, provider_key, remote_model_id, count}
     * OUTPUT: array trạng thái job cuối (`ready` hoặc `failed`).
     * SIDE EFFECT:
     * - gọi CoreAiGatewayContract::generateImage() (ghi AiRun/AiUsageLog)
     * - ghi file nhị phân ảnh vào disk `temp_processing`
     * - ghi Cache `ai_image_preview:{previewId}` và cập nhật `ai_image_job:{jobId}`
     * =====================================================================
     */
    public function execute(string $jobId, int $userId, array $payload): array
    {
        $this->store->markJobProcessing($jobId);

        $count = max(1, (int) ($payload['count'] ?? 1));
        $collectedCount = 0;
        $attempts = 0;

        try {
            while ($collectedCount < $count && $attempts < $count) {
                $attempts++;

                $request = new AiImageRequest(
                    prompt: (string) $payload['prompt'],
                    negativePrompt: null,
                    aspectRatio: (string) ($payload['aspect_ratio'] ?? '1:1'),
                    referenceImages: [],
                    options: [],
                    providerKey: $payload['provider_key'] ?? null,
                    modelId: $payload['remote_model_id'] ?? null,
                    module: 'media',
                    operation: 'ai_image_dialog',
                );

                $response = $this->gateway->generateImage($request);
                $aiRunId = method_exists($this->gateway, 'lastRun')
                    ? $this->gateway->lastRun()?->id
                    : null;

                if (empty($response->images)) {
                    throw new RuntimeException('Model AI không trả về dữ liệu ảnh hợp lệ.');
                }

                foreach ($response->images as $image) {
                    if ($collectedCount >= $count) {
                        break;
                    }

                    $binary = (string) ($image['data'] ?? '');
                    if ($binary === '') {
                        continue;
                    }

                    $previewItem = $this->storeGeneratedBinary(
                        jobId: $jobId,
                        userId: $userId,
                        binary: $binary,
                        mime: (string) ($image['mime'] ?? 'image/png'),
                        width: isset($image['width']) ? (int) $image['width'] : null,
                        height: isset($image['height']) ? (int) $image['height'] : null,
                        payload: $payload,
                        aiRunId: $aiRunId,
                        providerKey: $response->providerKey ?? ($payload['provider_key'] ?? null),
                        remoteModelId: $response->remoteModelId ?? ($payload['remote_model_id'] ?? null),
                        revisedPrompt: $response->revisedPrompt,
                    );

                    $this->store->appendJobResult($jobId, $previewItem);
                    $collectedCount++;
                }
            }

            if ($collectedCount === 0) {
                throw new RuntimeException('Model AI không trả về dữ liệu ảnh hợp lệ.');
            }

            return $this->store->markJobReady($jobId) ?? [];
        } catch (AiGatewayException $e) {
            Log::warning('AI image preview generation failed via CoreAi', [
                'job_id' => $jobId,
                'user_id' => $userId,
                'error_code' => $e->errorCode(),
                'message' => $e->getMessage(),
            ]);

            return $this->store->markJobFailed($jobId, $e->getMessage(), $e->errorCode()) ?? [];
        } catch (Throwable $e) {
            Log::error('Unexpected error generating AI image preview', [
                'job_id' => $jobId,
                'user_id' => $userId,
                'exception' => $e,
            ]);

            return $this->store->markJobFailed(
                $jobId,
                'Không thể tạo ảnh từ AI: ' . $e->getMessage(),
                'ai_preview_generation_failed',
            ) ?? [];
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lưu nhị phân một ảnh preview ra disk tạm & ghi Cache metadata
     * =====================================================================
     * INPUT: dữ liệu binary ảnh, kích thước, context AI & jobId/userId.
     * OUTPUT: array item công khai đưa vào `results` của job tracker.
     * SIDE EFFECT: ghi file trên disk `temp_processing` + Cache `ai_image_preview:{previewId}`.
     * =====================================================================
     */
    private function storeGeneratedBinary(
        string $jobId,
        int $userId,
        string $binary,
        string $mime,
        ?int $width,
        ?int $height,
        array $payload,
        int|string|null $aiRunId,
        ?string $providerKey,
        ?string $remoteModelId,
        ?string $revisedPrompt,
    ): array {
        if ($width === null || $height === null || $mime === '') {
            $detected = @getimagesizefromstring($binary);
            if ($detected !== false) {
                $width = $width ?? (int) $detected[0];
                $height = $height ?? (int) $detected[1];
                if ($mime === '' && ! empty($detected['mime'])) {
                    $mime = (string) $detected['mime'];
                }
            }
        }

        $safeMime = $mime !== '' ? $mime : 'image/png';
        $previewId = (string) Str::uuid();
        $relativePath = $this->store->relativePathForPreview($previewId, $safeMime);
        $size = strlen($binary);

        $this->store->disk()->put($relativePath, $binary);

        $this->store->putPreview($previewId, [
            'preview_id' => $previewId,
            'job_id' => $jobId,
            'user_id' => $userId,
            'disk' => AiImagePreviewStore::DISK,
            'path' => $relativePath,
            'mime' => $safeMime,
            'width' => $width,
            'height' => $height,
            'size' => $size,
            'ai_model_id' => $payload['ai_model_id'] ?? null,
            'provider' => $providerKey,
            'model' => $remoteModelId,
            'ai_run_id' => $aiRunId,
            'prompt' => $payload['prompt'],
            'description' => $payload['description'] ?? null,
            'aspect_ratio' => $payload['aspect_ratio'] ?? '1:1',
            'revised_prompt' => $revisedPrompt,
            'saved_asset_id' => null,
            'created_at' => now()->toIso8601String(),
        ]);

        return [
            'preview_id' => $previewId,
            'preview_url' => $this->store->signedPreviewUrl($previewId, false),
            'download_url' => $this->store->signedPreviewUrl($previewId, true),
            'mime' => $safeMime,
            'width' => $width,
            'height' => $height,
            'size' => $size,
            'saved_asset_id' => null,
        ];
    }
}
