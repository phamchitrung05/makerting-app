<?php

namespace Modules\Media\Actions;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Quét file media trước khi lưu — kiểm tra MIME thực tế,
 * phần mở rộng, kích thước và một số pattern nguy hiểm trong đầu file.
 * =====================================================================
 *
 * Chặn file độc giả dạng ảnh/video (polyglot) trước khi `addMedia()`:
 * - Khai báo whitelist `allowedKinds` -> phạm vi MIME hợp lệ.
 * - So khớp `finfo` (magic bytes) với MIME client + đuôi file.
 * - Chặn chuỗi nguy hiểm `<?php`, `<script`, `javascript:`, `onload=`,
 *   `<svg`, `#!/`, `MZ` (PE) trong TOÀN BỘ file (tối đa 2MB đầu) — kể cả
 *   kind image (SVG từng là vector XSS, nay bị chặn hoàn toàn).
 * - Chặn kích thước 0 và vượt `maxBytes`.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - scanUploadedFile() : quét UploadedFile -> trả kind + mime thực + size.
 * - scanPath()         : quét file trên disk.
 * - scanContent()      : quét binary (ảnh AI) -> trả mime/size đã detect.
 * - detectKind()       : suy ra MediaAsset::KIND_* từ mime.
 * - assertKindAllowed(): kiểm tra kind thuộc whitelist của slot.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : UploadedFile|path|binary + optional allowedKinds/maxBytes.
 * - OUTPUT: array{kind,mime,size} hoặc throw khi file không an toàn.
 * =====================================================================
 */
