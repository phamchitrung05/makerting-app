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

    public function fetch(string $url, array $options = []): RemoteFetchResultDto
    {
        $timeout = (int) ($options['timeout'] ?? 15);
        $maxBytes = (int) ($options['maxBytes'] ?? 5 * 1024 * 1024);
        $headers = $options['headers'] ?? [];
        $userAgent = $options['userAgent'] ?? 'MakertingApp-SafeFetcher/1.0';

        $parts = $this->assertUrlAllowed($url);
        $host = $parts['host'];
        $scheme = $parts['scheme'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        $ips = $this->resolvePublicIps($host);
        if ($ips === []) {
            throw RemoteFetchException::network($url, 'khong phan giai duoc host');
        }

        $resolve = array_map(
            static fn (string $ip): string => $host . ':' . $port . ':' . $ip,
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

    protected function assertUrlAllowed(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

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

        return $parts;
    }

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

    protected function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if ($packed === false || $ip === '::1') {
                return false;
            }
            $first = ord($packed[0]);
            $second = ord($packed[1]);
            if ($first === 0xfe && ($second & 0xc0) === 0x80) {
                return false;
            }
            if (($first & 0xfe) === 0xfc) {
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
