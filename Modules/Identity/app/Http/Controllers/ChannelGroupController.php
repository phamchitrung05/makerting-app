<?php

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Identity\Actions\SaveChannelGroupAction;
use Modules\Identity\Http\Requests\ChannelGroupStoreRequest;
use Modules\Identity\Http\Requests\ChannelGroupUpdateRequest;
use Modules\Identity\Http\Resources\ChannelGroupResource;
use Modules\Identity\Models\ChannelGroup;
use Modules\Identity\Services\ChannelGroupContextService;
use Symfony\Component\HttpFoundation\Response;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: CRUD ChannelGroup + endpoint /options cho dropdown.
 * =====================================================================
 * index  -> dataTable (VDataTableServer)
 * options-> danh sách nhẹ cho dropdown (id,name,code,color)
 * store/update/destroy qua SaveChannelGroupAction trong transaction.
 * =====================================================================
 */
class ChannelGroupController extends Controller
{
    public function __construct(
        protected ChannelGroupContextService $context,
    ) {}

    /**
     * GET /api/v1/admin/identity/channel-groups
     * INPUT: Request (search, per_page, page).
     * OUTPUT: BaseResponse::dataTable items=ChannelGroupResource.
     * SIDE EFFECT: SELECT channel_groups phân trang + search + eager members.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('itemsPerPage', $request->integer('per_page', 15));
        $search = (string) $request->query('search', '');

        // Chỉ trả các ChannelGroup user được phép — idsForUser trả null
        // khi user là super-admin/quản lý toàn bộ (không áp whereIn).
        $allowedIds = $this->context->idsForUser($request->user());

        $query = ChannelGroup::query()
            ->withCount('members')
            ->with('members')
            ->when($allowedIds !== null, fn ($q) => $q->whereIn('id', $allowedIds))
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            }))
            ->orderBy('name');

        $paginator = $query->paginate($perPage)->appends($request->query());

        return BaseResponse::dataTable(
            $paginator->setCollection(
                ChannelGroupResource::collection($paginator->getCollection())->collection
            )
        );
    }

    /**
     * GET /api/v1/admin/identity/channel-groups/options
     * INPUT: Request. OUTPUT: BaseResponse::success mảng {id,name,code,color}.
     * SIDE EFFECT: SELECT nhẹ theo phạm vi user.
     */
    public function options(Request $request)
    {
        $groups = $this->context->forUser($request->user())
            ->map(fn (ChannelGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'code' => $g->code,
                'color' => $g->color,
            ])
            ->values();

        return BaseResponse::success($groups);
    }

    /**
     * POST /api/v1/admin/identity/channel-groups
     * INPUT: ChannelGroupStoreRequest. OUTPUT: BaseResponse::created.
     * SIDE EFFECT: INSERT channel_groups + sync members (transaction).
     */
    public function store(ChannelGroupStoreRequest $request, SaveChannelGroupAction $action)
    {
        $group = $action->handle($request->validated(), null, $request->user()?->id);

        return BaseResponse::created(new ChannelGroupResource($group), 'Tạo nhóm kênh thành công');
    }

    /**
     * GET /api/v1/admin/identity/channel-groups/{channel_group}
     * INPUT: ChannelGroup. OUTPUT: BaseResponse::success.
     * SIDE EFFECT: kiểm tra user có quyền vào nhóm này không (403 nếu không).
     */
    public function show(Request $request, ChannelGroup $channelGroup)
    {
        if (! $this->context->canAccess($request->user(), $channelGroup->id)) {
            return BaseResponse::error('Không có quyền xem nhóm kênh này', Response::HTTP_FORBIDDEN);
        }

        $channelGroup->load('members');

        return BaseResponse::success(new ChannelGroupResource($channelGroup));
    }

    /**
     * PUT /api/v1/admin/identity/channel-groups/{channel_group}
     * INPUT: ChannelGroupUpdateRequest, ChannelGroup. OUTPUT: BaseResponse::success.
     * SIDE EFFECT: UPDATE channel_groups + sync members (transaction).
     */
    public function update(ChannelGroupUpdateRequest $request, ChannelGroup $channelGroup, SaveChannelGroupAction $action)
    {
        $group = $action->handle($request->validated(), $channelGroup, $request->user()?->id);

        return BaseResponse::success(new ChannelGroupResource($group), 'Cập nhật nhóm kênh thành công');
    }

    /**
     * DELETE /api/v1/admin/identity/channel-groups/{channel_group}
     * INPUT: ChannelGroup. OUTPUT: BaseResponse::noContent.
     * SIDE EFFECT: DELETE channel_groups (pivot cascade).
     */
    public function destroy(ChannelGroup $channelGroup)
    {
        $channelGroup->delete();

        return BaseResponse::noContent();
    }
}
