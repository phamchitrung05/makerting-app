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
        $sourceTypes = config('writingstyle.analysis.input.source_types', ['paste', 'url', 'file']);
        $urlMax = config('writingstyle.analysis.input.source_url_max', 2000);
        $textMin = config('writingstyle.analysis.input.reference_text_min', 50);
        $textMax = config('writingstyle.analysis.input.reference_text_max', 200000);
        $nameMax = config('writingstyle.analysis.input.name_max', 160);

        return [
            'source_type'    => ['required', 'in:' . implode(',', $sourceTypes)],
            'source_url'     => ['required_if:source_type,url', 'nullable', 'url:http,https', 'max:' . $urlMax],
            'reference_text' => ['required_if:source_type,paste', 'nullable', 'string', 'min:' . $textMin, 'max:' . $textMax],
            'name'           => ['nullable', 'string', 'max:' . $nameMax],
        ];
    }
}
