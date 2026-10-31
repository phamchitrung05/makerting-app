<?php

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload gỡ media khỏi Domain Model theo slot.
 * =====================================================================
 */
class MediaUsageDetachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'linkable_type' => ['required', 'string', 'max:64'],
            'linkable_id' => ['required', 'integer'],
            'slot' => ['required', 'string', 'max:80'],
            'media_asset_ids' => ['nullable', 'array'],
            'media_asset_ids.*' => ['integer'],
            'detach_all' => ['nullable', 'boolean'],
        ];
    }
}
