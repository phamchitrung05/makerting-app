<?php

namespace Modules\CoreAi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CoreAi\Models\AiProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload cập nhật AiProvider (partial update).
 * =====================================================================
 * - `key` unique bỏ qua chính provider đang sửa.
 * - `api_key` rỗng/null = giữ key cũ (controller bỏ qua, không ghi đè).
 * - `extra_keys` khi gửi = THAY TOÀN BỘ danh sách key phụ (controller
 *   encode lại extra_keys_json); gửi [] để xóa hết key phụ.
 * =====================================================================
 */
class AiProviderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $provider = $this->route('ai_provider') ?? $this->route('provider');
        $id = is_object($provider) ? $provider->id : $provider;

        return [
            'key' => ['sometimes', 'string', 'max:60', 'alpha_dash', Rule::unique('ai_providers', 'key')->ignore($id)],
            'name' => ['sometimes', 'string', 'max:120'],
            'kind' => ['sometimes', Rule::in(AiProvider::KINDS)],
            'driver' => ['sometimes', Rule::in(AiProvider::DRIVERS)],
            'base_url' => ['sometimes', 'url:http,https', 'max:255'],
            'api_key' => ['nullable', 'string', 'min:8', 'max:500'],
            'extra_keys' => ['nullable', 'array'],
            'extra_keys.*' => ['string', 'min:8', 'max:500', 'distinct'],
            'config_json' => ['nullable', 'array'],
            'request_timeout' => ['sometimes', 'integer', 'min:5', 'max:600'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
