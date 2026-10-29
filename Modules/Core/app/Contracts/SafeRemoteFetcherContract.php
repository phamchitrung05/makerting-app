<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\RemoteFetchResultDto;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng tải nội dung từ URL từ xa có chống SSRF.
 * =====================================================================
 *
 * Mọi module cần tải URL người dùng nhập (bài mẫu, ảnh tham chiếu, RSS...)
 * đều phải đi qua fetcher này thay vì file_get_contents/curl trực tiếp —
 * chặn IP nội bộ + metadata cloud và ghim DNS chống DNS Rebinding.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - fetch(string $url, array $options): tải URL, trả RemoteFetchResultDto
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : URL http/https công khai + tùy chọn timeout/size
 * - OUTPUT: RemoteFetchResultDto (status, body, headers, finalUrl)
 * =====================================================================
 */
interface SafeRemoteFetcherContract
{
    public function fetch(string $url, array $options = []): RemoteFetchResultDto;
}
