<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\SafeRemoteFetcherContract;
use Modules\Core\DTOs\RemoteFetchResultDto;
use Modules\Core\Exceptions\RemoteFetchException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tải nội dung URL từ xa có chống SSRF và ghim DNS.
 * =====================================================================
 * Dùng cURL trực tiếp để kiểm soát DNS resolution: resolve hostname ra IP,
 * kiểm tra IP không nằm trong dải nội bộ/metadata cloud, rồi ghim IP đó vào
 * CURLOPT_RESOLVE để chống DNS Rebinding giữa lúc kiểm tra và lúc tải.
 * =====================================================================
 */
class SafeRemoteFetcher implements SafeRemoteFetcherContract
{
    protected const BLOCKED_CIDRS = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
        '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
        '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',
        '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Tải nội dung URL từ xa có chống SSRF + DNS pinning.
     * =====================================================================
     *
     * INPUT :
     *   - string $url        — URL cần tải (chỉ http/https)
     *   - array  $options    — ['timeout' => int giây, 'maxBytes' => int,
     *                          'headers' => array<string,string>, 'userAgent' => string]
     *                          Mặc định đọc từ config('core.remote_fetch.*')
     *
     * OUTPUT: RemoteFetchResultDto — status, body, finalUrl, contentType.
     *         Ném RemoteFetchException khi URL bị chặn / lỗi mạng / quá lớn.
     *
     * SIDE EFFECT:
     *   - DNS query (dns_get_record) để kiểm IP trước khi fetch.
     *   - Mở kết nối TCP qua cURL, ghim IP vào CURLOPT_RESOLVE.
     * =====================================================================
     */
    public function fetch(string $url, array $options = []): RemoteFetchResultDto
    {
        $timeout = (int) ($options['timeout'] ?? config('core.remote_fetch.timeout', 15));
        $maxBytes = (int) ($options['maxBytes'] ?? config('core.remote_fetch.max_bytes', 5 * 1024 * 1024));
        $headers = $options['headers'] ?? [];
        $userAgent = $options['userAgent'] ?? config('core.remote_fetch.user_agent', 'MakertingApp-SafeFetcher/1.0');

        $parts = $this->assertUrlAllowed($url);
        // Host đã bóc [ ] từ assertUrlAllowed — dùng luôn, không đọc lại $parts.
        $host = $parts['host'];
        $scheme = $parts['scheme'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        $ips = $this->resolvePublicIps($host);
        if ($ips === []) {
            throw RemoteFetchException::network($url, 'khong phan giai duoc host');
        }

        // CURLOPT_RESOLVE: IPv6 bắt buộc bọc [ ] quanh IP, IPv4 để nguyên.
        $resolve = array_map(
            static function (string $ip) use ($host, $port): string {
                $ipPart = str_contains($ip, ':') ? "[{$ip}]" : $ip;

                return $host . ':' . $port . ':' . $ipPart;
            },
            [$ips[0]]
        );

        $body = '';
        $received = 0;
        $tooLarge = false;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_RESOLVE => $resolve,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body, &$received, &$tooLarge, $maxBytes): int {
                $received += strlen($chunk);
                if ($received > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);

        $headerArr = [];
        foreach ($headers as $k => $v) {
            $headerArr[] = $k . ': ' . $v;
        }
        if ($headerArr !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
        }

        curl_exec($ch);
        $errno = curl_errno($ch);
        $errstr = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $finalUrl = (string) (curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
        curl_close($ch);

        if ($tooLarge) {
            throw RemoteFetchException::network($url, 'noi dung vuot qua gioi han bytes');
        }
        if ($errno !== 0 && $status === 0) {
            throw RemoteFetchException::network($url, 'loi ket noi (' . $errno . '): ' . $errstr);
        }

        return new RemoteFetchResultDto(
            status: $status,
            body: $body,
            headers: [],
            finalUrl: $finalUrl,
            contentType: $contentType,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra URL có được phép tải không (chống SSRF cơ bản).
     * =====================================================================
     *
     * INPUT : string $url — URL đầy đủ người dùng cung cấp.
     * OUTPUT: array — kết quả parse_url() ['scheme','host','port',...] nếu hợp lệ.
     *         Ném RemoteFetchException khi scheme/host/IP bị chặn.
     *
     * SIDE EFFECT: chỉ chuẩn hóa chuỗi (lowercase, trim, bóc [] IPv6).
     * =====================================================================
     */
    protected function assertUrlAllowed(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        // parse_url trả host IPv6 kèm dấu [ ] — bóc ra để so sánh IP.
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw RemoteFetchException::blocked($url, 'scheme khong duoc phep');
        }
        if ($host === '') {
            throw RemoteFetchException::blocked($url, 'khong co host hop le');
        }

        if ($host === 'localhost'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.localhost')) {
            throw RemoteFetchException::blocked($url, 'host la noi bo');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! $this->isPublicIp($host)) {
                throw RemoteFetchException::blocked($url, 'IP thuoc dai noi bo');
            }
        }

        // Ghi đè host trong $parts bằng bản đã bóc [] để resolvePublicIps
        // và CURLOPT_RESOLVE nhận được hostname/IP sạch.
        $parts['host'] = $host;

        return $parts;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve hostname -> danh sách IP công cộng được phép dùng.
     * =====================================================================
     *
     * INPUT : string $host — hostname đã bóc dấu [] nếu là IPv6 literal.
     * OUTPUT: string[] — các IP public hợp lệ (IPv4/IPv6). Rỗng = không phân
     *         giải được; ném RemoteFetchException nếu mọi IP đều nội bộ.
     *
     * SIDE EFFECT: gọi dns_get_record() (network I/O).
     * =====================================================================
     */
    protected function resolvePublicIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host) ? [$host] : [];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        $ips = [];
        foreach ($records ?: [] as $r) {
            $ip = $r['ip'] ?? ($r['ipv6'] ?? null);
            if ($ip !== null) {
                $ips[] = $ip;
            }
        }

        $public = array_values(array_filter($ips, fn (string $ip): bool => $this->isPublicIp($ip)));

        if ($ips !== [] && $public === []) {
            throw RemoteFetchException::blocked($host, 'DNS tro ve dia chi noi bo');
        }

        return $public;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Quyết định 1 IP có phải public không (chặn SSRF IPv4+IPv6).
     * =====================================================================
     *
     * INPUT : string $ip — địa chỉ IP (IPv4 hoặc IPv6, không dấu []).
     * OUTPUT: bool — true = được phép (public); false = nội bộ/loopback/
     *         link-local/reserved → chặn.
     *
     * SIDE EFFECT: không có (chỉ tính toán trên chuỗi).
     *
     * CÁC TRƯỜNG HỢP CHẶN BỔ SUNG:
     *   - IPv4-mapped IPv6 (::ffff:a.b.c.d) → decode rồi check theo IPv4 CIDR.
     *   - ::1 (loopback), :: (unspecified), fe80::/10 (link-local),
     *     fc00::/7 (unique local), 2001:db8::/32 (documentation).
     * =====================================================================
     */
    protected function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if ($packed === false) {
                return false;
            }

            // IPv4-mapped IPv6: ::ffff:a.b.c.d — 12 byte đầu là 0x00*10 + 0xFF*2,
            // 4 byte cuối là IPv4 thường. Decode rồi check theo BLOCKED_CIDRS.
            $prefix = substr($packed, 0, 12);
            if ($prefix === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
                $ipv4 = inet_ntop(substr($packed, 12));
                if ($ipv4 !== false) {
                    return $this->isPublicIp($ipv4);
                }
                return false;
            }

            // IPv4-compatible IPv6 (deprecated): ::a.b.c.d — 12 byte đầu 0x00,
            // 4 byte cuối IPv4 (trừ :: và ::1 đã check riêng).
            if ($prefix === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00") {
                $ipv4 = inet_ntop(substr($packed, 12));
                if ($ipv4 !== false && $ipv4 !== '0.0.0.0' && $ipv4 !== '0.0.0.1') {
                    return $this->isPublicIp($ipv4);
                }
                return false;
            }

            // :: (all-zeros, unspecified) và ::1 (loopback).
            if ($ip === '::' || $ip === '::1' || $packed === str_repeat("\x00", 15) . "\x01") {
                return false;
            }

            $first = ord($packed[0]);
            $second = ord($packed[1]);

            // fe80::/10 — link-local.
            if ($first === 0xfe && ($second & 0xc0) === 0x80) {
                return false;
            }
            // fc00::/7 — unique local (ULA, private).
            if (($first & 0xfe) === 0xfc) {
                return false;
            }
            // 2001:db8::/32 — documentation prefix.
            if ($first === 0x20 && $second === 0x01
                && ord($packed[2]) === 0x0d && ord($packed[3]) === 0xb8) {
                return false;
            }
            // ff00::/8 — multicast.
            if ($first === 0xff) {
                return false;
            }

            return true;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $long = ip2long($ip);
        foreach (self::BLOCKED_CIDRS as $cidr) {
            [$subnet, $bits] = explode('/', $cidr);
            $mask = -1 << (32 - (int) $bits);
            if (($long & $mask) === (ip2long($subnet) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
