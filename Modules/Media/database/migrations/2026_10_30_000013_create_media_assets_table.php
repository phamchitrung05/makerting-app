<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo bảng `media_assets` — lớp metadata chủ của file media.
 * =====================================================================
 *
 * Kiến trúc 2 lớp với Spatie MediaLibrary:
 * - `media_assets` (bảng này): metadata nghiệp vụ (kind, visibility, folder,
 *   channel_group, checksum, size, alt_text) — model `MediaAsset` sở hữu.
 * - `media` (bảng Spatie): file vật lý + conversions (thumb/featured/og)
 *   gắn vào `MediaAsset` qua morph (collection 'library').
 *
 * `channel_group_id` nullable: media có thể dùng chung toàn hệ thống hoặc
 * gán riêng cho một nhóm kênh; cho phép lọc nhanh nhưng vẫn dùng chéo.
 * `kind` định loại nội dung: image|video|audio|document|archive.
 * `visibility` phân quyền URL: public (CDN-ready) | private (signed stream).
 * `checksum_sha256` (char 64) chống trùng file byte-perfect + phục vụ audit.
 * =====================================================================
 */
return new class extends Migration
{
    /**
     * CHỨC NĂNG: Tạo bảng media_assets.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: tạo bảng + index kind/visibility/folder/channel_group/checksum + FK.
     */
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_group_id')->nullable()->index();
            $table->string('folder', 60)->default('root')->index();
            $table->string('kind', 16)->index();
            $table->string('title', 255);
            $table->string('alt_text', 255)->nullable();
            $table->string('visibility', 16)->default('public')->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->char('checksum_sha256', 64)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            // FK channel_groups — nullOnDelete giữ asset khi nhóm kênh bị xóa.
            $table->foreign('channel_group_id')
                ->references('id')->on('channel_groups')
                ->nullOnDelete();
        });
    }

    /**
     * CHỨC NĂNG: Gỡ bảng media_assets khi rollback.
     * SIDE EFFECT: xóa bảng media_assets.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
