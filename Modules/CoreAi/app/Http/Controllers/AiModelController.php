<?php

namespace Modules\CoreAi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\CoreAi\Http\Requests\AiModelStoreRequest;
use Modules\CoreAi\Http\Requests\AiModelUpdateRequest;
use Modules\CoreAi\Http\Resources\AiModelResource;
use Modules\CoreAi\Models\AiModel;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: CRUD AiModel + liệt kê capability + lọc theo provider.
 * =====================================================================
 * index        -> dataTable lọc theo provider/capability/enabled/search.
 * options      -> danh sách nhẹ {id,remote_model_id,label,provider_id}
 *                 cho dropdown (lọc theo ai_provider_id khi có).
 * capabilities -> mảng CAP_* cho UI chọn checkbox.
 * store/update/destroy -> CRUD (partial update safe).
 * =====================================================================
 */
class AiModelController extends Controller
{
    /**
     * GET /api/v1/admin/core-ai/models
     * INPUT: Request (ai_provider_id, capability, is_enabled, search).
     * OUTPUT: BaseResponse::dataTable items=AiModelResource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('itemsPerPage', $request->integer('per_page', 15));
        $providerId = $request->integer('ai_provider_id');
        $capability = (string) $request->query('capability', '');
        $isEnabled = $request->query('is_enabled');
        $search = (string) $request->query('search', '');

        $query = AiModel::query()
            ->with('provider')
            ->when($providerId > 0, fn ($q) => $q->where('ai_provider_id', $providerId))
            ->when($capability !== '', fn ($q) => $q->withCapability($capability))
            ->when($isEnabled !== null && $isEnabled !== '', fn ($q) => $q->where('is_enabled', (bool) $isEnabled))
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('label', 'like', "%{$search}%")
                    ->orWhere('remote_model_id', 'like', "%{$search}%");
            }))
            ->orderBy('ai_provider_id')->orderBy('remote_model_id');

        $paginator = $query->paginate($perPage)->appends($request->query());

        return BaseResponse::dataTable(
            $paginator->setCollection(
                AiModelResource::collection($paginator->getCollection())->collection
            )
        );
    }

    /**
     * GET /api/v1/admin/core-ai/models/options
     * INPUT: Request (ai_provider_id, capability — lọc nhẹ cho dropdown).
     * OUTPUT: mảng {id,remote_model_id,label,ai_provider_id,is_enabled}.
     */
    public function options(Request $request)
    {
        $providerId = $request->integer('ai_provider_id');
        $capability = (string) $request->query('capability', '');

        $models = AiModel::query()
            ->usable()
            ->when($providerId > 0, fn ($q) => $q->where('ai_provider_id', $providerId))
            ->when($capability !== '', fn ($q) => $q->withCapability($capability))
            ->orderBy('remote_model_id')
            ->get(['id', 'ai_provider_id', 'remote_model_id', 'label', 'is_enabled'])
            ->map(fn (AiModel $m) => [
                'id' => $m->id,
                'ai_provider_id' => $m->ai_provider_id,
                'remote_model_id' => $m->remote_model_id,
                'label' => $m->label,
                'is_enabled' => (bool) $m->is_enabled,
            ])
            ->values();

        return BaseResponse::success($models);
    }

    /**
     * GET /api/v1/admin/core-ai/models/capabilities
     * INPUT: không. OUTPUT: mảng [{value,label,icon,color}] — slug CAP_*
     * kèm nhãn tiếng Việt + icon tabler cho UI checkbox dialog model.
     */
    public function capabilities()
    {
        $options = [
            ['value' => AiModel::CAP_TEXT_GENERATION, 'label' => 'Sinh văn bản', 'icon' => 'tabler-file-text', 'color' => 'primary'],
            ['value' => AiModel::CAP_STRUCTURED_OUTPUT, 'label' => 'Xuất có cấu trúc (JSON)', 'icon' => 'tabler-braces', 'color' => 'info'],
            ['value' => AiModel::CAP_VISION, 'label' => 'Hiểu ảnh (Vision)', 'icon' => 'tabler-eye', 'color' => 'success'],
            ['value' => AiModel::CAP_IMAGE_GENERATION, 'label' => 'Sinh ảnh', 'icon' => 'tabler-photo', 'color' => 'warning'],
            ['value' => AiModel::CAP_TTS, 'label' => 'Chuyển chữ thành giọng (TTS)', 'icon' => 'tabler-microphone', 'color' => 'error'],
            ['value' => AiModel::CAP_STT, 'label' => 'Chuyển giọng thành chữ (STT)', 'icon' => 'tabler-headphones', 'color' => 'secondary'],
        ];

        return BaseResponse::success($options);
    }

