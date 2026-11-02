<?php

namespace Modules\CoreAi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\CoreAi\Models\AiModel;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload cập nhật AiModel (partial update).
 * =====================================================================
 * Unique (ai_provider_id, remote_model_id) bỏ qua chính model đang sửa;
 * nếu đổi ai_provider_id lẫn remote_model_id cùng lúc vẫn unique theo
 * cặp MỚI (where() dùng giá trị input mới khi có).
 * =====================================================================
 */
class AiModelUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $model = $this->route('ai_model') ?? $this->route('model');
        $id = is_object($model) ? $model->id : $model;
        $providerId = $this->input('ai_provider_id', is_object($model) ? $model->ai_provider_id : null);

        return [
            'ai_provider_id' => ['sometimes', 'integer', Rule::exists('ai_providers', 'id')],
            'remote_model_id' => [
                'sometimes', 'string', 'max:191',
                Rule::unique('ai_models', 'remote_model_id')
                    ->where('ai_provider_id', $providerId)
                    ->ignore($id),
            ],
            'label' => ['sometimes', 'string', 'max:191'],
            // Bỏ min:1 — cho phép [] (rỗng = "Chưa có capabilities").
            'capabilities' => ['sometimes', 'array'],
            'capabilities.*' => ['string', Rule::in(AiModel::CAPABILITIES)],
            'pricing' => ['nullable', 'array'],
            'pricing.input_per_mtok' => ['nullable', 'numeric', 'min:0'],
            'pricing.output_per_mtok' => ['nullable', 'numeric', 'min:0'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }
}
