<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate input cho API preview slug.
 * =====================================================================
 *
 * Endpoint: GET /api/v1/admin/core/slug-preview
 * Query: ?source=...&sluggable_type=...&locale=vi
 * =====================================================================
 */
class SlugPreviewRequest extends FormRequest
{
    /**
     * CHỨC NĂNG: Cho phép request đi qua (đã có middleware auth+permission).
     * INPUT : không có. OUTPUT: true.
     * SIDE EFFECT: không có.
     */
    public function authorize(): bool
    {
        return true; // endpoint đã qua middleware auth:sanctum + permission
    }

    /**
     * CHỨC NĂNG: Quy tắc validate body của request preview slug.
     * INPUT : không có. OUTPUT: array rules Laravel.
     * SIDE EFFECT: không có; 422 ValidationException nếu vi phạm.
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:250'],
            'sluggable_type' => ['required', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:16'],
        ];
    }
}
