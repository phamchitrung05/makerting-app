<?php

namespace Modules\Media\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Action upload file vào `media_assets` + gắn file Spatie.
 * =====================================================================
 *
 * Quy trình:
 * 1. Checksum sha256 — nếu trùng asset cũ -> trả asset cũ (dedupe).
 * 2. ScanMediaSecurityAction — chặn file độc/mime lệch/đuôi cấm/vượt size.
 * 3. Tạo MediaAsset (metadata).
 * 4. `addMedia($file)->toMediaCollection('library', $disk)` — ghi vật lý
 *    + sinh conversions (thumb/featured/og nếu là image).
 * 5. Tôn trọng `allowedKinds` của slot khi truyền vào.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - execute()     : upload UploadedFile -> MediaAsset (có file Spatie).
 * - executeMany() : upload nhiều file cùng lúc (trả collection).
 * - dedupeByChecksum() : tìm asset trùng checksum (public helper).
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : UploadedFile + metadata (folder/visibility/channelGroup/title/alt).
 * - OUTPUT: MediaAsset (đã gắn file Spatie + conversions).
 * =====================================================================
 */
class UploadMediaAssetAction
{
    public function __construct(
        private readonly CalculateMediaChecksumAction $checksum,
        private readonly ScanMediaSecurityAction $scan,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Upload một file -> MediaAsset (dedupe theo checksum)
     * =====================================================================
     *
     * INPUT:
     * - $file         : UploadedFile (multipart/form-data 'file').
     * - $options      : [
     *     'folder'         => string 'root'|'images'|'videos'|...,
     *     'visibility'     => 'public'|'private' (default 'public'),
     *     'channel_group_id'=> ?int,
     *     'title'          => ?string (mặc định basename không đuôi),
     *     'alt_text'       => ?string,
     *     'allowed_kinds'  => ?array MediaAsset::KIND_*,
     *     'max_bytes'      => ?int (default config('media.max_upload_bytes', 50MB)),
     *     'disk'           => ?string disk Spatie (default 'media' hoặc 'public'),
     *     'created_by'     => ?int user id,
     *     'dedupe'         => bool (default true) — trả asset cũ khi trùng checksum.
     *   ]
     *
     * OUTPUT: MediaAsset — mới (wasRecentlyCreated=true) hoặc cũ (dedupe).
     *
     * EXCEPTION: InvalidArgumentException khi file độc/mime lệch/size vượt/
     *            kind không thuộc allowed_kinds.
     *
     * SIDE EFFECT:
     * - Có thể tạo bản ghi media_assets + ghi file vào disk Spatie + conversions.
     * - Khi dedupe trả asset cũ: KHÔNG ghi file mới, chỉ trả model cũ.
     * =====================================================================
     */
    public function execute(UploadedFile $file, array $options = []): MediaAsset
    {
        $maxBytes = $options['max_bytes'] ?? config('media.max_upload_bytes', 50 * 1024 * 1024);
        $allowedKinds = $options['allowed_kinds'] ?? null;
        $dedupe = $options['dedupe'] ?? true;
        $visibility = $options['visibility'] ?? MediaAsset::VISIBILITY_PUBLIC;
        $disk = $options['disk']
            ?? ($visibility === MediaAsset::VISIBILITY_PRIVATE
                ? 'media_private'
                : 'media_public');
        $createdBy = $options['created_by'] ?? auth()->id();

        // 1. Checksum — dedupe trước khi scan/ghi, NHƯNG vẫn phải kiểm
        // allowed_kinds: asset cũ cùng checksum nhưng sai kind (video thay
        // image) không được phép trả về cho slot yêu cầu image.
        $checksum = $this->checksum->fromUploadedFile($file);
        if ($dedupe) {
            $existing = $this->dedupeByChecksum($checksum);
            if ($existing) {
                if ($allowedKinds !== null && $allowedKinds !== []
                    && ! in_array($existing->kind, $allowedKinds, true)) {
                    throw new InvalidArgumentException(
                        "File này đã tồn tại với kind [{$existing->kind}] " .
                        'không thuộc whitelist (' . implode(',', $allowedKinds) . ').',
                    );
                }

                return $existing;
            }
        }

        // 2. Scan bảo mật + detect kind/mime/size thực.
        $scan = $this->scan->scanUploadedFile($file, $allowedKinds, $maxBytes);

        // 3. Tạo metadata MediaAsset.
        $title = $options['title']
            ?? pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME)
            ?: 'media-' . substr($checksum, 0, 8);

        /** @var MediaAsset $asset */
        $asset = DB::transaction(function () use ($file, $options, $checksum, $scan, $title, $disk, $createdBy, $visibility) {
            $asset = MediaAsset::create([
                'channel_group_id' => $options['channel_group_id'] ?? null,
                'folder' => $options['folder'] ?? 'root',
                'kind' => $scan['kind'],
                'title' => $title,
                'alt_text' => $options['alt_text'] ?? null,
                'visibility' => $visibility,
                'checksum_sha256' => $checksum,
                'created_by' => $createdBy,
            ]);

            // 4. Gắn file vật lý Spatie + conversions.
            $asset->addMedia($file->getRealPath())
                ->usingName($asset->title)
                ->usingFileName($this->buildFileName($file, $checksum))
                ->toMediaCollection(MediaAsset::COLLECTION_LIBRARY, $disk);

            // Lấy size/mime thực từ file Spatie vừa gắn để cập nhật width/height nếu là ảnh.
            $media = $asset->mediaFile();
            if ($media && str_starts_with((string) $media->mime_type, 'image/')) {
                $size = @getimagesize($media->getPath());
                if ($size) {
                    $asset->update(['width' => $size[0], 'height' => $size[1]]);
                }
            }

            return $asset;
        });

        return $asset;
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Upload nhiều file cùng lúc
     * =====================================================================
     * INPUT: array<int,UploadedFile>, $options (giống execute()).
     * OUTPUT: array<int,MediaAsset> — giữ thứ tự mảng.
     * SIDE EFFECT: gọi execute() từng file (transaction riêng mỗi cái).
     * =====================================================================
     */
    public function executeMany(array $files, array $options = []): array
    {
        $out = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                throw new InvalidArgumentException('Phần tử không phải UploadedFile.');
            }
            $out[] = $this->execute($file, $options);
        }

