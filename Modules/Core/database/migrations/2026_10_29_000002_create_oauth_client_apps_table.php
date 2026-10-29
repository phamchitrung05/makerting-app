<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `oauth_client_apps` lưu cụm OAuth App mạng xã hội.
 * =====================================================================
 *
 * Lưu trữ các bộ ứng dụng OAuth (Google Cloud Console Apps, Meta Developer
 * Apps, TikTok Developer Apps) để xoay vòng và chia tải quota ngày —
 * giải quyết giới hạn YouTube Data API v3 / Meta Graph trên 1 App duy nhất.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - up(): tạo bảng oauth_client_apps
 * - down(): gỡ bảng oauth_client_apps khi rollback
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Schema Builder
 * - OUTPUT: bảng `oauth_client_apps` trong database
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng `oauth_client_apps` kèm cột quota và trạng thái.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng oauth_client_apps (client_secret_encrypted, daily_quota_*).
     */
    public function up(): void
    {
        Schema::create('oauth_client_apps', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32); // facebook | youtube | tiktok
            $table->string('name', 160);
            $table->string('client_id', 255);
            $table->text('client_secret_encrypted');
            $table->string('redirect_uri', 500);
            $table->json('scopes_json');
            $table->unsignedInteger('daily_quota_limit')->nullable();
            $table->unsignedInteger('daily_quota_used')->default(0);
            $table->timestamp('quota_reset_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['platform', 'is_active'], 'oauth_apps_platform_active_index');
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng `oauth_client_apps` khi rollback. SIDE EFFECT: xóa bảng.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_client_apps');
    }
};