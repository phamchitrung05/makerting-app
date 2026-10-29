<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Đổi mật khẩu của tài khoản admin nội bộ.
 * =====================================================================
 * Kiểm chứng mật khẩu hiện tại trước khi cập nhật mật khẩu mới và thu hồi
 * toàn bộ Sanctum token khác (đăng xuất thiết bị cũ) trừ token hiện tại.
 * =====================================================================
 */
class UpdateAdminPasswordAction
{
    /**
     * Đổi mật khẩu user.
     * INPUT:
     * - $user            User đang đăng nhập
     * - $currentPassword string mật khẩu hiện tại (bắt buộc kiểm chứng)
     * - $newPassword     string mật khẩu mới (đã validate ở FormRequest)
     * OUTPUT: void.
     * SIDE EFFECT: UPDATE password (hashed), xóa Sanctum token cũ trừ hiện tại.
     * EXCEPTION: ValidationException 422 khi mật khẩu hiện tại sai.
     */
    public function handle(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không đúng'],
            ]);
        }

        $user->password = $newPassword; // cast 'hashed' tự băm
        $user->save();

        // Thu hồi mọi token khác trừ token đang dùng (đăng xuất thiết bị cũ).
        $currentToken = $user->currentAccessToken();
        $user->tokens()
            ->when($currentToken, fn ($q) => $q->where('id', '!=', $currentToken->id))
            ->delete();
    }
}
