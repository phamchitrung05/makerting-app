<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload tạo/cập nhật Role kèm danh sách quyền.
 * =====================================================================
 */
class RoleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('role')?->id ?? $this->route('role');

        $registered = app(\Modules\Identity\Registries\PermissionRegistry::class)
            ->allPermissionNames();

        return [
            'name' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('roles', 'name')->ignore($id)],
            'permissions' => ['nullable', 'array'],
            // Chỉ cho phép các quyền đã đăng ký trong PermissionRegistry —
            // chặn gửi '*' hay chuỗi rác làm bẩn bảng permissions.
            'permissions.*' => ['string', 'distinct', Rule::in($registered)],
        ];
    }
}
