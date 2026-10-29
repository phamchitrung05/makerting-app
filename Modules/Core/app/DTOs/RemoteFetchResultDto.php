<?php

namespace Modules\Core\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO kết quả tải URL từ xa (bất biến).
 * =====================================================================
 *
 * Kết quả trả về của SafeRemoteFetcherContract::fetch(). Truyền qua ranh giới
 * module dưới dạng readonly — không mang Eloquent model của module khác.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : status HTTP, body nhị phân/text, headers, finalUrl sau redirect
 * - OUTPUT: readonly object dùng đọc nội dung đã tải
 * =====================================================================
 */
final readonly class RemoteFetchResultDto
{
    /**
     * CHỨC NĂNG: khởi tạo DTO kết quả fetch.
     * INPUT: status, body, headers (assoc), finalUrl, contentType.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers,
        public string $finalUrl,
        public ?string $contentType = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: kiểm tra response có thành công (2xx) không
     * =====================================================================
     *
     * INPUT: không có tham số.
     * OUTPUT: bool.
     * SIDE EFFECT: không có.
     * =====================================================================
     */
    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
