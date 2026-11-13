<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `visual_style_shots` — công thức góc chụp con.
 * =====================================================================
 *
 * Mỗi shot là một biến thể cách chụp thuộc 1 DNA visual_style: cho phép
 * ghi đè `composition_override_json` / `camera_override_json` lên DNA gốc,
 * thêm `prompt_suffix` riêng và gợi ý `suggested_aspect_ratio` (16:9/9:16/
 * 1:1/4:5...). `code` định danh duy nhất trong phạm vi 1 style.
 *
 * SIDE EFFECT: tạo bảng + index (visual_style_id, sort_order, is_active)
 *              + unique (visual_style_id, code) + FK cascade.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng visual_style_shots.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + index + unique + FK cascade.
     */
    public function up(): void
    {
        Schema::create('visual_style_shots', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('visual_style_id')->index();

            // Định danh góc chụp trong 1 style (hero, thumbnail, b_roll...).
            $table->string('code', 60);

            $table->string('name', 160);
            $table->text('description')->nullable();

            // Override nhóm composition của DNA gốc (camera_angle,
            // framing_rule, depth_of_field...). Null = kế thừa nguyên DNA.
            $table->json('composition_override_json')->nullable();

            // Override nhóm camera của DNA gốc (lens_type, shot_medium,
            // perspective...). Null = kế thừa nguyên DNA.
            $table->json('camera_override_json')->nullable();

            // Suffix bổ sung vào prompt lớp ShotRecipe.
            $table->text('prompt_suffix')->nullable();

            // Tỷ lệ khung hình gợi ý cho shot (16:9|9:16|1:1|4:5...).
            $table->string('suggested_aspect_ratio', 16)->default('16:9');

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->unique(['visual_style_id', 'code'], 'visual_style_shots_style_code_unique');

            // FK visual_styles — xóa cascade theo style cha.
            $table->foreign('visual_style_id')
                ->references('id')->on('visual_styles')
                ->cascadeOnDelete();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng visual_style_shots khi rollback.
     * SIDE EFFECT: xóa bảng visual_style_shots.
     */
    public function down(): void
    {
        Schema::dropIfExists('visual_style_shots');
    }
};
