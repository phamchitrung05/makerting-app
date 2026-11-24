<?php

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload cập nhật metadata MediaAsset.
 * =====================================================================
 * Chỉ cho sửa metadata (title/alt_text/folder/visibility/channel_group_id)
 * — file vật lý + checksum không đổi (upload lại nếu cần file mới).
 * =====================================================================
 */
class MediaAssetUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        \Modules\Media\Support\MediaMetadataHelper::prepareRequestMetadata($this);
    }

    public function rules(): array
    {
        return array_merge([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'folder' => \Modules\Media\Support\MediaMetadataHelper::folderRules(['sometimes', 'required']),
            'visibility' => ['sometimes', 'required', Rule::in(MediaAsset::VISIBILITIES)],
            'channel_group_id' => ['nullable', 'integer', 'exists:channel_groups,id'],
        ], \Modules\Media\Support\MediaMetadataHelper::validationRules());
    }
}
