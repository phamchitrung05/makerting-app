<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * =====================================================================
 * MIGRATION: Bảng `seo_metadata` — siêu dữ liệu SEO đa hình 1-1.
 * =====================================================================
 * Mỗi Domain Model (Post, Category, LandingPage...) có nhiều nhất 1 dòng
 * seo_metadata qua morph (seoable_type + seoable_id). Chỉ lưu các trường
 * người dùng chủ động ghi đè; giá trị trống được trait HasSeoMeta bù từ
 * title/slug/thumbnail + mẫu tiêu đề chung của SeoSettings khi render.
 *
 * og_image_id trỏ tới Modules/Media `media_assets` (P1-06).
 * =====================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type', 64)->comment('Morph alias của model chủ (post, category...)');
            $table->unsignedBigInteger('seoable_id')->comment('ID của model chủ');
            $table->string('focus_keyword', 160)->nullable();
            $table->string('seo_title')->nullable()->comment('Chỉ lưu khi người dùng ghi đè title mặc định');
            $table->string('seo_description', 500)->nullable();
            $table->string('canonical_url', 2048)->nullable()->comment('Ghi đè canonical; trống = slug + base_url');
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->unsignedBigInteger('og_image_id')->nullable();
            $table->json('schema_org_json')->nullable();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id'], 'seo_metadata_seoable_unique');
            $table->index('seoable_id');

            $table->foreign('og_image_id')
                ->references('id')->on('media_assets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
    }
};
