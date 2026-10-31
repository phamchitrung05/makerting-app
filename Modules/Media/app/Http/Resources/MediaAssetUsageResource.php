<?php

namespace Modules\Media\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa MediaAssetUsage (gán asset vào model theo slot).
 * =====================================================================
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Request. OUTPUT: array — id/media_asset_id/linkable_type/
 *         linkable_id/slot/sort_order + media_asset (nếu eager loaded).
 * =====================================================================
 */
class MediaAssetUsageResource extends JsonResource
{
    /**
     * CHỨC NĂNG: Chuyển MediaAssetUsage -> array trả API.
     * INPUT: Request. OUTPUT: array.
     * SIDE EFFECT: đọc mediaAsset nếu eager loaded.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_asset_id' => $this->media_asset_id,
            'linkable_type' => $this->linkable_type,
            'linkable_id' => $this->linkable_id,
            'slot' => $this->slot,
            'sort_order' => $this->sort_order,
            'media_asset' => new MediaAssetResource($this->whenLoaded('mediaAsset')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
