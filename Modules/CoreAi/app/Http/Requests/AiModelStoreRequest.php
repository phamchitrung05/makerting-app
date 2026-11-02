<?php

namespace Modules\CoreAi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CoreAi\Models\AiModel;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload tạo mới AiModel.
 * =====================================================================
 * `capabilities` phải là subset AiModel::CAPABILITIES. Unique theo cặp
 * (ai_provider_id, remote_model_id) — DB có unique index, FormRequest
 * kiểm chứng trước để trả 422 thay vì UniqueConstraintViolationException.
 * =====================================================================
 */
class AiModelStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $providerId = $this->input('ai_provider_id');

        return [
            'ai_provider_id' => ['required', 'integer', Rule::exists('ai_providers', 'id')],
            'remote_model_id' => [
                'required', 'string', 'max:191',
                Rule::unique('ai_models', 'remote_model_id')
                    ->where('ai_provider_id', $providerId),
            ],
            'label' => ['required', 'string', 'max:191'],
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['string', Rule::in(AiModel::CAPABILITIES)],
            'pricing' => ['nullable', 'array'],
            'pricing.input_per_mtok' => ['nullable', 'numeric', 'min:0'],
            'pricing.output_per_mtok' => ['nullable', 'numeric', 'min:0'],
            'is_enabled' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
