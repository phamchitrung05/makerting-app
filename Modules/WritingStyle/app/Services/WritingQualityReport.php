<?php

namespace Modules\WritingStyle\Services;

use Modules\WritingStyle\DTOs\WritingBrief;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Tạo báo cáo kiểm tra bài viết dựa trên độ dài, định dạng và cụm từ cần đối chiếu.
 * =====================================================================
 *
 * ArticleWriterStep gọi sau khi nhận bài và trả báo cáo bên cạnh text cho UI xem xét.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - inspect(): Đối chiếu bài với độ dài, Markdown được phép, CTA, chi tiết bắt buộc và cụm từ của mẫu.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Bài viết, WritingBrief và danh sách dữ kiện riêng của mẫu.
 * - OUTPUT: array length/checks/missing_details/possible_sample_leaks/warnings.
 *
 * BỐI CẢNH SỬ DỤNG: ArticleWriterStep gọi sau khi nhận bài và trả báo cáo bên cạnh text cho UI xem xét.
 * =====================================================================
 */
final class WritingQualityReport
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Đối chiếu bài với độ dài, Markdown được phép, CTA, chi tiết bắt buộc và cụm từ của mẫu
     * =====================================================================
     *
     * INPUT:
     * - string $text, WritingBrief $brief, array $sourceSpecificItems
     *
     * OUTPUT:
     * - array báo cáo kiểm tra bằng quy tắc; đối chiếu chuỗi không chứng minh tính đúng đắn ngữ nghĩa
     *
     * SIDE EFFECT:
     * - Chỉ đếm và tìm chuỗi, không gọi AI, sửa bài hoặc ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - JsonException nếu brief không mã hóa được. Không mở transaction.
     * =====================================================================
     */
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
        $supplied = PromptPack::json($brief->data);
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
