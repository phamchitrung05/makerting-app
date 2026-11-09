<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * =====================================================================
     * Bổ sung cột tone_instructions (Ô 3 - Prompt bổ sung 3: Chỉ dẫn Sắc thái
     * & Phối âm cảm xúc) vào bảng writing_profiles.
     * =====================================================================
     */
    public function up(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('writing_profiles', 'tone_instructions')) {
                $table->text('tone_instructions')
                    ->nullable()
                    ->after('tone_sliders_json')
                    ->comment('Ô 3 - Prompt bổ sung 3: Chỉ dẫn Sắc thái & Phối âm cảm xúc (tone_instructions)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('writing_profiles', 'tone_instructions')) {
                $table->dropColumn('tone_instructions');
            }
        });
    }
};
