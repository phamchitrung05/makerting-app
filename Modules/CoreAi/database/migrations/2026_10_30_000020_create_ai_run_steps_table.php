<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `ai_run_steps` — bước con idempotent trong run.
 * =====================================================================
 *
 * Pipeline nhiều bước (Analyzer → Writer → Editor, analyze → generate)
 * ghi từng bước vào đây. `step_key` định danh bước ('analyze', 'draft'...);
 * `input_hash` = sha256(input_json của bước) — unique cùng ai_run_id +
 * step_key, cho phép resume: bước đã chạy với input y hệt → reuse
 * output_json, không gọi lại AI (idempotent, tiết kiệm chi phí).
 *
 * `latency_ms` + `diagnostics_json` phục vụ quan sát hiệu năng từng bước.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng ai_run_steps.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: CREATE TABLE ai_run_steps + FK cascade + unique idempotent.
     */
    public function up(): void
    {
        Schema::create('ai_run_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('ai_run_id');
            $table->string('step_key', 60);
            $table->char('input_hash', 64)->comment('sha256(input) — idempotent reuse');
            $table->string('status', 16)->default('queued')
                ->comment('queued|running|ready|failed|skipped');
            $table->json('output_json')->nullable();
            $table->json('diagnostics_json')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();

            $table->unique(['ai_run_id', 'step_key', 'input_hash'], 'ai_run_steps_idempotent_unique');

            $table->foreign('ai_run_id')
                ->references('id')->on('ai_runs')
                ->cascadeOnDelete();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng ai_run_steps khi rollback.
     * SIDE EFFECT: DROP TABLE ai_run_steps.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_run_steps');
    }
};
