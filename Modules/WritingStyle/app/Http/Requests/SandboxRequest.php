<?php

namespace Modules\WritingStyle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload DNA Sandbox (topic + target_tier + runtime overrides).
 */
class SandboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic'            => ['required', 'string', 'min:3', 'max:2000'],
            'target_tier'      => ['nullable', 'in:micro,short,medium,long'],
            'target_sentences' => ['nullable', 'integer', 'min:1', 'max:200'],
            'temperature'      => ['nullable', 'numeric', 'min:0', 'max:2'],

            // Hiệu chỉnh tone runtime — 8 trục theo config('writingstyle.tone_axes').
            // Chỉ nhận key hợp lệ + giá trị 0..100; không bắt buộc đủ 8.
            'tone_overrides'            => ['nullable', 'array'],
            'tone_overrides.*'          => ['integer', 'min:0', 'max:100'],

            // Ghi đè style_instructions (Ô 1) khi cần (FE muốn Sandbox thấy NGAY, không bắt Lưu trước).
            'style_instructions'        => ['nullable', 'string', 'min:10', 'max:10000'],

            // Ghi đè voice_texture_instructions (Ô 2) và 5 thanh trượt Chất văn runtime.
            'voice_texture_instructions'        => ['nullable', 'string', 'max:10000'],
            'voice_texture_sliders_json'        => ['nullable', 'array'],
            'voice_texture_sliders_json.*'      => ['integer', 'min:0', 'max:100'],
        ];
    }
}
