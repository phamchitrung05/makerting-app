<?php

namespace Modules\WritingStyle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\WritingStyle\DTOs\WritingBrief;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate đầu vào một bước pipeline và dữ liệu phụ thuộc từ client.
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
 *   - brief: yêu cầu nội dung mới; outline_approved: cờ xác nhận đã duyệt dàn ý.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - authorize(): Cho phép FormRequest tiếp tục sau middleware xác thực và phân quyền.
 * - rules(): Khai báo kiểu dữ liệu, giới hạn và validation cho payload request.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Topic/brief, tier, artifacts, cờ duyệt và runtime overrides.
 * - OUTPUT: validated() cung cấp dữ liệu đã kiểm tra cấu trúc và giới hạn.
 *
 * BỐI CẢNH SỬ DỤNG: Runner kiểm tra sâu nội dung artifacts và fingerprint sau FormRequest.
 * =====================================================================
 */
class RunSandboxStepRequest extends FormRequest
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Cho phép FormRequest tiếp tục; quyền cụ thể được kiểm tra qua middleware route
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool true; xác thực và permission do route xử lý.
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo tập validation cho các trường đầu vào của request
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array theo hợp đồng của method.
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function rules(): array
    {
        return [
            ...WritingBrief::rules(),
            'topic'            => ['required_without:brief.topic', 'nullable', 'string', 'min:3', 'max:2000'],
            'target_tier'      => ['nullable', 'in:micro,short,medium,long'],
            'target_sentences' => ['nullable', 'integer', 'min:1', 'max:200'],
            'temperature'      => ['nullable', 'numeric', 'min:0', 'max:2'],

            // Hiệu chỉnh tone runtime — 8 trục theo config('writingstyle.v3.axes').
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
