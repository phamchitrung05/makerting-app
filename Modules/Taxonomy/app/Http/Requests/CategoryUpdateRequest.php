<?php

namespace Modules\Taxonomy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Taxonomy\Models\Category;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload cập nhật Category.
 * =====================================================================
 * Chặn parent_id tự trỏ (A→A) và vòng (A→B→A).
 * =====================================================================
 */
class CategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm ở middleware permission
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
                function (string $attr, mixed $value, \Closure $fail): void {
                    if ($this->parentWouldCycle((int) $value)) {
                        $fail('Danh mục cha không hợp lệ (tạo vòng lặp).');
                        return;
                    }

                    $parent = Category::find($value);
                    $category = $this->route('category');
                    $scope = $this->input('scope', $category instanceof Category ? $category->scope : null);

                    if ($parent && $parent->scope !== null && $parent->scope !== $scope) {
                        $fail('Danh mục cha phải thuộc cùng scope hoặc scope dùng chung.');
                    }
                },
            ],
            'scope' => [
                'nullable', 'string', 'max:40',
                Rule::notIn(['shared', 'any', 'all']),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $category = $this->route('category');
                    if (! $category instanceof Category) {
                        return;
                    }

                    // 1) Cha hiện tại (hoặc cha mới nếu payload có parent_id) phải
                    //    cùng scope / scope chung — chặn lệch khi đổi scope một mình.
                    $parentId = $this->input('parent_id', $category->parent_id);
                    if ($parentId && $value !== null) {
                        $parent = Category::find($parentId);
                        if ($parent && $parent->scope !== null && $parent->scope !== $value) {
                            $fail('Danh mục cha phải thuộc cùng scope hoặc scope dùng chung.');
                            return;
                        }
                    }

                    // 2) Con hiện tại (cây con đã có) — đổi scope cha làm con lệch.
                    if ($value !== null) {
                        $conflict = Category::where('parent_id', $category->id)
                            ->whereNotNull('scope')
                            ->where('scope', '!=', $value)
                            ->exists();
                        if ($conflict) {
                            $fail('Không thể đổi scope vì danh mục con đang ở scope khác.');
                        }
                    }
                },
            ],
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer'],
            'show_on_menu' => ['nullable', 'boolean'],
        ];
    }

    /**
     * CHỨC NĂNG: parent_id mới có tạo vòng (self hoặc hậu duệ) không.
     * INPUT: $parentId. OUTPUT: bool — true = vòng lặp.
     */
    private function parentWouldCycle(?int $parentId): bool
    {
        if (! $parentId) {
            return false;
        }

        $category = $this->route('category');
        $selfId = $category instanceof Category ? (int) $category->id : 0;

        if ($parentId === $selfId) {
            return true;
        }

        // Leo lên cây cha từ parent mới: nếu gặp chính mình -> vòng.
        $seen = [];
        $node = Category::find($parentId);
        while ($node) {
            if ($node->id === $selfId) {
                return true;
            }
            if (isset($seen[$node->id])) {
                break; // Vòng có sẵn trong DB, dừng tránh lặp vô hạn.
            }
            $seen[$node->id] = true;
            $node = $node->parent;
        }

        return false;
    }
}
