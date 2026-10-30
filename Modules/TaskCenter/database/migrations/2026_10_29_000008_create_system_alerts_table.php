<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng system_alerts — cảnh báo hệ thống
 * (token MXH hết hạn, lỗi quota, lỗi render) gửi Telegram/Chuông Admin.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - up()   : tạo bảng system_alerts + index.
 * - down() : xóa bảng khi rollback.
 * INPUT/OUTPUT: schema system_alerts.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng system_alerts.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: CREATE TABLE system_alerts + index severity/read_at.
     */
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('severity', 20)->default('info')->index(); // info|warning|critical
            $table->string('category', 60)->index();                  // token_expiry|quota|render|publish...
            $table->string('title', 255);
            $table->text('message');
            $table->json('context_json')->nullable();
            $table->timestamp('telegram_sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['severity', 'created_at']);
            $table->index('read_at');
        });
    }

    /**
     * Gỡ bảng system_alerts khi rollback.
     * SIDE EFFECT: DROP TABLE system_alerts.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
