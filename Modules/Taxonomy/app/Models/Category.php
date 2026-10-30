<?php

namespace Modules\Taxonomy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasSlug;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model Eloquent cho bảng `categories`.
 * =====================================================================
 *
 * Danh mục cây cha–con với scope phân vùng (null = dùng chung).
 * Dùng HasSlug (nguồn slug = cột 'name').
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - parent()/children()   : quan hệ cây cha–con.
 * - posts()/categorizables() : pivot đa hình ngược.
 * - scopeScope()/scopeActive() : lọc theo phân vùng + trạng thái.
 * INPUT/OUTPUT: bản ghi categories.
 * =====================================================================
 */
class Category extends Model
{
    use HasSlug, SoftDeletes;

    protected $table = 'categories';

    /** Cột nguồn slug = 'name' (mặc định trait dùng 'title'). */
    protected string $slugSource = 'name';

    protected $fillable = [
        'parent_id', 'scope', 'name', 'description',
        'status', 'sort_order', 'show_on_menu',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'show_on_menu' => 'boolean',
    ];

    /**
     * CHỨC NĂNG: Danh mục cha (null = gốc).
     * INPUT: không. OUTPUT: BelongsTo. SIDE EFFECT: không.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * CHỨC NĂNG: Danh mục con (sắp theo sort_order).
     * INPUT: không. OUTPUT: HasMany. SIDE EFFECT: không.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order')->orderBy('name');
    }

    /**
     * CHỨC NĂNG: Tất cả hàng đổ danh mục (đệ quy qua children).
     * INPUT: không. OUTPUT: HasMany (children + con cháu). SIDE EFFECT: không.
     */
    public function descendants(): HasMany
    {
        return $this->children()->with(['slug', 'descendants']);
    }

    /**
     * CHỨC NĂNG: Mọi model được gán danh mục này (pivot đa hình ngược).
     * INPUT: class-string $modelClass. OUTPUT: MorphToMany.
     * SIDE EFFECT: không.
     */
    public function entries(string $modelClass): MorphToMany
    {
        return $this->morphedByMany(
            $modelClass, 'categorizable', 'categorizables',
            'category_id', 'categorizable_id',
        )->withPivot('sort_order')->withTimestamps();
    }

    /**
     * CHỨC NĂNG: Lọc theo phân vùng (scope) — chuẩn 3 ngữ nghĩa.
     * INPUT:
     * - null / '' / 'any' / 'all' : KHÔNG lọc (mọi scope).
     * - 'shared'                  : CHỈ danh mục dùng chung (scope IS NULL).
     * - 'blog'|'youtube'|...      : scope đó + scope dùng chung (kế thừa).
     * OUTPUT: Builder. SIDE EFFECT: không.
     * LƯU Ý: 'shared' là sentinel dành riêng — FormRequest cấm dùng làm
     * giá trị scope thật để tránh xung đột ngữ nghĩa.
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
     * CHỨC NĂNG: Chỉ danh mục đang hoạt động.
     * INPUT: query. OUTPUT: Builder status='active'. SIDE EFFECT: không.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
