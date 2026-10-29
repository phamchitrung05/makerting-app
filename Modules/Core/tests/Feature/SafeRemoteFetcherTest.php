<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\Exceptions\RemoteFetchException;
use Modules\Core\Services\SafeRemoteFetcher;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Test SafeRemoteFetcher chặn SSRF (không gọi mạng thật).
 * =====================================================================
 * Chỉ kiểm chứng nhánh chặn bảo mật (assertUrlAllowed/resolvePublicIps) —
 * không thực hiện HTTP request ra ngoài trong test.
 * =====================================================================
 */
class SafeRemoteFetcherTest extends TestCase
{
    private function fetcher(): SafeRemoteFetcher
    {
        return new SafeRemoteFetcher();
    }

    public function test_chan_scheme_khong_http(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('file:///etc/passwd');
    }

    public function test_chan_ip_loopback(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://127.0.0.1/admin');
    }

    public function test_chan_ip_private(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://192.168.1.1/router');
    }

    public function test_chan_metadata_cloud(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://169.254.169.254/latest/meta-data');
    }

    public function test_chan_host_localhost(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://localhost:8000/api');
    }

    public function test_chan_ip_noi_bo_10(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://10.0.0.5/internal');
    }

    /**
     * CHỨC NĂNG: chặn IPv6 loopback (::1) — trước đây bypass được.
     * INPUT : URL http://[::1]/. OUTPUT: RemoteFetchException.
     */
    public function test_chan_ipv6_loopback(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://[::1]/admin');
    }

    /**
     * CHỨC NĂNG: chặn IPv4-mapped IPv6 (::ffff:127.0.0.1) — bypass SSRF IPv4.
     * INPUT : URL http://[::ffff:127.0.0.1]/. OUTPUT: RemoteFetchException.
     */
    public function test_chan_ipv4_mapped_ipv6_loopback(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://[::ffff:127.0.0.1]/admin');
    }

    /**
     * CHỨC NĂNG: chặn IPv4-mapped IPv6 trỏ tới metadata cloud (169.254.169.254).
     * INPUT : URL http://[::ffff:169.254.169.254]/. OUTPUT: RemoteFetchException.
     */
    public function test_chan_ipv4_mapped_ipv6_metadata(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://[::ffff:169.254.169.254]/latest/meta-data');
    }

    /**
     * CHỨC NĂNG: chặn IPv4-mapped IPv6 trỏ tới dải private 10.x.
     * INPUT : URL http://[::ffff:10.0.0.1]/. OUTPUT: RemoteFetchException.
     */
    public function test_chan_ipv4_mapped_ipv6_private(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://[::ffff:10.0.0.1]/internal');
    }

    /**
     * CHỨC NĂNG: chặn IPv6 unspecified (::).
     * INPUT : URL http://[::]/. OUTPUT: RemoteFetchException.
     */
    public function test_chan_ipv6_unspecified(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://[::]/');
    }

    /**
     * CHỨC NĂNG: chặn IPv6 link-local fe80::/10.
     * INPUT : URL http://[fe80::1]/. OUTPUT: RemoteFetchException.
     */
    public function test_chan_ipv6_link_local(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://[fe80::1]/');
    }

    /**
     * CHỨC NĂNG: chặn IPv6 unique-local fc00::/7 (private network).
     * INPUT : URL http://[fd00::1]/. OUTPUT: RemoteFetchException.
     */
    public function test_chan_ipv6_unique_local(): void
    {
        $this->expectException(RemoteFetchException::class);
        $this->fetcher()->fetch('http://[fd00::1]/internal');
    }

    /**
     * CHỨC NĂNG: resolvePublicIps trả IP sạch khi host đã bóc [].
     * INPUT : gọi qua reflection với host '2606:4700::1111' (Cloudflare DNS).
     * OUTPUT: mảng chứa '2606:4700::1111'.
     */
    public function test_resolve_ipv6_literal_tra_ip_sach(): void
    {
        $fetcher = $this->fetcher();
        $method = new \ReflectionMethod($fetcher, 'resolvePublicIps');
        $method->setAccessible(true);

        $ips = $method->invoke($fetcher, '2606:4700::1111');
        $this->assertContains('2606:4700::1111', $ips);
    }
}
