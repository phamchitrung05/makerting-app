<?php

namespace Modules\WritingStyle\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\WritingStyle\Http\Requests\StoreWritingProfileRequest;
use Modules\WritingStyle\Http\Resources\WritingProfileResource;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Services\WritingProfileDefinition;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Controller quản lý CRUD hồ sơ văn phong (WritingProfile).
 * =====================================================================
 *
 * Cung cấp các endpoint RESTful trong nhóm /v1/admin/writing-style/writing-profiles:
 * - GET    /writing-profiles          : Danh sách phân trang kèm bộ lọc
 * - GET    /writing-profiles/options  : 4 tiers và 16 câu hỏi giải mã DNA
 * - POST   /writing-profiles          : Tạo mới hồ sơ thủ công (origin=manual)
 * - GET    /writing-profiles/{id}     : Chi tiết một hồ sơ kèm taxonomy
 * - PUT    /writing-profiles/{id}     : Cập nhật thông tin hồ sơ và đồng bộ taxonomy
 * - DELETE /writing-profiles/{id}     : Xóa mềm hồ sơ
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - index(Request $request): Trả danh sách hồ sơ dạng dataTable
 * - options(): Trả danh sách 4 phân tầng độ dài và 16 câu hỏi giải mã
 * - store(StoreWritingProfileRequest $request): Tạo hồ sơ mới và đồng bộ taxonomy
 * - show(WritingProfile $writingProfile): Xem chi tiết hồ sơ
 * - update(StoreWritingProfileRequest $request, WritingProfile $writingProfile): Cập nhật hồ sơ
 * - destroy(WritingProfile $writingProfile): Xóa hồ sơ
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : StoreWritingProfileRequest, Request, WritingProfile route binding
 * - OUTPUT: BaseResponse (JSON)
 * =====================================================================
 */
