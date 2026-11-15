<?php

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload yêu cầu AI tạo ảnh preview tạm.
 * =====================================================================
 *
 * Dùng cho endpoint `POST /api/v1/admin/media/ai-image/generate`.
 * Kiểm tra prompt, mô tả tùy chọn, tỉ lệ khung hình, model AI tùy chọn
 * và số lượng ảnh cần sinh (1, 2 hoặc 4).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : HTTP JSON request tạo ảnh AI.
 * - OUTPUT: mảng dữ liệu đã validate qua validated().
 * =====================================================================
 */
class GenerateAiImageRequest extends FormRequest
{
    public const ASPECT_RATIOS = ['1:1', '4:3', '3:4', '16:9', '9:16'];

    public const COUNTS = [1, 2, 4];

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
     * CHỨC NĂNG: Chuẩn hóa chuỗi prompt/description trước khi validate.
     * INPUT: không.
     * OUTPUT: void (cập nhật request input khi cần).
     */
    protected function prepareForValidation(): void
    {
        $merged = [];

        if (is_string($this->input('prompt'))) {
            $merged['prompt'] = trim($this->input('prompt'));
        }

        if ($this->has('description')) {
            $desc = $this->input('description');
            if ($desc === null || (is_string($desc) && (trim($desc) === '' || strtolower(trim($desc)) === 'null'))) {
                $merged['description'] = null;
            } elseif (is_string($desc)) {
                $merged['description'] = trim($desc);
            }
        }

        if ($this->has('ai_model_id')) {
            $modelId = $this->input('ai_model_id');
            if ($modelId === '' || (is_string($modelId) && strtolower(trim($modelId)) === 'null')) {
                $merged['ai_model_id'] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * CHỨC NĂNG: Định nghĩa rule validate đầu vào cho request tạo ảnh AI.
     * INPUT: không.
     * OUTPUT: array<string, array<int, mixed>>.
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'aspect_ratio' => ['required', 'string', Rule::in(self::ASPECT_RATIOS)],
            'ai_model_id' => ['nullable', 'integer', 'exists:ai_models,id'],
            'count' => ['required', 'integer', Rule::in(self::COUNTS)],
            'reference_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,avif,gif,bmp',
                'max:10240',
            ],
        ];
    }

    /**
     * CHỨC NĂNG: Thông báo lỗi validate tiếng Việt rõ nghĩa cho UI dialog.
     * INPUT: không.
     * OUTPUT: array<string, string>.
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Vui lòng nhập prompt mô tả hình ảnh cần tạo.',
            'prompt.max' => 'Prompt không được vượt quá 2000 ký tự.',
            'description.max' => 'Mô tả hình ảnh không được vượt quá 1000 ký tự.',
            'aspect_ratio.required' => 'Vui lòng chọn tỉ lệ khung hình.',
            'aspect_ratio.in' => 'Tỉ lệ khung hình chỉ hỗ trợ 1:1, 4:3, 3:4, 16:9 hoặc 9:16.',
            'ai_model_id.exists' => 'Model AI được chọn không tồn tại trong hệ thống.',
            'count.required' => 'Vui lòng chọn số lượng ảnh cần tạo.',
            'count.in' => 'Số lượng ảnh chỉ hỗ trợ 1, 2 hoặc 4.',
            'reference_image.file' => 'Ảnh tham chiếu tải lên không hợp lệ.',
            'reference_image.image' => 'Tệp ảnh tham chiếu phải là hình ảnh hợp lệ.',
            'reference_image.mimes' => 'Ảnh tham chiếu chỉ hỗ trợ JPG, PNG, WEBP, AVIF, GIF hoặc BMP.',
            'reference_image.max' => 'Ảnh tham chiếu không được vượt quá 10 MB.',
        ];
    }
}
