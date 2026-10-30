<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `taggables` — pivot đa hình gán Tag.
 * =====================================================================
 *
 * Gán bất kỳ model nào vào Tag qua cặp (taggable_type, taggable_id).
 * Unique 3 cột chặn gán trùng 1 tag 2 lần trên cùng bản ghi.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng taggables.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + FK cascade + unique + index tra cứu ngược.
     */
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tag_id');
            $table->string('taggable_type', 64);
            $table->unsignedBigInteger('taggable_id');
            $table->timestamps();

            $table->foreign('tag_id')
                ->references('id')->on('tags')
                ->cascadeOnDelete();
            $table->unique(
                ['tag_id', 'taggable_type', 'taggable_id'],
                'taggables_unique',
            );
            $table->index(
                ['taggable_type', 'taggable_id'],
                'taggables_morph_index',
            );
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng taggables khi rollback. SIDE EFFECT: xóa bảng.
     */
    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
