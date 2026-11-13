<?php

namespace Modules\WritingStyle\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Services\PromptPack;
use Modules\WritingStyle\Http\Requests\StoreWritingProfileRequest;
use Modules\WritingStyle\Http\Resources\WritingProfileResource;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Models\WritingProfileAnalysis;
use Modules\WritingStyle\Pipelines\SandboxPipelineRunner;
use Modules\WritingStyle\Services\WritingProfileDefinition;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: CRUD hồ sơ DNA văn phong, gắn phân tích và cấp options cho frontend.
 * =====================================================================
 *
 * Cung cấp các endpoint RESTful trong nhóm /v1/admin/writing-style/writing-profiles:
 * - GET    /writing-profiles          : Danh sách phân trang kèm bộ lọc
 * - GET    /writing-profiles/options  : 4 tiers và 16 câu hỏi giải mã DNA
 * - POST   /writing-profiles          : Tạo hồ sơ khi Lưu (kèm analysis_id -> origin=reference)
 * - GET    /writing-profiles/{id}     : Chi tiết một hồ sơ kèm taxonomy
 * - PUT    /writing-profiles/{id}     : Cập nhật hồ sơ và đồng bộ taxonomy
 * - DELETE /writing-profiles/{id}     : Xóa cứng hồ sơ và giải phóng slug
 *
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - index(): Lọc theo search/tier/origin/status, sắp id mới nhất và phân trang tối đa 100.
 * - options(): Cấp tiers, câu hỏi, pipelines, phiên bản prompt, định nghĩa trục và presets.
 * - store(): Tạo profile và gắn analysis đã succeeded nếu có analysis_id.
 * - show(): Tải chi tiết profile và các phân tích liên quan.
 * - update(): Áp dụng các trường được gửi, tăng version và đồng bộ slug/taxonomy.
 * - destroy(): Xóa cứng profile và giải phóng slug theo hook model.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Request đã validate hoặc WritingProfile được route model binding.
 * - OUTPUT: BaseResponse chứa profile, dataTable, options hoặc response 204.
 *
 * BỐI CẢNH SỬ DỤNG: Các route admin dùng auth:sanctum và permission middleware của WritingStyle.
 * =====================================================================
 */
