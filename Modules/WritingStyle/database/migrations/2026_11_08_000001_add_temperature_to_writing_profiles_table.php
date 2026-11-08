<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bổ sung cột `temperature` cho bảng `writing_profiles`.
 * =====================================================================
 * Cho phép mỗi hồ sơ văn phong cấu hình độ bay bổng / sáng tạo riêng
 * (mặc định 0.70; khoảng giá trị 0.00 – 1.00 hoặc tối đa 2.00).
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            $table->decimal('temperature', 3, 2)
                ->nullable()
                ->default(0.85)
                ->after('dominant_tier')
                ->comment('Độ bay bổng / sáng tạo của mô hình khi sinh văn bản');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            $table->dropColumn('temperature');
        });
    }
};
