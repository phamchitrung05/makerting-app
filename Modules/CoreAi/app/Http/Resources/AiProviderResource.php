<?php

namespace Modules\CoreAi\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa AiProvider trả về API admin.
 * =====================================================================
 * KHÔNG bao giờ trả api_key_encrypted/extra_keys_json ra ngoài — chỉ trả
 * cờ `api_key_configured` + số lượng key phụ để UI hiển thị trạng thái.
 * `models` eager-loaded khi cần (index liệt kê kèm số lượng).
 * =====================================================================
 */
class AiProviderResource extends JsonResource
{
    /**
     * INPUT: Request. OUTPUT: array chuẩn cho BaseResponse.data.
     * SIDE EFFECT: đọc quan hệ models nếu đã eager load.
     */
    public function toArray(Request $request): array
    {
        $extraKeys = is_array($this->extra_keys_json) ? $this->extra_keys_json : [];

        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'kind' => $this->kind,
            'driver' => $this->driver,
            'base_url' => $this->base_url,
            'request_timeout' => $this->request_timeout,
            'is_active' => (bool) $this->is_active,
            'test_status' => $this->test_status,
            'last_tested_at' => $this->last_tested_at?->toISOString(),
            'config_json' => $this->config_json,
            // Che key — chỉ lộ trạng thái cấu hình.
            'api_key_configured' => filled($this->api_key_encrypted),
            'extra_keys_count' => count($extraKeys),
            'models_count' => $this->whenCounted('models'),
            'models' => $this->relationLoaded('models')
                ? AiModelResource::collection($this->models)
                : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
