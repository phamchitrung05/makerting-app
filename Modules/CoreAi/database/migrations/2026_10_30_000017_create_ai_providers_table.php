<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `ai_providers` — cấu hình nhà cung cấp AI.
 * =====================================================================
 *
 * Mỗi dòng là 1 kết nối tới nhà cung cấp (OpenAI, Anthropic, Gemini,
 * gateway tự host, ElevenLabs...). `key` là slug unique dùng trong log/
 * diagnostics; `driver` quyết định lớp driver xử lý request
 * (openai|gemini|anthropic|openai-compatible|elevenlabs).
 *
 * `api_key_encrypted` + `extra_keys_json` (encrypted) lưu khóa bí mật —
 * CredentialVault mã hóa trước khi ghi; KHÔNG BAO GIỜ đưa plaintext vào
 * log, diagnostics hay `connection_snapshot_json` của ai_runs.
 * `extra_keys_json` là mảng các key phụ để xoay vòng khi chạm RPM.
 *
 * `kind`: 'official' = kết nối chính thức | 'gateway' = cổng trung gian
 * (OpenRouter, LiteLLM, self-host...) — cho phép nhiều key cùng driver.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng ai_providers.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: CREATE TABLE ai_providers + unique key + index.
     */
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique()->comment('Slug unique định danh provider');
            $table->string('name', 120);
            $table->string('kind', 16)->default('official')->comment('official|gateway');
            $table->string('driver', 32)->comment('openai|gemini|anthropic|openai-compatible|elevenlabs');
            $table->string('base_url', 255);
            $table->text('api_key_encrypted');
            $table->json('extra_keys_json')->nullable()->comment('Mảng ciphertext key phụ (xoay vòng RPM)');
            $table->json('config_json')->nullable()->comment('Cấu hình riêng driver: region, version...');
            $table->unsignedInteger('request_timeout')->default(120)->comment('Giây tối đa mỗi request');
            $table->boolean('is_active')->default(true)->index();
            $table->string('test_status', 32)->nullable()->comment('ok|failed|untested');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->index('driver');
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng ai_providers khi rollback.
     * SIDE EFFECT: DROP TABLE ai_providers.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
