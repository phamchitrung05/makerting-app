<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `ai_provenances` — truy xuất nguồn gốc AI.
 * =====================================================================
 *
 * Khi một trường của Domain Model được AI sinh/áp vào (post.title,
 * post.excerpt, social_variant.body...), ghi một dòng provenance:
 * - target_type/target_id : model nhận nội dung (morph thủ công, varchar).
 * - field_name            : trường được AI tạo ('title', 'body'...).
 * - ai_run_id             : run đã sinh nội dung (nullable + nullOnDelete —
 *   giữ provenance dù run bị GC).
 * - value_hash            : sha256 nội dung đã áp — đối chiếu nhanh xem
 *   người biên tập có sửa lại sau đó không (edited = hash khác).
 * - applied_by            : user nhấn "Áp dụng" (nullable cho auto-apply).
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng ai_provenances.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: CREATE TABLE ai_provenances + morph index + FK nullOnDelete.
     */
    public function up(): void
    {
        Schema::create('ai_provenances', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id');
            $table->string('field_name', 80);
            $table->uuid('ai_run_id')->nullable();
            $table->char('value_hash', 64)->comment('sha256(value đã áp) — phát hiện edit tay');
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id'], 'ai_provenances_target_index');
            $table->index(['target_type', 'field_name'], 'ai_provenances_field_index');

            $table->foreign('ai_run_id')
                ->references('id')->on('ai_runs')
                ->nullOnDelete();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng ai_provenances khi rollback.
     * SIDE EFFECT: DROP TABLE ai_provenances.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_provenances');
    }
};
