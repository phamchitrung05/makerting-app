<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `media_asset_usages` — pivot đa hình gán
 * MediaAsset vào một Domain Model theo `slot`.
 * =====================================================================
 *
 * `slot` là khóa logic định nghĩa vai trò media trên model:
 *   'post.thumbnail'      -> ảnh đại diện bài viết
 *   'post.gallery'        -> gallery ảnh
 *   'social_variant.media'-> ảnh/video đăng mạng xã hội
 *   'video_project.output'-> file .mp4 kết xuất
 *   'visual_style.reference'-> ảnh tham chiếu DNA
 *
 * Unique 3 cột (media_asset_id, linkable_type, linkable_id, slot) chặn gán
 * trùng cùng 1 asset vào cùng 1 slot 2 lần; sort_order để sắp thứ tự gallery.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng media_asset_usages.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + FK cascade + unique + index tra cứu ngược.
     */
    public function up(): void
    {
        Schema::create('media_asset_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_asset_id');
            $table->string('linkable_type', 64);
            $table->unsignedBigInteger('linkable_id');
            $table->string('slot', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('media_asset_id')
                ->references('id')->on('media_assets')
                ->cascadeOnDelete();

            // Chặn gán trùng cùng 1 asset vào cùng slot 2 lần.
            $table->unique(
                ['media_asset_id', 'linkable_type', 'linkable_id', 'slot'],
                'media_asset_usages_unique',
            );

            // Tra cứu "model này dùng media gì ở slot nào, theo thứ tự".
            $table->index(
                ['linkable_type', 'linkable_id', 'slot', 'sort_order'],
                'media_asset_usages_morph_slot_index',
            );
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng media_asset_usages khi rollback.
     * SIDE EFFECT: xóa bảng media_asset_usages.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_asset_usages');
    }
};
