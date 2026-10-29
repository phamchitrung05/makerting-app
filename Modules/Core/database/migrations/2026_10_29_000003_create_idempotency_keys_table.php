<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `idempotency_keys` chống gọi trùng các tác vụ tốn phí.
 * =====================================================================
 *
 * Lưu khóa idempotency từ header `Idempotency-Key` cho các request nhạy cảm
 * (chạy AI, render video, đăng mạng xã hội). Trả lại response đã lưu nếu key
 * đã hoàn thành; trả 409 nếu key đang được xử lý (`locked_until` còn hiệu lực).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - up(): tạo bảng idempotency_keys
 * - down(): gỡ bảng idempotency_keys khi rollback
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Schema Builder
 * - OUTPUT: bảng `idempotency_keys` trong database
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng `idempotency_keys` lưu response & khóa trùng lặp.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng idempotency_keys (primary key `key`, index `expires_at`).
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key', 128)->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('route_signature', 191);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body_json')->nullable();
            $table->timestamp('locked_until');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng `idempotency_keys` khi rollback. SIDE EFFECT: xóa bảng.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};