        return $out;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tìm asset trùng checksum sha256
     * =====================================================================
     * INPUT: sha256 hex. OUTPUT: ?MediaAsset.
     * SIDE EFFECT: SELECT media_assets WHERE checksum_sha256.
     * =====================================================================
     */
    public function dedupeByChecksum(string $checksum): ?MediaAsset
    {
        return MediaAsset::query()
            ->where('checksum_sha256', $checksum)
            ->first();
    }

    /* =====================================================================
     * Helpers nội bộ
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo tên file đích theo checksum + đuôi file AN TOÀN
     * =====================================================================
     * Đuôi file lấy từ MIME thực (finfo) thay vì đuôi gốc client gửi —
     * chặn client khai báo ".php"/".zip" nhưng MIME lại là image/png
     * (đuôi file cuối phải khớp với nội dung thực).
     *
     * INPUT: UploadedFile, sha256.
     * OUTPUT: "<sha256>.<ext>" — ext từ MIME, hoặc 'bin' khi MIME lạ.
     * =====================================================================
     */
    private function buildFileName(UploadedFile $file, string $checksum): string
    {
        // Ưu tiên ext từ MIME thực; fallback ext gốc khi MIME trả ext an toàn.
        $ext = $this->extensionFromMime($file);
        if ($ext === null) {
            $ext = strtolower((string) $file->getClientOriginalExtension());
        }
        if ($ext === '' || strlen($ext) > 8) {
            $ext = 'bin';
        }

        return $checksum . '.' . $ext;
    }

    /**
     * CHỨC NĂNG: Đoán đuôi file an toàn từ MIME thực của file upload.
     * INPUT: UploadedFile. OUTPUT: ?string ext (không dấu chấm) hoặc null.
     */
    private function extensionFromMime(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if (! $path) {
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);

        $map = [
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg',
            'image/png' => 'png', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'image/avif' => 'avif',
            'image/bmp' => 'bmp', 'image/x-icon' => 'ico',
            'video/mp4' => 'mp4', 'video/webm' => 'webm',
            'video/quicktime' => 'mov', 'video/x-matroska' => 'mkv',
            'video/x-msvideo' => 'avi', 'video/mpeg' => 'mpg',
            'audio/mpeg' => 'mp3', 'audio/wav' => 'wav',
            'audio/x-wav' => 'wav', 'audio/mp4' => 'm4a',
            'audio/aac' => 'aac', 'audio/ogg' => 'ogg',
            'audio/flac' => 'flac', 'audio/opus' => 'opus',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip', 'application/x-zip-compressed' => 'zip',
            'application/x-rar-compressed' => 'rar', 'application/vnd.rar' => 'rar',
            'application/x-7z-compressed' => '7z',
            'application/gzip' => 'gz', 'application/x-gzip' => 'gz',
            'application/x-tar' => 'tar', 'application/x-bzip2' => 'bz2',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt', 'text/csv' => 'csv',
            'application/json' => 'json',
            'application/xml' => 'xml', 'text/xml' => 'xml',
        ];

        return $map[$mime] ?? null;
    }
}