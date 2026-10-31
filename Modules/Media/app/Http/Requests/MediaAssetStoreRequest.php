<?php

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload upload media mới (single hoặc many).
 * =====================================================================
 * - file|files[] : bắt buộc (multipart), MIME thực kiểm bởi Scan action.
 * - folder       : 'root'|'images'|'videos'|... (<=60 ký tự).
 * - visibility   : public|private (default public).
 * - channel_group_id, title, alt_text : metadata tùy chọn.
 * - allowed_kinds: giới hạn kind khi slot đích yêu cầu.
 * =====================================================================
 */
class MediaAssetStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * CHỨC NĂNG: Merge file bag vào input để rule file validate đúng.
     * INPUT: không. OUTPUT: void (side effect trên $this->request).
     */
    protected function prepareForValidation(): void
    {
        // Merge file bag vào request data để rule `file`/`files` thấy field.
        if ($this->hasFile('file')) {
            $this->merge(['file' => $this->file('file')]);
        }
        if ($this->hasFile('files')) {
            $this->merge(['files' => $this->file('files')]);
        }
    }

    public function rules(): array
    {
        return [
            'file' => ['required_without:files', 'file', 'max:' . $this->maxKb()],
            'files' => ['required_without:file', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:' . $this->maxKb()],
            'folder' => ['nullable', 'string', 'max:60'],
            'visibility' => ['nullable', Rule::in(MediaAsset::VISIBILITIES)],
            'channel_group_id' => ['nullable', 'integer', 'exists:channel_groups,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'allowed_kinds' => ['nullable', 'array'],
            'allowed_kinds.*' => [Rule::in(MediaAsset::KINDS)],
            'dedupe' => ['nullable', 'boolean'],
        ];
    }

    /**
     * CHỨC NĂNG: max upload KB cho rule `file`.
     * INPUT: không. OUTPUT: int KB (default 50MB từ config).
     */
    private function maxKb(): int
    {
        $bytes = (int) config('media.max_upload_bytes', 50 * 1024 * 1024);

        return max(1, (int) ceil($bytes / 1024));
    }
}
