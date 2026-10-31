<?php

namespace Modules\Seo\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Media\Models\MediaAsset;
use Modules\Seo\Models\SeoMetadata;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Trait gán siêu dữ liệu SEO đa hình vào Domain Model.
 * =====================================================================
 *
 * Model `use HasSeoMeta` có quan hệ morphOne `seoMetadata()` (1-1 qua
 * seoable_type/seoable_id). Chỉ các trường người dùng ghi đè được lưu;
 * phần trống do SeoMetadataService fallback sang title/slug/thumbnail.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - seoMetadata()        : morphOne SeoMetadata
 * - seo()                : lấy bản ghi (có thể null)
 * - resolveOgImageUrl()  : URL ảnh OG từ MediaAsset (cho SeoMetadataService)
 * - seoFieldsForApi()    : mảng field thô để sync nhận từ request
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : quan hệ morphOne; og_image_id -> Modules/Media media_assets.
 * - OUTPUT: SeoMetadata|null + URL og image.
 * =====================================================================
 */
trait HasSeoMeta
{
    /**
     * CHỨC NĂNG: Quan hệ morphOne tới bản ghi seo_metadata của model.
     * INPUT: không. OUTPUT: MorphOne.
     */
    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    /**
     * CHỨC NĂNG: Bản ghi SEO hiện có (null nếu chưa từng lưu).
     * INPUT: không. OUTPUT: ?SeoMetadata.
     */
    public function seo(): ?SeoMetadata
    {
        return $this->seoMetadata()->first();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: URL ảnh OG — đọc MediaAsset theo og_image_id của meta.
     * =====================================================================
     * Được SeoMetadataService::ogImageFor() gọi khi model có quan hệ này.
     * INPUT: ?SeoMetadata (đã nạp sẵn). OUTPUT: ?string URL media.
     * =====================================================================
     */
    public function resolveOgImageUrl(?SeoMetadata $meta): ?string
    {
        if (! $meta?->og_image_id) {
            return null;
        }

        $asset = MediaAsset::find($meta->og_image_id);

        return $asset?->getFirstMediaUrl('library', 'og') ?: $asset?->getFirstMediaUrl('library');
    }
}
