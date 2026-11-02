<?php

namespace Modules\CoreAi\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Core\Services\CredentialVault;
use Modules\Core\Http\Responses\BaseResponse;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\Http\Requests\AiProviderStoreRequest;
use Modules\CoreAi\Http\Requests\AiProviderUpdateRequest;
use Modules\CoreAi\Http\Resources\AiProviderResource;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvider;
use Modules\CoreAi\Registries\AiDriverRegistry;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: CRUD AiProvider + test-ping + liệt kê driver.
 * =====================================================================
 * index   -> dataTable (search kind/driver/active).
 * store   -> mã hóa api_key/extra_keys qua CredentialVault trước khi ghi.
 * update  -> partial; api_key rỗng = giữ cũ; extra_keys gửi = thay hết.
 * test    -> gọi driver->test(resolved) ghi test_status/last_tested_at.
 * drivers -> mảng driver đã đăng ký trong AiDriverRegistry (cho UI chọn).
 * =====================================================================
 */
class AiProviderController extends Controller
{
    public function __construct(
        private readonly CredentialVault $vault,
        private readonly AiDriverRegistry $drivers,
    ) {}

    /**
     * GET /api/v1/admin/core-ai/providers
     * INPUT: Request (search, kind, driver, is_active, itemsPerPage).
     * OUTPUT: BaseResponse::dataTable items=AiProviderResource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->integer('itemsPerPage', $request->integer('per_page', 15));
        $search = (string) $request->query('search', '');
        $kind = (string) $request->query('kind', '');
        $driver = (string) $request->query('driver', '');
        $isActive = $request->query('is_active');

        $query = AiProvider::query()
            ->withCount('models')
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")->orWhere('key', 'like', "%{$search}%");
            }))
            ->when($kind !== '', fn ($q) => $q->where('kind', $kind))
            ->when($driver !== '', fn ($q) => $q->where('driver', $driver))
            ->when($isActive !== null && $isActive !== '', fn ($q) => $q->where('is_active', (bool) $isActive))
            ->orderBy('name');

        $paginator = $query->paginate($perPage)->appends($request->query());

        return BaseResponse::dataTable(
            $paginator->setCollection(
                AiProviderResource::collection($paginator->getCollection())->collection
            )
        );
    }

    /**
     * POST /api/v1/admin/core-ai/providers
     * INPUT: AiProviderStoreRequest. OUTPUT: BaseResponse::created.
     * SIDE EFFECT: INSERT ai_providers + mã hóa api_key/extra_keys.
     */
    public function store(AiProviderStoreRequest $request)
    {
        $data = $request->validated();

        $provider = AiProvider::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'kind' => $data['kind'],
            'driver' => $data['driver'],
            'base_url' => $data['base_url'],
            'api_key_encrypted' => $this->vault->encrypt($data['api_key']),
            'extra_keys_json' => $this->encodeExtraKeys($data['extra_keys'] ?? []),
            'config_json' => $data['config_json'] ?? null,
            'request_timeout' => $data['request_timeout'] ?? 120,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return BaseResponse::created(
            new AiProviderResource($provider),
            'Tạo nhà cung cấp AI thành công',
        );
    }

    /**
     * GET /api/v1/admin/core-ai/providers/{ai_provider}
     * INPUT: AiProvider. OUTPUT: BaseResponse::success kèm models.
     */
    public function show(AiProvider $aiProvider)
    {
        $aiProvider->load('models');

        return BaseResponse::success(new AiProviderResource($aiProvider));
    }

    /**
     * PUT /api/v1/admin/core-ai/providers/{ai_provider}
     * INPUT: AiProviderUpdateRequest + AiProvider. OUTPUT: success.
     * SIDE EFFECT: UPDATE; api_key rỗng giữ cũ; extra_keys gửi = thay hết.
     */
    public function update(AiProviderUpdateRequest $request, AiProvider $aiProvider)
    {
        $data = $request->validated();

        // Trường text/đơn giản — chỉ cập nhật field có trong payload.
        foreach (['key', 'name', 'kind', 'driver', 'base_url', 'config_json', 'request_timeout', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $aiProvider->{$f} = $data[$f];
            }
        }

        // api_key chỉ ghi khi client gửi giá trị mới không rỗng.
        if (array_key_exists('api_key', $data) && filled($data['api_key'])) {
            $aiProvider->api_key_encrypted = $this->vault->encrypt($data['api_key']);
        }

        // extra_keys gửi (kể cả []) = THAY TOÀN BỘ danh sách key phụ.
        if (array_key_exists('extra_keys', $data)) {
            $aiProvider->extra_keys_json = $this->encodeExtraKeys($data['extra_keys'] ?? []);
        }

        $aiProvider->save();

        return BaseResponse::success(
            new AiProviderResource($aiProvider->fresh()),
            'Cập nhật nhà cung cấp thành công',
        );
    }

    /**
     * DELETE /api/v1/admin/core-ai/providers/{ai_provider}
     * INPUT: AiProvider. OUTPUT: noContent (models cascade qua FK).
     */
    public function destroy(AiProvider $aiProvider)
    {
        $aiProvider->delete();

        return BaseResponse::noContent();
    }

