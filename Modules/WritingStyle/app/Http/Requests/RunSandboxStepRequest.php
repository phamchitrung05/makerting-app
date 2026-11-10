<?php

namespace Modules\WritingStyle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\WritingStyle\DTOs\WritingBrief;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload thực thi 1 step trong Sandbox Pipeline.
 * =====================================================================
 *
 * POST /v1/admin/writing-style/writing-profiles/{profile}/sandbox/steps/{step}
 *
 * Chấp nhận:
 *   - topic, target_tier, target_sentences, temperature, tone_overrides,
 *     style_instructions, voice_texture_instructions, voice_texture_sliders_json
 *     (tương thích với SandboxRequest hiện có — cùng overrides cho compiler).
 *   - artifacts: map stepKey => artifact của các step đã chạy trước (FE giữ
 *     và gửi lại ở bước sau khi bước đó `depends_on` bước trước).
 *   - upstream_prompt_blocks: cho phép client tự truyền khối chỉ dẫn thay vì
 *     artifacts (ít dùng — chủ yếu artifacts để Runner compileToPromptBlock).
 * =====================================================================
 */
class RunSandboxStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...WritingBrief::rules(),
            'topic'            => ['required_without:brief.topic', 'nullable', 'string', 'min:3', 'max:2000'],
            'target_tier'      => ['nullable', 'in:micro,short,medium,long'],
            'target_sentences' => ['nullable', 'integer', 'min:1', 'max:200'],
            'temperature'      => ['nullable', 'numeric', 'min:0', 'max:2'],

            // Hiệu chỉnh tone runtime — 8 trục theo config('writingstyle.tone_axes').
            'tone_overrides'            => ['nullable', 'array'],
            'tone_overrides.*'          => ['nullable', 'integer', 'min:0', 'max:100'],

            // Ghi đè style_instructions (Ô 1) — FE muốn Sandbox thấy ngay.
            'style_instructions'        => ['nullable', 'string', 'min:1', 'max:10000'],

            // Ghi đè voice_texture_instructions (Ô 2) + 5 thanh trượt Chất văn.
            'voice_texture_instructions'        => ['nullable', 'string', 'max:10000'],
            'tone_instructions'                 => ['nullable', 'string', 'max:10000'],
            'voice_texture_sliders_json'        => ['nullable', 'array'],
            'voice_texture_sliders_json.*'      => ['nullable', 'integer', 'min:0', 'max:100'],

            // Artifacts của các step đã chạy trước — key = stepKey, value = artifact.
            'outline_approved'          => ['sometimes', 'boolean'],
            'artifacts'                 => ['nullable', 'array', 'max:12'],
            'artifacts.*'               => ['array', 'max:30'],
        ];
    }
}
