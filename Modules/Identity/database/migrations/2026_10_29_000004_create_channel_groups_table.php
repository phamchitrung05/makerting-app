<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `channel_groups` (Nhóm kênh / Ngách nội dung).
 * =====================================================================
 * Gom cụm các Fanpage, Kênh YouTube, Tài khoản TikTok cùng một thương
 * hiệu/ngách nội dung, kèm cấu hình mặc định (defaults_json) để các module
 * sản xuất nội dung kế thừa văn phong/DNA/TTS/watermark/CTA thống nhất.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * Tạo bảng channel_groups.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng channel_groups + unique code.
     */
    public function up(): void
    {
        Schema::create('channel_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 60)->unique();
            $table->string('color', 20)->default('primary');
            $table->text('description')->nullable();
            $table->json('defaults_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Gỡ bảng channel_groups khi rollback.
     * SIDE EFFECT: xóa bảng channel_groups.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_groups');
    }
};
