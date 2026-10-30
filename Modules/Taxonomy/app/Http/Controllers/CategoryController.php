<?php

namespace Modules\Taxonomy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\Taxonomy\Http\Requests\CategoryStoreRequest;
use Modules\Taxonomy\Http\Requests\CategoryUpdateRequest;
use Modules\Taxonomy\Http\Resources\CategoryResource;
use Modules\Taxonomy\Models\Category;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: CRUD Category + /options + /tree.
 * =====================================================================
 * index   -> dataTable (VDataTableServer) lọc theo scope/search.
 * options -> danh sách nhẹ {id,name,scope,parent_id} cho dropdown.
 * tree    -> cây cha–con (nested children) cho UI dạng tree.
 * =====================================================================
 */
class CategoryController extends Controller
{
    /**
     * GET /api/v1/admin/taxonomy/categories
     * INPUT: Request (scope, search, status, itemsPerPage, page).
     * OUTPUT: BaseResponse::dataTable items=CategoryResource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('itemsPerPage', $request->integer('per_page', 15));
        $scope = $request->query('scope');
        $search = (string) $request->query('search', '');
        $status = (string) $request->query('status', '');

        $query = Category::query()
            ->with(['slug', 'parent', 'parent.slug'])
            ->scope($scope)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderBy('sort_order')->orderBy('name');

        $paginator = $query->paginate($perPage)->appends($request->query());

        return BaseResponse::dataTable(
            $paginator->setCollection(
                CategoryResource::collection($paginator->getCollection())->collection
            )
        );
    }

    /**
     * GET /api/v1/admin/taxonomy/categories/options
     * INPUT: Request (scope — lọc phân vùng, mặc định trả mọi scope).
     * OUTPUT: BaseResponse::success mảng {id,name,scope,parent_id}.
     */
    public function options(Request $request)
    {
        $scope = $request->query('scope');

        $categories = Category::query()
            ->active()
            ->scope($scope)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'parent_id', 'scope', 'name'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'scope' => $c->scope,
                'parent_id' => $c->parent_id,
            ])
            ->values();

        return BaseResponse::success($categories);
    }

    /**
     * GET /api/v1/admin/taxonomy/categories/tree
     * INPUT: Request (scope — 'any'|null = không lọc, string = phân vùng).
     * OUTPUT: BaseResponse::success cây cha–con nested children.
     */
    public function tree(Request $request)
    {
        $scope = $request->query('scope');

        $roots = Category::query()
            ->whereNull('parent_id')
            ->scope($scope)
            ->with(['slug', 'descendants'])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return BaseResponse::success(CategoryResource::collection($roots));
    }

    /**
     * POST /api/v1/admin/taxonomy/categories
     * INPUT: CategoryStoreRequest. OUTPUT: BaseResponse::created.
     * SIDE EFFECT: INSERT categories + sync slug (HasSlug).
     */
    public function store(CategoryStoreRequest $request)
    {
        $category = Category::create($request->validated());

        return BaseResponse::created(
            new CategoryResource($category->load('parent')),
            'Tạo danh mục thành công',
        );
    }

    /**
     * GET /api/v1/admin/taxonomy/categories/{category}
     * INPUT: Category. OUTPUT: BaseResponse::success.
     */
    public function show(Category $category)
    {
        $category->load(['parent', 'descendants']);

        return BaseResponse::success(new CategoryResource($category));
    }

    /**
     * PUT /api/v1/admin/taxonomy/categories/{category}
     * INPUT: CategoryUpdateRequest, Category. OUTPUT: BaseResponse::success.
     * SIDE EFFECT: UPDATE categories; kiểm tra vòng parent trong FormRequest.
     */
    public function update(CategoryUpdateRequest $request, Category $category)
    {
        $category->update($request->validated());

        return BaseResponse::success(
            new CategoryResource($category->fresh()->load('parent')),
            'Cập nhật danh mục thành công',
        );
    }

    /**
     * DELETE /api/v1/admin/taxonomy/categories/{category}
     * INPUT: Category. OUTPUT: BaseResponse::noContent.
     * SIDE EFFECT: softDeletes cha + reparent con lên ông (hoặc về gốc)
     * để không bị mồ côi trên /categories/tree; pivot categorizables của cha
     * cascade qua FK khi hard-delete (soft-delete giữ pivot là chủ đích).
     */
    public function destroy(Category $category)
    {
        // Con của danh mục bị xóa mềm trỏ lên cha của nó (null = về gốc).
        // SoftDeletes không kích hoạt nullOnDelete() của FK MySQL, nên phải
        // reparent thủ công trước khi xóa mềm.
        Category::where('parent_id', $category->id)
            ->update(['parent_id' => $category->parent_id]);

        $category->delete();

        return BaseResponse::noContent();
    }
}
