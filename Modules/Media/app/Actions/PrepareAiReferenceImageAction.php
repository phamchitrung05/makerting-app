<?php

namespace Modules\Media\Actions;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Quét bảo mật & chuẩn hóa ảnh tham chiếu trước khi gửi AI.
 * =====================================================================
 *
 * Dùng chung cho cả 2 bước luồng tạo ảnh AI:
 * - Bước 1 (Gợi ý prompt - Vision/Text): ép kích thước <= 1536px & dung lượng
 *   nhẹ (<= 3.5 MB) với MIME tương thích mọi Vision model (JPEG/PNG/WEBP/GIF).
 * - Bước 2 (Tạo ảnh - Image Edit/Reference): giữ nguyên độ phân giải gốc nhưng
 *   chuyển định dạng không phổ biến (AVIF/BMP) sang PNG/JPEG sạch & an toàn.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : UploadedFile + giới hạn kích thước/dung lượng/MIME đích.
 * - OUTPUT: array{mime:string, binary:string}.
 * =====================================================================
 */
class PrepareAiReferenceImageAction
{
    public function __construct(
        private readonly ScanMediaSecurityAction $scanner,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Quét bảo mật UploadedFile và chuẩn hóa binary/MIME cho AI.
     * =====================================================================
     * INPUT:
     * - $file: tệp ảnh tải lên từ UI
     * - $maxInputBytes: giới hạn dung lượng file đầu vào (mặc định 10 MB)
     * - $maxDimension: cạnh dài tối đa nếu cần thu nhỏ cho Vision (null = giữ gốc)
     * - $maxOutputBytes: ngưỡng dung lượng sau chuẩn hóa
     * - $allowedOutputMimes: tập MIME đích tương thích với AI upstream
     * OUTPUT: array{mime:string, binary:string}.
     * EXCEPTION: InvalidArgumentException khi file độc hại/hỏng hoặc không giải mã được.
     * =====================================================================
     */
    public function scanAndRead(
        UploadedFile $file,
        int $maxInputBytes = 10 * 1024 * 1024,
        ?int $maxDimension = null,
        int $maxOutputBytes = 8 * 1024 * 1024,
        array $allowedOutputMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ): array {
        $scan = $this->scanner->scanUploadedFile($file, [MediaAsset::KIND_IMAGE], $maxInputBytes);
        $mime = strtolower((string) ($scan['mime'] ?? 'image/png'));

        $path = $file->getRealPath();
        $binary = is_string($path) && $path !== '' ? @file_get_contents($path) : false;
        if (! is_string($binary) || $binary === '') {
            throw new InvalidArgumentException('Không đọc được nội dung tệp ảnh tham chiếu.');
        }

        $info = @getimagesizefromstring($binary);
        $width = is_array($info) ? (int) ($info[0] ?? 0) : 0;
        $height = is_array($info) ? (int) ($info[1] ?? 0) : 0;

        $mimeSupported = in_array($mime, $allowedOutputMimes, true);
        $needsResize = $maxDimension !== null && ($width > $maxDimension || $height > $maxDimension);
        $needsCompress = strlen($binary) > $maxOutputBytes;

        if ($mimeSupported && ! $needsResize && ! $needsCompress) {
            return [
                'mime' => $mime === 'image/jpg' ? 'image/jpeg' : $mime,
                'binary' => $binary,
            ];
        }

        return $this->reencodeWithGd($binary, $mime, $maxDimension, $maxOutputBytes, $allowedOutputMimes);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Giải mã, thu nhỏ (nếu cần) và nén lại ảnh qua thư viện GD.
     * INPUT: binary gốc, mime, maxDimension, maxOutputBytes, allowedOutputMimes.
     * OUTPUT: array{mime:string, binary:string}.
     * =====================================================================
     */
    private function reencodeWithGd(
        string $binary,
        string $sourceMime,
        ?int $maxDimension,
        int $maxOutputBytes,
        array $allowedOutputMimes,
    ): array {
        if (! function_exists('imagecreatefromstring')) {
            throw new InvalidArgumentException('Máy chủ chưa hỗ trợ xử lý lại định dạng ảnh này. Vui lòng dùng JPG hoặc PNG.');
        }

        $image = @imagecreatefromstring($binary);
        if (! $image && $sourceMime === 'image/avif' && function_exists('imagecreatefromavif')) {
            $tmp = @tempnam(sys_get_temp_dir(), 'ai_ref_');
            if (is_string($tmp)) {
                @file_put_contents($tmp, $binary);
                $image = @imagecreatefromavif($tmp);
                @unlink($tmp);
            }
        }

        if (! $image) {
            throw new InvalidArgumentException('Không thể giải mã tệp ảnh tham chiếu. Vui lòng thử ảnh JPG, PNG hoặc WEBP.');
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $target = $image;

        if ($maxDimension !== null && $maxDimension > 0 && max($srcW, $srcH) > $maxDimension) {
            $scale = $maxDimension / max($srcW, $srcH);
            $newW = max(1, (int) round($srcW * $scale));
            $newH = max(1, (int) round($srcH * $scale));

            $resized = imagecreatetruecolor($newW, $newH);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

            imagedestroy($image);
            $target = $resized;
        }

        // Ưu tiên PNG nếu nguồn PNG/alpha và vừa ngưỡng; ngược lại nén JPEG tối ưu dung lượng.
        if (
            in_array($sourceMime, ['image/png', 'image/webp', 'image/avif'], true)
            && in_array('image/png', $allowedOutputMimes, true)
        ) {
            $png = $this->encodePng($target);
            if ($png !== '' && strlen($png) <= $maxOutputBytes) {
                imagedestroy($target);

                return ['mime' => 'image/png', 'binary' => $png];
            }
        }

        // Flatten alpha lên nền trắng trước khi nén JPEG.
        $w = imagesx($target);
        $h = imagesy($target);
        $flat = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefilledrectangle($flat, 0, 0, $w, $h, $white);
        imagecopy($flat, $target, 0, 0, 0, 0, $w, $h);
        imagedestroy($target);

        foreach ([88, 78, 65] as $quality) {
            ob_start();
            imagejpeg($flat, null, $quality);
            $jpg = (string) ob_get_clean();
            if ($jpg !== '' && strlen($jpg) <= $maxOutputBytes) {
                imagedestroy($flat);

                return ['mime' => 'image/jpeg', 'binary' => $jpg];
            }
        }

        imagedestroy($flat);

        throw new InvalidArgumentException('Ảnh tham chiếu quá lớn sau khi nén. Vui lòng chọn ảnh có dung lượng nhỏ hơn.');
    }

    private function encodePng(\GdImage $image): string
    {
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }
}
