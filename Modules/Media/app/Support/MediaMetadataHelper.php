<?php

namespace Modules\Media\Support;

use Illuminate\Foundation\Http\FormRequest;

/**
 * =========================================================================
 * CHỨC NĂNG: Chuẩn hóa & khai báo validation dùng chung cho metadata media
 * =========================================================================
 * Đảm bảo `description` và `tags` được sanitize đồng nhất giữa upload,
 * update thông tin và lưu ảnh tạo bởi AI.
 */
final class MediaMetadataHelper
{
    /**
     * Rule validate dùng chung cho description và tags trên FormRequest.
     *
     * @return array<string, array<int, string>>
     */
    public static function validationRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * Chuẩn hóa input `description` và `tags` trước khi Laravel chạy validator.
     */
    public static function prepareRequestMetadata(FormRequest $request): void
    {
        $merged = [];

        if ($request->exists('description')) {
            $description = $request->input('description');
            if ($description === null || is_string($description)) {
                $merged['description'] = self::normalizeDescription($description);
            }
        }

        if ($request->exists('tags') && is_array($request->input('tags'))) {
            $merged['tags'] = self::normalizeTagsInput($request->input('tags'));
        }

        if ($merged !== []) {
            $request->merge($merged);
        }
    }

    /**
     * Trim mô tả; chuỗi rỗng sau khi trim sẽ chuyển thành null.
     */
    public static function normalizeDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $trimmed = trim($description);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Làm sạch danh sách tag khi ghi DB (chỉ nhận chuỗi hợp lệ, loại trùng).
     *
     * @return array<int, string>
     */
    public static function normalizeTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $stringOnly = array_values(array_filter($tags, static fn ($item) => is_string($item)));

        return self::normalizeTagsInput($stringOnly);
    }

    /**
     * Trim + loại rỗng + loại trùng không phân biệt hoa thường cho mảng tag input,
     * nhưng vẫn giữ nguyên phần tử không phải string để rule `tags.*` báo lỗi 422.
     *
     * @param  array<int|string, mixed>  $tags
     * @return array<int, mixed>
     */
    private static function normalizeTagsInput(array $tags): array
    {
        $normalized = [];
        $seen = [];

        foreach ($tags as $item) {
            // Multipart FormData + ConvertEmptyStringsToNull biến chuỗi rỗng thành null -> bỏ qua như tag rỗng.
            if ($item === null) {
                continue;
            }

            if (! is_string($item)) {
                $normalized[] = $item;
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed === '') {
                continue;
            }

            $key = mb_strtolower($trimmed, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $trimmed;
        }

        return $normalized;
    }
}
