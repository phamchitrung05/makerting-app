<?php

namespace Modules\WritingStyle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload tạo/cập nhật WritingProfile thủ công (origin=manual).
 */
class StoreWritingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm ở middleware permission
    }

    public function rules(): array
    {
        return [
            'name'                  => [$this->method() === 'POST' ? 'required' : 'sometimes', 'string', 'max:160'],
            'description'           => ['nullable', 'string', 'max:2000'],
            'dominant_tier'         => ['nullable', 'in:micro,short,medium,long'],
            'rules_json'            => ['nullable', 'array'],
            'evidence_json'         => ['nullable', 'array'],
            'style_instructions'    => ['nullable', 'string', 'max:10000'],
            'status'                => ['nullable', 'in:draft,active'],
            'is_enabled'            => ['nullable', 'boolean'],
        ];
    }
}
