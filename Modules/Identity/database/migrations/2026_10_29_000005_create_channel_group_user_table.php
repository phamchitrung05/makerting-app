<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `channel_group_user` phân công nhân sự theo nhóm.
 * =====================================================================
 * Bảng pivot gán user vào channel_group kèm vai trò trong nhóm
 * (manager/editor/reviewer) — phân quyền nhân sự phụ trách từng cụm kênh.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * Tạo bảng channel_group_user.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng pivot + FK cascade + unique (group,user).
     */
    public function up(): void
    {
        Schema::create('channel_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_group_id')->constrained('channel_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_in_group', 20)->default('editor'); // manager|editor|reviewer
            $table->timestamps();

            $table->unique(['channel_group_id', 'user_id'], 'cg_user_unique');
        });
    }

    /**
     * Gỡ bảng channel_group_user khi rollback.
     * SIDE EFFECT: xóa bảng channel_group_user.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_group_user');
    }
};
