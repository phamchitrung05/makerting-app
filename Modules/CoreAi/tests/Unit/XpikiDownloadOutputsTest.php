<?php

namespace Modules\CoreAi\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\DTOs\RemoteFetchResultDto;
use Modules\Core\Services\CredentialVault;
use Modules\CoreAi\Drivers\XpikiDriver;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvider;
use Modules\CoreAi\Services\AiProviderClient;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Regression test downloadOutputs() của XpikiDriver.
 * =====================================================================
 * Bug: `outputs[].url` có thể là URL tuyệt đối (CDN/S3 presigned) nhưng
 * `downloadOutputs` trước đây luôn gọi `$this->url($provider, $rel)` ->
 * ghép base_url vào -> 'https://api.x/v1/https://storage.x/...' -> 404.
 * Đồng thời luôn gửi `Authorization: Bearer` kể cả sang host khác -> bị
 * từ chối + rò rỉ key. Test này khóa hành vi đúng sau khi vá.
 *
 * Cách kiểm: bind mock AiProviderClient (driver gọi qua `client()` =
 * `app(AiProviderClient::class)` singleton) capture mọi `request()`:
 * URL cuối + headers + option — rồi assert từng trường hợp.
 * =====================================================================
 */
class XpikiDownloadOutputsTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $baseUrl = 'https://api.xpiki.com/v1'): AiProvider
    {
        return AiProvider::create([
            'key' => 'xpiki', 'name' => 'Xpiki', 'kind' => 'official', 'driver' => 'xpiki',
            'base_url' => $baseUrl,
            'api_key_encrypted' => app(CredentialVault::class)->encrypt('secret-key'),
            'request_timeout' => 60, 'is_active' => true,
        ]);
    }

    private function model(AiProvider $p): AiModel
    {
        return AiModel::create([
            'ai_provider_id' => $p->id, 'remote_model_id' => 'xpiki-img-1', 'label' => 'img',
            'capabilities_json' => ['image_generation'], 'is_enabled' => true, 'is_available' => true,
        ]);
    }

    private function resolved(AiProvider $p, AiModel $m): AiResolvedModelDto
    {
        return new AiResolvedModelDto(provider: $p, model: $m, apiKey: 'secret-key', keyIndex: 0);
    }

    /**
     * Mock AiProviderClient capture URL + headers của mọi request().
     * Trả [driver, &$calls] — $calls[i] = ['url'=>,'headers'=>].
     */
    private function driverWithCapture(array &$calls): XpikiDriver
    {
        $mock = new class($calls) extends AiProviderClient {
            public array $cap = [];
            public function __construct(private &$callsRef) {}
            public function request(
                \Modules\CoreAi\Models\AiProvider $provider, string $method, string $url,
                array $headers = [], string|array|null $body = null, array $options = [],
            ): RemoteFetchResultDto {
                $this->callsRef[] = ['url' => $url, 'headers' => $headers, 'options' => $options];
                return new RemoteFetchResultDto(
                    status: 200, body: 'BIN', headers: [], finalUrl: $url, contentType: 'image/png'
                );
            }
        };
        $this->app->instance(AiProviderClient::class, $mock);

        return new XpikiDriver();
    }

    /**
     * Gọi downloadOutputs (protected) qua subclass expose public.
     */
    private function invokeDownload(XpikiDriver $driver, AiResolvedModelDto $r, array $task): array
    {
        $exposed = new class extends XpikiDriver {
            public function pubDownload(AiResolvedModelDto $r, array $task, string $prefix): array
            {
                return $this->downloadOutputs($r, $task, $prefix);
            }
        };

        return $exposed->pubDownload($r, $task, 'image/');
    }

    /* =================================================================
     * Bug #1 — URL tuyệt đối KHÔNG bị ghép base_url (404 trước vá).
     * ================================================================= */

    public function test_output_url_tuyet_doi_cdn_khong_bi_ghep_base_url(): void
    {
        $p = $this->provider('https://api.xpiki.com/v1');
        $m = $this->model($p);

        $calls = [];
        $driver = $this->driverWithCapture($calls);

        $task = ['status' => 'succeeded', 'outputs' => [
            ['url' => 'https://storage.xpiki.com/outputs/abc.png?sig=xyz', 'mime_type' => 'image/png'],
        ]];

        $this->invokeDownload($driver, $this->resolved($p, $m), $task);

        $this->assertCount(1, $calls);
        // Phải gọi thẳng URL CDN — KHÔNG ghép base_url.
        $this->assertSame('https://storage.xpiki.com/outputs/abc.png?sig=xyz', $calls[0]['url']);
    }

    /* =================================================================
     * Bug #2 — KHÔNG gửi Bearer khi URL tuyệt đối ra host khác (CDN/presigned).
     * ================================================================= */

    public function test_khong_gui_bearer_sang_host_khac(): void
    {
        $p = $this->provider('https://api.xpiki.com/v1');
        $m = $this->model($p);

        $calls = [];
        $driver = $this->driverWithCapture($calls);

        $task = ['status' => 'succeeded', 'outputs' => [
            ['url' => 'https://storage.xpiki.com/outputs/a.png', 'mime_type' => 'image/png'],
        ]];

        $this->invokeDownload($driver, $this->resolved($p, $m), $task);

        $auth = array_filter($calls[0]['headers'], fn (string $h) => stripos($h, 'authorization') === 0);
        $this->assertCount(0, $auth, 'không được gửi Authorization ra host ngoài');
    }

    /* =================================================================
     * Cùng host provider (api.xpiki.com) — vẫn gửi Bearer (endpoint nội bộ).
     * ================================================================= */

    public function test_url_tuyet_doi_cung_host_van_gui_bearer(): void
    {
        $p = $this->provider('https://api.xpiki.com/v1');
        $m = $this->model($p);

        $calls = [];
        $driver = $this->driverWithCapture($calls);

        $task = ['status' => 'succeeded', 'outputs' => [
            ['url' => 'https://api.xpiki.com/v1/outputs/b.png', 'mime_type' => 'image/png'],
        ]];

        $this->invokeDownload($driver, $this->resolved($p, $m), $task);

        $this->assertSame('https://api.xpiki.com/v1/outputs/b.png', $calls[0]['url']);
        $auth = array_filter($calls[0]['headers'], fn (string $h) => stripos($h, 'authorization') === 0);
        $this->assertNotCount(0, $auth, 'cùng host provider phải gửi Bearer');
    }

    /* =================================================================
     * Relative path (backward compat) — ghép base_url + gửi Bearer.
     * ================================================================= */

    public function test_relative_path_van_ghep_base_url_va_gui_bearer(): void
    {
        $p = $this->provider('https://api.xpiki.com/v1');
        $m = $this->model($p);

        $calls = [];
        $driver = $this->driverWithCapture($calls);

        $task = ['status' => 'succeeded', 'outputs' => [
            ['url' => 'outputs/c.png', 'mime_type' => 'image/png'],
        ]];

        $this->invokeDownload($driver, $this->resolved($p, $m), $task);

        $this->assertSame('https://api.xpiki.com/v1/outputs/c.png', $calls[0]['url']);
        $auth = array_filter($calls[0]['headers'], fn (string $h) => stripos($h, 'authorization') === 0);
        $this->assertNotCount(0, $auth, 'relative path phải gửi Bearer (endpoint provider)');
    }
}
