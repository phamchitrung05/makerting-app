<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\Models\OAuthClientApp;
use Modules\Core\Services\CredentialVault;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Test CredentialVault mã hóa/giải mã + chọn app quota.
 * =====================================================================
 */
class CredentialVaultTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private function vault(): CredentialVault
    {
        return new CredentialVault();
    }

    public function test_encrypt_decrypt(): void
    {
        $cipher = $this->vault()->encrypt('secret-abc');
        $this->assertNotSame('secret-abc', $cipher);
        $this->assertSame('secret-abc', $this->vault()->decrypt($cipher));
    }

    public function test_decrypt_or_null_tra_null_khi_hong(): void
    {
        $this->assertNull($this->vault()->decryptOrNull('chuoi-khong-hop-le'));
        $this->assertNull($this->vault()->decryptOrNull(null));
    }

    public function test_pick_available_app_con_quota(): void
    {
        OAuthClientApp::create([
            'platform' => 'youtube',
            'name' => 'App A',
            'client_id' => 'id-a',
            'client_secret_encrypted' => $this->vault()->encrypt('sec-a'),
            'redirect_uri' => 'https://app/cb',
            'scopes_json' => ['scope1'],
            'daily_quota_limit' => 100,
            'daily_quota_used' => 100, // hết quota
            'is_active' => true,
        ]);
        OAuthClientApp::create([
            'platform' => 'youtube',
            'name' => 'App B',
            'client_id' => 'id-b',
            'client_secret_encrypted' => $this->vault()->encrypt('sec-b'),
            'redirect_uri' => 'https://app/cb',
            'scopes_json' => ['scope1'],
            'daily_quota_limit' => 100,
            'daily_quota_used' => 10, // còn quota
            'is_active' => true,
        ]);

        $picked = $this->vault()->pickAvailableApp('youtube');
        $this->assertNotNull($picked);
        $this->assertSame('App B', $picked->name);
    }
}
