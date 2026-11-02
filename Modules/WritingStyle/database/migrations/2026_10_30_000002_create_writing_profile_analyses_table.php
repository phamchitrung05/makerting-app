<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `writing_profile_analyses` — lịch sử giải mã
 * DNA văn phong từ một nguồn mẫu (dán text / URL / file).
 * =====================================================================
 *
 * Mỗi lần chạy "Giải mã văn phong" (AnalyzeWritingProfileJob) tạo 1 bản ghi:
 * - Nguồn: `source_type` (paste/url/file), `source_url`, `reference_text`
 *   (full text mẫu), `source_hash` sha256 để dedupe.
 * - Trạng thái pipeline: `status` pending|running|succeeded|failed,
 *   `ai_run_id` (ai_runs), `error_code/message`.
 * - Kết quả: `result_json` (rules + evidence + instructions thô),
 *   `detected_tier` cấp độ độ dài tự phát hiện, `draft_profile_id` trỏ tới
 *   WritingProfile draft được tạo ra (người dùng duyệt → active).
 * - `expires_at` — bản ghi phân tích có thể tự hủy sau N ngày (bảo mật text
 *   mẫu) — cleanup job sẽ xóa.
 *
 * SIDE EFFECT: tạo bảng + index (status, source_hash, draft_profile_id).
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng writing_profile_analyses.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + index + FK draft_profile_id.
     */
    public function up(): void
    {
        Schema::create('writing_profile_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('name', 160)->nullable()
                ->comment('Tên gợi ý do AI/user đặt cho profile kết quả');

            // Nguồn mẫu: paste (dán text) | url (fetch URL) | file (upload).
            $table->string('source_type', 16)->default('paste')
                ->index()
                ->comment('paste|url|file');
            $table->text('source_url')->nullable();
            $table->longText('reference_text');
            // Cấp độ độ dài tự phát hiện trên reference_text — micro|short|
            // medium|long; trùng ý nghĩa dominant_tier của profile kết quả.
            $table->string('detected_tier', 10)->nullable()
                ->index()
                ->comment('micro|short|medium|long — detectTier(reference_text)');

            // sha256(reference_text) — dedupe + tránh phân tích lại cùng mẫu.
            $table->char('source_hash', 64)->index();

            // Trạng thái pipeline giải mã.
            $table->string('status', 16)->default('pending')
                ->index()
                ->comment('pending|running|succeeded|failed');

            // Liên kết ai_runs của lệnh CoreAi::executeStructured giải mã.
            $table->uuid('ai_run_id')->nullable()->index();

            // Kết quả thô trả về (rules_json + evidence_json + instructions +
            // metadata) trước khi biên dịch thành draft profile.
            $table->json('result_json')->nullable();

            // WritingProfile draft được tạo ra — người dùng duyệt → 'active'.
            $table->foreignId('draft_profile_id')
                ->nullable()
                ->constrained('writing_profiles')
                ->nullOnDelete();

            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();

            // Bản ghi phân tích có thể tự hủy (giữ reference_text mẫu lâu là
            // rủi ro riêng tư) — cleanup job theo expires_at.
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng writing_profile_analyses khi rollback.
     * SIDE EFFECT: xóa bảng writing_profile_analyses.
     */
    public function down(): void
    {
        Schema::dropIfExists('writing_profile_analyses');
    }
};
