<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Slug;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Dọn các bản ghi slug dư thừa của MediaAsset (#15).
 * =====================================================================
 *
 * MediaAsset không dùng slug nghiệp vụ nhưng trước đây gắn trait HasSlug,
 * làm phát sinh nhiều dòng trong bảng `slugs` mỗi lần tạo/sửa title.
 * Migration này gỡ toàn bộ bản ghi cũ thuộc sluggable_type MediaAsset.
 * =====================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('slugs')) {
            return;
        }

        $types = array_values(array_unique([
            MediaAsset::class,
            (new MediaAsset())->getMorphClass(),
        ]));

        Slug::query()->whereIn('sluggable_type', $types)->delete();
    }

    public function down(): void
    {
        // MediaAsset đã gỡ HasSlug nên không khôi phục dữ liệu slug cũ.
    }
};
