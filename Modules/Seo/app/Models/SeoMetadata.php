<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model bản ghi `seo_metadata` — khối meta SEO/OG/Canonical
 * đa hình gắn 1-1 vào một Domain Model bất kỳ.
 * =====================================================================
 *
 * Chỉ lưu các trường người dùng chủ động ghi đè; phần rỗng được
 * SeoMetadataService/HasSeoMeta fallback lúc render. Quan hệ ngược lại
 * model chủ qua morphTo `seoable()`.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - seoable()          : morphTo model chủ (Post, Category, ...)
 * - metaRobots()       : chuỗi robots "index,follow" / "noindex,..." khi render
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : seoable_type/seoable_id + các cột seo_* / og_* / robots_*.
 * - OUTPUT: bản ghi ghi đè do người dùng lưu.
 * =====================================================================
 */
class SeoMetadata extends Model
{
    protected $table = 'seo_metadata';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
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

    protected $casts = [
        'robots_index' => 'boolean',
        'robots_follow' => 'boolean',
        'schema_org_json' => 'array',
    ];

    /**
     * CHỨC NĂNG: Quan hệ morphTo về model chủ (Post, Category, ...).
     * INPUT: không. OUTPUT: MorphTo.
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * CHỨC NĂNG: Chuỗi content của thẻ <meta name="robots">.
     * INPUT: đọc robots_index/robots_follow. OUTPUT: 'index,follow'|...
     */
    public function metaRobots(): string
    {
        $index = $this->robots_index ? 'index' : 'noindex';
        $follow = $this->robots_follow ? 'follow' : 'nofollow';

        return "{$index},{$follow}";
    }
}
