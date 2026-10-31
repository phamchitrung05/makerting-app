<?php

namespace Modules\Media\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Media\DTOs\StoreGeneratedMediaDto;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Action lưu media do AI / pipeline nội bộ sinh ra
 * (ảnh VisualStyle, video render, âm thanh TTS...) vào `media_assets`.
 * =====================================================================
 *
 * Khác `UploadMediaAssetAction` ở chỗ:
 * - Nguồn là file trên disk (tmpPath) hoặc binary content — không phải
 *   UploadedFile người dùng.
 * - Nhận `StoreGeneratedMediaDto` với `source` + `meta` (prompt, model,
 *   task_run_id, channel_motif_id...) lưu vào custom_properties của media.
 * - Vẫn checksum dedupe + scan bảo mật trước khi ghi.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - execute()       : lưu từ tmpPath -> MediaAsset.
 * - executeContent(): lưu từ binary string (ảnh AI trong bộ nhớ).
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : StoreGeneratedMediaDto.
 * - OUTPUT: MediaAsset với file Spatie + custom_properties chứa meta AI.
 * =====================================================================
 */
class StoreGeneratedMediaAction
{
    public function __construct(
        private readonly CalculateMediaChecksumAction $checksum,
        private readonly ScanMediaSecurityAction $scan,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Lưu media từ file trên disk (tmpPath)
     * =====================================================================
     * INPUT: StoreGeneratedMediaDto.
     * OUTPUT: MediaAsset.
     * EXCEPTION: InvalidArgumentException khi file độc/kind không hợp lệ.
     * SIDE EFFECT: DB transaction — tạo media_assets + gắn file Spatie +
     *              gán custom_properties['ai_generation'] chứa meta.
     * =====================================================================
     */
    public function execute(StoreGeneratedMediaDto $dto): MediaAsset
    {
        if (! is_file($dto->tmpPath)) {
            throw new InvalidArgumentException("Đường dẫn [{$dto->tmpPath}] không phải file.");
        }

        $checksum = $dto->checksumSha256 ?? $this->checksum->fromPath($dto->tmpPath);
        $existing = MediaAsset::query()->where('checksum_sha256', $checksum)->first();
        if ($existing) {
            return $existing;
        }

        // Scan file — chỉ giới hạn theo kind khai báo.
        $scan = $this->scan->scanPath($dto->tmpPath, [$dto->kind]);
        $disk = $dto->visibility === MediaAsset::VISIBILITY_PRIVATE
            ? 'media_private'
            : 'media_public';

        return DB::transaction(function () use ($dto, $checksum, $scan, $disk) {
            $asset = MediaAsset::create([
                'channel_group_id' => $dto->channelGroupId,
                'folder' => $dto->folder,
                'kind' => $scan['kind'],
                'title' => $dto->title,
                'alt_text' => $dto->altText,
                'visibility' => $dto->visibility,
                'checksum_sha256' => $checksum,
                'created_by' => $dto->createdBy,
            ]);

            // Ghi kích thước ảnh TRƯỚC khi addMedia — Spatie move() file
            // khỏi tmpPath nên đọc sau khi addMedia sẽ thất bại.
            $dimensions = null;
            if ($scan['kind'] === MediaAsset::KIND_IMAGE) {
                $dimensions = @getimagesize($dto->tmpPath) ?: null;
            }

            $fileName = $dto->fileName ?? ($checksum . '.' . $this->guessExtension($dto->tmpPath, $scan['mime']));

            $media = $asset->addMedia($dto->tmpPath)
                ->usingName($dto->title)
                ->usingFileName($fileName)
                ->toMediaCollection(MediaAsset::COLLECTION_LIBRARY, $disk);

            // Lưu meta AI vào custom_properties để truy vết nguồn.
            if ($dto->source !== null || $dto->meta !== null) {
                $media->setCustomProperty('ai_generation', array_merge(
                    ['source' => $dto->source],
                    $dto->meta ?? [],
                ));
                $media->save();
            }

            // Ghi kích thước đã đọc trước khi file bị move.
            if ($dimensions) {
                $asset->update(['width' => $dimensions[0], 'height' => $dimensions[1]]);
            }

            return $asset;
        });
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Lưu media từ nội dung binary (ảnh AI sinh trong bộ nhớ)
     * =====================================================================
     * INPUT: $content binary, StoreGeneratedMediaDto (tmpPath bị bỏ qua).
     * OUTPUT: MediaAsset.
     * EXCEPTION: InvalidArgumentException khi content độc/rỗng.
     * SIDE EFFECT: tạo tmp file -> execute() rồi xóa.
     * =====================================================================
     */
    public function executeContent(string $content, StoreGeneratedMediaDto $dto): MediaAsset
    {
        if ($content === '') {
            throw new InvalidArgumentException('Nội dung media rỗng.');
        }

        // Ghi ra tmp rồi tái dùng execute() cho đồng nhất.
        $tmp = tempnam(sys_get_temp_dir(), 'media_gen_');
        if ($tmp === false) {
            throw new InvalidArgumentException('Không tạo được file tmp.');
        }
        file_put_contents($tmp, $content);

        try {
            $newDto = new StoreGeneratedMediaDto(
                tmpPath: $tmp,
                kind: $dto->kind,
                title: $dto->title,
                folder: $dto->folder,
                visibility: $dto->visibility,
                channelGroupId: $dto->channelGroupId,
                createdBy: $dto->createdBy,
                source: $dto->source,
                meta: $dto->meta,
                altText: $dto->altText,
                fileName: $dto->fileName,
                mime: $dto->mime,
                checksumSha256: $dto->checksumSha256 ?? hash('sha256', $content),
            );

            return $this->execute($newDto);
        } finally {
            @unlink($tmp);
        }
    }

    /* =====================================================================
     * Helpers nội bộ
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Đoán đuôi file từ path/mime khi caller không truyền.
     * INPUT: $path, $mime. OUTPUT: đuôi file (không dấu chấm).
     */
    private function guessExtension(string $path, string $mime): string
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && strlen($ext) <= 5) {
            return $ext;
        }

        $map = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            'image/gif' => 'gif', 'image/avif' => 'avif',
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
        ];

        return $map[$mime] ?? 'bin';
    }
}