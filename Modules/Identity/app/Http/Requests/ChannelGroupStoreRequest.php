<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload tạo mới ChannelGroup.
 * =====================================================================
 */
class ChannelGroupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm ở middleware permission
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('channel_groups', 'code')],
            'color' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'defaults_json' => ['nullable', 'array'],
            'members' => ['nullable', 'array'],
            'members.*.user_id' => ['required_with:members', 'integer', 'exists:users,id'],
            'members.*.role_in_group' => ['required_with:members', Rule::in(['manager', 'editor', 'reviewer'])],
        ];
    }
}