    /**
     * GET /api/v1/admin/core-ai/providers/drivers
     * INPUT: không. OUTPUT: mảng driver slug đã đăng ký (cho dropdown).
     */
    public function drivers()
    {
        return BaseResponse::success([
            'drivers' => $this->drivers->registered(),
            'kinds' => AiProvider::KINDS,
        ]);
    }

    /**
     * POST /api/v1/admin/core-ai/providers/{ai_provider}/test
     * INPUT: AiProvider. OUTPUT: {ok, latency_ms, message, models?}.
     * SIDE EFFECT: gọi HTTP tới provider; ghi test_status + last_tested_at.
     * Mô tả: ping kết nối dùng model bất kỳ usable của provider (hoặc
     * model đầu tiên) — chỉ cần resolved hợp lệ để driver->test gọi HTTP.
     */
    public function test(AiProvider $aiProvider)
    {
        $apiKey = $aiProvider->apiKey();
        if ($apiKey === null || $apiKey === '') {
            return BaseResponse::error('Nhà cung cấp chưa cấu hình API key', 422);
        }

        // Chọn model bất kỳ để test (ưu tiên usable); test chỉ cần resolved.
        $model = $aiProvider->models()->usable()->first()
            ?? $aiProvider->models()->first();

        $resolved = new AiResolvedModelDto(
            provider: $aiProvider,
            model: $model ?? new AiModel(['remote_model_id' => 'ping', 'ai_provider_id' => $aiProvider->id]),
            apiKey: $apiKey,
            keyIndex: 0,
        );

        try {
            $driver = $this->drivers->resolve($aiProvider);
            $result = $driver->test($resolved);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        $aiProvider->markTestResult((bool) ($result['ok'] ?? false));

        return BaseResponse::success(array_merge($result, [
            'provider_id' => $aiProvider->id,
            'test_status' => $aiProvider->fresh()->test_status,
        ]));
    }

    /**
     * POST /api/v1/admin/core-ai/providers/{ai_provider}/sync-models
     * INPUT: AiProvider. OUTPUT: {ok,synced,created,updated,models[]}.
     * SIDE EFFECT: gọi driver->test() lấy models[] → upsert ai_models theo
     * (ai_provider_id, remote_model_id); model đã tồn tại -> is_available=true,
     * model mới -> tạo với capability đoán theo remote_model_id.
     * SIDE EFFECT: ghi test_status + last_tested_at.
     */
    public function syncModels(AiProvider $aiProvider)
    {
        $apiKey = $aiProvider->apiKey();
        if ($apiKey === null || $apiKey === '') {
            return BaseResponse::error('Nhà cung cấp chưa cấu hình API key', 422);
        }

        $resolved = new AiResolvedModelDto(
            provider: $aiProvider,
            model: new AiModel(['remote_model_id' => 'sync', 'ai_provider_id' => $aiProvider->id]),
            apiKey: $apiKey,
            keyIndex: 0,
        );

        try {
            $driver = $this->drivers->resolve($aiProvider);
            $result = $driver->test($resolved);
        } catch (\Throwable $e) {
            $aiProvider->markTestResult(false);
            return BaseResponse::error('Không ping được nhà cung cấp: '.$e->getMessage(), 502);
        }

        $aiProvider->markTestResult((bool) ($result['ok'] ?? false));

        $remoteIds = is_array($result['models'] ?? null) ? $result['models'] : [];
        $created = 0;
        $updated = 0;
        $synced = [];

        foreach ($remoteIds as $remoteId) {
            if (! is_string($remoteId) || $remoteId === '') {
                continue;
            }

            $model = AiModel::firstOrNew([
                'ai_provider_id' => $aiProvider->id,
                'remote_model_id' => $remoteId,
            ]);

            if ($model->exists) {
                // Model đã có: giữ capabilities/label/is_enabled admin đã chỉnh —
                // chỉ đánh dấu is_available (provider còn phục vụ model này).
                $model->is_available = true;
                $updated++;
            } else {
                // Model mới: capabilities = [] (mảng rỗng) — admin gán tay qua
                // dialog Capabilities, không đoán để tránh gán sai ngầm.
                $model->label = $remoteId;
                $model->capabilities_json = [];
                $model->is_enabled = true;
                $model->is_available = true;
                $created++;
            }

            $model->save();
            $synced[] = $remoteId;
        }

        return BaseResponse::success([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? 'Đồng bộ xong',
            'synced' => count($synced),
            'created' => $created,
            'updated' => $updated,
            'models' => $synced,
            'test_status' => $aiProvider->fresh()->test_status,
        ]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Mã hóa từng key phụ → mảng ciphertext cho extra_keys_json.
     * =====================================================================
     * INPUT: string[] plaintext keys. OUTPUT: string[] ciphertext (json-able).
     * =====================================================================
     */
    private function encodeExtraKeys(array $keys): ?array
    {
        $encoded = [];
        foreach ($keys as $k) {
            if (is_string($k) && $k !== '') {
                $encoded[] = $this->vault->encrypt($k);
            }
        }

        return $encoded === [] ? null : $encoded;
    }
}
