<?php

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload gán media vào Domain Model theo slot.
 * =====================================================================
 * - linkable_type : morph_type alias hoặc FQCN của model đích.
 * - linkable_id   : id model đích.
 * - slot          : slot đã đăng ký trong MediaSlotRegistry.
 * - media_asset_ids[] : danh sách asset id (thứ tự = sort_order).
 * - mode          : 'attach' (append) | 'replace' (xóa slot cũ) | 'sync' (set đúng list).
 * =====================================================================
 */
class MediaUsageAttachRequest extends FormRequest
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
            'media_asset_ids' => ['required', 'array', 'min:1', 'max:200'],
            'media_asset_ids.*' => ['integer', 'exists:media_assets,id'],
            'mode' => ['nullable', Rule::in(['attach', 'replace', 'sync'])],
        ];
    }
}
