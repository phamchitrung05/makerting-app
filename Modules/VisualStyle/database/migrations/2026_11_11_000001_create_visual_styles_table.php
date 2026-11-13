<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `visual_styles` — DNA nhiếp ảnh 5 nhóm.
 * =====================================================================
 *
 * Một visual_style là "bộ gen ảnh" giải mã từ ảnh tham chiếu (Vision AI):
 * gồm `dna_json` 5 nhóm enum bắt buộc (composition, camera, lighting,
 * environment, color_mood) + subject_rules + confidence, kèm
 * `compiled_prompt_preview` (prompt 7 lớp đã ghép) và `negative_prompt`.
 *
 * `reference_media_id` trỏ tới MediaAsset ảnh mẫu; `status` theo vòng đời
 * giải mã (draft|analyzing|analyzed|active|archived|failed). Dùng HasSlug +
 * HasTaxonomy + HasMediaSlots('visual_style.reference') ở model.
 *
 * SIDE EFFECT: tạo bảng + index (status, is_enabled, schema_version) + FK.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng visual_styles.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + index lọc + FK media_assets.
     */
    public function up(): void
    {
        Schema::create('visual_styles', function (Blueprint $table) {
            $table->id();

            $table->string('name', 160);
            $table->text('description')->nullable();

            // Ảnh tham chiếu DNA — trỏ tới media_assets.id (FK nullOnDelete).
            $table->unsignedBigInteger('reference_media_id')->nullable()->index();

            // DNA nhiếp ảnh 5 nhóm enum bắt buộc + subject_rules +
            // style_constraints + confidence + taxonomy_suggestion.
            $table->json('dna_json')->nullable();

            // Phiên bản schema của dna_json (tương thích khi nâng cấp pack).
            $table->string('schema_version', 16)->default('1.0');

            // Prompt 7 lớp đã ghép — preview cho UI trước khi sinh ảnh.
            $table->text('compiled_prompt_preview')->nullable();

            // Negative prompt riêng của style (tránh biến dạng/watermark...).
            $table->text('negative_prompt')->nullable();

            // Vòng đời giải mã: draft → analyzing → analyzed → active
            // (hoặc archived/failed). Chỉ 'active' được dùng để sinh ảnh.
            $table->string('status', 16)->default('draft')
                ->index()
                ->comment('draft|analyzing|analyzed|active|archived|failed');

            // Version tăng mỗi lần chỉnh sửa (rollback/audit/dedupe snapshot).
            $table->unsignedInteger('version')->default(1);

            $table->boolean('is_enabled')->default(true)->index();

            // Thời điểm giải mã xong (job ghi khi chuyển sang analyzed/active).
            $table->timestamp('analyzed_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // FK media_assets — giữ style khi ảnh tham chiếu bị xóa.
            $table->foreign('reference_media_id')
                ->references('id')->on('media_assets')
                ->nullOnDelete();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng visual_styles khi rollback.
     * SIDE EFFECT: xóa bảng visual_styles.
     */
    public function down(): void
    {
        Schema::dropIfExists('visual_styles');
    }
};
