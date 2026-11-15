<?php

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload lưu ảnh preview AI vào Media Library.
 * =====================================================================
 *
 * Dùng cho endpoint `POST /api/v1/admin/media/ai-image/save`.
 * Kiểm tra preview_id (UUID hợp lệ) cùng metadata đích như thư mục, tiêu đề,
 * alt text, visibility và channel_group_id tùy chọn.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : HTTP JSON request lưu ảnh AI preview vào MediaAsset.
 * - OUTPUT: mảng dữ liệu đã validate qua validated().
 * =====================================================================
 */
class SaveGeneratedImageRequest extends FormRequest
{
    /**
     * CHỨC NĂNG: Cho phép đi qua FormRequest (quyền kiểm qua middleware permission:media.manage).
     * INPUT: không.
     * OUTPUT: bool.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        \Modules\Media\Support\MediaMetadataHelper::prepareRequestMetadata($this);
    }

    /**
     * CHỨC NĂNG: Định nghĩa rule validate đầu vào khi lưu ảnh AI vào thư viện.
     * INPUT: không.
     * OUTPUT: array<string, array<int, mixed>>.
     */
    public function rules(): array
    {
        return array_merge([
            'preview_id' => ['required', 'uuid'],
            'folder' => ['nullable', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', 'string', Rule::in(MediaAsset::VISIBILITIES)],
            'channel_group_id' => ['nullable', 'integer', 'exists:channel_groups,id'],
            'style' => ['nullable', 'string', 'max:100'],
            'model_label' => ['nullable', 'string', 'max:120'],
        ], \Modules\Media\Support\MediaMetadataHelper::validationRules());
    }

    /**
     * CHỨC NĂNG: Thông báo lỗi validate tiếng Việt rõ nghĩa.
     * INPUT: không.
     * OUTPUT: array<string, string>.
     */
    public function messages(): array
    {
        return [
            'preview_id.required' => 'Thiếu mã preview ảnh cần lưu.',
            'preview_id.uuid' => 'Mã preview không đúng định dạng UUID.',
            'folder.max' => 'Tên thư mục không được vượt quá 60 ký tự.',
            'title.max' => 'Tiêu đề ảnh không được vượt quá 255 ký tự.',
            'alt_text.max' => 'Alt text không được vượt quá 255 ký tự.',
            'visibility.in' => 'Chế độ hiển thị phải là public hoặc private.',
            'channel_group_id.exists' => 'Nhóm kênh được chọn không tồn tại.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
            'tags.array' => 'Danh sách nhãn không hợp lệ.',
            'tags.max' => 'Chỉ có thể gắn tối đa 30 nhãn.',
            'tags.*.string' => 'Mỗi nhãn phải là chuỗi ký tự hợp lệ.',
            'tags.*.max' => 'Mỗi nhãn không được vượt quá 50 ký tự.',
        ];
    }
}
