<?php

namespace Modules\Settings\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Settings\Registries\SettingsRegistry;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Middleware phân quyền động cho route /settings/{group}.
 * =====================================================================
 *
 * Vì Spatie 'permission:xxx' nhận chuỗi tĩnh, không resolve {group} trên
 * route, middleware này đọc route param {group} + tiền tố permission()
 * của class Settings tương ứng để suy ra quyền cần có:
 *   ability 'view'   -> '{permission}.view'
 *   ability 'manage' -> '{permission}.manage'
 * Super-admin vẫn qua nhờ Gate::before trong IdentityServiceProvider.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - handle($request, $next, $ability) : kiểm tra quyền theo group.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Request có route param {group} + arg $ability.
 * - OUTPUT: tiếp tục pipeline hoặc 403/404.
 * =====================================================================
 */
class ResolveSettingsPermission
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra user có quyền '{permission}.{ability}' của group
     * =====================================================================
     * INPUT:
     * - $request : HTTP request có route param {group}.
     * - $next    : middleware kế tiếp.
     * - $ability : 'view' | 'manage' (mặc định 'view').
     *
     * OUTPUT: Response của pipeline hoặc ném 404/403.
     *
     * SIDE EFFECT: không (chỉ đọc quyền user).
     * EXCEPTION:
     * - UnauthorizedException 404 khi group chưa đăng ký;
     * - 403 khi user thiếu quyền tương ứng.
     * =====================================================================
     */
    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $user = $request->user();

        if (! $user) {
            throw UnauthorizedException::notLoggedIn();
        }

        $group = (string) $request->route('group');
        $class = $this->registry->resolve($group);

        if ($class === null) {
            abort(404, "Nhóm cài đặt [{$group}] không tồn tại.");
        }

        $permission = $class::permission() . '.' . $ability;

        if (! $user->can($permission)) {
            throw UnauthorizedException::forPermissions([$permission]);
        }

        return $next($request);
    }
}