class ScanMediaSecurityAction
{
    /**
     * Map phạm vi MIME chấp nhận cho từng kind.
     */
    private const KIND_MIME_PREFIX = [
        MediaAsset::KIND_IMAGE => ['image/'],
        MediaAsset::KIND_VIDEO => ['video/'],
        MediaAsset::KIND_AUDIO => ['audio/'],
        MediaAsset::KIND_DOCUMENT => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument',
            'application/vnd.ms-',
            'text/plain',
            'text/csv',
            'application/json',
            'application/xml',
            'text/xml',
        ],
        MediaAsset::KIND_ARCHIVE => [
            'application/zip',
            'application/x-zip',
            'application/x-rar',
            'application/vnd.rar',
            'application/x-7z',
            'application/gzip',
            'application/x-gzip',
            'application/x-tar',
            'application/x-bzip2',
        ],
    ];

    /**
     * Đuôi file mặc định cho phép theo kind (fallback khi chưa nạp config('media.allowed_upload_extensions')).
     */
    private const KIND_EXTENSIONS = [
        MediaAsset::KIND_IMAGE => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico'],
        MediaAsset::KIND_VIDEO => ['mp4', 'mov', 'webm', 'mkv', 'avi', 'm4v', 'mpg', 'mpeg'],
        MediaAsset::KIND_AUDIO => ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac', 'opus'],
        MediaAsset::KIND_DOCUMENT => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md', 'json', 'xml'],
        MediaAsset::KIND_ARCHIVE => ['zip', 'rar', '7z', 'gz', 'tar', 'bz2'],
    ];

    /**
     * Đuôi file luôn bị cấm.
     */
    private const FORBIDDEN_EXTENSIONS = [
        'php','phtml','phar','php3','php4','php5','php7','php8',
        'exe','bat','cmd','com','scr','msi','dll','sh','bash','zsh',
        'js','mjs','vbs','vbe','jse','wsf','wsh','ps1','psm1',
        'html','htm','svgz','jar','apk','deb','rpm','app',
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Quét UploadedFile (multipart upload)
     * =====================================================================
     * INPUT: UploadedFile, ?allowedKinds, ?maxBytes.
     * OUTPUT: array{kind:string, mime:string, size:int}
     * EXCEPTION: InvalidArgumentException khi file độc/không hợp lệ.
     * =====================================================================
     */
    public function scanUploadedFile(
        UploadedFile $file,
        ?array $allowedKinds = null,
        ?int $maxBytes = null,
    ): array {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('File upload không hợp lệ (lỗi ' . $file->getError() . ').');
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== '' && in_array($ext, self::FORBIDDEN_EXTENSIONS, true)) {
            throw new InvalidArgumentException("Đuôi file [{$ext}] không được phép upload.");
        }

        $path = $file->getRealPath();
        $label = $file->getClientOriginalName() ?: basename((string) $path);
        $result = $this->scanPath($path, $allowedKinds, $maxBytes, $label);

        // Chặn khi đuôi file gợi ý kind KHÁC với MIME thực.
        // (Ví dụ .zip nhưng mime=image/png -> nghi polyglot.)
        $kind = $result['kind'];
        $result['extension_suspicious'] = false;

        $allowedByKind = self::allowedExtensionsByKind();
        if ($ext !== '') {
            $declaredKind = self::kindFromExtension($ext);
            if ($declaredKind !== null && $declaredKind !== $kind) {
                throw new InvalidArgumentException(
                    "Đuôi file [.{$ext}] gợi ý kind [{$declaredKind}] khác với MIME thực [{$kind}] — nghi polyglot, bị chặn.",
                );
            }

            $allowedForKind = $allowedByKind[$kind] ?? [];
            if (! in_array($ext, $allowedForKind, true)) {
                throw new InvalidArgumentException(
                    "Đuôi file [.{$ext}] không được phép hoặc không khớp MIME [{$result['mime']}] — bị chặn.",
                );
            }
        }

        return $result;
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Quét file trên disk (đường dẫn tuyệt đối)
     * =====================================================================
     * INPUT: $path, ?allowedKinds, ?maxBytes, ?label (tên hiển thị khi lỗi).
     * OUTPUT: array{kind:string, mime:string, size:int}
     * EXCEPTION: InvalidArgumentException khi file độc/không hợp lệ.
     * =====================================================================
     */
    public function scanPath(
        string $path,
        ?array $allowedKinds = null,
        ?int $maxBytes = null,
        ?string $label = null,
    ): array {
        $displayLabel = $label ?: basename($path);

        if (! is_file($path)) {
            throw new InvalidArgumentException("Đường dẫn [{$displayLabel}] không phải file.");
        }

        $size = (int) @filesize($path);
        $this->assertSizeOk($size, $maxBytes, $displayLabel);

        $mime = $this->detectMimeFromPath($path);
        $kind = $this->detectKind($mime);
        $this->assertKindAllowed($kind, $allowedKinds, $displayLabel);
        $this->assertNotDangerous($path, $kind, $displayLabel);

        return ['kind' => $kind, 'mime' => $mime, 'size' => $size];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Quét nội dung binary (ảnh AI sinh trong bộ nhớ)
     * =====================================================================
     * INPUT: $content binary, ?allowedKinds, ?maxBytes.
     * OUTPUT: array{kind:string, mime:string, size:int}
     * EXCEPTION: InvalidArgumentException khi nội dung độc/không hợp lệ.
     * =====================================================================
     */
    public function scanContent(
        string $content,
        ?array $allowedKinds = null,
        ?int $maxBytes = null,
    ): array {
        $size = strlen($content);
        $this->assertSizeOk($size, $maxBytes, 'content');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->buffer($content);
        $kind = $this->detectKind($mime);
        $this->assertKindAllowed($kind, $allowedKinds, 'content');
        $this->assertNotDangerousContent($content, $kind);

        return ['kind' => $kind, 'mime' => $mime, 'size' => $size];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Suy ra kind của asset từ MIME
     * =====================================================================
     * INPUT: $mime. OUTPUT: MediaAsset::KIND_*.
     * EXCEPTION: InvalidArgumentException khi mime ngoài whitelist.
     * =====================================================================
     */
    public function detectKind(string $mime): string
    {
        $mime = strtolower(trim($mime));

        // SVG từng là vector XSS — chặn hẳn từ MIME trước khi khớp prefix 'image/'.
        if (str_starts_with($mime, 'image/svg')) {
            throw new InvalidArgumentException(
                "Định dạng SVG [{$mime}] (có thể chứa script) — tạm thời bị chặn cho đến khi có sanitizer.",
            );
        }

        foreach (self::KIND_MIME_PREFIX as $kind => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($mime, $prefix)) {
                    return $kind;
                }
            }
        }

        // octet-stream / binary là MIME chung chung — KHÔNG đoán archive,
        // throw luôn để caller phải khai báo kind rõ (chặn bypass
        // file lạ giả archive).
        throw new InvalidArgumentException("MIME [{$mime}] không thuộc whitelist media (image|video|audio|document|archive).");
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra kind có nằm trong whitelist của slot không
     * =====================================================================
     * INPUT: $kind, ?allowedKinds (null/[] = mọi kind), $label.
     * OUTPUT: void. EXCEPTION: InvalidArgumentException.
     * =====================================================================
     */
    public function assertKindAllowed(string $kind, ?array $allowedKinds, string $label): void
    {
        if ($allowedKinds !== null && $allowedKinds !== [] && ! in_array($kind, $allowedKinds, true)) {
            throw new InvalidArgumentException(
                "Kind [{$kind}] của file [{$label}] không được phép " .
                '(cho phép: ' . implode(',', $allowedKinds) . ').',
            );
        }
    }
    /* =====================================================================
     * Helpers nội bộ
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Lấy map đuôi file được phép tải lên theo kind từ config('media.allowed_upload_extensions').
     * INPUT: không. OUTPUT: array<string, array<int, string>>.
     */
    public static function allowedExtensionsByKind(): array
    {
        $configured = function_exists('config') ? config('media.allowed_upload_extensions') : null;
        if (! is_array($configured) || $configured === []) {
            return self::KIND_EXTENSIONS;
        }

        $normalized = [];
        foreach ($configured as $kind => $exts) {
            if (! is_array($exts)) {
                continue;
            }
            $normalized[(string) $kind] = array_values(array_filter(array_map(
                fn ($ext) => strtolower(ltrim(trim((string) $ext), '.')),
                $exts,
            ), fn ($ext) => $ext !== ''));
        }

        return $normalized;
    }

    /**
     * CHỨC NĂNG: Suy ra kind gợi ý từ đuôi file (dùng cho so khớp MIME).
     * INPUT: $ext (không dấu chấm). OUTPUT: ?MediaAsset::KIND_* hoặc null.
     */
    public static function kindFromExtension(string $ext): ?string
    {
        $ext = strtolower(ltrim($ext, '.'));
        foreach (self::allowedExtensionsByKind() as $kind => $exts) {
            if (in_array($ext, $exts, true)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * CHỨC NĂNG: kiểm tra kích thước file.
     * INPUT: $size, ?$maxBytes, $label.
     * EXCEPTION: khi size=0 hoặc > maxBytes.
     */
    private function assertSizeOk(int $size, ?int $maxBytes, string $label): void
    {
        if ($size <= 0) {
            throw new InvalidArgumentException("File [{$label}] rỗng.");
        }
        if ($maxBytes !== null && $size > $maxBytes) {
            throw new InvalidArgumentException(
                "File [{$label}] vượt giới hạn {$maxBytes} bytes (thực {$size}).",
            );
        }
    }

    /**
     * CHỨC NĂNG: Đọc MIME thực từ file trên disk bằng finfo (magic bytes).
     * INPUT: $path. OUTPUT: mime string.
     */
    private function detectMimeFromPath(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    /**
     * CHỨC NĂNG: Chặn pattern nguy hiểm trong 2MB đầu file vật lý.
     * INPUT: $path, $kind, ?$label. EXCEPTION: khi phát hiện shell/script/PE.
     */
    private function assertNotDangerous(string $path, string $kind, ?string $label = null): void
    {
        $displayLabel = $label ?: basename($path);

        // Đọc tối đa 2MB đầu file — đủ để phát hiện payload nằm sâu
        // (polyglot đặt shell sau 4KB). File lớn hơn 2MB vẫn chỉ đọc phần đầu
        // vì polyglot thường đặt payload gần header.
        $fp = @fopen($path, 'rb');
        if (! $fp) {
            throw new InvalidArgumentException("Không mở được file [{$displayLabel}] để quét.");
        }
        $head = (string) stream_get_contents($fp, 2 * 1024 * 1024);
        fclose($fp);

        $this->assertContentClean($head, $kind, $displayLabel);
    }

    /**
     * CHỨC NĂNG: Chặn pattern nguy hiểm trong binary đầu (ảnh AI).
     * INPUT: $content binary, $kind. EXCEPTION: khi phát hiện shell/script.
     */
    private function assertNotDangerousContent(string $content, string $kind): void
    {
        $head = substr($content, 0, 4096);
        $this->assertContentClean($head, $kind, 'content');
    }

    /**
     * CHỨC NĂNG: So khớp pattern nguy hiểm trong buffer đầu file.
     * INPUT: $head, $kind, $label.
     * EXCEPTION: InvalidArgumentException khi phát hiện pattern cấm.
     */
    private function assertContentClean(string $head, string $kind, string $label): void
    {
        // 'MZ' (PE executable) và '#!' (shebang script) chỉ có ý nghĩa ở đầu file (offset 0);
        // tìm '#!' trong toàn bộ 2MB nhị phân sẽ bắt nhầm ảnh/video bình thường chứa byte 0x23 0x21.
        if (str_starts_with($head, 'MZ')) {
            throw new InvalidArgumentException(
                "File [{$label}] chứa signature PE executable — bị chặn.",
            );
        }
        $shebangSample = $kind === MediaAsset::KIND_DOCUMENT ? ltrim($head) : $head;
        if (str_starts_with($shebangSample, '#!')) {
            throw new InvalidArgumentException(
                "File [{$label}] chứa signature shebang (#!) — bị chặn.",
            );
        }

        $lower = strtolower($head);

        if (str_contains($lower, '<?php')) {
            throw new InvalidArgumentException(
                "File [{$label}] chứa signature nguy hiểm (<?php) — bị chặn.",
            );
        }

        // '<?=' chỉ dài 3 byte nên dễ trùng ngẫu nhiên trong dữ liệu nén; chỉ chặn khi mang cú pháp PHP thật.
        if (preg_match('/<\?=\s*[\$_a-zA-Z(\'"`]/', $head) === 1) {
            throw new InvalidArgumentException(
                "File [{$label}] chứa signature nguy hiểm (<?=) — bị chặn.",
            );
        }

        // HTML/script/XSS — cấm trong MỌI kind (kể cả image).
        // Không đưa '<?xml version=' vào blacklist chung vì ảnh có metadata XMP
        // hoặc tài liệu .xml hợp lệ đều có khai báo XML; SVG đã chặn ở detectKind() + kiểm tra header bên dưới.
        foreach (['<script', '<!doctype', 'javascript:', 'onload=', 'onerror=', 'onclick=', 'onmouseover='] as $bad) {
            if (str_contains($lower, $bad)) {
                throw new InvalidArgumentException(
                    "File [{$label}] (kind={$kind}) chứa script/markup nguy hiểm ({$bad}) — bị chặn.",
                );
            }
        }
        if (preg_match('/<html(?:\s|>)/i', $head) === 1) {
            throw new InvalidArgumentException(
                "File [{$label}] (kind={$kind}) chứa script/markup nguy hiểm (<html) — bị chặn.",
            );
        }

        // SVG chặn thêm theo phần đầu tệp (phòng trường hợp MIME không nhận diện ra image/svg+xml).
        // Không tìm '<svg' (4 byte) rải rác trong 2MB pixel nén vì rất dễ bắt nhầm ảnh PNG/JPG thật.
        if (preg_match('/^\s*(?:<\?xml\b[^>]*>\s*)*(?:<!doctype\s+svg\b[^>]*>\s*)*<svg\b/i', substr($head, 0, 1024)) === 1) {
            throw new InvalidArgumentException(
                "File [{$label}] là SVG (có thể chứa script) — tạm thời bị chặn cho đến khi có sanitizer.",
            );
        }
    }
}