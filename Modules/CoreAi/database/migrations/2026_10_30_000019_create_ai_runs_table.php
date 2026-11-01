<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `ai_runs` — một lượt chạy AI end-to-end.
 * =====================================================================
 *
 * UUID PK: cho phép client/hàng đợi tạo id trước khi insert. `parent_id`
 * + `session_id` nhóm các run "tạo lại" (regenerate) cùng một ngữ cảnh;
 * `generation_no` đếm lần regenerate. `module`/`operation` ghi module gọi
 * (blog.write, visual_style.analyze...) để truy vết.
 *
 * `connection_snapshot_json` chụp cấu hình provider+model tại thời điểm
 * chạy (key provider, remote_model_id, base_url, capabilities) — TUYỆT
 * ĐỐI không chứa api_key/extra_keys. `input_json`/`output_json` giữ payload
 * cho retry/audit; `diagnostics_json` giữ latency, số lần thử, key index.
 * `expires_at` cho phép GC các run nháp không còn cần.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng ai_runs.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: CREATE TABLE ai_runs + index status/session/module.
     */
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->index();
            $table->uuid('session_id')->index();
            $table->string('module', 50)->index();
            $table->string('operation', 80);
            $table->unsignedInteger('generation_no')->default(1);
            $table->string('status', 16)->default('queued')->index()
                ->comment('queued|running|ready|partial|applied|failed|cancelled|expired');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->json('connection_snapshot_json')->nullable()
                ->comment('provider+model config lúc chạy — KHÔNG chứa api key');
            $table->json('input_json');
            $table->json('output_json')->nullable();
            $table->json('diagnostics_json')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('initiated_by')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng ai_runs khi rollback.
     * SIDE EFFECT: DROP TABLE ai_runs.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
