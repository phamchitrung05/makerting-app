<?php

namespace Modules\Media\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Quản lý Cache tracker, file tạm & signed URL cho AI preview.
 * =====================================================================
 *
 * Tập trung toàn bộ key Cache (`ai_image_job:{jobId}`, `ai_image_preview:{previewId}`),
 * cấu hình TTL (`media.ai_preview_ttl_minutes`), disk `temp_processing`, mapping MIME
 * sang đuôi file an toàn và sinh URL signed tương đối cho preview/download ảnh.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - initJob() / getJob() / markJobProcessing() / appendJobResult() / markJobReady() / markJobFailed()
 * - toPublicJobPayload(): format dữ liệu job sạch trả về API
 * - putPreview() / getPreview() / markPreviewSaved()
 * - signedPreviewUrl() / relativePathForPreview() / extensionForMime() / safeStreamMime()
 * - disk() / ttlMinutes() / expiresAt()
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : jobId, previewId, userId, metadata job/preview, binary path trên disk tạm.
 * - OUTPUT: payload tracker/preview chuẩn hóa + signed URL preview an toàn.
 * =====================================================================
 */
class AiImagePreviewStore
{
    public const DISK = 'temp_processing';

    private const JOB_KEY_PREFIX = 'ai_image_job:';

    private const PREVIEW_KEY_PREFIX = 'ai_image_preview:';

    private const SAFE_STREAM_MIMES = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    /**
     * CHỨC NĂNG: Trả về TTL (phút) cấu hình cho job/preview AI tạm.
     * INPUT: không.
     * OUTPUT: int >= 1 (mặc định 60 phút từ config media.ai_preview_ttl_minutes).
     */
    public function ttlMinutes(): int
    {
        return max(1, (int) config('media.ai_preview_ttl_minutes', 60));
    }

    /**
     * CHỨC NĂNG: Tính mốc thời gian hết hạn cho Cache và signed URL.
     * INPUT: không.
     * OUTPUT: CarbonInterface.
     */
    public function expiresAt(): CarbonInterface
    {
        return now()->addMinutes($this->ttlMinutes());
    }

    /**
     * CHỨC NĂNG: Lấy instance Filesystem cho vùng lưu ảnh preview AI tạm.
     * INPUT: không.
     * OUTPUT: Filesystem (disk temp_processing).
     */
    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo trạng thái ban đầu cho job tạo ảnh AI trong Cache
     * =====================================================================
     * INPUT:
     * - $jobId: UUID của job
     * - $userId: ID user gửi yêu cầu (kiểm ownership khi poll)
     * - $total: tổng số ảnh yêu cầu (1, 2 hoặc 4)
     * - $meta: thông tin ngữ cảnh bổ sung
     * OUTPUT: array trạng thái job vừa lưu.
     * SIDE EFFECT: ghi Cache key `ai_image_job:{jobId}`.
     * =====================================================================
     */
    public function initJob(string $jobId, int $userId, int $total, array $meta = []): array
    {
        $job = [
            'job_id' => $jobId,
            'user_id' => $userId,
            'status' => 'queued',
            'total' => $total,
            'completed' => 0,
            'results' => [],
            'error' => null,
            'error_code' => null,
            'meta' => $meta,
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->jobKey($jobId), $job, $this->expiresAt());

        return $job;
    }

    /**
     * CHỨC NĂNG: Đọc trạng thái job tạo ảnh AI từ Cache.
     * INPUT: string $jobId.
     * OUTPUT: ?array (null nếu job không tồn tại hoặc đã hết TTL).
     */
    public function getJob(string $jobId): ?array
    {
        $job = Cache::get($this->jobKey($jobId));

        return is_array($job) ? $job : null;
    }

