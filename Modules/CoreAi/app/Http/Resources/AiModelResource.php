<?php

namespace Modules\CoreAi\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa AiModel trả về API admin.
 * =====================================================================
 * Trả capabilities/pricing thật (không phải secret) + provider_key join
 * để UI nhóm model theo provider.
 * =====================================================================
 */
class AiModelResource extends JsonResource
{
    /**
     * INPUT: Request. OUTPUT: array chuẩn cho BaseResponse.data.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ai_provider_id' => $this->ai_provider_id,
            'remote_model_id' => $this->remote_model_id,
            'label' => $this->label,
            'capabilities' => $this->capabilities(),
            'pricing' => $this->pricing_json,
            'is_enabled' => (bool) $this->is_enabled,
            'is_available' => (bool) $this->is_available,
            // Ước tính phí khi có pricing.
            'input_price_per_mtok' => $this->inputPricePerMtok(),
            'output_price_per_mtok' => $this->outputPricePerMtok(),
            'provider' => new AiProviderResource($this->whenLoaded('provider')),
            'provider_key' => $this->whenLoaded('provider', fn () => $this->provider->key),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
