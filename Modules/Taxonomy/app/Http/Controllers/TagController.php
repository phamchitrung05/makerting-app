<?php

namespace Modules\Taxonomy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Taxonomy\Http\Requests\TagStoreRequest;
use Modules\Taxonomy\Http\Requests\TagUpdateRequest;
use Modules\Taxonomy\Http\Resources\TagResource;
use Modules\Taxonomy\Models\Tag;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: CRUD Tag + /options.
 * =====================================================================
 * index   -> dataTable (VDataTableServer) lọc theo scope/search/status.
 * options -> danh sách nhẹ {id,name,scope} cho dropdown + AI gợi ý.
 * =====================================================================
 */
class TagController extends Controller
{
    /**
     * GET /api/v1/admin/taxonomy/tags
     * INPUT: Request (scope, search, status, itemsPerPage, page).
     * OUTPUT: BaseResponse::dataTable items=TagResource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('itemsPerPage', $request->integer('per_page', 15));
        $scope = $request->query('scope');
        $search = (string) $request->query('search', '');
        $status = (string) $request->query('status', '');

        $query = Tag::query()
            ->with('slug')
            ->scope($scope)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy('name');

        $paginator = $query->paginate($perPage)->appends($request->query());

        return BaseResponse::dataTable(
            $paginator->setCollection(
                TagResource::collection($paginator->getCollection())->collection
            )
        );
    }

    /**
     * GET /api/v1/admin/taxonomy/tags/options
     * INPUT: Request (scope — lọc phân vùng, mặc định trả mọi scope).
     * OUTPUT: BaseResponse::success mảng {id,name,scope}.
     */
    public function options(Request $request)
    {
        $scope = $request->query('scope');

        $tags = Tag::query()
            ->active()
            ->scope($scope)
            ->orderBy('name')
            ->get(['id', 'scope', 'name'])
            ->map(fn (Tag $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'scope' => $t->scope,
            ])
            ->values();

        return BaseResponse::success($tags);
    }

    /**
     * POST /api/v1/admin/taxonomy/tags
     * INPUT: TagStoreRequest. OUTPUT: BaseResponse::created.
     * SIDE EFFECT: INSERT tags + sync slug (HasSlug).
     */
    public function store(TagStoreRequest $request)
    {
        $tag = Tag::create($request->validated());

        return BaseResponse::created(new TagResource($tag), 'Tạo thẻ thành công');
    }

    /**
     * GET /api/v1/admin/taxonomy/tags/{tag}
     * INPUT: Tag. OUTPUT: BaseResponse::success.
     */
    public function show(Tag $tag)
    {
        return BaseResponse::success(new TagResource($tag));
    }

    /**
     * PUT /api/v1/admin/taxonomy/tags/{tag}
     * INPUT: TagUpdateRequest, Tag. OUTPUT: BaseResponse::success.
     */
    public function update(TagUpdateRequest $request, Tag $tag)
    {
        $tag->update($request->validated());

        return BaseResponse::success(new TagResource($tag->fresh()), 'Cập nhật thẻ thành công');
    }

    /**
     * DELETE /api/v1/admin/taxonomy/tags/{tag}
     * INPUT: Tag. OUTPUT: BaseResponse::noContent.
     * SIDE EFFECT: softDeletes; pivot taggables cascade qua FK.
     */
    public function destroy(Tag $tag)
    {
        $tag->delete();

        return BaseResponse::noContent();
    }
}