class WritingProfileController extends Controller
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy danh sách hồ sơ văn phong phân trang kèm bộ lọc
     * =====================================================================
     *
     * INPUT:
     * - Request $request: Chứa query params search, tier, origin, status, per_page
     *
     * OUTPUT:
     * - \Illuminate\Http\JsonResponse: BaseResponse::dataTable phân trang
     *
     * SIDE EFFECT:
     * - Truy vấn bảng writing_profiles kèm quan hệ categories và tags
     *
     * EXCEPTION/TRANSACTION:
     * - Không có
     * =====================================================================
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $search = trim((string) $request->query('search', ''));

        $items = WritingProfile::query()
            ->with(['categories', 'tags'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->filled('tier'), fn ($q) => $q->where('dominant_tier', $request->query('tier')))
            ->when($request->filled('origin'), fn ($q) => $q->where('origin', $request->query('origin')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('id')
            ->paginate($perPage);

        return BaseResponse::dataTable($items);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy danh mục 4 phân tầng độ dài và 16 câu hỏi bóc tách DNA
     * =====================================================================
     *
     * INPUT:
     * - Không có
     *
     * OUTPUT:
     * - \Illuminate\Http\JsonResponse: 200 BaseResponse::success({ tiers, rules })
     *
     * SIDE EFFECT:
     * - Đọc cấu hình từ config/writingstyle.php
     *
     * EXCEPTION/TRANSACTION:
     * - Không có
     * =====================================================================
     */
    public function options()
    {
        $tiers = [];
        foreach (WritingProfile::TIERS as $t) {
            $tiers[] = [
                'value'         => $t,
                'label'         => $t,
                'sentence_hint' => WritingProfileDefinition::tierLabel($t),
            ];
        }

        $rules = [];
        foreach (WritingProfileDefinition::ruleKeys() as $k) {
            $rules[] = ['key' => $k, 'question' => WritingProfileDefinition::ruleQuestion($k)];
        }

        return BaseResponse::success([
            'tiers' => $tiers,
            'rules' => $rules,
        ]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo mới hồ sơ văn phong thủ công (origin=manual)
     * =====================================================================
     *
     * INPUT:
     * - StoreWritingProfileRequest $request: Dữ liệu hồ sơ và taxonomy đã validate
     *
     * OUTPUT:
     * - \Illuminate\Http\JsonResponse: 201 BaseResponse::created(WritingProfileResource)
     *
     * SIDE EFFECT:
     * - Thêm mới bản ghi vào bảng writing_profiles và đồng bộ categories/tags
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException nếu vi phạm ràng buộc
     * =====================================================================
     */
    public function store(StoreWritingProfileRequest $request)
    {
        $data = $request->validated();
        $profile = WritingProfile::create([
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'dominant_tier'      => $data['dominant_tier'] ?? WritingProfile::TIER_MEDIUM,
            'rules_json'         => $data['rules_json'] ?? [],
            'evidence_json'      => $data['evidence_json'] ?? [],
            'style_instructions' => $data['style_instructions'] ?? '',
            'origin'             => WritingProfile::ORIGIN_MANUAL,
            'status'             => $data['status'] ?? WritingProfile::STATUS_DRAFT,
            'is_enabled'         => $data['is_enabled'] ?? true,
            'created_by'         => $request->user()?->id,
        ]);

        $taxonomyKeys = ['categories', 'new_categories', 'tags', 'new_tags'];
        if ($request->hasAny($taxonomyKeys)) {
            $profile->syncTaxonomyWithNew($request->only($taxonomyKeys));
        }

        return BaseResponse::created(new WritingProfileResource($profile->load(['categories', 'tags'])), 'Tạo văn phong thành công');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Xem chi tiết một hồ sơ văn phong kèm phân loại
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $writingProfile: Model tìm theo route binding
     *
     * OUTPUT:
     * - \Illuminate\Http\JsonResponse: 200 BaseResponse::success(WritingProfileResource)
     *
     * SIDE EFFECT:
     * - Eager load quan hệ categories và tags
     *
     * EXCEPTION/TRANSACTION:
     * - 404 ModelNotFoundException nếu ID không tồn tại
     * =====================================================================
     */
    public function show(WritingProfile $writingProfile)
    {
        return BaseResponse::success(new WritingProfileResource($writingProfile->load(['categories', 'tags'])));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Cập nhật thông tin hồ sơ văn phong và đồng bộ taxonomy
     * =====================================================================
     *
     * INPUT:
     * - StoreWritingProfileRequest $request: Dữ liệu cập nhật
     * - WritingProfile $writingProfile: Model cần cập nhật
     *
     * OUTPUT:
     * - \Illuminate\Http\JsonResponse: 200 BaseResponse::success(WritingProfileResource)
     *
     * SIDE EFFECT:
     * - Cập nhật bảng writing_profiles, tăng version lên 1 và sync taxonomy
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException nếu vi phạm ràng buộc
     * =====================================================================
     */
    public function update(StoreWritingProfileRequest $request, WritingProfile $writingProfile)
    {
        $data = $request->validated();
        foreach (['name', 'description', 'dominant_tier', 'rules_json', 'evidence_json', 'style_instructions', 'status', 'is_enabled'] as $f) {
            if (array_key_exists($f, $data)) {
                $writingProfile->{$f} = $data[$f];
            }
        }
        $writingProfile->version = ($writingProfile->version ?? 1) + 1;
        $writingProfile->save();

        $taxonomyKeys = ['categories', 'new_categories', 'tags', 'new_tags'];
        if ($request->hasAny($taxonomyKeys)) {
            $writingProfile->syncTaxonomyWithNew($request->only($taxonomyKeys));
        }

        return BaseResponse::success(new WritingProfileResource($writingProfile->load(['categories', 'tags'])), 'Cập nhật văn phong thành công');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Xóa một hồ sơ văn phong
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $writingProfile: Model cần xóa
     *
     * OUTPUT:
     * - \Illuminate\Http\JsonResponse: 204 BaseResponse::noContent()
     *
     * SIDE EFFECT:
     * - Xóa mềm hoặc xóa cứng bản ghi trong writing_profiles
     *
     * EXCEPTION/TRANSACTION:
     * - Không có
     * =====================================================================
     */
    public function destroy(WritingProfile $writingProfile)
    {
        $writingProfile->delete();

        return BaseResponse::noContent();
    }
}
