<?php

namespace Modules\SocialPublisher\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Đăng ký event-listener của module SocialPublisher.
 * =====================================================================
 *
 * Quản lý ánh xạ event → listener của module; khai báo listener khi nghiệp vụ
 * phát Domain Event, hoặc để auto-discover bằng $shouldDiscoverEvents.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - configureEmailVerification(): đăng ký listener xác thực email
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : mảng $listen ánh xạ event sang listener
 * - OUTPUT: listener đăng ký vào dispatcher khi boot
 * =====================================================================
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
