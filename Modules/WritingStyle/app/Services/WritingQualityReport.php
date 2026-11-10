<?php

namespace Modules\WritingStyle\Services;

use Modules\WritingStyle\DTOs\WritingBrief;

final class WritingQualityReport
{
    public static function inspect(string $text, WritingBrief $brief, array $sourceSpecificItems): array
    {
        $length = $brief->data['length'];
        $count = $length['unit'] === 'words'
            ? WritingProfileDefinition::countWords($text)
            : WritingProfileDefinition::countSentences($text);
        $checks = ['length' => $count >= $length['min'] && $count <= $length['max']];
        $format = $brief->data['format'];
        $checks['format'] = ! (
            (($format['headings'] ?? null) === false && preg_match('/^\s*#{1,6}\s+\S/mu', $text))
            || (($format['bullets'] ?? null) === false && preg_match('/^\s*(?:[-*+•]|\d+[.)])\s+\S/mu', $text))
        );

        $required = array_filter([...$brief->data['required_details'], $brief->data['cta']]);
        $missing = array_values(array_filter($required, static fn (string $term) => mb_stripos($text, $term) === false));
        $supplied = PromptV3::json($brief->data);
        $possibleLeaks = array_values(array_filter($sourceSpecificItems, static fn ($term) => is_string($term)
            && mb_strlen(trim($term)) >= 3
            && mb_stripos($supplied, $term) === false
            && mb_stripos($text, $term) !== false));
        $warnings = [];
        if (! $checks['length']) {
            $warnings[] = 'Độ dài ngoài khoảng '.$brief->lengthHint().'.';
        }
        if (! $checks['format']) {
            $warnings[] = 'Có định dạng ngoài yêu cầu của brief.';
        }
        if ($missing !== []) {
            $warnings[] = 'Cần đối chiếu lại chi tiết bắt buộc hoặc CTA. Kiểm tra tự động dựa trên cụm từ.';
        }
        if ($possibleLeaks !== []) {
            $warnings[] = 'Có cụm từ có thể là dữ kiện riêng của bài mẫu.';
        }

        return [
            'length' => [...$length, 'actual' => $count],
            'checks' => $checks,
            'missing_details' => $missing,
            'possible_sample_leaks' => $possibleLeaks,
            'warnings' => $warnings,
        ];
    }
}
