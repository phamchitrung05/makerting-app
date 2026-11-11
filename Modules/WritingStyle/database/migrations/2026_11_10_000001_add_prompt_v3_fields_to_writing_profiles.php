<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bổ sung dữ liệu profile V3 và phiên bản prompt của analysis.
 * =====================================================================
 *
 * Laravel migration runner áp dụng thay đổi schema theo thứ tự migration.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - up(): Thêm hai cột JSON trên profile và cột prompt_version trên analysis.
 * - down(): Gỡ các cột do migration V3 đã thêm.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Hai bảng writing_profiles và writing_profile_analyses đã tồn tại.
 * - OUTPUT: Schema có các cột nullable, dữ liệu cũ được giữ khi up().
 *
 * BỐI CẢNH SỬ DỤNG: Laravel migration runner áp dụng thay đổi schema theo thứ tự migration.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Thêm hai cột JSON trên profile và cột prompt_version trên analysis
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - ALTER TABLE thêm style_analysis_json, slider_preferences_json và prompt_version.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi schema/DB truyền lên. Transaction do migration runner và driver quản lý, không khóa trong method.
     * =====================================================================
     */
    public function up(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table): void {
            $table->json('style_analysis_json')->nullable();
            $table->json('slider_preferences_json')->nullable();
        });
        Schema::table('writing_profile_analyses', function (Blueprint $table): void {
            $table->unsignedTinyInteger('prompt_version')->nullable();
        });
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gỡ các cột do migration V3 đã thêm
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - DROP COLUMN, dữ liệu trong các cột V3 bị mất khi rollback migration.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi schema/DB truyền lên. Transaction theo migration runner/driver.
     * =====================================================================
     */
    public function down(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table): void {
            $table->dropColumn(['style_analysis_json', 'slider_preferences_json']);
        });
        Schema::table('writing_profile_analyses', function (Blueprint $table): void {
            $table->dropColumn('prompt_version');
        });
    }
};
