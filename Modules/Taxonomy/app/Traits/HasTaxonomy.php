<?php

namespace Modules\Taxonomy\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Taxonomy\Models\Categorizable;
use Modules\Taxonomy\Models\Category;
use Modules\Taxonomy\Models\Tag;
use Modules\Taxonomy\Models\Taggable;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Trait gán Category/Tag đa hình dùng chung cho Domain Model.
 * =====================================================================
 *
 * Model `use HasTaxonomy` nhận quan hệ morphToMany categories()/tags() và
 * hàm syncTaxonomyWithNew() nhận cả ID sẵn có + tên mới do AI đề xuất
 * (tự tạo Category/Tag mới trong cùng transaction khi chưa tồn tại).
 *
 * Payload syncTaxonomyWithNew():
 *   [
 *     'categories'    => [id...],           // ID Category có sẵn
 *     'new_categories'=> ['Tên mới', ...],   // tạo Category mới nếu thiếu
 *     'tags'          => [id...],
 *     'new_tags'      => ['tag ai gợi ý', ...],
 *     'scope'         => 'blog',             // phân vùng khi tạo mới
 *   ]
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - categories()/tags()     : morphToMany qua categorizables/taggables
 * - syncTaxonomyWithNew()   : gộp ID + tên mới -> sync pivot (transaction)
 * - resolveCategoryIds()    : biến tên mới -> id (firstOrCreate theo scope)
 * - resolveTagIds()         : biến tên mới -> id
 * - taxonomyScope()         : scope mặc định khi tạo (override trong model)
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : model có trait + payload categories/tags/new_*.
 * - OUTPUT: pivot đồng bộ + danh mục/thẻ mới được tạo khi cần.
 * =====================================================================
 */
trait HasTaxonomy
{
    /**
     * CHỨC NĂNG: Quan hệ morphToMany về Category.
     * INPUT: không. OUTPUT: MorphToMany (pivot sort_order).
     * SIDE EFFECT: không.
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(
            Category::class, 'categorizable', 'categorizables',
            'categorizable_id', 'category_id',
        )->withPivot('sort_order')->withTimestamps();
    }

    /**
     * CHỨC NĂNG: Quan hệ morphToMany về Tag.
     * INPUT: không. OUTPUT: MorphToMany. SIDE EFFECT: không.
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(
            Tag::class, 'taggable', 'taggables',
            'taggable_id', 'tag_id',
        )->withTimestamps();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đồng bộ danh mục + thẻ của model, nhận cả ID và tên mới
     * =====================================================================
     *
     * INPUT:
     * - $payload array — keys: categories[], new_categories[], tags[],
     *   new_tags[], scope (tùy chọn, mặc định taxonomyScope() của model).
     *
     * OUTPUT: array { categories:[id...], tags:[id...] } — id cuối đã sync.
     *
     * SIDE EFFECT:
     * - DB transaction: tạo Category/Tag mới (firstOrCreate theo scope+name)
     *   khi tên chưa tồn tại, rồi sync() pivot — thay thế toàn bộ gán cũ.
     * - Không ghi gì khi cả 4 key đều vắng.
     * =====================================================================
     */
    public function syncTaxonomyWithNew(array $payload): array
    {
        $scope = $payload['scope'] ?? $this->taxonomyScope();
        $scope = $scope === '' ? null : $scope;

        return DB::transaction(function () use ($payload, $scope) {
            $categoryIds = $this->resolveCategoryIds(
                $payload['categories'] ?? [],
                $payload['new_categories'] ?? [],
                $scope,
            );

            $tagIds = $this->resolveTagIds(
                $payload['tags'] ?? [],
                $payload['new_tags'] ?? [],
                $scope,
            );

            if (array_key_exists('categories', $payload) || array_key_exists('new_categories', $payload)) {
                $this->categories()->sync($this->orderedCategoryPivot($categoryIds));
            }

            if (array_key_exists('tags', $payload) || array_key_exists('new_tags', $payload)) {
                $this->tags()->sync($tagIds);
            }

            return ['categories' => $categoryIds, 'tags' => $tagIds];
        });
    }

    /* =====================================================================
     * Helpers nội bộ
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Gộp ID có sẵn + id của Category mới tạo theo tên.
     * INPUT: $ids int[], $names string[], $scope string|null.
     * OUTPUT: int[] id duy nhất giữ thứ tự (id cũ trước, mới sau).
     * SIDE EFFECT: firstOrCreate Category (scope+name) khi tên chưa có.
     */
    private function resolveCategoryIds(array $ids, array $names, ?string $scope): array
    {
        $out = array_values(array_unique(array_map('intval', $ids)));
        $this->assertIdsInScope(Category::query(), $out, $scope, 'categories');

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $cat = Category::firstOrCreate(
                ['scope' => $scope, 'name' => $name],
                ['status' => 'active'],
            );

            if (! in_array($cat->id, $out, true)) {
                $out[] = $cat->id;
            }
        }

        return $out;
    }

    /**
     * CHỨC NĂNG: Gộp ID có sẵn + id của Tag mới tạo theo tên.
     * INPUT: $ids int[], $names string[], $scope string|null.
     * OUTPUT: int[] id duy nhất giữ thứ tự.
     * SIDE EFFECT: firstOrCreate Tag (scope+name) khi tên chưa có.
     */
    private function resolveTagIds(array $ids, array $names, ?string $scope): array
    {
        $out = array_values(array_unique(array_map('intval', $ids)));
        $this->assertIdsInScope(Tag::query(), $out, $scope, 'tags');

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['scope' => $scope, 'name' => $name],
                ['status' => 'active'],
            );

            if (! in_array($tag->id, $out, true)) {
                $out[] = $tag->id;
            }
        }

        return $out;
    }

    /**
     * CHỨC NĂNG: Từ chối gán ID taxonomy thuộc scope khác.
     * INPUT: Eloquent query, danh sách ID, scope hiện hành, tên field.
     * OUTPUT: void. EXCEPTION: ValidationException khi có ID ngoài scope.
     * SIDE EFFECT: SELECT kiểm tra các ID đã chọn.
     */
    private function assertIdsInScope($query, array $ids, ?string $scope, string $field): void
    {
        if ($ids === []) {
            return;
        }

        $query->whereIn('id', $ids)
            ->where(function ($q) use ($scope) {
                if ($scope === null) {
                    $q->whereNull('scope');
                } else {
                    $q->whereNull('scope')->orWhere('scope', $scope);
                }
            });

        if ($query->count() !== count($ids)) {
            throw ValidationException::withMessages([
                $field => 'Có mục phân loại không tồn tại hoặc không thuộc scope được phép.',
            ]);
        }
    }

    /**
     * CHỨC NĂNG: Tạo map pivot Category ID => sort_order theo thứ tự gửi.
     * INPUT: int[] id. OUTPUT: array id=>['sort_order'=>int]. SIDE EFFECT: không.
     */
    private function orderedCategoryPivot(array $ids): array
    {
        $pivot = [];
        foreach ($ids as $position => $id) {
            $pivot[$id] = ['sort_order' => $position];
        }

        return $pivot;
    }

    /**
     * CHỨC NĂNG: Scope mặc định gán cho Category/Tag mới khi payload không
     * nêu 'scope'. Model override bằng protected $taxonomyScope = 'blog'.
     * INPUT: không. OUTPUT: string|null. SIDE EFFECT: không.
     */
    protected function taxonomyScope(): ?string
    {
        return property_exists($this, 'taxonomyScope') ? $this->taxonomyScope : null;
    }
}
