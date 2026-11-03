<?php

namespace Modules\WritingStyle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload giải mã văn phong (paste/url/file).
 */
class AnalyzeTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type'    => ['required', 'in:paste,url,file'],
            'source_url'     => ['required_if:source_type,url', 'nullable', 'url:http,https', 'max:2000'],
            'reference_text' => ['required_if:source_type,paste', 'nullable', 'string', 'min:50', 'max:200000'],
            'name'           => ['nullable', 'string', 'max:160'],
        ];
    }
}
