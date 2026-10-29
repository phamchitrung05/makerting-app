<?php

namespace Modules\Core\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Models\OAuthClientApp;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Kho lưu trữ và giải mã bí mật OAuth/tập tin nhạy cảm.
 * =====================================================================
 * Bọc `Crypt` của Laravel: mã hóa client_secret/access_token/refresh_token
 * trước khi ghi DB, giải mã khi dùng. Cung cấp helpers đọc OAuthClientApp và
 * chọn app còn quota để xoay vòng nhiều app cùng nền tảng.
 * =====================================================================
 */
class CredentialVault
{
    /**
     * Mã hóa một chuỗi bí mật để lưu DB.
     * INPUT: plaintext string. OUTPUT: ciphertext (Laravel encrypted).
     * SIDE EFFECT: không có.
     */
    public function encrypt(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    /**
     * Giải mã chuỗi đã mã hóa.
     * INPUT: ciphertext string. OUTPUT: plaintext.
     * EXCEPTION: DecryptException khi ciphertext hỏng/sai key.
     */
    public function decrypt(string $cipher): string
    {
        return Crypt::decryptString($cipher);
    }

    /**
     * Giải mã an toàn — trả null thay vì ném exception khi ciphertext hỏng.
     * INPUT: ?string ciphertext. OUTPUT: ?string plaintext.
     * SIDE EFFECT: không có.
     */
    public function decryptOrNull(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return null;
        }

        try {
            return Crypt::decryptString($cipher);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Lấy client_secret đã giải mã của một OAuthClientApp.
     * INPUT: OAuthClientApp. OUTPUT: ?string secret.
     * SIDE EFFECT: không có.
     */
    public function clientSecret(OAuthClientApp $app): ?string
    {
        return $this->decryptOrNull($app->client_secret_encrypted);
    }

    /**
     * Chọn một OAuthClientApp còn quota cho nền tảng (xoay vòng chia tải).
     * INPUT: platform (facebook|youtube|tiktok).
     * OUTPUT: ?OAuthClientApp — app active còn quota, null nếu hết.
     * SIDE EFFECT: SELECT theo platform + is_active.
     */
    public function pickAvailableApp(string $platform): ?OAuthClientApp
    {
        return OAuthClientApp::forPlatform($platform)
            ->active()
            ->get()
            ->first(fn (OAuthClientApp $app): bool => $app->hasQuotaRemaining());
    }

    /**
     * Ghi nhận một đơn vị quota đã dùng cho app.
     * INPUT: OAuthClientApp, số đơn vị. OUTPUT: void.
     * SIDE EFFECT: UPDATE daily_quota_used.
     */
    public function consumeQuota(OAuthClientApp $app, int $units = 1): void
    {
        $app->increment('daily_quota_used', $units);
    }
}
