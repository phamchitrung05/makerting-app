<?php

namespace Modules\Taxonomy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasSlug;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model Eloquent cho bảng `tags`.
 * =====================================================================
 *
 * Thẻ phân loại phẳng với scope phân vùng (null = dùng chung).
 * Dùng HasSlug (nguồn slug = cột 'name').
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - entries()              : pivot đa hình ngược về model gán.
 * - scopeScope()/scopeActive() : lọc theo phân vùng + trạng thái.
 * INPUT/OUTPUT: bản ghi tags.
 * =====================================================================
 */
class Tag extends Model
{
    use HasSlug, SoftDeletes;

    protected $table = 'tags';

    /** Cột nguồn slug = 'name'. */
    protected string $slugSource = 'name';

    protected $fillable = [
        'scope', 'name', 'description', 'status',
    ];

    /**
     * CHỨC NĂNG: Mọi model được gán thẻ này (pivot đa hình ngược).
     * INPUT: class-string $modelClass. OUTPUT: MorphToMany. SIDE EFFECT: không.
     */
    public function entries(string $modelClass): MorphToMany
    {
        return $this->morphedByMany(
            $modelClass, 'taggable', 'taggables',
            'tag_id', 'taggable_id',
        )->withTimestamps();
    }

    /**
     * CHỨC NĂNG: Lọc theo phân vùng (scope) — chuẩn 3 ngữ nghĩa.
     * INPUT:
     * - null / '' / 'any' / 'all' : KHÔNG lọc (mọi scope).
     * - 'shared'                  : CHỈ thẻ dùng chung (scope IS NULL).
     * - 'blog'|'youtube'|...      : scope đó + scope dùng chung (kế thừa).
     * OUTPUT: Builder. SIDE EFFECT: không.
     */
    public function scopeScope($query, ?string $scope)
    {
        if ($scope === null || $scope === '' || $scope === 'any' || $scope === 'all') {
            return $query;
        }

        if ($scope === 'shared') {
            return $query->whereNull('scope');
        }

        return $query->where(function ($q) use ($scope) {
            $q->where('scope', $scope)->orWhereNull('scope');
        });
    }

    /**
     * CHỨC NĂNG: Chỉ thẻ đang hoạt động.
     * INPUT: query. OUTPUT: Builder status='active'. SIDE EFFECT: không.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
