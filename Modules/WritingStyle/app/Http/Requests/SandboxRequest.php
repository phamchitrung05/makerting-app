<?php

namespace Modules\WritingStyle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload DNA Sandbox (topic + target_tier).
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
        ];
    }
}
