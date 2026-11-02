<?php

namespace Modules\CoreAi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload tạo mới AiProvider.
 * =====================================================================
 * `api_key` là trường input (không lưu thẳng vào cột — controller mã hóa
 * qua CredentialVault trước khi ghi api_key_encrypted). `extra_keys` là
 * mảng key phụ nhận vào; controller mã hóa từng key → extra_keys_json.
 * =====================================================================
 */
class AiProviderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm ở middleware permission
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('ai_providers', 'key')],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(AiProvider::KINDS)],
            'driver' => ['required', Rule::in(AiProvider::DRIVERS)],
            'base_url' => ['required', 'url:http,https', 'max:255'],
            'api_key' => ['required', 'string', 'min:8', 'max:500'],
            'extra_keys' => ['nullable', 'array'],
            'extra_keys.*' => ['string', 'min:8', 'max:500', 'distinct'],
            'config_json' => ['nullable', 'array'],
            'request_timeout' => ['nullable', 'integer', 'min:5', 'max:600'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
