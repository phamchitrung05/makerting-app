<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `writing_profiles` — hồ sơ DNA văn phong.
 * =====================================================================
 *
 * Mỗi profile là một "bộ gen" 16 khóa thuộc tính văn phong (`rules_json`)
 * trả lời câu hỏi "yếu tố này trong bài mẫu là gì" — kèm trích dẫn thực
 * (`evidence_json`), hướng dẫn hành văn tự do (`style_instructions`), cấp
 * độ độ dài chủ đạo (`dominant_tier`) và nguồn gốc (`origin`).
 *
 * Dùng `HasTaxonomy` scope 'writing_profile' + `HasSlug` (qua trait ở model).
 * `version` tăng mỗi lần sửa để rollback/audit; `status` draft|active chỉ
 * cho phép dùng khi 'active'.
 *
 * SIDE EFFECT: tạo bảng + index (status, is_enabled, dominant_tier, origin).
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng writing_profiles.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + index lọc.
     */
    public function up(): void
    {
        Schema::create('writing_profiles', function (Blueprint $table) {
            $table->id();

            $table->string('name', 160);
            $table->text('description')->nullable();

            // Cấp độ độ dài chủ đạo của profile (PLAN.md §300):
            // micro (1–3 câu/<60 từ) | short (4–10 câu/60–180 từ)
            // | medium (11–30 câu/180–600 từ) | long (>600 từ).
            $table->string('dominant_tier', 10)->default('medium')
                ->index()
                ->comment('micro|short|medium|long — cấp độ độ dài chủ đạo');

            // 16 khóa thuộc tính DNA văn phong — mỗi key = 1 yếu tố trong bài
            // mẫu (tone, pronouns, emotion, opening, sentence_rhythm,
            // paragraph_rhythm, transitions, vocabulary, technical_terms,
            // structure_patterns, headings, bullets, examples, ending, avoid,
            // uncertainties). Giá trị = mô tả ngắn AI tự rút ra.
            $table->json('rules_json');

            // Mảng trích dẫn kiểm chứng — từng phần tử {rule_key, excerpt,
            // note}; chỉ lưu excerpt THẬT từ reference_text (chống bịa).
            $table->json('evidence_json')->nullable();

            // Hướng dẫn hành văn tự do biên dịch từ DNA (<= 10000 ký tự).
            $table->text('style_instructions')->nullable();

            // manual = nhập tay; reference = reverse-engineer từ bài mẫu.
            $table->string('origin', 16)->default('manual')
                ->index()
                ->comment('manual|reference');

            // Hash sha256 của nguồn mẫu (dedupe + trace ngược).
            $table->char('source_hash', 64)->nullable();

            // Metadata phân tích: word_count, sentence_count, detected_tier,
            // ai_model_used — do job phân tích ghi lại.
            $table->json('analysis_metadata_json')->nullable();

            // Version tăng mỗi lần chỉnh sửa (rollback/audit/dedupe snapshot).
            $table->unsignedInteger('version')->default(1);

            $table->string('status', 16)->default('draft')
                ->index()
                ->comment('draft|active — chỉ active mới được dùng để sinh text');

            $table->boolean('is_enabled')->default(true)->index();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng writing_profiles khi rollback.
     * SIDE EFFECT: xóa bảng writing_profiles.
     */
    public function down(): void
    {
        Schema::dropIfExists('writing_profiles');
    }
};
