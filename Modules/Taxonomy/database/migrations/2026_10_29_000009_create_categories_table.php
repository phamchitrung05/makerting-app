<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `categories` — danh mục cây cha–con + `scope`.
 * =====================================================================
 *
 * scope=null : danh mục dùng chung toàn hệ thống;
 * scope='blog' | 'visual_style' | 'youtube' | 'campaign' : phân vùng riêng
 * cho từng module (tránh trộn danh mục bài viết với danh mục kênh YouTube).
 * parent_id self-FK nullOnDelete : xóa cha -> con lên gốc, không xóa dây chuyền.
 * Dùng HasSlug (slug toàn cục) + HasThumbnail (Media) + softDeletes.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng categories.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + index scope/parent/sort + FK self.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('scope', 40)->nullable()->index();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active');
            $table->integer('sort_order')->default(0);
            $table->boolean('show_on_menu')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')
                ->references('id')->on('categories')
                ->nullOnDelete();
            $table->index(['scope', 'parent_id', 'sort_order'], 'categories_scope_parent_sort_index');
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng categories khi rollback.
     * SIDE EFFECT: xóa bảng categories.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
