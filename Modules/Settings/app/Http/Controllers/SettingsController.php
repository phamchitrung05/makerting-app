<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Settings\Http\Requests\UpdateSettingsRequest;
use Modules\Settings\Services\ModularSettingsService;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Controller điều phối HTTP của module Settings.
 * =====================================================================
 *
 * Chuẩn luồng: Route → permission middleware → FormRequest → Controller
 * → ModularSettingsService → BaseResponse. Controller không chứa business
 * rule, chỉ gọi service và bọc kết quả vào envelope chuẩn.
 *
 * Endpoint:
 * - GET    /api/v1/admin/settings          -> index()  liệt kê group + meta.
 * - GET    /api/v1/admin/settings/{group}  -> show()   payload (che secret).
 * - PUT    /api/v1/admin/settings/{group}  -> update() khóa version.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - index()  : trả describe() các group đã đăng ký.
 * - show()   : trả get() một group (values đã mask secret + version).
 * - update() : trả update() một group; 409 khi version lệch; 404 khi group lạ.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Request/UpdateSettingsRequest + route param {group}.
 * - OUTPUT: JsonResponse BaseResponse {success,message,data,errors,meta}.
 * =====================================================================
 */
class SettingsController extends Controller
{
    public function __construct(private readonly ModularSettingsService $service) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: GET /api/v1/admin/settings — danh sách group + metadata
     * =====================================================================
     * INPUT: không.
     * OUTPUT: BaseResponse::success {groups:[{group,permission,secrets,defaults}]}.
     * SIDE EFFECT: không.
     * =====================================================================
     */
    public function index(): JsonResponse
    {
        return BaseResponse::success(
            ['groups' => $this->service->describe()],
            'Lấy danh sách nhóm cài đặt thành công',
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: GET /api/v1/admin/settings/{group} — payload một group
     * =====================================================================
     * INPUT: route param {group}.
     * OUTPUT: BaseResponse::success {group,version,values,secrets};
     *         404 khi group chưa đăng ký.
     * SIDE EFFECT: không.
     * =====================================================================
     */
    public function show(string $group): JsonResponse
    {
        try {
            return BaseResponse::success(
                $this->service->get($group),
                'Lấy cấu hình thành công',
            );
        } catch (InvalidArgumentException $e) {
            return BaseResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: PUT /api/v1/admin/settings/{group} — cập nhật có khóa version
     * =====================================================================
     * INPUT: UpdateSettingsRequest (expected_version + values).
     * OUTPUT:
     * - 200 {group,version,values,secrets} khi ghi thành công;
     * - 409 JsonResponse trả thẳng từ service khi version lệch;
     * - 404 khi group chưa đăng ký.
     * SIDE EFFECT: ghi DB (xem service); trả response 409 trực tiếp khi
     * service trả JsonResponse thay vì array.
     * =====================================================================
     */
    public function update(UpdateSettingsRequest $request, string $group): JsonResponse
    {
        try {
            $result = $this->service->update(
                $group,
                $request->valuesPayload(),
                $request->expectedVersion(),
            );

            // Service trả thẳng JsonResponse 409 khi version lệch.
            if ($result instanceof JsonResponse) {
                return $result;
            }

            return BaseResponse::success($result, 'Cập nhật cấu hình thành công');
        } catch (InvalidArgumentException $e) {
            return BaseResponse::error($e->getMessage(), 404);
        }
    }
}
