<?php

namespace Modules\Settings\Contracts;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng cấu hình typed mà mọi Settings class của các
 * module phải implement để được SettingsRegistry nhận diện và trang Admin
 * Settings tự sinh tab.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - group()              : khóa nhóm lưu trong bảng settings (vd 'site').
 * - defaults()           : giá trị mặc định khi chưa có bản ghi DB.
 * - validationRules()    : rule Laravel validate payload update.
 * - secretProperties()   : danh sách property bí mật -> che thành *_configured.
 * - permission()         : tiền tố permission -> '{p}.view' / '{p}.manage'.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : static calls từ SettingsRegistry/ModularSettingsService.
 * - OUTPUT: metadata mô tả một nhóm cấu hình.
 * =====================================================================
 */
interface ModularSettingsContract
{
    /**
     * Khóa nhóm (group) lưu trong bảng settings, phải duy nhất toàn hệ thống.
     * Dùng làm {group} trên route /api/v1/admin/settings/{group} và tab UI.
     *
     * @return string vd 'site', 'security', 'mail'...
     */
    public static function group(): string;

    /**
     * Giá trị mặc định của toàn bộ property (trừ 'version' — hệ thống quản).
     *
     * @return array<string,mixed> map property => giá trị mặc định.
     */
    public static function defaults(): array;

    /**
     * Rule validate khi PUT cập nhật (không gồm 'version').
     *
     * @return array<string, mixed> map property => rule Laravel.
     */
    public static function validationRules(): array;

    /**
     * Danh sách property bí mật. Trả API chỉ gửi cờ '{name}_configured';
     * update bỏ trống thì giữ nguyên giá trị cũ trong DB.
     *
     * @return array<int,string> vd ['mail_password'].
     */
    public static function secretProperties(): array;

    /**
     * Tiền tố permission: sinh '{permission}.view' và '{permission}.manage'.
     *
     * @return string vd 'settings.site'.
     */
    public static function permission(): string;
}
