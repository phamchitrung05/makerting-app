<?php

namespace Modules\Taxonomy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Taxonomy\Models\Category;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload tạo mới Category.
 * =====================================================================
 */
class CategoryStoreRequest extends FormRequest
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
            ],
            'scope' => [
                'nullable', 'string', 'max:40',
                // Cấm sentinel dùng trong query — 'shared' là "chỉ scope NULL",
                // 'any'/'all' là "không lọc"; để làm giá trị sẽ lệch ngữ nghĩa.
                Rule::notIn(['shared', 'any', 'all']),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Cha phải cùng scope hoặc scope dùng chung (null); cha ở
                    // scope X chỉ chứa con scope X (shared là thành phần "hỗn hợp").
                    $parentId = $this->input('parent_id');
                    if ($parentId && $value !== null) {
                        $parent = Category::find($parentId);
                        if ($parent && $parent->scope !== null && $parent->scope !== $value) {
                            $fail('Danh mục cha phải thuộc cùng scope hoặc scope dùng chung.');
                        }
                    }
                },
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer'],
            'show_on_menu' => ['nullable', 'boolean'],
        ];
    }
}
