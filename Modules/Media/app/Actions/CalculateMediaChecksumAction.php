<?php

namespace Modules\Media\Actions;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tính checksum SHA-256 của file media để chống upload trùng.
 * =====================================================================
 *
 * Dùng cho `UploadMediaAssetAction` trước khi ghi DB — nếu checksum đã
 * tồn tại trên `media_assets.checksum_sha256`, trả về asset cũ thay vì
 * upload lại (tiết kiệm storage + tránh trùng lặp ảnh/video).
 *
 * Hỗ trợ 3 nguồn:
 * - `UploadedFile` (multipart/form-data)
 * - đường dẫn file trên disk (string path tuyệt đối)
 * - nội dung binary (string bytes — khi sinh ảnh AI)
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - fromUploadedFile() : checksum file upload Laravel.
 * - fromPath()         : checksum file trên disk.
 * - fromContent()      : checksum nội dung binary.
 * - resolve()          : tự nhận dạng nguồn -> checksum.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : UploadedFile|path|binary.
 * - OUTPUT: chuỗi hex sha256 (64 ký tự).
 * =====================================================================
 */
class CalculateMediaChecksumAction
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Checksum một UploadedFile Laravel
     * =====================================================================
     * INPUT: UploadedFile. OUTPUT: sha256 hex.
     * EXCEPTION: khi file lỗi/không đọc được.
     * =====================================================================
     */
    public function fromUploadedFile(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if (! $path || ! is_file($path)) {
            throw new InvalidArgumentException('UploadedFile không hợp lệ hoặc không đọc được.');
        }

        return $this->fromPath($path);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Checksum một file trên disk
     * =====================================================================
     * INPUT: $path — đường dẫn tuyệt đối. OUTPUT: sha256 hex.
     * EXCEPTION: khi path không tồn tại/không phải file.
     * =====================================================================
     */
    public function fromPath(string $path): string
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Đường dẫn [{$path}] không phải file.");
        }

        $hash = @hash_file('sha256', $path);
        if ($hash === false) {
            throw new InvalidArgumentException("Không đọc được file [{$path}] để checksum.");
        }

        return $hash;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Checksum nội dung binary (ảnh AI sinh ra)
     * =====================================================================
     * INPUT: $content — chuỗi bytes. OUTPUT: sha256 hex.
     * =====================================================================
     */
    public function fromContent(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tự nhận dạng nguồn -> checksum
     * =====================================================================
     * - UploadedFile -> fromUploadedFile()
     * - string is_file -> fromPath()
     * - string còn lại -> fromContent() (binary)
     *
     * INPUT: UploadedFile|string. OUTPUT: sha256 hex.
     * EXCEPTION: InvalidArgumentException khi nguồn không hỗ trợ.
     * =====================================================================
     */
    public function resolve(UploadedFile|string $source): string
    {
        if ($source instanceof UploadedFile) {
            return $this->fromUploadedFile($source);
        }

        if (is_string($source) && is_file($source)) {
            return $this->fromPath($source);
        }

        if (is_string($source)) {
            return $this->fromContent($source);
        }

        throw new InvalidArgumentException('Nguồn checksum không được hỗ trợ.');
    }
}
