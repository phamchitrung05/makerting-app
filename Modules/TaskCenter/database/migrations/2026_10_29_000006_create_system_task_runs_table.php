<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng system_task_runs — nhật ký tập trung mọi
 * tiến trình chạy ngầm (AI, render video, publish MXH...).
 * =====================================================================
 *
 * Mỗi dòng = 1 lần enqueue/chạy của một Job bất kỳ; `dedupe_key` unique
 * chặn enqueue trùng cùng một công việc. `taskable_*` morph trỏ về model
 * gốc (Post, VideoProject, SocialPost...).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - up()   : tạo bảng system_task_runs + index/unique.
 * - down() : xóa bảng khi rollback.
 * INPUT/OUTPUT: schema system_task_runs.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng system_task_runs.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: CREATE TABLE system_task_runs + unique dedupe_key + index.
     */
    public function up(): void
    {
        Schema::create('system_task_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dedupe_key', 191)->unique();      // chống enqueue trùng
            $table->string('module', 50)->index();            // CoreAi/VideoStudio/...
            $table->string('task_type', 80)->index();         // video_render/ai_analysis...
            $table->string('title', 255);
            $table->string('status', 20)->default('queued')->index(); // queued|running|completed|partial|failed|cancelled
            $table->unsignedTinyInteger('progress')->default(0);      // 0..100
            $table->unsignedBigInteger('channel_group_id')->nullable()->index();
            $table->string('taskable_type', 64)->nullable();
            $table->string('taskable_id', 64)->nullable();
            $table->unsignedBigInteger('initiated_by')->nullable()->index();
            $table->json('meta_json')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['taskable_type', 'taskable_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Gỡ bảng system_task_runs khi rollback.
     * SIDE EFFECT: DROP TABLE system_task_runs.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_task_runs');
    }
};
