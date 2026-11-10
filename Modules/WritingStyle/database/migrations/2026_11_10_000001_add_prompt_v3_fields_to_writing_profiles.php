<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
