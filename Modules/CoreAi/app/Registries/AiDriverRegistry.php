<?php

namespace Modules\CoreAi\Registries;

use Modules\CoreAi\Contracts\AiProviderDriverContract;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Models\AiProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Registry ánh xạ driver slug → class driver.
 * =====================================================================
 *
 * Module nghiệp vụ/driver mở rộng đăng ký trong `boot()` theo Registry
 * pattern của PLAN §4.1 — CoreAiServiceProvider gọi `register()` cho
 * từng driver có sẵn (`openai`,`anthropic`,`gemini`,`openai-compatible`,
 * `anthropic-compatible`,`elevenlabs`).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register()   : đăng ký driver slug → class/closure tạo driver.
 * - resolve()    : lấy instance driver cho 1 AiProvider (ném nếu chưa đăng
 *                  ký hoặc driver không phải AiProviderDriverContract).
 * - has()        : kiểm slug driver đã đăng ký chưa.
 * - registered() : liệt kê các slug đã đăng ký.
 * - forget()     : gỡ đăng ký (test).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : slug driver → class-string|Closure|instance.
 * - OUTPUT: AiProviderDriverContract đã resolve cho provider.
 * =====================================================================
 */
class AiDriverRegistry
{
    /**
     * driver slug → class-string|Closure|instance.
     *
     * @var array<string, class-string|Closure|AiProviderDriverContract>
     */
    protected array $drivers = [];

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký driver cho slug.
     * =====================================================================
     * INPUT: slug + class-string|Closure|instance.
     * OUTPUT: void.
     * SIDE EFFECT: thay đổi registry (in-memory).
     * =====================================================================
     */
    public function register(string $slug, string|\Closure|AiProviderDriverContract $driver): void
    {
        $this->drivers[$slug] = $driver;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve driver cho một provider.
     * =====================================================================
     * INPUT: AiProvider.
     * OUTPUT: AiProviderDriverContract.
     * EXCEPTION: AiGatewayException::unsupported khi chưa đăng ký hoặc class
     *            không implement contract.
     * =====================================================================
     */
    public function resolve(AiProvider $provider): AiProviderDriverContract
    {
        $slug = $provider->driver;
        $driver = $this->drivers[$slug] ?? null;

        if ($driver === null) {
            throw AiGatewayException::unsupported("driver '{$slug}' chưa được đăng ký", ['driver' => $slug]);
        }

        // Closure → gọi để lấy instance.
        if ($driver instanceof \Closure) {
            $driver = $driver($provider);
        }
        // class-string → container resolve.
        elseif (is_string($driver)) {
            $driver = app($driver);
        }

        if (! $driver instanceof AiProviderDriverContract) {
            throw AiGatewayException::unsupported("driver '{$slug}' không hợp lệ", ['driver' => $slug]);
        }

        return $driver;
    }

    /** INPUT: slug. OUTPUT: bool đã đăng ký chưa. */
    public function has(string $slug): bool
    {
        return isset($this->drivers[$slug]);
    }

    /** INPUT: không. OUTPUT: string[] slug đã đăng ký. */
    public function registered(): array
    {
        return array_keys($this->drivers);
    }

    /** INPUT: slug. OUTPUT: void — gỡ đăng ký. */
    public function forget(string $slug): void
    {
        unset($this->drivers[$slug]);
    }
}