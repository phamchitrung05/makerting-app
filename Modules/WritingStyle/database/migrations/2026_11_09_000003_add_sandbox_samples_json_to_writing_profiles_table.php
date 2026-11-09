<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Bổ sung cột `sandbox_samples_json` vào bảng `writing_profiles`.
 * =====================================================================
 * Lưu danh sách các bản viết thử (DNA Sandbox) mà người dùng đã ghim/lưu
 * để đọc lại và đối chiếu giữa các lần tinh chỉnh văn phong.
 * =====================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('writing_profiles', 'sandbox_samples_json')) {
                $table->json('sandbox_samples_json')
                    ->nullable()
                    ->after('tone_instructions')
                    ->comment('Danh sách các bản viết thử đã lưu trong Phòng thử DNA');
            }
        });
    }

    public function down(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('writing_profiles', 'sandbox_samples_json')) {
                $table->dropColumn('sandbox_samples_json');
            }
        });
    }
};
