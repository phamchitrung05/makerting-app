<?php

namespace Modules\CoreAi\Services;

use Modules\Core\DTOs\RemoteFetchResultDto;
use Modules\Core\Services\SafeRemoteFetcher;
use Modules\CoreAi\Exceptions\AiGatewayException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: HTTP client chuyên dụng gọi API nhà cung cấp AI.
 * =====================================================================
 *
 * Kế thừa `SafeRemoteFetcher` để tái dùng chống SSRF + DNS pinning
 * (assertUrlAllowed + resolvePublicIps + CURLOPT_RESOLVE). Mở rộng cho
 * nhu cầu AI:
 * - POST/PUT JSON + multipart (upload audio/binary).
 * - Nhận binary lớn (audio TTS, ảnh sinh ra) — maxBytes riêng.
 * - Timeout theo provider (`request_timeout`, default 120s).
 *   Thời gian sống của worker CLI do queue/process manager quản lý; client
 *   không thay max_execution_time của CLI sau mỗi request AI.
 * - Map lỗi HTTP/curl sang `AiGatewayException` với errorCode phân loại
 *   và context KHÔNG chứa api key/header Authorization.
 *
 * Key rotation (`extra_keys_json`): khi request trả 429/401 có thể thử
 * key tiếp theo trong `provider->allKeys()` — caller (driver) gọi
 * `request()` trong vòng lặp theo số key còn lại.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - request()         : gọi HTTP tới provider (JSON/multipart, key rotate).
 * - requestJson()     : POST JSON, decode response array.
 * - buildAuthHeaders(): headers chuẩn theo driver (auth, version...).
 * - mapHttpError()    : status+errno → AiGatewayException (sanitize ctx).
 * - rotateContext()   : context chẩn đoán không chứa secret.
 * - maxKeyIndex()     : số key có thể thử (1 main + N extra).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AiProvider + method/url/headers/body (+keyIndex cho rotation).
 * - OUTPUT: RemoteFetchResultDto|array — driver chuyển thành DTO riêng.
 * =====================================================================
 */
class AiProviderClient extends SafeRemoteFetcher
{
    /** Mặc định kích thước response tối đa (32MB — đủ audio/ảnh lớn). */
    protected int $maxBytes = 32 * 1024 * 1024;

    /** Boundary multipart vừa build (để normalizeBody gắn vào Content-Type). */
    protected ?string $lastMultipartBoundary = null;