class WritingProfileController extends Controller
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Lọc theo search/tier/origin/status, sắp id mới nhất và phân trang tối đa 100
     * =====================================================================
     *
     * INPUT:
     * - Request $request
     *
     * OUTPUT:
     * - BaseResponse dataTable; eager load categories và tags
     *
     * SIDE EFFECT:
     * - Đọc DB. Yêu cầu writing.profile.view.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi truy vấn có thể truyền lên. Không mở transaction.
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
     * CHỨC NĂNG: Cấp tiers, câu hỏi, pipelines, phiên bản prompt, định nghĩa trục và presets
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - BaseResponse options V2/V3
     *
     * SIDE EFFECT:
     * - Đọc config và validate mô tả pipeline. Yêu cầu writing.profile.view.
     *
     * EXCEPTION/TRANSACTION:
     * - InvalidPipelineException nếu cấu hình pipeline sai. Không mở transaction.
     * =====================================================================
     */
    public function options()
    {
        $tiers = [];
        $prevS = 0;
        $prevW = 0;
        foreach (WritingProfile::TIERS as $t) {
            $maxS = (int) config("writingstyle.tiers.{$t}.max_sentences", PHP_INT_MAX);
            $maxW = (int) config("writingstyle.tiers.{$t}.max_words", PHP_INT_MAX);

            // Ngưỡng phân loại mẫu (detectTier) — FE hiển thị trực tiếp
            // trong "Cấp độ tự nhận diện" mà không cần tự suy ngưỡng.
            $hint = $maxS === PHP_INT_MAX
                ? "> {$prevS} câu hoặc > {$prevW} từ"
                : "≤ {$maxS} câu hoặc ≤ {$maxW} từ";

            $tiers[] = [
                'value'         => $t,
                'label'         => config("writingstyle.tiers.{$t}.label", $t),
                'sentence_hint' => WritingBrief::fromInput('', $t, [])->lengthHint(),
                // Ngưỡng phân loại từ config — FE chỉ hiển thị, không tự tính.
                'hint'          => $hint,
                'max_sentences' => $maxS,
                'max_words'     => $maxW,
            ];
            $prevS = $maxS;
            $prevW = $maxW;
        }

        // Câu hỏi DNA lấy từ prompt pack (15 khóa quan sát); `uncertainties`
        // được thêm riêng vì nó là báo cáo điểm chưa xác định, không phải feature.
        $questions = (array) PromptPack::dict('questions');
        $rules = [];
        foreach (WritingProfileDefinition::ruleKeys() as $k) {
            $rules[] = [
                'key' => $k,
                'question' => $k === 'uncertainties'
                    ? 'Những khía cạnh nào bài mẫu quá ngắn hoặc không đủ dữ liệu để kết luận chắc chắn? (liệt kê các điểm còn thiếu — để ngỏ, không bịa)'
                    : (string) ($questions[$k] ?? ''),
            ];
        }

        return BaseResponse::success([
            'tiers' => $tiers,
            'rules' => $rules,
            // Sơ đồ Pipeline của 4 tier — FE render Pipeline Stepper biết trước
            // các bước + trạng thái phụ thuộc (PLAN-WRITING-STYLE-SANDBOX-PIPELINE).
            'pipelines' => app(SandboxPipelineRunner::class)->describePipelines(),
            'prompt_version' => PromptPack::version(),
            'axis_definitions' => PromptPack::dict('axes'),
            'presets' => PromptPack::dict('presets') ?? [],
        ]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo profile và gắn analysis đã succeeded nếu có analysis_id
     * =====================================================================
     *
     * INPUT:
     * - StoreWritingProfileRequest $request
     *
     * OUTPUT:
     * - BaseResponse created kèm categories/tags
     *
     * SIDE EFFECT:
     * - Ghi profile, slug, taxonomy và draft_profile_id của analysis. Yêu cầu writing.profile.create.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi DB/slug/taxonomy có thể truyền lên. Không bao transaction cho toàn bộ các lần ghi, không giữ khóa.
     * =====================================================================
     */
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

    /**
     * =====================================================================
     * CHỨC NĂNG: Tải chi tiết profile và các phân tích liên quan
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $writingProfile
     *
     * OUTPUT:
     * - BaseResponse profile với categories, tags, analyses
     *
     * SIDE EFFECT:
     * - Đọc DB. Yêu cầu writing.profile.view.
     *
     * EXCEPTION/TRANSACTION:
     * - ModelNotFoundException có thể xảy ra ở route binding. Không mở transaction.
     * =====================================================================
     */
    public function show(WritingProfile $writingProfile)
    {
        return BaseResponse::success(new WritingProfileResource(
            $writingProfile->load(['categories', 'tags', 'analyses'])
        ));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Áp dụng các trường được gửi, tăng version và đồng bộ slug/taxonomy
     * =====================================================================
     *
     * INPUT:
     * - StoreWritingProfileRequest $request, WritingProfile $writingProfile
     *
     * OUTPUT:
     * - BaseResponse profile đã cập nhật
     *
     * SIDE EFFECT:
     * - Ghi profile và các quan hệ khi request có dữ liệu. Yêu cầu writing.profile.create.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi validation, DB hoặc slug có thể truyền lên. Không mở transaction hoặc optimistic lock.
     * =====================================================================
     */
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

    /**
     * =====================================================================
     * CHỨC NĂNG: Xóa cứng profile và giải phóng slug theo hook model
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $writingProfile
     *
     * OUTPUT:
     * - BaseResponse noContent
     *
     * SIDE EFFECT:
     * - DELETE profile và slug liên quan. Yêu cầu writing.profile.manage.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi DB truyền lên. Không tự mở transaction.
     * =====================================================================
     */
    public function destroy(WritingProfile $writingProfile)
    {
        $writingProfile->delete();

        return BaseResponse::noContent();
    }
}
