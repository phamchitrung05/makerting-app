<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate input cho API preview slug.
 * =====================================================================
 */
class SlugPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // endpoint đã qua middleware auth:sanctum + permission
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:250'],
            'sluggable_type' => ['required', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:16'],
        ];
    }
}
