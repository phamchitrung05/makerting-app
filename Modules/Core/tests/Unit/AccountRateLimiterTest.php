<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\Services\AccountRateLimiter;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Test AccountRateLimiter dùng RateLimiter array store.
 * =====================================================================
 */
class AccountRateLimiterTest extends TestCase
{
    private function limiter(): AccountRateLimiter
    {
        return new AccountRateLimiter();
    }

    public function test_tao_key_chuan(): void
    {
        $key = $this->limiter()->key('facebook', 42);
        $this->assertSame('acct-rate:facebook:42', $key);
    }

    public function test_allowed_khi_moi(): void
    {
        $this->assertTrue($this->limiter()->allowed('facebook', 1, 5, 60));
    }

    public function test_hit_tang_bo_dem(): void
    {
        $limiter = $this->limiter();
        $this->assertSame(1, $limiter->hit('facebook', 1, 60));
        $this->assertSame(2, $limiter->hit('facebook', 1, 60));
        $this->assertSame(3, $limiter->hit('facebook', 1, 60));
    }

    public function test_chan_khi_qua_max(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $limiter->hit('facebook', 1, 60);
        }

        $this->assertFalse($limiter->allowed('facebook', 1, 5, 60));
    }

    public function test_retry_after_tra_0_khi_con_luot(): void
    {
        $this->assertSame(0, $this->limiter()->retryAfter('facebook', 999, 5));
    }

    public function test_retry_after_tra_so_giay_khi_bi_chan(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 3; $i++) {
            $limiter->hit('youtube', 5, 60);
        }

        $retry = $limiter->retryAfter('youtube', 5, 3);
        $this->assertGreaterThan(0, $retry);
        $this->assertLessThanOrEqual(60, $retry);
    }

    public function test_attempt_chay_callback_khi_con_luot(): void
    {
        $result = $this->limiter()->attempt('tiktok', 7, fn () => 'posted', 5, 60);
        $this->assertSame('posted', $result);
    }

    public function test_attempt_tra_false_khi_bi_chan(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 3; $i++) {
            $limiter->hit('tiktok', 9, 60);
        }

        $called = false;
        $result = $limiter->attempt('tiktok', 9, function () use (&$called) {
            $called = true;
            return 'x';
        }, 3, 60);

        $this->assertFalse($called);
        $this->assertFalse($result);
    }

    public function test_reset_xoa_bo_dem(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $limiter->hit('facebook', 1, 60);
        }
        $this->assertFalse($limiter->allowed('facebook', 1, 5, 60));

        $limiter->reset('facebook', 1);
        $this->assertTrue($limiter->allowed('facebook', 1, 5, 60));
    }

    public function test_key_doc_lap_giua_platform_va_account(): void
    {
        $limiter = $this->limiter();
        $limiter->hit('facebook', 1, 60);
        $limiter->hit('facebook', 1, 60);

        // Cùng platform nhưng account khác → vẫn còn lượt.
        $this->assertTrue($limiter->allowed('facebook', 2, 5, 60));
        // Cùng account nhưng platform khác → vẫn còn lượt.
        $this->assertTrue($limiter->allowed('youtube', 1, 5, 60));
    }
}
