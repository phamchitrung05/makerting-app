<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung cột tone_sliders_json (8 trục cảm xúc của Sandbox sắc thái)
     * vào bảng writing_profiles.
     */
    public function up(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('writing_profiles', 'tone_sliders_json')) {
                $table->json('tone_sliders_json')
                    ->nullable()
                    ->after('voice_texture_sliders_json')
                    ->comment('8 thanh trượt Sandbox sắc thái cảm xúc (0-100): humor, empathy, assertiveness, formality, inspiration, candor, depth, urgency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('writing_profiles', 'tone_sliders_json')) {
                $table->dropColumn('tone_sliders_json');
            }
        });
    }
};