    /**
     * CHỨC NĂNG: Đánh dấu job đang xử lý (`processing`).
     * INPUT: string $jobId.
     * OUTPUT: ?array.
     * SIDE EFFECT: cập nhật Cache key `ai_image_job:{jobId}`.
     */
    public function markJobProcessing(string $jobId): ?array
    {
        return $this->updateJob($jobId, ['status' => 'processing']);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gắn thêm một ảnh preview hoàn tất vào danh sách kết quả của job
     * =====================================================================
     * INPUT: string $jobId, array $previewItem (metadata công khai trả cho UI).
     * OUTPUT: ?array trạng thái job sau khi tăng completed.
     * SIDE EFFECT: cập nhật Cache key `ai_image_job:{jobId}`.
     * =====================================================================
     */
    public function appendJobResult(string $jobId, array $previewItem): ?array
    {
        $job = $this->getJob($jobId);
        if ($job === null) {
            return null;
        }

        $results = is_array($job['results'] ?? null) ? $job['results'] : [];
        $results[] = $previewItem;

        return $this->updateJob($jobId, [
            'results' => $results,
            'completed' => count($results),
        ]);
    }

    /**
     * CHỨC NĂNG: Đánh dấu job đã sinh xong toàn bộ ảnh (`ready`).
     * INPUT: string $jobId.
     * OUTPUT: ?array.
     * SIDE EFFECT: cập nhật Cache key `ai_image_job:{jobId}`.
     */
    public function markJobReady(string $jobId): ?array
    {
        return $this->updateJob($jobId, ['status' => 'ready']);
    }

    /**
     * CHỨC NĂNG: Đánh dấu job thất bại (`failed`) kèm thông điệp & mã lỗi.
     * INPUT: string $jobId, string $message, ?string $errorCode.
     * OUTPUT: ?array.
     * SIDE EFFECT: cập nhật Cache key `ai_image_job:{jobId}`.
     */
    public function markJobFailed(string $jobId, string $message, ?string $errorCode = null): ?array
    {
        return $this->updateJob($jobId, [
            'status' => 'failed',
            'error' => $message,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * CHỨC NĂNG: Chuyển payload job nội bộ thành JSON public an toàn cho API response.
     * INPUT: array $job.
     * OUTPUT: array {job_id, status, total, completed, results, error, error_code}.
     */
    public function toPublicJobPayload(array $job): array
    {
        return [
            'job_id' => $job['job_id'],
            'status' => $job['status'],
            'total' => (int) ($job['total'] ?? 0),
            'completed' => (int) ($job['completed'] ?? 0),
            'results' => array_values($job['results'] ?? []),
            'error' => $job['error'] ?? null,
            'error_code' => $job['error_code'] ?? null,
        ];
    }

    /**
     * CHỨC NĂNG: Lưu metadata của một ảnh preview AI tạm vào Cache.
     * INPUT: string $previewId, array $previewMeta.
     * OUTPUT: array metadata vừa lưu.
     * SIDE EFFECT: ghi Cache key `ai_image_preview:{previewId}`.
     */
    public function putPreview(string $previewId, array $previewMeta): array
    {
        Cache::put($this->previewKey($previewId), $previewMeta, $this->expiresAt());

        return $previewMeta;
    }

    /**
     * CHỨC NĂNG: Đọc metadata ảnh preview AI tạm từ Cache.
     * INPUT: string $previewId.
     * OUTPUT: ?array (null nếu không tồn tại hoặc hết TTL).
     */
    public function getPreview(string $previewId): ?array
    {
        $preview = Cache::get($this->previewKey($previewId));

        return is_array($preview) ? $preview : null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gắn `saved_asset_id` vào Cache preview và đồng bộ về job tracker
     * =====================================================================
     * INPUT: string $previewId, int $assetId.
     * OUTPUT: ?array metadata preview đã cập nhật.
     * SIDE EFFECT: cập nhật Cache `ai_image_preview:{previewId}` và item tương ứng
     *              trong `ai_image_job:{jobId}` (nếu job còn trong Cache).
     * =====================================================================
     */
    public function markPreviewSaved(string $previewId, int $assetId): ?array
    {
        $preview = $this->getPreview($previewId);
        if ($preview === null) {
            return null;
        }

        $preview['saved_asset_id'] = $assetId;
        $this->putPreview($previewId, $preview);

        $jobId = $preview['job_id'] ?? null;
        if (is_string($jobId) && $jobId !== '') {
            $job = $this->getJob($jobId);
            if ($job !== null && is_array($job['results'] ?? null)) {
                $job['results'] = array_map(function (array $item) use ($previewId, $assetId) {
                    if (($item['preview_id'] ?? null) === $previewId) {
                        $item['saved_asset_id'] = $assetId;
                    }

                    return $item;
                }, $job['results']);
                $this->updateJob($jobId, ['results' => $job['results']]);
            }
        }

        return $preview;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Sinh signed URL tương đối để xem hoặc tải ảnh preview tạm
     * =====================================================================
     * Dùng relative signed route (`absolute: false` kết hợp middleware `signed:relative`)
     * để chạy ổn định qua Vite dev proxy và mọi domain không phụ thuộc APP_URL host.
     *
     * INPUT: string $previewId, bool $download (thêm query download=1).
     * OUTPUT: string URL có chữ ký HMAC + expires.
     * =====================================================================
     */
    public function signedPreviewUrl(string $previewId, bool $download = false): string
    {
        $params = ['previewId' => $previewId];
        if ($download) {
            $params['download'] = 1;
        }

        return URL::temporarySignedRoute(
            'api.media.ai-image.preview',
            $this->expiresAt(),
            $params,
            absolute: false
        );
    }

    /**
     * CHỨC NĂNG: Map MIME ảnh sang đuôi file an toàn cho tên file tạm/MediaAsset.
     * INPUT: string $mime.
     * OUTPUT: string extension (png|jpg|webp|gif|avif).
     */
    public function extensionForMime(string $mime): string
    {
        $normalized = strtolower(trim(explode(';', $mime)[0]));

        return match ($normalized) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'png',
        };
    }

    /**
     * CHỨC NĂNG: Tạo đường dẫn tương đối lưu binary preview trên disk `temp_processing`.
     * INPUT: string $previewId, string $mime.
     * OUTPUT: string đường dẫn dạng `ai-previews/{previewId}.{ext}`.
     */
    public function relativePathForPreview(string $previewId, string $mime): string
    {
        return 'ai-previews/' . $previewId . '.' . $this->extensionForMime($mime);
    }

    /**
     * CHỨC NĂNG: Lọc Content-Type an toàn khi stream ảnh preview inline tránh XSS SVG/HTML.
     * INPUT: string $mime.
     * OUTPUT: string MIME an toàn (`image/png`... hoặc `application/octet-stream`).
     */
    public function safeStreamMime(string $mime): string
    {
        $normalized = strtolower(trim(explode(';', $mime)[0]));

        return in_array($normalized, self::SAFE_STREAM_MIMES, true)
            ? $normalized
            : 'application/octet-stream';
    }

    /**
     * CHỨC NĂNG: Cập nhật một phần field cho job tracker đang nằm trong Cache.
     * INPUT: string $jobId, array $attributes.
     * OUTPUT: ?array.
     */
    private function updateJob(string $jobId, array $attributes): ?array
    {
        $job = $this->getJob($jobId);
        if ($job === null) {
            return null;
        }

        $updated = array_merge($job, $attributes, [
            'updated_at' => now()->toIso8601String(),
        ]);

        Cache::put($this->jobKey($jobId), $updated, $this->expiresAt());

        return $updated;
    }

    private function jobKey(string $jobId): string
    {
        return self::JOB_KEY_PREFIX . $jobId;
    }

    private function previewKey(string $previewId): string
    {
        return self::PREVIEW_KEY_PREFIX . $previewId;
    }
}
