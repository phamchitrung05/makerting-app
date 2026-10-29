<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Điều tiết tần suất thao tác theo tài khoản mạng xã hội.
 * =====================================================================
 * Bọc RateLimiter để giới hạn số lần gọi API/đăng bài trên mỗi tài khoản
 * (social_account_id + platform) trong một khoảng thời gian — tránh bị
 * mạng xã hội quét spam/khóa. Cung cấp `attempt` và kiểm tra còn lượt.
 * =====================================================================
 */
class AccountRateLimiter
{
    /**
     * Tạo khóa rate-limit chuẩn cho một tài khoản.
     * INPUT: platform, accountId. OUTPUT: string key.
     * SIDE EFFECT: không có.
     */
    public function key(string $platform, int|string $accountId): string
    {
        return "acct-rate:{$platform}:{$accountId}";
    }

    /**
     * Kiểm tra tài khoản còn lượt thao tác trong cửa sổ không.
     * INPUT: platform, accountId, maxAttempts, decaySeconds.
     * OUTPUT: bool — true nếu chưa vượt giới hạn.
     * SIDE EFFECT: không có (read-only).
     */
    public function allowed(string $platform, int|string $accountId, int $maxAttempts = 30, int $decaySeconds = 60): bool
    {
        return ! RateLimiter::tooManyAttempts($this->key($platform, $accountId), $maxAttempts);
    }

    /**
     * Ghi nhận một lượt thao tác và trả số lượt còn lại.
     * INPUT: platform, accountId, decaySeconds.
     * OUTPUT: int — số lần đã dùng trong cửa sổ.
     * SIDE EFFECT: tăng bộ đếm RateLimiter.
     */
    public function hit(string $platform, int|string $accountId, int $decaySeconds = 60): int
    {
        return RateLimiter::hit($this->key($platform, $accountId), $decaySeconds);
    }

    /**
     * Thực thi callback nếu còn lượt; ném/return false nếu bị giới hạn.
     * INPUT: platform, accountId, maxAttempts, decaySeconds, callback.
     * OUTPUT: mixed — kết quả callback, hoặc false nếu bị limit.
     * SIDE EFFECT: tăng bộ đếm khi được phép.
     */
    public function attempt(string $platform, int|string $accountId, callable $callback, int $maxAttempts = 30, int $decaySeconds = 60): mixed
    {
        $key = $this->key($platform, $accountId);

        return RateLimiter::attempt($key, $maxAttempts, $callback, $decaySeconds);
    }

    /**
     * Số giây phải chờ trước khi tài khoản được phép thao tác tiếp.
     * INPUT: platform, accountId, maxAttempts.
     * OUTPUT: int giây (0 nếu còn lượt).
     * SIDE EFFECT: không có.
     */
    public function retryAfter(string $platform, int|string $accountId, int $maxAttempts = 30): int
    {
        $key = $this->key($platform, $accountId);

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return 0;
        }

        return RateLimiter::availableIn($key);
    }

    /**
     * Xóa bộ đếm của tài khoản.
     * INPUT: platform, accountId. OUTPUT: void.
     * SIDE EFFECT: clear cache key.
     */
    public function reset(string $platform, int|string $accountId): void
    {
        RateLimiter::clear($this->key($platform, $accountId));
    }
}
