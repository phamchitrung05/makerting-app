<?php

namespace Modules\WritingStyle\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Services\PromptV3;
use Modules\WritingStyle\Http\Requests\StoreWritingProfileRequest;
use Modules\WritingStyle\Http\Resources\WritingProfileResource;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\WritingStyle\Pipelines\SandboxPipelineRunner;
use Modules\WritingStyle\Services\WritingProfileDefinition;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Controller quản lý CRUD hồ sơ văn phong (WritingProfile).
 * =====================================================================
 *
 * Cung cấp các endpoint RESTful trong nhóm /v1/admin/writing-style/writing-profiles:
 * - GET    /writing-profiles          : Danh sách phân trang kèm bộ lọc
 * - GET    /writing-profiles/options  : 4 tiers và 16 câu hỏi giải mã DNA
 * - POST   /writing-profiles          : Tạo hồ sơ khi Lưu (kèm analysis_id -> origin=reference)
 * - GET    /writing-profiles/{id}     : Chi tiết một hồ sơ kèm taxonomy
 * - PUT    /writing-profiles/{id}     : Cập nhật hồ sơ và đồng bộ taxonomy
 * - DELETE /writing-profiles/{id}     : Xóa mềm hồ sơ
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - index(Request $request): Trả danh sách hồ sơ dạng dataTable
 * - options(): Trả danh sách 4 phân tầng độ dài và 16 câu hỏi giải mã
 * - store(StoreWritingProfileRequest $request): Tạo hồ sơ khi Lưu + gắn analysis
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

    public function options()
    {
        $tiers = [];
        foreach (WritingProfile::TIERS as $t) {
            $tiers[] = [
                'value'         => $t,
                'label'         => config("writingstyle.tiers.{$t}.label", $t),
                'sentence_hint' => PromptV3::enabled() ? WritingBrief::fromInput('', $t, [])->lengthHint() : WritingProfileDefinition::tierLabel($t),
            ];
        }

        $rules = [];
        foreach (WritingProfileDefinition::ruleKeys() as $k) {
            $rules[] = [
                'key' => $k,
                'question' => PromptV3::enabled() ? config('writingstyle.v3.questions.'.$k, WritingProfileDefinition::ruleQuestion($k)) : WritingProfileDefinition::ruleQuestion($k),
            ];
        }

        return BaseResponse::success([
            'tiers' => $tiers,
            'rules' => $rules,
            // Sơ đồ Pipeline của 4 tier — FE render Pipeline Stepper biết trước
            // các bước + trạng thái phụ thuộc (PLAN-WRITING-STYLE-SANDBOX-PIPELINE).
            'pipelines' => app(SandboxPipelineRunner::class)->describePipelines(),
            'prompt_version' => (int) config('writingstyle.prompt_version', 2),
            'axis_definitions' => config('writingstyle.v3.axes'),
            'presets' => config('writingstyle.v3.presets', []),
        ]);
    }

    public function store(StoreWritingProfileRequest $request)
    {
        $data = $request->validated();

        $analysis = null;
        if (! empty($data['analysis_id'])) {
            $analysis = WritingProfileAnalysis::find($data['analysis_id']);
        }
        $linkedAnalysis = ($analysis !== null && $analysis->status === WritingProfileAnalysis::STATUS_SUCCEEDED)
            ? $analysis
            : null;

        $analysisResult = $linkedAnalysis?->result_json ?? [];

        $profile = WritingProfile::create([
            'name'                       => $data['name'],
            'description'                => $data['description'] ?? null,
            'dominant_tier'              => $data['dominant_tier'] ?? WritingProfile::TIER_MEDIUM,
            'temperature'                => $data['temperature'] ?? 0.70,
            'rules_json'                 => $data['rules_json'] ?? ($analysisResult['rules_json'] ?? []),
            'style_analysis_json'        => $analysisResult['style_analysis_json'] ?? null,
            'slider_preferences_json'    => $data['slider_preferences_json'] ?? null,
            'evidence_json'              => $data['evidence_json'] ?? ($analysisResult['evidence_json'] ?? []),
            'style_instructions'         => $data['style_instructions'] ?? ($analysisResult['style_instructions'] ?? ''),
            'voice_texture_instructions' => $data['voice_texture_instructions']
                ?? ($analysisResult['voice_texture_instructions'] ?? ''),
            'voice_texture_sliders_json' => $data['voice_texture_sliders_json']
                ?? ($analysisResult['voice_texture_sliders_json'] ?? null),
            'tone_sliders_json'          => $data['tone_sliders_json']
                ?? ($analysisResult['tone_sliders_json'] ?? null),
            // Ô 3 - Chỉ dẫn Sắc thái & Phối âm cảm xúc: do NGƯỜI DÙNG soạn
            // tay theo mục đích viết bài (không do AI giải mã) — POST nhận
            // trực tiếp giống PUT để hai verb đối xứng.
            'tone_instructions'          => $data['tone_instructions'] ?? '',
            'origin'                     => $linkedAnalysis !== null
                ? WritingProfile::ORIGIN_REFERENCE
                : WritingProfile::ORIGIN_MANUAL,
            'status'                     => $data['status'] ?? WritingProfile::STATUS_DRAFT,
            'is_enabled'                 => $data['is_enabled'] ?? true,
            'created_by'                 => $request->user()?->id,
        ]);

        if (! empty($data['slug'])) {
            $profile->syncSlugNow($data['slug']);
        }

        if ($linkedAnalysis !== null) {
            $profile->source_hash = $linkedAnalysis->source_hash;
            $profile->analysis_metadata_json = $linkedAnalysis->result_json['metadata'] ?? null;
            $profile->save();

            $linkedAnalysis->draft_profile_id = $profile->id;
            $linkedAnalysis->save();
        }

        $taxonomyKeys = ['categories', 'new_categories', 'tags', 'new_tags'];
        if ($request->hasAny($taxonomyKeys)) {
            $profile->syncTaxonomyWithNew($request->only($taxonomyKeys));
        }

        return BaseResponse::created(new WritingProfileResource($profile->load(['categories', 'tags'])), 'Tạo văn phong thành công');
    }

    public function show(WritingProfile $writingProfile)
    {
        return BaseResponse::success(new WritingProfileResource(
            $writingProfile->load(['categories', 'tags', 'analyses'])
        ));
    }

    public function update(StoreWritingProfileRequest $request, WritingProfile $writingProfile)
    {
        $data = $request->validated();
        $updatable = [
            'name',
            'description',
            'dominant_tier',
            'temperature',
            'rules_json',
            'evidence_json',
            'style_instructions',
            'voice_texture_instructions',
            'voice_texture_sliders_json',
            'tone_sliders_json',
            'slider_preferences_json',
            'tone_instructions',
            'status',
            'is_enabled',
        ];
        foreach ($updatable as $f) {
            if (array_key_exists($f, $data)) {
                $writingProfile->{$f} = $data[$f];
            }
        }
        $writingProfile->version = ($writingProfile->version ?? 1) + 1;
        $writingProfile->save();

        if (array_key_exists('slug', $data) && filled($data['slug'])) {
            $writingProfile->syncSlugNow($data['slug']);
        }

        $taxonomyKeys = ['categories', 'new_categories', 'tags', 'new_tags'];
        if ($request->hasAny($taxonomyKeys)) {
            $writingProfile->syncTaxonomyWithNew($request->only($taxonomyKeys));
        }

        return BaseResponse::success(new WritingProfileResource($writingProfile->load(['categories', 'tags'])), 'Cập nhật văn phong thành công');
    }

    public function destroy(WritingProfile $writingProfile)
    {
        $writingProfile->delete();

        return BaseResponse::noContent();
    }
}
