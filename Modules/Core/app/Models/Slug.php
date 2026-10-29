<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model Eloquent cho bảng `slugs` (slug đa hình toàn cục).
 * =====================================================================
 *
 * Mỗi dòng là một slug gắn với một tài nguyên (morphTo). Chỉ một slug
 * is_primary=true cho mỗi (sluggable, locale); các slug cũ is_primary=false
 * được giữ lại để phục vụ Redirect 301.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - sluggable(): quan hệ morphTo tới model chủ sở hữu
 * - scopePrimary($query): lọc slug đang là primary
 * - scopeForType($query, $type): lọc theo sluggable_type
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : thuộc tính sluggable_type/id, slug, locale, is_primary
 * - OUTPUT: bản ghi Slug + quan hệ morphTo
 * =====================================================================
 */
class Slug extends Model
{
    protected $table = 'slugs';

    protected $fillable = [
        'sluggable_type',
        'sluggable_id',
        'slug',
        'locale',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * CHỨC NĂNG: quan hệ đa hình tới model sở hữu slug.
     * INPUT: không có tham số. OUTPUT: MorphTo.
     * SIDE EFFECT: không có.
     */
    public function sluggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * CHỨC NĂNG: scope lọc chỉ slug đang là primary.
     * INPUT: $query Builder. OUTPUT: Builder đã lọc is_primary=true.
     * SIDE EFFECT: không có.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * CHỨC NĂNG: scope lọc theo loại tài nguyên.
     * INPUT: $query Builder, $type (sluggable_type morph alias).
     * OUTPUT: Builder đã lọc theo sluggable_type.
     * SIDE EFFECT: không có.
     */
    public function scopeForType($query, string $type)
    {
        return $query->where('sluggable_type', $type);
    }
}
