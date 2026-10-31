<?php

namespace Modules\Seo\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\Seo\Models\SeoMetadata;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Action ghi đè/cập nhật khối seo_metadata của một model.
 * =====================================================================
 *
 * Chỉ upsert các trường được truyền trong payload (người dùng ghi đè);
 * field không gửi giữ nguyên giá trị cũ. updateOrCreate theo cặp khóa
 * morph (seoable_type, seoable_id) — an toàn để chạy lại.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - execute(Model $model, array $payload) : SeoMetadata đã lưu
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : model chủ (dùng HasSeoMeta) + payload các trường seo_* / og_*.
 * - OUTPUT: SeoMetadata vừa tạo/cập nhật.
 * SIDE EFFECT: INSERT/UPDATE bảng seo_metadata.
 * =====================================================================
 */
class SyncSeoMetadataAction
{
    /** Các cột được phép ghi đè từ payload. */
    private const FIELDS = [
        'focus_keyword',
        'seo_title',
        'seo_description',
        'canonical_url',
        'robots_index',
        'robots_follow',
        'og_title',
        'og_description',
        'og_image_id',
        'schema_org_json',
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Upsert seo_metadata cho model theo payload ghi đè.
     * =====================================================================
     * INPUT:
     * - $model   : model chủ (Model đã dùng HasSeoMeta).
     * - $payload : mảng field ghi đè (chỉ lấy các key trong FIELDS).
     * OUTPUT: SeoMetadata đã persist (mới hoặc đã cập nhật).
     * EXCEPTION: không ném — để FormRequest validate trước.
     * =====================================================================
     */
    public function execute(Model $model, array $payload): SeoMetadata
    {
        $values = Arr::only($payload, self::FIELDS);

        /** @var SeoMetadata $meta */
        $meta = $model->seoMetadata()->updateOrCreate(
            [
                'seoable_type' => $model->getMorphClass(),
                'seoable_id' => $model->getKey(),
            ],
            $values,
        );

        return $meta;
    }
}
