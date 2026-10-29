<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Identity\Actions\UpdateAdminPasswordAction;
use Modules\Identity\Http\Requests\LoginRequest;
use Modules\Identity\Http\Requests\UpdatePasswordRequest;
use Modules\Identity\Http\Resources\UserResource;
use Modules\Identity\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Xác thực admin nội bộ qua Sanctum (guard admin).
 * =====================================================================
 * Cung cấp login/logout/me/đổi mật khẩu. Login trả về đúng contract Vuexy
 * mong đợi: { accessToken, userData, userAbilityRules }.
 * =====================================================================
 */
class AuthController extends Controller
{
    /**
     * POST /api/v1/admin/identity/auth/login
     * INPUT: LoginRequest {email,password,remember}.
     * OUTPUT: BaseResponse::success {accessToken,userData,userAbilityRules}.
     * EXCEPTION: BaseResponse::error 422 khi sai thông tin đăng nhập.
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        /** @var User|null $user */
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return BaseResponse::validation(
                ['email' => ['Email hoặc mật khẩu không đúng']],
                'Đăng nhập thất bại'
            );
        }

        $user->load(['roles', 'permissions']);

        $token = $user->createToken('admin', ['*'])->plainTextToken;

        $userData = (new UserResource($user))->toArray($request);
        $userData['username'] = $user->email;

        return BaseResponse::success([
            'accessToken' => $token,
            'userData' => $userData,
            'userAbilityRules' => $user->abilityRules(),
        ], 'Đăng nhập thành công');
    }

    /**
     * POST /api/v1/admin/identity/auth/logout
     * INPUT: Request (Bearer token). OUTPUT: BaseResponse::success.
     * SIDE EFFECT: thu hồi Sanctum token hiện tại.
     */
    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return BaseResponse::success([], 'Đã đăng xuất');
    }

    /**
     * GET /api/v1/admin/identity/auth/me
     * INPUT: Request. OUTPUT: BaseResponse::success {userData,userAbilityRules}.
     */
    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['roles', 'permissions']);

        return BaseResponse::success([
            'userData' => (new UserResource($user))->toArray($request),
            'userAbilityRules' => $user->abilityRules(),
        ]);
    }

    /**
     * PUT /api/v1/admin/identity/auth/password
     * INPUT: UpdatePasswordRequest {current_password,password,password_confirmation}.
     * OUTPUT: BaseResponse::success.
     * SIDE EFFECT: đổi mật khẩu + thu hồi token khác.
     */
    public function updatePassword(UpdatePasswordRequest $request, UpdateAdminPasswordAction $action)
    {
        $data = $request->validated();
        $action->handle($request->user(), $data['current_password'], $data['password']);

        return BaseResponse::success([], 'Đổi mật khẩu thành công');
    }
}
