<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Core\Contracts\SlugServiceContract;
use Modules\Core\Http\Requests\SlugPreviewRequest;
use Modules\Core\Http\Responses\BaseResponse;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Endpoint preview slug — trả slug khả dụng trước khi lưu.
 * =====================================================================
 * Cho phép frontend gọi khi admin nhập tiêu đề để xem trước slug sẽ được tạo
 * (real-time). Chỉ đọc, không ghi DB.
 * =====================================================================
 */
class SlugPreviewController extends Controller
{
    public function __construct(protected SlugServiceContract $slugs) {}

    /**
     * GET /api/v1/admin/core/slug-preview?source=...&sluggable_type=...
     * INPUT: SlugPreviewRequest (source, sluggable_type, locale).
     * OUTPUT: BaseResponse::success { slug }.
     * SIDE EFFECT: không có.
     */
    public function show(SlugPreviewRequest $request)
    {
        $data = $request->validated();

        $slug = $this->slugs->preview(
            $data['source'],
            $data['sluggable_type'],
            $data['locale'] ?? null,
        );

        return BaseResponse::success(['slug' => $slug], 'Sinh slug thành công');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gợi ý slug cho field nhập trên UI — trả đủ trạng thái trùng.
     * =====================================================================
     *
     * POST /api/v1/admin/core/slug-suggest
     * INPUT : SlugPreviewRequest (source, sluggable_type, sluggable_id, locale).
     * OUTPUT: BaseResponse::success {slug, available, taken_by} —
     *   slug      = chuỗi sẽ dùng (đã suffix -1,-2 nếu base đã chiếm),
     *   available = base slugify(source) còn trống hay không,
     *   taken_by  = base đang bị record khác chiếm (khi available=false).
     * SIDE EFFECT: chỉ đọc, không ghi DB.
     * =====================================================================
     */
    public function suggest(SlugPreviewRequest $request)
    {
        $data = $request->validated();

        $result = $this->slugs->suggest(
            $data['source'],
            $data['sluggable_type'],
            $data['sluggable_id'] ?? null,
            $data['locale'] ?? null,
        );

        return BaseResponse::success($result, 'Sinh slug thành công');
    }
}
