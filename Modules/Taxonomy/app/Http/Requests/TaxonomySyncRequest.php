<?php

namespace Modules\Taxonomy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload gán taxonomy cho một model bất kỳ.
 * =====================================================================
 * Nhận categories[] + new_categories[] + tags[] + new_tags[] + scope cho
 * HasTaxonomy::syncTaxonomyWithNew() — hỗ trợ tag do AI đề xuất (new_tags).
 * =====================================================================
 */
class TaxonomySyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền kiểm ở middleware permission
    }

    public function rules(): array
    {
        return [
            'categories' => ['nullable', 'array'],
            'categories.*' => [
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'new_categories' => ['nullable', 'array'],
            'new_categories.*' => ['string', 'max:160'],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')
                    ->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*' => ['string', 'max:120'],
            'scope' => ['nullable', 'string', 'max:40'],
        ];
    }
}
