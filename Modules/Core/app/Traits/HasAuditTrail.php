<?php

namespace Modules\Core\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Trait tự động ghi nhận created_by / updated_by cho Model.
 * =====================================================================
 *
 * Lắp vào các Model nghiệp vụ cần truy vết ai tạo/sửa bản ghi. Tự điền
 * `created_by` khi tạo và `updated_by` khi sửa bằng id user đang đăng nhập
 * (auth()->id()); bỏ qua khi request chạy nền (queue/console, không có user).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - bootHasAuditTrail(): đăng ký observer creating/updating
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : user id từ auth()->id()
 * - OUTPUT: tự điền cột created_by/updated_by nếu model có các cột đó
 * =====================================================================
 */
trait HasAuditTrail
{
    /**
     * =====================================================================
     * CHỨC NĂNG: đăng ký observer tự điền cột audit khi tạo/sửa
     * =====================================================================
     *
     * INPUT: không có tham số (Laravel gọi khi boot trait).
     * OUTPUT: void — gắn callback creating/updating.
     *
     * SIDE EFFECT:
     * - creating: gán created_by & updated_by = auth()->id() nếu cột tồn tại
     * - updating: gán updated_by = auth()->id() nếu cột tồn tại
     * =====================================================================
     */
    public static function bootHasAuditTrail(): void
    {
        $userId = static fn (): ?int => auth()->id();

        static::creating(function (Model $model) use ($userId): void {
            $id = $userId();
            if ($id === null) {
                return;
            }
            if ($model->isFillable('created_by') || array_key_exists('created_by', $model->getAttributes()) === false) {
                $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $id);
            }
            if (array_key_exists('updated_by', $model->getAttributes()) === false || $model->isFillable('updated_by')) {
                $model->setAttribute('updated_by', $model->getAttribute('updated_by') ?? $id);
            }
        });

        static::updating(function (Model $model) use ($userId): void {
            $id = $userId();
            if ($id === null) {
                return;
            }
            $model->setAttribute('updated_by', $id);
        });
    }
}
