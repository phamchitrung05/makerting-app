<?php

namespace Modules\WritingStyle\Services;

use Modules\Core\Contracts\SafeRemoteFetcherContract;
use Modules\Core\DTOs\RemoteFetchResultDto;
use Modules\Core\Exceptions\RemoteFetchException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bóc nội dung bài viết từ URL người dùng nhập (GĐ5).
 * =====================================================================
 *
 * Tải HTML qua SafeRemoteFetcherContract (đã có chống SSRF + DNS pinning),
 * rồi tách phần văn bản chính bằng DOMDocument/DOMXPath của PHP core —
 * loại script/style/nav/footer/header/aside/iframe/form để giữ lại
 * phần văn bản thuần phục vụ giải mã văn phong.
 *
 * SafeRemoteFetcher KHÔNG follow redirect (CURLOPT_FOLLOWLOCATION=false)
 * và KHÔNG ném lỗi khi server trả 404/500 — nên fetcher này tự xử lý:
 * - Đọc headers['location'] để theo redirect tối đa 3 lần, mỗi lần gọi lại
 *   qua SafeRemoteFetcherContract (IP được re-check chống SSRF qua đàu).
 * - Kiểm HTTP status 2xx và content-type text/html trước khi bóc nội dung.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - fetchFromUrl(string $url): trích reference_text + preview meta từ URL.
 * - followRedirects(string $url): theo 3xx tới URL cuối (re-check SSRF).
 * - extractBodyText(string $html): bóc nội dung chính từ HTML.
 * - extractTitle(string $html): lấy <title>/og:title.
 * - buildMeta(RemoteFetchResultDto, string): {domain,title,word_count}.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : URL http/https công khai.
 * - OUTPUT: array{reference_text:string, meta:array{domain,title,word_count}}
 *           — ném RemoteFetchException khi bị chặn / không tải được / nội
 *           dung quá ngắn để giải mã.
 * =====================================================================
 */
class ReferenceSourceFetcher
{
    /** Số redirect tối đa được theo (301/302/303/307/308). */
    protected const MAX_REDIRECTS = 3;

    /** Độ dài body tối đa nhận xử lý (khớp giới hạn max của AnalyzeTextRequest). */
    protected const MAX_BYTES = 2 * 1024 * 1024;

    /** Độ dài nội dung tối thiểu sau khi strip — ngắn hơn coi như lỗi nghiệp vụ. */
    protected const MIN_CHARS = 50;

