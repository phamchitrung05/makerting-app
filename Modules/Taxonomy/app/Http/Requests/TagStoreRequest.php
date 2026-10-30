<?php

namespace Modules\Taxonomy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload tạo mới Tag.
 * =====================================================================
 */
class TagStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm ở middleware permission
    }

    public function rules(): array
    {
        return [
            'scope' => [
                'nullable', 'string', 'max:40',
                Rule::notIn(['shared', 'any', 'all']),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
