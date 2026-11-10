<?php

namespace Modules\WritingStyle\Services;

use Illuminate\Validation\ValidationException;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;

final class PromptV3
{
    public static function enabled(): bool
    {
        return (int) config('writingstyle.prompt_version', 2) === 3;
    }

    public static function objectSchema(array $fields): array
    {
        return [
            'type' => 'object',
            'properties' => $fields,
            'required' => array_keys($fields),
            'additionalProperties' => false,
        ];
    }

    public static function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    public static function checkContext(string ...$parts): void
    {
        if (array_sum(array_map('mb_strlen', $parts)) > config('writingstyle.v3.max_context_chars', 40000)) {
            throw ValidationException::withMessages(['brief' => 'Đầu vào quá dài. Hãy rút gọn bài mẫu, hồ sơ hoặc brief.']);
        }
    }

    public static function requireComplete(AiStructuredResponse $response): void
    {
        $reason = $response->raw['choices'][0]['finish_reason']
            ?? $response->raw['stop_reason']
            ?? $response->raw['candidates'][0]['finishReason']
            ?? null;

        if ($reason !== null && ! in_array($reason, ['stop', 'end_turn', 'stop_sequence', 'tool_use', 'STOP'], true)) {
            throw new InvalidPipelineException('AI chưa hoàn thành đầu ra. Hãy kiểm tra giới hạn hoặc thử lại.');
        }
    }
}
