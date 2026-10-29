<?php

namespace Modules\Core\Exceptions;

use RuntimeException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Exception ném ra khi SafeRemoteFetcher từ chối/timeout URL.
 * =====================================================================
 *
 * Được ném khi URL rơi vào dải IP nội bộ/metadata cloud, DNS resolve ra IP
 * cấm, vượt timeout, hoặc body lớn hơn giới hạn — phân biệt lỗi bảo mật
 * (SSRF) với lỗi HTTP thường để controller map status phù hợp.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - blocked(string $url, string $reason): tạo exception do chặn SSRF
 * - network(string $url, string $reason): tạo exception do lỗi mạng
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : URL gây lỗi + lý do
 * - OUTPUT: RemoteFetchException kèm $url + $blockedByPolicy
 * =====================================================================
 */
class RemoteFetchException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $url = '',
        public readonly bool $blockedByPolicy = false,
    ) {
        parent::__construct($message);
    }

    public static function blocked(string $url, string $reason): self
    {
        return new self("URL bị chặn vì lý do an toàn: {$reason}", $url, true);
    }

    public static function network(string $url, string $reason): self
    {
        return new self("Không tải được URL: {$reason}", $url, false);
    }
}
