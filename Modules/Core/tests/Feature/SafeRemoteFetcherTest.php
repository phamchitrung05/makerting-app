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
}
