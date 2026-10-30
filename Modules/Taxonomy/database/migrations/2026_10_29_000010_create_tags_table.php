<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `tags` — thẻ phân loại phẳng + `scope`.
 * =====================================================================
 *
 * scope=null : tag dùng chung; scope='blog'|'visual_style'|... : phân vùng
 * riêng cho từng module. Dùng HasSlug + softDeletes.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng tags.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + index scope.
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 40)->nullable()->index();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng tags khi rollback. SIDE EFFECT: xóa bảng tags.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
