<?php

namespace Modules\WritingStyle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\WritingStyle\DTOs\WritingBrief;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate đầu vào viết thử trực tiếp, bao gồm brief và runtime overrides.
 * =====================================================================
 * Validate payload DNA Sandbox (topic + target_tier + runtime overrides).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - authorize(): Cho phép FormRequest tiếp tục sau middleware xác thực và phân quyền.
 * - rules(): Khai báo kiểu dữ liệu, giới hạn và validation cho payload request.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : HTTP payload; topic có thể lấy từ brief.topic.
 * - OUTPUT: validated() cung cấp payload theo rules.
 *
 * BỐI CẢNH SỬ DỤNG: Authorize cấp ứng dụng do auth:sanctum và writing.evaluate middleware xử lý.
 * =====================================================================
 */
class SandboxRequest extends FormRequest
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
        $tempMin = config('writingstyle.profile.temperature.min', 0);
        $tempMax = config('writingstyle.profile.temperature.max', 2);
        $styleMin = (int) config('writingstyle.profile.instructions.style_min', 1);
        $styleMax = (int) config('writingstyle.profile.instructions.style_max', 10000);
        $voiceTextureMax = (int) config('writingstyle.profile.instructions.voice_texture_max', 10000);
        $toneMax = (int) config('writingstyle.profile.instructions.tone_max', 10000);

        return [
            ...WritingBrief::rules(),
            ...WritingBrief::requestRules(['required_without:brief.topic', 'nullable']),
            'target_tier'      => ['nullable', 'in:micro,short,medium,long'],
            'temperature'      => ['nullable', 'numeric', "min:{$tempMin}", "max:{$tempMax}"],

            // Hiệu chỉnh tone runtime — 8 trục theo config('writingstyle.v3.axes').
            // Chỉ nhận key hợp lệ + giá trị 0..100; không bắt buộc đủ 8.
            'tone_overrides'            => ['nullable', 'array'],
            'tone_overrides.*'          => ['nullable', 'integer', 'min:0', 'max:100'],

            // Ghi đè style_instructions (Ô 1) khi cần (FE muốn Sandbox thấy NGAY, không bắt Lưu trước).
            'style_instructions'        => ['nullable', 'string', "min:{$styleMin}", "max:{$styleMax}"],

            // Ghi đè voice_texture_instructions (Ô 2) và 5 thanh trượt Chất văn runtime.
            'voice_texture_instructions'        => ['nullable', 'string', "max:{$voiceTextureMax}"],
            'tone_instructions'                 => ['nullable', 'string', "max:{$toneMax}"],
            'voice_texture_sliders_json'        => ['nullable', 'array'],
            'voice_texture_sliders_json.*'      => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
