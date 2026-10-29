<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `slugs` quản lý slug duy nhất toàn cục.
 * =====================================================================
 *
 * Bảng đa hình trung tâm (morph) cho mọi tài nguyên có URL công khai
 * (Post, Category, Tag, VisualStyle...). Giữ lịch sử slug cũ
 * (is_primary = false) phục vụ Redirect 301 bảo toàn SEO khi đổi tiêu đề.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - up(): tạo bảng slugs với cột, unique và index
 * - down(): gỡ bảng slugs khi rollback
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Schema Builder
 * - OUTPUT: bảng `slugs` trong database
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng `slugs` kèm ràng buộc unique và index truy vấn.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng slugs + unique + index.
     */
    public function up(): void
    {
        Schema::create('slugs', function (Blueprint $table) {
            $table->id();
            $table->string('sluggable_type', 64);
            $table->unsignedBigInteger('sluggable_id');
            $table->string('slug', 250);
            $table->string('locale', 16)->default('vi');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->unique(['sluggable_type', 'slug', 'locale'], 'slugs_type_slug_locale_unique');
            $table->index(['sluggable_type', 'sluggable_id', 'is_primary'], 'slugs_type_id_primary_index');
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng `slugs` khi rollback. SIDE EFFECT: xóa bảng slugs.
     */
    public function down(): void
    {
        Schema::dropIfExists('slugs');
    }
};