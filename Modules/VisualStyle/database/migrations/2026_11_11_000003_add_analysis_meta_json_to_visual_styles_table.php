<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Thêm cột `analysis_meta_json` vào `visual_styles`.
 * =====================================================================
 *
 * Lưu diagnostics lần giải mã DNA gần nhất (model, provider, tokens,
 * latency_ms, ai_run_id) — phục vụ audit + debug + provenance, không ảnh
 * hưởng tới `dna_json` nội dung.
 *
 * SIDE EFFECT: alter bảng visual_styles thêm cột json nullable.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Thêm cột analysis_meta_json.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: alter bảng visual_styles.
     */
    public function up(): void
    {
        Schema::table('visual_styles', function (Blueprint $table) {
            // Diagnostics lần giải mã gần nhất: {ai_run_id, model, provider,
            // tokens: {input, output}, latency_ms}.
            $table->json('analysis_meta_json')->nullable()->after('negative_prompt');
        });
    }

    /**
     * CHỨC NĂNG: Gỡ cột analysis_meta_json khi rollback.
     * SIDE EFFECT: alter bảng visual_styles drop cột.
     */
    public function down(): void
    {
        Schema::table('visual_styles', function (Blueprint $table) {
            $table->dropColumn('analysis_meta_json');
        });
    }
};
