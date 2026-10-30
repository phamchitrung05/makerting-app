<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `categorizables` — pivot đa hình gán Category.
 * =====================================================================
 *
 * Gán bất kỳ model nào (Post, VisualStyle, Campaign...) vào Category qua
 * cặp (categorizable_type, categorizable_id); sort_order để sắp thứ tự.
 * Unique 3 cột chặn gán trùng 1 category 2 lần trên cùng bản ghi.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng categorizables.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + FK cascade + unique + index tra cứu ngược.
     */
    public function up(): void
    {
        Schema::create('categorizables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('categorizable_type', 64);
            $table->unsignedBigInteger('categorizable_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->cascadeOnDelete();
            $table->unique(
                ['category_id', 'categorizable_type', 'categorizable_id'],
                'categorizables_unique',
            );
            $table->index(
                ['categorizable_type', 'categorizable_id'],
                'categorizables_morph_index',
            );
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng categorizables khi rollback.
     * SIDE EFFECT: xóa bảng categorizables.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorizables');
    }
};
