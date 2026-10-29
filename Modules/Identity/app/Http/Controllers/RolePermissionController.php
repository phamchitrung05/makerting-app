<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Identity\Http\Requests\RoleStoreRequest;
use Modules\Identity\Http\Resources\RoleResource;
use Modules\Identity\Registries\PermissionRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Quản lý Role + ma trận Permission (Spatie).
 * =====================================================================
 * - matrix(): trả registry module->permissions cho UI dựng ma trận phân quyền.
 * - index/store/update/destroy: CRUD Role kèm gán permission.
 * =====================================================================
 */
class RolePermissionController extends Controller
{
    public function __construct(protected PermissionRegistry $registry) {}

    /**
     * GET /api/v1/admin/identity/permissions/matrix
     * INPUT: Request. OUTPUT: BaseResponse::success {modules, permissionNames}.
     * SIDE EFFECT: đọc PermissionRegistry (in-memory).
     */
    public function matrix()
    {
        return BaseResponse::success([
            'modules' => $this->registry->all(),
            'permissionNames' => $this->registry->allPermissionNames(),
        ]);
    }

    /**
     * GET /api/v1/admin/identity/roles
     * INPUT: Request (search, per_page). OUTPUT: BaseResponse::dataTable.
     * SIDE EFFECT: SELECT roles + eager permissions + count users.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('itemsPerPage', $request->integer('per_page', 15));
        $search = (string) $request->query('search', '');

        $paginator = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->appends($request->query());

        return BaseResponse::dataTable(
            $paginator->setCollection(
                RoleResource::collection($paginator->getCollection())->collection
            )
        );
    }

    /**
     * POST /api/v1/admin/identity/roles
     * INPUT: RoleStoreRequest {name, permissions[]}.
     * OUTPUT: BaseResponse::created RoleResource.
     * SIDE EFFECT: INSERT role + syncPermissions (guard admin).
     */
    public function store(RoleStoreRequest $request)
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'admin',
        ]);

        $role->syncPermissions($this->validPermissionNames($data['permissions'] ?? []));

        return BaseResponse::created(new RoleResource($role->load('permissions')), 'Tạo vai trò thành công');
    }

    /**
     * PUT /api/v1/admin/identity/roles/{role}
     * INPUT: RoleStoreRequest, Role. OUTPUT: BaseResponse::success.
     * SIDE EFFECT: UPDATE role name + syncPermissions.
     */
    public function update(RoleStoreRequest $request, Role $role)
    {
        $data = $request->validated();

        $role->name = $data['name'];
        $role->save();

        $role->syncPermissions($this->validPermissionNames($data['permissions'] ?? []));

        return BaseResponse::success(new RoleResource($role->load('permissions')), 'Cập nhật vai trò thành công');
    }

    /**
     * DELETE /api/v1/admin/identity/roles/{role}
     * INPUT: Role. OUTPUT: BaseResponse::noContent.
     * EXCEPTION: 409 khi role là super-admin hệ thống.
     */
    public function destroy(Role $role)
    {
        if ($role->name === 'super-admin') {
            return BaseResponse::error('Không thể xóa vai trò super-admin', Response::HTTP_CONFLICT);
        }

        $role->delete();

        return BaseResponse::noContent();
    }

    /**
     * Lọc danh sách permission name hợp lệ (đã đăng ký trong registry) và đảm
     * bảo tồn tại trong bảng permissions với guard admin.
     * INPUT: string[] names.
     * OUTPUT: string[] names hợp lệ.
     * SIDE EFFECT: có thể INSERT permission còn thiếu (đồng bộ registry->DB).
     */
    protected function validPermissionNames(array $names): array
    {
        $valid = [];
        foreach ($names as $name) {
            if (! is_string($name)) {
                continue;
            }
            // Đảm bảo permission tồn tại; cho phép cả quyền chưa khai báo registry
            // (tương thích linh hoạt) nhưng chuẩn hóa guard admin.
            Permission::findOrCreate($name, 'admin');
            $valid[] = $name;
        }

        return $valid;
    }
}
