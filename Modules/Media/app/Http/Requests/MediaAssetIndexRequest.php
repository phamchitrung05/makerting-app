<?php

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate query lọc danh sách media (index/dataTable).
 * =====================================================================
 * Chấp nhận: kind/visibility/folder/channel_group_id/search/
 *          itemsPerPage|per_page/page/sortBy/orderBy.
 * =====================================================================
 */
class MediaAssetIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::in(MediaAsset::KINDS)],
            'visibility' => ['nullable', Rule::in(MediaAsset::VISIBILITIES)],
            'folder' => ['nullable', 'string', 'max:60'],
            'channel_group_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:160'],
            'itemsPerPage' => ['nullable', 'integer', 'min:1', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sortBy' => ['nullable', 'string', 'max:40'],
            'orderBy' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
