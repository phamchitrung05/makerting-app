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

    /**
     * CHỨC NĂNG: Định nghĩa rule validate đầu vào khi lưu ảnh AI vào thư viện.
     * INPUT: không.
     * OUTPUT: array<string, array<int, mixed>>.
     */
    public function rules(): array
    {
        return [
            'preview_id' => ['required', 'uuid'],
            'folder' => ['nullable', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', 'string', Rule::in(MediaAsset::VISIBILITIES)],
            'channel_group_id' => ['nullable', 'integer', 'exists:channel_groups,id'],
        ];
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
        ];
    }
}
