<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `ai_models` — model thuộc một provider.
 * =====================================================================
 *
 * `remote_model_id` là ID phía nhà cung cấp (gpt-4o, claude-3-5-sonnet...);
 * `label` là tên hiển thị thân thiện. `capabilities_json` là mảng các
 * capability model hỗ trợ: text_generation, structured_output, vision,
 * image_generation, tts, stt — ModelResolver lọc theo capability khi
 * chọn model cho một tác vụ.
 *
 * `pricing_json` (nullable) dạng {input_per_mtok, output_per_mtok, per_image...}
 * để ước tính estimated_cost_usd ghi vào ai_usage_logs.
 * `is_enabled` = admin cho phép dùng; `is_available` = trạng thái thực tế
 * sau lần sync/ping gần nhất.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng ai_models.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: CREATE TABLE ai_models + FK cascade + unique composite.
     */
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_provider_id')->index();
            $table->string('remote_model_id', 191)->comment('ID model phía provider');
            $table->string('label', 160);
            $table->json('capabilities_json')->comment('["text_generation","vision",...]');
            $table->json('pricing_json')->nullable()->comment('Bảng giá để ước tính cost');
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['ai_provider_id', 'remote_model_id'], 'ai_models_provider_remote_unique');

            $table->foreign('ai_provider_id')
                ->references('id')->on('ai_providers')
                ->cascadeOnDelete();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng ai_models khi rollback.
     * SIDE EFFECT: DROP TABLE ai_models.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
