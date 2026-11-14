<?php

namespace Modules\Media\Actions;

use Illuminate\Support\Str;
use Modules\CoreAi\Models\AiProvenance;
use Modules\CoreAi\Models\AiRun;
use Modules\Media\DTOs\StoreGeneratedMediaDto;
use Modules\Media\Models\MediaAsset;
use Modules\Media\Services\AiImagePreviewStore;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuyển ảnh preview AI tạm thành MediaAsset chính thức.
 * =====================================================================
 *
 * Kiểm tra preview trong Cache và quyền sở hữu user hiện tại, đọc binary từ
 * `temp_processing`, xử lý idempotency nếu ảnh này đã được lưu trước đó,
 * rồi ủy quyền cho StoreGeneratedMediaAction::executeContent() để quét bảo mật,
 * chống trùng checksum SHA-256 và gắn metadata `ai_generation`.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : previewId, userId, input metadata (folder, title, alt_text, visibility, channel_group_id).
 * - OUTPUT: MediaAsset đã load creator, channelGroup, usages_count.
 * =====================================================================
 */
class SaveAiPreviewToMediaAction
{
    public function __construct(
        private readonly AiImagePreviewStore $store,
        private readonly StoreGeneratedMediaAction $storeGeneratedMedia,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Lưu một ảnh preview AI từ vùng tạm vào Media Library
     * =====================================================================
     * INPUT:
     * - string $previewId: UUID ảnh preview
     * - int $userId: ID user đang thực hiện thao tác lưu
     * - array $input: {folder?, title?, alt_text?, visibility?, channel_group_id?}
     * OUTPUT: MediaAsset (đã gắn relations creator, channelGroup, usages_count).
     * SIDE EFFECT:
     * - ghi MediaAsset + Spatie Media (nếu chưa từng lưu previewId/checksum này)
     * - cập nhật `saved_asset_id` vào Cache preview & job tracker
     * EXCEPTION:
     * - NotFoundHttpException (404) khi preview hết hạn hoặc mất file tạm
     * - AccessDeniedHttpException (403) khi preview thuộc user khác
     * - InvalidArgumentException (422) khi file lỗi bảo mật (từ ScanMediaSecurityAction)
     * =====================================================================
     */
    public function execute(string $previewId, int $userId, array $input = []): MediaAsset
    {
        $preview = $this->store->getPreview($previewId);
        if ($preview === null) {
            throw new NotFoundHttpException('Ảnh preview không tồn tại hoặc đã hết hạn.');
        }

        if ((int) ($preview['user_id'] ?? 0) !== $userId) {
            throw new AccessDeniedHttpException('Bạn không có quyền lưu ảnh preview của người dùng khác.');
        }

        $savedAssetId = $preview['saved_asset_id'] ?? null;
        if ($savedAssetId !== null) {
            $existing = MediaAsset::query()->find($savedAssetId);
            if ($existing !== null) {
                return $existing->load(['creator', 'channelGroup'])->loadCount('usages');
            }
        }

        $path = (string) ($preview['path'] ?? '');
        $disk = $this->store->disk();
        if ($path === '' || ! $disk->exists($path)) {
            throw new NotFoundHttpException('File ảnh preview tạm không còn trên hệ thống.');
        }

        $binary = $disk->get($path);
        if (! is_string($binary) || $binary === '') {
            throw new NotFoundHttpException('Không đọc được dữ liệu file ảnh preview tạm.');
        }

        $title = $this->resolveTitle(
            $input['title'] ?? null,
            $preview['description'] ?? null,
            $preview['prompt'] ?? null,
        );

        $mime = (string) ($preview['mime'] ?? 'image/png');
        $slug = Str::slug(Str::limit($title, 50, ''), '-');
        $fileName = ($slug !== '' ? $slug : 'ai-image') . '.' . $this->store->extensionForMime($mime);

        $meta = array_filter([
            'prompt' => $preview['prompt'] ?? null,
            'description' => $preview['description'] ?? null,
            'aspect_ratio' => $preview['aspect_ratio'] ?? null,
            'ai_model_id' => $preview['ai_model_id'] ?? null,
            'provider' => $preview['provider'] ?? null,
            'model' => $preview['model'] ?? null,
            'ai_run_id' => $preview['ai_run_id'] ?? null,
            'revised_prompt' => $preview['revised_prompt'] ?? null,
            'preview_id' => $previewId,
        ], fn ($value) => $value !== null && $value !== '');

        $folder = isset($input['folder']) && trim((string) $input['folder']) !== ''
            ? trim((string) $input['folder'])
            : 'root';

        $visibility = $input['visibility'] ?? MediaAsset::VISIBILITY_PUBLIC;
        $altText = isset($input['alt_text']) && trim((string) $input['alt_text']) !== ''
            ? trim((string) $input['alt_text'])
            : $title;

        $dto = new StoreGeneratedMediaDto(
            tmpPath: $path,
            kind: MediaAsset::KIND_IMAGE,
            title: $title,
            folder: $folder,
            visibility: $visibility,
            channelGroupId: isset($input['channel_group_id']) ? (int) $input['channel_group_id'] : null,
            createdBy: $userId,
            source: 'ai_dialog',
            meta: $meta,
            altText: $altText,
            fileName: $fileName,
            mime: $mime,
            checksumSha256: null,
        );

        $asset = $this->storeGeneratedMedia->executeContent($binary, $dto);
        $this->recordAiProvenance($asset, $preview, $userId);
        $this->store->markPreviewSaved($previewId, $asset->id);

        return $asset->load(['creator', 'channelGroup'])->loadCount('usages');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghi vết nguồn gốc AI vào bảng ai_provenances qua HasAiProvenance
     * =====================================================================
     * INPUT: MediaAsset $asset, array $preview metadata, int $userId.
     * OUTPUT: void.
     * SIDE EFFECT: Tạo bản ghi ai_provenances (field_name = checksum_sha256)
     *              liên kết AiRun (nếu tồn tại) — lỗi ghi provenance không
     *              chặn luồng lưu ảnh chính.
     * =====================================================================
     */
    private function recordAiProvenance(MediaAsset $asset, array $preview, int $userId): void
    {
        if (! $asset->checksum_sha256 || $asset->isAiGenerated('checksum_sha256')) {
            return;
        }

        try {
            $run = ! empty($preview['ai_run_id'])
                ? AiRun::query()->find($preview['ai_run_id'])
                : null;

            AiProvenance::record(
                target: $asset,
                field: 'checksum_sha256',
                value: (string) $asset->checksum_sha256,
                run: $run,
                appliedBy: $userId,
            );
        } catch (Throwable) {
            // Không để lỗi audit/provenance làm hỏng thao tác lưu ảnh của người dùng.
        }
    }

    /**
     * CHỨC NĂNG: Chọn tiêu đề an toàn theo thứ tự title input -> description -> prompt -> fallback.
     * INPUT: ?string ...$candidates.
     * OUTPUT: string tiêu đề đã rút gọn <= 120 ký tự.
     */
    private function resolveTitle(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $clean = preg_replace('/\s+/u', ' ', trim($candidate));
                if (is_string($clean) && $clean !== '') {
                    return Str::limit($clean, 120, '');
                }
            }
        }

        return 'Ảnh AI tạo';
    }
}
