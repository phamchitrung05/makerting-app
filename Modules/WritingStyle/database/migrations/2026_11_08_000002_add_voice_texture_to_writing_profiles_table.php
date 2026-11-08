<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung Ô 2 (voice_texture_instructions) và 5 thanh trượt Chất văn (voice_texture_sliders_json)
     * vào bảng writing_profiles.
     */
    public function up(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('writing_profiles', 'voice_texture_instructions')) {
                $table->text('voice_texture_instructions')
                    ->nullable()
                    ->after('style_instructions')
                    ->comment('Prompt bổ sung 2: Chỉ dẫn Chất văn & Nghệ thuật Nhấn - Lướt');
            }

            if (! Schema::hasColumn('writing_profiles', 'voice_texture_sliders_json')) {
                $table->json('voice_texture_sliders_json')
                    ->nullable()
                    ->after('voice_texture_instructions')
                    ->comment('5 thanh trượt canh chỉnh Chất văn (0-100): voice_timbre, vernacular_flow, emotional_nuance, rhythm_variation, emphasis_glide');
            }
        });
    }

    public function down(): void
    {
        Schema::table('writing_profiles', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('writing_profiles', 'voice_texture_sliders_json')) {
                $cols[] = 'voice_texture_sliders_json';
            }
            if (Schema::hasColumn('writing_profiles', 'voice_texture_instructions')) {
                $cols[] = 'voice_texture_instructions';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
