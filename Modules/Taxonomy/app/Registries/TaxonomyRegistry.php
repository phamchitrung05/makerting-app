<?php

namespace Modules\Taxonomy\Registries;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Registry ánh xạ model → taxonomy (tra cứu ngược tập trung).
 * =====================================================================
 *
 * Thay cho việc viết cứng quan hệ ngược trong Category: mỗi model dùng
 * HasTaxonomy tự đăng ký alias -> class để TaxonomyRegistry biết model nào
 * "có thể gán taxonomy". Dùng để tra cứu các model được gán taxonomy và
 * cung cấp morph alias ổn định thay cho FQCN trong các bảng pivot.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - register(string $alias, string $modelClass) : gán alias cho model.
 * - models()       : map alias -> FQCN.
 * - resolve()      : lấy FQCN theo alias.
 * - aliasFor()     : lấy alias theo FQCN.
 * - idsOf($type,$id): các model đang gán 1 danh mục/thẻ.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : alias morph (vd 'post', 'visual_style') + FQCN.
 * - OUTPUT: tra cứu hai chiều taxonomy <-> model.
 * =====================================================================
 */
class TaxonomyRegistry
{
    /** @var array<string, class-string> alias => model class */
    private array $models = [];

    /**
     * CHỨC NĂNG: Đăng ký model có thể gán taxonomy và alias morph.
     * INPUT: $alias (dùng trong morph), $modelClass FQCN.
     * OUTPUT: void. SIDE EFFECT: ghi map nội bộ + thêm alias vào morphMap.
     */
    public function register(string $alias, string $modelClass): void
    {
        $this->models[$alias] = $modelClass;
        Relation::morphMap([$alias => $modelClass]);
    }

    /**
     * CHỨC NĂNG: map alias -> class của mọi model đã đăng ký.
     * INPUT: không. OUTPUT: array<string,class-string>. SIDE EFFECT: không.
     */
    public function models(): array
    {
        return $this->models;
    }

    /**
     * CHỨC NĂNG: Lấy FQCN model theo alias.
     * INPUT: alias string. OUTPUT: class-string|null. SIDE EFFECT: không.
     */
    public function resolve(string $alias): ?string
    {
        return $this->models[$alias] ?? null;
    }

    /**
     * CHỨC NĂNG: Lấy alias theo FQCN model.
     * INPUT: class-string. OUTPUT: string|null. SIDE EFFECT: không.
     */
    public function aliasFor(string $modelClass): ?string
    {
        $alias = array_search($modelClass, $this->models, true);

        return $alias === false ? null : $alias;
    }

    /**
     * CHỨC NĂNG: id các model có taxonomy đang gán 1 danh mục/thẻ.
     * INPUT: 'category'|'tag', $id của Category/Tag.
     * OUTPUT: array{alias=>int[]} nhóm theo alias morph.
     * SIDE EFFECT: SELECT pivot.
     */
    public function idsOf(string $type, int $id): array
    {
        $table = $type === 'category' ? 'categorizables' : 'taggables';
        $morph = $type === 'category' ? 'categorizable' : 'taggable';
        $fk = $type === 'category' ? 'category_id' : 'tag_id';

        return DB::table($table)
            ->where($fk, $id)
            ->select([$morph . '_type as type', $morph . '_id as id'])
            ->get()
            ->groupBy('type')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();
    }
}
