<?php

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload yêu cầu AI gợi ý prompt tạo ảnh.
 * =====================================================================
 *
 * Dùng cho endpoint `POST /api/v1/admin/media/ai-image/suggest-prompt`.
 * Nhận mô tả văn bản (`description`) và/hoặc ảnh tham chiếu (`reference_image`)
 * kèm phong cách (`style`) và tỉ lệ khung hình (`aspect_ratio`) để gửi
 * sang model văn bản/vision của CoreAi tổng hợp prompt tối ưu.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : multipart/form-data hoặc JSON request.
 * - OUTPUT: mảng dữ liệu đã validate qua validated().
 * =====================================================================
 */
class SuggestAiImagePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * CHỨC NĂNG: Chuẩn hóa chuỗi đầu vào trước khi validate.
     * INPUT: không.
     * OUTPUT: void.
     */
    protected function prepareForValidation(): void
    {
        $merged = [];

        foreach (['description', 'style', 'aspect_ratio'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $trimmed = trim($this->input($field));
                $merged[$field] = ($trimmed === '' || strtolower($trimmed) === 'null') ? null : $trimmed;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * CHỨC NĂNG: Rule validate đầu vào gợi ý prompt.
     * INPUT: không.
     * OUTPUT: array<string, array<int, mixed>>.
     */
    public function rules(): array
    {
        $allowedStyles = array_keys((array) config('media.prompt.ai_image_suggest.styles', []));

        return [
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,avif,gif,bmp',
                'max:10240',
            ],
            'style' => ['nullable', 'string', Rule::in($allowedStyles)],
            'aspect_ratio' => ['nullable', 'string', Rule::in(GenerateAiImageRequest::ASPECT_RATIOS)],
        ];
    }

    /**
     * CHỨC NĂNG: Bắt buộc có ít nhất mô tả hình ảnh hoặc ảnh tham chiếu.
     * INPUT: Validator $validator.
     * OUTPUT: void.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $description = $this->input('description');
            $hasDescription = is_string($description) && trim($description) !== '';
            $hasImage = $this->hasFile('reference_image');

            if (! $hasDescription && ! $hasImage) {
                $validator->errors()->add(
                    'description',
                    'Vui lòng nhập mô tả hình ảnh hoặc chọn ảnh tham chiếu để AI gợi ý prompt.'
                );
            }
        });
    }

    /**
     * CHỨC NĂNG: Thông báo lỗi tiếng Việt rõ nghĩa cho dialog.
     * INPUT: không.
     * OUTPUT: array<string, string>.
     */
    public function messages(): array
    {
        return [
            'description.max' => 'Mô tả hình ảnh không được vượt quá 1000 ký tự.',
            'reference_image.file' => 'Ảnh tham chiếu tải lên không hợp lệ.',
            'reference_image.image' => 'Tệp ảnh tham chiếu phải là hình ảnh hợp lệ.',
            'reference_image.mimes' => 'Ảnh tham chiếu chỉ hỗ trợ JPG, PNG, WEBP, AVIF, GIF hoặc BMP.',
            'reference_image.max' => 'Ảnh tham chiếu không được vượt quá 10 MB.',
            'style.in' => 'Phong cách hình ảnh không hợp lệ.',
            'aspect_ratio.in' => 'Tỉ lệ khung hình không hợp lệ.',
        ];
    }
}
