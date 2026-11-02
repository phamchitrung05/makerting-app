<?php

namespace Modules\CoreAi\Services;

use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvider;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chọn cặp provider+model khả dụng cho một tác vụ AI.
 * =====================================================================
 *
 * ModelResolver là nơi DUY NHẤT quyết định "gọi model nào". Nó:
 * - Lọc `ai_models` đang usable (is_enabled + is_available + provider active)
 *   và có đủ capability yêu cầu (text/structured/vision/image/tts/stt).
 * - Tôn trọng gợi ý của caller: `providerKey`/`modelId` — nếu chỉ định rõ
 *   thì dùng đúng cặp đó (vẫn phải usable + có capability, else báo lỗi).
 * - Trả về `AiResolvedModelDto` kèm `apiKey` (index 0 = key chính) — vòng
 *   xoay vòng key phụ do `AbstractAiDriver::withKeyRotation()` xử lý khi
 *   gặp rate_limit/auth.
 * - Sắp xếp ưu tiên model có pricing rẻ nhất trước (nếu có) để tiết kiệm
 *   chi phí — provider nào không có giá thì đứng sau.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - resolve()        : chọn cặp provider+model theo capability (+ hint).
 * - resolveMany()    : trả danh sách ứng viên (dùng khi cần fallback thủ công).
 * - candidates()     : query AiModel usable + capability (+ hint lọc).
 * - pickFirst()      : lấy ứng viên đầu theo thứ tự ưu tiên.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : capability (CAP_*) + ?providerKey + ?modelId.
 * - OUTPUT: AiResolvedModelDto (provider+model+apiKey+keyIndex) hoặc ném
 *           AiGatewayException::unsupported khi không có cặp nào khả dụng.
 * =====================================================================
 */
class ModelResolver
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Chọn một cặp provider+model để chạy capability.
     * =====================================================================
     * INPUT :
     *   - string $capability  — một trong AiModel::CAP_*.
     *   - ?string $providerKey — chỉ định rõ provider (null = tự chọn).
     *   - ?string $modelId     — chỉ định rõ remote_model_id (null = tự chọn).
     *
     * OUTPUT: AiResolvedModelDto — provider+model+apiKey(index 0).
     * EXCEPTION:
     *   - AiGatewayException::unsupported — không có model khả dụng nào.
     *   - AiGatewayException::auth        — có model nhưng không có API key.
     * SIDE EFFECT: SELECT ai_models + ai_providers + decrypt key.
     * =====================================================================
     */
    public function resolve(
        string $capability,
        ?string $providerKey = null,
        ?string $modelId = null,
    ): AiResolvedModelDto {
        $model = $this->pickFirst($capability, $providerKey, $modelId);

        if ($model === null) {
            throw AiGatewayException::unsupported(
                "không có model khả dụng cho capability '{$capability}'",
                [
                    'capability' => $capability,
                    'provider_key' => $providerKey,
                    'model_id' => $modelId,
                ]
            );
        }

        $provider = $model->provider;
        if ($provider === null || ! $provider->is_active) {
            throw AiGatewayException::unsupported(
                "provider của model '{$model->remote_model_id}' không khả dụng",
                ['capability' => $capability, 'model_id' => $model->remote_model_id]
            );
        }

        // Key index 0 = key chính; vòng xoay vòng do driver xử lý.
        $keys = $provider->allKeys();
        $apiKey = $keys[0] ?? null;

        if ($apiKey === null || $apiKey === '') {
            throw AiGatewayException::auth(
                "provider '{$provider->key}' chưa có API key khả dụng",
                ['provider_key' => $provider->key, 'model_id' => $model->remote_model_id]
            );
        }

        return new AiResolvedModelDto(
            provider: $provider,
            model: $model,
            apiKey: $apiKey,
            keyIndex: 0,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy danh sách mọi ứng viên usable theo capability (+ hint).
     * =====================================================================
     * INPUT: capability + ?providerKey + ?modelId.
     * OUTPUT: AiModel[] — đã eager-load provider, sắp theo ưu tiên chi phí.
     * SIDE EFFECT: SELECT ai_models + providers.
     * =====================================================================
     */
    public function resolveMany(
        string $capability,
        ?string $providerKey = null,
        ?string $modelId = null,
    ): array {
        return $this->candidates($capability, $providerKey, $modelId)->get()->all();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ứng viên đầu tiên theo thứ tự ưu tiên (rẻ trước).
     * =====================================================================
     * INPUT: capability + ?providerKey + ?modelId.
     * OUTPUT: ?AiModel (đã eager-load provider).
     * =====================================================================
     */
    protected function pickFirst(
        string $capability,
        ?string $providerKey,
        ?string $modelId,
    ): ?AiModel {
        return $this->candidates($capability, $providerKey, $modelId)->first();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Query AiModel usable + capability + áp gợi ý caller.
     * =====================================================================
     * - Chỉ model is_enabled + is_available + provider is_active.
     * - capabilities_json phải chứa $capability (JSON contains).
     * - $providerKey → lọc theo provider.key; $modelId → remote_model_id.
     * - Sắp xếp: model có pricing rẻ nhất trước (input+output/mtok), không
     *   có giá → đẩy xuống cuối; sau đó theo id tăng dần cho ổn định.
     *
     * INPUT: capability + ?providerKey + ?modelId.
     * OUTPUT: \Illuminate\Database\Eloquent\Builder<AiModel>.
     * =====================================================================
     */
    protected function candidates(
        string $capability,
        ?string $providerKey,
        ?string $modelId,
    ) {
        return AiModel::query()
            ->usable()
            ->withCapability($capability)
            ->with('provider')
            ->when(
                $providerKey !== null && $providerKey !== '',
                fn ($q) => $q->whereHas('provider', fn ($p) => $p->where('key', $providerKey))
            )
            ->when(
                $modelId !== null && $modelId !== '',
                fn ($q) => $q->where('remote_model_id', $modelId)
            )
            // Ưu tiên model có bảng giá + rẻ hơn; null pricing xếp sau.
            ->orderByRaw(
                'CASE WHEN pricing_json IS NULL THEN 1 ELSE 0 END ASC'
            )
            ->orderBy('id');
    }
}
