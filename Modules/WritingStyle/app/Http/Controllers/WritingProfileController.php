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
 * CHỨC NĂNG FILE: CRUD WritingProfile + /options (tiers + rule keys).
 * =====================================================================
 * Route group /v1/admin/writing-style/writing-profiles.
 * =====================================================================
 */
class WritingProfileController extends Controller
{
    /**
     * GET /writing-profiles — danh sách (search + lọc tier/origin/status).
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $search = trim((string) $request->query('search', ''));

        $items = WritingProfile::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->filled('tier'), fn ($q) => $q->where('dominant_tier', $request->query('tier')))
            ->when($request->filled('origin'), fn ($q) => $q->where('origin', $request->query('origin')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('id')
            ->paginate($perPage);

        return BaseResponse::dataTable($items);
    }

    /**
     * GET /writing-profiles/options — tiers + 16 rule keys + câu hỏi giải mã.
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
     * POST /writing-profiles — tạo tay (origin=manual, status draft/active).
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

        return BaseResponse::created(new WritingProfileResource($profile), 'Tạo văn phong thành công');
    }

    /**
     * GET /writing-profiles/{writing_profile}.
     */
    public function show(WritingProfile $writingProfile)
    {
        return BaseResponse::success(new WritingProfileResource($writingProfile));
    }

    /**
     * PUT /writing-profiles/{writing_profile}.
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

        return BaseResponse::success(new WritingProfileResource($writingProfile), 'Cập nhật văn phong thành công');
    }

    /**
     * DELETE /writing-profiles/{writing_profile}.
     */
    public function destroy(WritingProfile $writingProfile)
    {
        $writingProfile->delete();

        return BaseResponse::noContent();
    }
}