    /**
     * POST /api/v1/admin/core-ai/models
     * INPUT: AiModelStoreRequest. OUTPUT: BaseResponse::created.
     */
    public function store(AiModelStoreRequest $request)
    {
        $data = $request->validated();

        $model = AiModel::create([
            'ai_provider_id' => $data['ai_provider_id'],
            'remote_model_id' => $data['remote_model_id'],
            'label' => $data['label'],
            'capabilities_json' => array_values($data['capabilities']),
            'pricing_json' => $data['pricing'] ?? null,
            'is_enabled' => $data['is_enabled'] ?? true,
            'is_available' => $data['is_available'] ?? true,
        ]);

        return BaseResponse::created(
            new AiModelResource($model->load('provider')),
            'Tạo mô hình AI thành công',
        );
    }

    /**
     * GET /api/v1/admin/core-ai/models/{ai_model}
     * INPUT: AiModel. OUTPUT: BaseResponse::success kèm provider.
     */
    public function show(AiModel $aiModel)
    {
        $aiModel->load('provider');

        return BaseResponse::success(new AiModelResource($aiModel));
    }

    /**
     * PUT /api/v1/admin/core-ai/models/{ai_model}
     * INPUT: AiModelUpdateRequest + AiModel. OUTPUT: success (partial safe).
     */
    public function update(AiModelUpdateRequest $request, AiModel $aiModel)
    {
        $data = $request->validated();

        foreach (['ai_provider_id', 'remote_model_id', 'label', 'is_enabled', 'is_available'] as $f) {
            if (array_key_exists($f, $data)) {
                $aiModel->{$f} = $data[$f];
            }
        }
        if (array_key_exists('capabilities', $data)) {
            $aiModel->capabilities_json = array_values($data['capabilities']);
        }
        if (array_key_exists('pricing', $data)) {
            $aiModel->pricing_json = $data['pricing'];
        }

        $aiModel->save();

        return BaseResponse::success(
            new AiModelResource($aiModel->fresh()->load('provider')),
            'Cập nhật mô hình thành công',
        );
    }

    /**
     * DELETE /api/v1/admin/core-ai/models/{ai_model}
     * INPUT: AiModel. OUTPUT: noContent.
     */
    public function destroy(AiModel $aiModel)
    {
        $aiModel->delete();

        return BaseResponse::noContent();
    }

    /**
     * POST /api/v1/admin/core-ai/models/{ai_model}/test
     * INPUT: AiModel. OUTPUT: {ok,latency_ms,message,model_id}.
     * SIDE EFFECT: gọi driver->test() với resolved chứa remote_model_id của
     * model này — kiểm tra model có khả dụng trên provider không; cập nhật
     * is_available của model + test_status của provider.
     */
    public function test(AiModel $aiModel)
    {
        $provider = $aiModel->provider;
        if ($provider === null) {
            return BaseResponse::error('Model không thuộc provider nào', 422);
        }
        $apiKey = $provider->apiKey();
        if ($apiKey === null || $apiKey === '') {
            return BaseResponse::error('Nhà cung cấp chưa cấu hình API key', 422);
        }

        $resolved = new \Modules\CoreAi\DTOs\AiResolvedModelDto(
            provider: $provider,
            model: $aiModel,
            apiKey: $apiKey,
            keyIndex: 0,
        );

        try {
            $driver = app(\Modules\CoreAi\Registries\AiDriverRegistry::class)->resolve($provider);
            $result = $driver->test($resolved);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        $ok = (bool) ($result['ok'] ?? false);
        $aiModel->update(['is_available' => $ok]);
        $provider->markTestResult($ok);

        return BaseResponse::success(array_merge($result, [
            'model_id' => $aiModel->id,
            'is_available' => $ok,
        ]));
    }
}
