<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Settings\Registries\SettingsRegistry;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Validate payload PUT cập nhật một nhóm cài đặt.
 * =====================================================================
 *
 * - Bắt buộc 'expected_version' integer để khóa lạc quan (409 nếu lệch).
 * - Merge rule từ class Settings (validationRules()) cho phần values.
 * - 'values' phải là mảng; key lạ bị bỏ qua (không báo lỗi cứng).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - authorize()         : cho phép (middleware permission lo phân quyền).
 * - rules()             : rule động theo group route param.
 * - expectedVersion()   : lấy expected_version đã cast int.
 * - groupClass()        : resolve class Settings của group.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : HTTP PUT /api/v1/admin/settings/{group} body {expected_version, values}.
 * - OUTPUT: validated data cho controller.
 * =====================================================================
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * CHỨC NĂNG: Cho phép request — phân quyền đã có middleware
     * 'permission:settings.{group}.manage' xử lý trước.
     * INPUT: không. OUTPUT: bool true. SIDE EFFECT: không.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Rule validate động theo group trên route
     * =====================================================================
     * INPUT: route param {group}.
     * OUTPUT: array rule — expected_version + values.* theo validationRules().
     * SIDE EFFECT: không.
     * =====================================================================
     */
    public function rules(): array
    {
        $rules = [
            'expected_version' => ['required', 'integer', 'min:0'],
            'values' => ['required', 'array'],
        ];

        $class = $this->groupClass();

        if ($class !== null) {
            foreach ($class::validationRules() as $key => $rule) {
                $rules["values.{$key}"] = $rule;
            }
        }

        return $rules;
    }

    /**
     * CHỨC NĂNG: Lấy expected_version dưới dạng int cho service so khớp.
     * INPUT: validated body. OUTPUT: int. SIDE EFFECT: không.
     */
    public function expectedVersion(): int
    {
        return (int) $this->input('expected_version', 0);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy mảng values đã validated (chỉ giữ key khai báo trong
     * validationRules() của class Settings — chặn key lạ)
     * =====================================================================
     * - Chặn key trùng tên property nội bộ của Spatie Settings (config,
     *   mapper, loaded, originalValues, version...) làm fill() ném Error.
     * - Chặn key rác client gửi kèm (mail_password_configured, hack_key...)
     *   chỉ giữ các property Settings khai báo trong validationRules().
     * INPUT: validated body. OUTPUT: array — chỉ key hợp lệ.
     * SIDE EFFECT: không.
     * =====================================================================
     */
    public function valuesPayload(): array
    {
        $values = (array) $this->input('values', []);
        $class = $this->groupClass();

        if ($class === null) {
            return $values; // Controller sẽ 404 group chưa đăng ký.
        }

        // Chỉ giữ các key Settings class khai báo trong validationRules().
        $allowed = array_keys($class::validationRules());

        return array_intersect_key($values, array_flip($allowed));
    }

    /**
     * CHỨC NĂNG: Resolve class Settings của {group} từ route qua registry;
     * trả null nếu group chưa đăng ký (controller sẽ 404).
     * INPUT: route param. OUTPUT: class-string|null. SIDE EFFECT: không.
     */
    private function groupClass(): ?string
    {
        $group = (string) $this->route('group');

        if ($group === '') {
            return null;
        }

        return app(SettingsRegistry::class)->resolve($group);
    }
}
