<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng ai_usage_logs — nhật ký chi phí AI nội bộ
 * theo Kênh/Chiến dịch (provider, model, capability, token, cost).
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - up()   : tạo bảng ai_usage_logs + index.
 * - down() : xóa bảng khi rollback.
 * INPUT/OUTPUT: schema ai_usage_logs.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng ai_usage_logs.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: CREATE TABLE ai_usage_logs + index.
     */
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('ai_run_id')->nullable()->index();
            $table->unsignedBigInteger('channel_group_id')->nullable()->index();
            $table->unsignedBigInteger('social_account_id')->nullable()->index();
            $table->string('provider', 60);
            $table->string('model', 120);
            $table->string('capability', 20);                 // text|vision|image|tts|stt
            $table->bigInteger('input_units')->default(0);
            $table->bigInteger('output_units')->default(0);
            $table->decimal('estimated_cost_usd', 12, 6)->nullable();
            $table->timestamp('created_at')->useCurrent();
            // Chỉ cần created_at — bảng log append-only, không cần updated_at.
        });
    }

    /**
     * Gỡ bảng ai_usage_logs khi rollback.
     * SIDE EFFECT: DROP TABLE ai_usage_logs.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