    public function __construct(
        protected readonly SafeRemoteFetcherContract $fetcher,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Tải URL, bóc phần nội dung bài viết và meta preview.
     * =====================================================================
     *
     * INPUT : string $url — URL bài viết công khai.
     *
     * OUTPUT: array{
     *   reference_text : string — văn bản thuần đã strip boilerplate,
     *   meta           : array{domain:string, title:string, word_count:int}
     * }
     *
     * SIDE EFFECT: gọi mạng tới URL (qua SafeRemoteFetcherContract).
     *
     * EXCEPTION: RemoteFetchException khi URL bị chặn SSRF, không tải được,
     *            HTTP status không phải 2xx, content không phải HTML, hoặc
     *            nội dung bóc được ngắn hơn MIN_CHARS.
     * =====================================================================
     */
    public function fetchFromUrl(string $url): array
    {
        $result = $this->followRedirects($url);

        $this->assertSuccess($result);
        $this->assertHtml($result);

        $text = $this->extractBodyText($result->body);
        if (mb_strlen($text) < self::MIN_CHARS) {
            throw RemoteFetchException::network(
                $result->finalUrl,
                'noi dung trich xuat qua ngan (<' . self::MIN_CHARS . ' ky tu) — co the trang can dang nhap hoac la SPA',
            );
        }

        // Cắt mềm nội dung quá dài thay vì fail — AnalyzeTextRequest giới
        // hạn 200000 ký tự; giữ phần đầu để giải mã vẫn đại diện được giọng.
        if (mb_strlen($text) > 180000) {
            $text = mb_substr($text, 0, 180000);
        }

        return [
            'reference_text' => $text,
            'meta'           => $this->buildMeta($result, $text),
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gọi fetch và theo chuỗi redirect tới URL cuối cùng.
     * =====================================================================
     * SafeRemoteFetcher không follow redirect, nên đây là chỗ duy nhất biết
     * header Location. Mỗi hop gọi lại qua contract nên IP đích vẫn được
     * re-validate — chống redirect SSRF (URL hợp lệ → chuyển sang IP nội bộ).
     *
     * INPUT : string $url — URL khởi đầu.
     * OUTPUT: RemoteFetchResultDto của request cuối (status không phải 3xx).
     * EXCEPTION: RemoteFetchException khi vượt MAX_REDIRECTS / Location lạ.
     * =====================================================================
     */
    protected function followRedirects(string $url): RemoteFetchResultDto
    {
        $current = $url;

        for ($i = 0; $i <= self::MAX_REDIRECTS; $i++) {
            $result = $this->fetcher->fetch($current, [
                'timeout'  => 15,
                'maxBytes' => self::MAX_BYTES,
            ]);

            if (! $this->isRedirect($result->status)) {
                return $result;
            }

            $location = $result->headers['location'] ?? null;
            if (! is_string($location) || $location === '') {
                // Server báo 3xx nhưng không cấp Location — coi như response cuối.
                return $result;
            }

            $current = $this->resolveLocation($current, $location);
        }

        throw RemoteFetchException::network($url, 'vuot qua so luot redirect toi da (' . self::MAX_REDIRECTS . ')');
    }

    /**
     * CHỨC NĂNG: status có phải mã redirect mà cần follow không.
     * INPUT : int status. OUTPUT: bool.
     */
    protected function isRedirect(int $status): bool
    {
        return in_array($status, [301, 302, 303, 307, 308], true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa Location header thành URL tuyệt đối.
     * =====================================================================
     * Location có thể là đường tương đối (/path, ../path, //host/path).
     * Ghép với base URL để có URL đầy đủ trước khi re-validate.
     *
     * INPUT : string $base (URL hiện tại), string $location (header).
     * OUTPUT: string URL tuyệt đối.
     * =====================================================================
     */
    protected function resolveLocation(string $base, string $location): string
    {
        $location = trim($location);

        // URL tuyệt đối / protocol-relative -> normalize rồi dùng luôn.
        if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
            return $location;
        }

        if (str_starts_with($location, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $location;
        }

        $parts = parse_url($base) ?: [];
        $basePrefix = ($parts['scheme'] ?? 'https') . '://'
            . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $basePrefix . $location;
        }

        // Đường tương đối — ghép vào thư mục của base path.
        $dir = preg_replace('/[^\/]*$/', '', (string) ($parts['path'] ?? '/'));

        return $basePrefix . $dir . $location;
    }

    /**
     * CHỨC NĂNG: Chắc status HTTP là 2xx — SafeRemoteFetcher không ném 404/500.
     * INPUT : RemoteFetchResultDto. OUTPUT: void. EXCEPTION: lỗi HTTP.
     */
    protected function assertSuccess(RemoteFetchResultDto $result): void
    {
        if (! $result->isSuccess()) {
            throw RemoteFetchException::network(
                $result->finalUrl,
                'may chu tra ve HTTP ' . $result->status,
            );
        }
    }

    /**
     * CHỨC NĂNG: Chắc content-type là HTML/văn bản — từ chối binary/PDF/JSON.
     * INPUT : RemoteFetchResultDto. OUTPUT: void. EXCEPTION: sai loại nội dung.
     */
    protected function assertHtml(RemoteFetchResultDto $result): void
    {
        $contentType = strtolower((string) $result->contentType);

        // Cho phép HTML và plain text; từ chối PDF/ảnh/JSON/CSV/XML-JSON.
        $looksText = str_contains($contentType, 'text/html')
            || str_contains($contentType, 'text/plain')
            || str_contains($contentType, 'application/xhtml');

        if ($contentType !== '' && ! $looksText) {
            throw RemoteFetchException::network(
                $result->finalUrl,
                'content-type khong phai van ban (' . $contentType . ')',
            );
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Bóc phần văn bản chính từ HTML bằng DOMDocument core.
     * =====================================================================
     * Chiến lược ưu tiên <article>/<main>; nếu không có thì lấy toàn <body>
     * sau khi xóa các khối boilerplate (script/style/nav/header/footer/aside/
     * form/iframe/noscript/comment). Không cần dependency ngoài.
     *
     * INPUT : string $html — HTML thô đã tải.
     * OUTPUT: string — văn bản thuần đã normalize khoảng trắng.
     * =====================================================================
     */
    protected function extractBodyText(string $html): string
    {
        $dom = new \DOMDocument();

        // Nuốt warning từ HTML lỗi cú pháp của thế giới thật.
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($dom);

        // Xóa các thẻ không chứa nội dung bài viết.
        foreach (['script', 'style', 'nav', 'header', 'footer', 'aside', 'form',
                  'iframe', 'noscript', 'template', 'svg', 'select'] as $tag) {
            foreach (iterator_to_array($xpath->query('//' . $tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Xóa comment (HTML comment không phải node content).
        foreach (iterator_to_array($xpath->query('//comment()')) as $node) {
            $node->parentNode?->removeChild($node);
        }

        // Ưu tiên phần tử chứa bài viết; fallback toàn <body>.
        $container = $xpath->query('//article')->item(0)
            ?? $xpath->query('//main')->item(0)
            ?? $xpath->query('//body')->item(0)
            ?? $dom->documentElement;

        $text = $container === null ? '' : $container->textContent;

        return $this->normalizeWhitespace($text);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy tiêu đề trang cho meta preview (title tag > og:title).
     * =====================================================================
     * INPUT : string $html. OUTPUT: ?string tiêu đề đã decode entity.
     * =====================================================================
     */
    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    /**
     * CHỨC NĂNG: Chuẩn hóa khoảng trắng sau strip — gộp dòng, gộp space.
     * INPUT : string raw text. OUTPUT: string đã normalize.
     */
    protected function normalizeWhitespace(string $text): string
    {
        // Decode entity phổ biến (&nbsp; &amp; ...) trước khi gộp khoảng trắng.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text); // &nbsp; UTF-8 bytes
        $text = preg_replace('/[\r\n\t]+/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);  // giữ đoạn, bỏ trống dư
        $text = preg_replace('/[ \x{00A0}]+/u', ' ', $text);

        return trim($text);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Dựng meta preview cho FE hiển thị hộp thông tin bài viết.
     * =====================================================================
     * INPUT : RemoteFetchResultDto đã xử lý + văn bản đã strip.
     * OUTPUT: array{domain:string, title:string, word_count:int}
     * =====================================================================
     */
    protected function buildMeta(RemoteFetchResultDto $result, string $text): array
    {
        $host = parse_url($result->finalUrl, PHP_URL_HOST) ?: '';

        return [
            'domain'     => $host,
            'title'      => $this->extractTitle($result->body) ?? $host,
            'word_count' => count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []),
        ];
    }
}