    /**
     * =====================================================================
     * CHỨC NĂNG: Gọi HTTP tới provider AI — POST JSON/multipart, binary
     * response, timeout riêng, và hỗ trợ xoay vòng key qua keyIndex.
     * =====================================================================
     * INPUT :
     *   - AiProvider $provider — config + keys.
     *   - string $method  — GET/POST/PUT/DELETE.
     *   - string $url     — endpoint đầy đủ (đã ghép base_url).
     *   - array  $headers — headers bổ sung.
     *   - string|array|null $body — JSON string / multipart / binary.
     *   - array  $options — 'keyIndex','timeout','maxBytes','contentType'.
     *
     * OUTPUT: RemoteFetchResultDto — status/body/contentType/finalUrl.
     * EXCEPTION: AiGatewayException (network/auth/rate_limit/provider_error).
     * SIDE EFFECT:
     * - DNS resolve + TCP qua cURL + CURLOPT_RESOLVE (DNS pin).
     * - Trong web request, tăng thời gian chạy PHP để đủ ngân sách HTTP và
     *   xử lý kết quả. Trong CLI, giữ nguyên max_execution_time của caller để
     *   không để lại đồng hồ hữu hạn làm queue worker chết lúc chờ job mới.
     * - Timeout HTTP vẫn áp dụng bằng CURLOPT_TIMEOUT ở mọi SAPI.
     * TRANSACTION: Không mở transaction.
     * =====================================================================
     */
    public function request(
        \Modules\CoreAi\Models\AiProvider $provider,
        string $method,
        string $url,
        array $headers = [],
        string|array|null $body = null,
        array $options = [],
    ): RemoteFetchResultDto {
        $method = strtoupper($method);
        $timeout = (int) ($options['timeout'] ?? $provider->request_timeout ?? 120);
        if (PHP_SAPI !== 'cli') {
            @set_time_limit(max(180, $timeout + 30));
        }
        $maxBytes = (int) ($options['maxBytes'] ?? $this->maxBytes);
        $keyIndex = (int) ($options['keyIndex'] ?? 0);

        // SSRF + DNS pin — tái dùng từ SafeRemoteFetcher.
        $parts = $this->assertUrlAllowed($url);
        $host = $parts['host'];
        $scheme = $parts['scheme'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        $ips = $this->resolvePublicIps($host);
        if ($ips === []) {
            throw AiGatewayException::network('khong phan giai duoc host', $this->rotateContext($provider, $url, $keyIndex));
        }

        $resolve = array_map(
            static function (string $ip) use ($host, $port): string {
                $ipPart = str_contains($ip, ':') ? "[{$ip}]" : $ip;
                return $host . ':' . $port . ':' . $ipPart;
            },
            [$ips[0]]
        );

        [$bodyStr, $contentType] = $this->normalizeBody($body, $options);

        $bodyOut = '';
        $received = 0;
        $tooLarge = false;

        $ch = curl_init();
        $curlHeaders = $headers;
        if ($contentType !== null && ! $this->hasHeader($headers, 'Content-Type')) {
            $curlHeaders[] = 'Content-Type: ' . $contentType;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_RESOLVE => $resolve,
            CURLOPT_USERAGENT => 'MakertingApp-CoreAi/1.0',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$bodyOut, &$received, &$tooLarge, $maxBytes): int {
                $received += strlen($chunk);
                if ($received > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $bodyOut .= $chunk;
                return strlen($chunk);
            },
        ]);

        if ($curlHeaders !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($bodyStr !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
            }
        }

        $started = microtime(true);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $errstr = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $finalUrl = (string) (curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
        $contentTypeResp = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        curl_close($ch);

        $ctx = $this->rotateContext($provider, $url, $keyIndex, $status, $latencyMs);

        if ($tooLarge) {
            throw AiGatewayException::providerError('response vuot qua gioi han bytes', $ctx);
        }
        if ($errno !== 0 && $status === 0) {
            $isTimeout = $errno === CURLE_OPERATION_TIMEDOUT;
            $code = $isTimeout ? 'timeout' : 'network';
            throw new AiGatewayException('loi ket noi (' . $errno . '): ' . $errstr, $code, $ctx);
        }
        if ($status >= 400) {
            throw $this->mapHttpError($status, $bodyOut, $ctx);
        }

        return new RemoteFetchResultDto(
            status: $status,
            body: $bodyOut,
            headers: ['x-latency-ms' => (string) $latencyMs],
            finalUrl: $finalUrl,
            contentType: $contentTypeResp,
        );
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: POST JSON và decode response thành array.
     * INPUT: provider + url + payload + headers + options.
     * OUTPUT: array — json_decode(response body).
     * =====================================================================
     */
    public function requestJson(
        \Modules\CoreAi\Models\AiProvider $provider,
        string $url,
        array $payload = [],
        array $headers = [],
        array $options = [],
        string $method = 'POST',
    ): array {
        $body = $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $options['contentType'] = 'application/json';

        $res = $this->request($provider, $method, $url, $headers, $body, $options);

        $data = json_decode($res->body, true);
        if (! is_array($data)) {
            throw AiGatewayException::providerError(
                'response khong phai JSON hop le',
                $this->rotateContext($provider, $url, (int) ($options['keyIndex'] ?? 0), $res->status)
            );
        }

        return $data;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Build headers chuẩn cho driver (auth + version + custom).
     * INPUT: authHeader + apiKey + scheme + extra. OUTPUT: string[].
     * =====================================================================
     */
    public function buildAuthHeaders(string $authHeader, string $apiKey, string $scheme = 'Bearer', array $extra = []): array
    {
        $headers = [];
        $value = $scheme !== '' ? $scheme . ' ' . $apiKey : $apiKey;
        $headers[] = $authHeader . ': ' . $value;

        foreach ($extra as $name => $v) {
            $headers[] = $name . ': ' . $v;
        }

        return $headers;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Map HTTP status → AiGatewayException có errorCode đúng.
     * INPUT: status + body + context. OUTPUT: AiGatewayException.
     * =====================================================================
     */
    protected function mapHttpError(int $status, string $body, array $ctx): AiGatewayException
    {
        $msg = $this->extractErrorMessage($body) ?? "HTTP {$status}";

        return match (true) {
            $status === 401 || $status === 403 => AiGatewayException::auth($msg, $ctx),
            $status === 429 => AiGatewayException::rateLimit($msg, $ctx),
            $status >= 500 => AiGatewayException::providerError($msg, $ctx),
            // 404 = endpoint không tồn tại (mã riêng để driver compatible chỉ
            // tha trường hợp này — KHÔNG tha 400/422 là lỗi request thật).
            $status === 404 => AiGatewayException::withCode('not_found', $msg, $ctx),
            $status === 400 || $status === 422 => AiGatewayException::withCode('invalid_request', $msg, $ctx),
            default => AiGatewayException::providerError($msg, $ctx),
        };
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Context chẩn đoán an toàn — KHÔNG chứa api key/secret.
     * INPUT: provider + url + keyIndex + status + latencyMs. OUTPUT: array.
     * =====================================================================
     */
    protected function rotateContext(
        \Modules\CoreAi\Models\AiProvider $provider,
        string $url,
        int $keyIndex = 0,
        ?int $status = null,
        ?int $latencyMs = null,
    ): array {
        return [
            'provider_key' => $provider->key,
            'driver' => $provider->driver,
            'base_url' => $provider->base_url,
            'url' => $url,
            'key_index' => $keyIndex,
            'status' => $status,
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Số key có thể thử (index cao nhất = count(allKeys)-1).
     * INPUT: provider. OUTPUT: int >=0.
     * =====================================================================
     */
    public function maxKeyIndex(\Modules\CoreAi\Models\AiProvider $provider): int
    {
        return max(0, count($provider->allKeys()) - 1);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy key plaintext theo index (0=main, 1..N=extra).
     * INPUT: provider + index. OUTPUT: ?string — null nếu vượt quá.
     * =====================================================================
     */
    public function keyAt(\Modules\CoreAi\Models\AiProvider $provider, int $index): ?string
    {
        return $provider->allKeys()[$index] ?? null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa body thành string + content-type.
     * INPUT: body + options. OUTPUT: [?string bodyStr, ?string contentType].
     * =====================================================================
     */
    protected function normalizeBody(string|array|null $body, array $options): array
    {
        if ($body === null) {
            return [null, null];
        }
        if (is_string($body)) {
            return [$body, $options['contentType'] ?? 'application/json'];
        }

        // array — multipart nếu có field file, ngược lại JSON.
        foreach ($body as $v) {
            if (is_array($v) && (isset($v['path']) || isset($v['contents']) || isset($v['file']))) {
                $mp = $this->buildMultipart($body);

                return [$mp, ($options['contentType'] ?? 'multipart/form-data') . '; boundary=' . $this->lastMultipartBoundary];
            }
        }

        return [json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json'];
    }
    /**
     * =====================================================================
     * CHỨC NĂNG: Build multipart/form-data body string thủ công.
     * INPUT: array fields. OUTPUT: string body multipart.
     * =====================================================================
     */
    protected function buildMultipart(array $fields): string
    {
        $boundary = '----CoreAiFormBoundary' . bin2hex(random_bytes(8));
        $this->lastMultipartBoundary = $boundary;
        $lines = [];
        foreach ($fields as $name => $value) {
            if (is_array($value) && (isset($value['path']) || isset($value['contents']))) {
                $contents = $value['contents'] ?? (isset($value['path']) && is_file($value['path']) ? file_get_contents($value['path']) : '');
                $filename = $value['filename'] ?? (isset($value['path']) ? basename($value['path']) : 'file');
                $mime = $value['mime'] ?? 'application/octet-stream';
                $lines[] = "--{$boundary}";
                $lines[] = 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $filename . '"';
                $lines[] = 'Content-Type: ' . $mime;
                $lines[] = '';
                $lines[] = $contents;
            } else {
                $lines[] = "--{$boundary}";
                $lines[] = 'Content-Disposition: form-data; name="' . $name . '"';
                $lines[] = '';
                $lines[] = is_scalar($value) ? (string) $value : json_encode($value);
            }
        }
        $lines[] = "--{$boundary}--";
        $lines[] = '';

        return implode("\r\n", $lines);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra header đã có trong mảng chưa.
     * INPUT: headers[] + name. OUTPUT: bool.
     * =====================================================================
     */
    protected function hasHeader(array $headers, string $name): bool
    {
        $prefix = strtolower($name) . ':';
        foreach ($headers as $h) {
            if (stripos($h, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Trích thông điệp lỗi từ body provider trả về.
     * INPUT: body string. OUTPUT: ?string.
     * =====================================================================
     */
    protected function extractErrorMessage(string $body): ?string
    {
        $data = json_decode($body, true);
        if (! is_array($data)) {
            return mb_substr($body, 0, 300);
        }

        // Thử các key lỗi phổ biến của OpenAI/Anthropic/Gemini.
        $err = $data['error'] ?? $data['errors'] ?? $data['message'] ?? null;
        if (is_array($err)) {
            return $err['message'] ?? $err['msg'] ?? json_encode($err);
        }
        if (is_string($err)) {
            return $err;
        }

        return null;
    }
}
