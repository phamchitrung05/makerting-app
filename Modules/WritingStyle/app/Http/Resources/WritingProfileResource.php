<?php

namespace Modules\WritingStyle\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Serialize WritingProfile cho API/UI.
 * =====================================================================
 * Trả rules_json + evidence_json đầy đủ (admin xem/đối chiếu trong Edit),
 * dominant_tier, analysis_metadata. KHÔNG có secret nào cần che.
 * =====================================================================
 */
class WritingProfileResource extends JsonResource
{
    /** INPUT: request. OUTPUT: array. */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'description'           => $this->description,
            'dominant_tier'         => $this->dominant_tier,
            'rules_json'            => $this->rules_json,
            'evidence_json'         => $this->evidence_json,
            'style_instructions'    => $this->style_instructions,
            'origin'                => $this->origin,
            'status'                => $this->status,
            'is_enabled'            => $this->is_enabled,
            'analysis_metadata_json' => $this->analysis_metadata_json,
            'version'               => $this->version,
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}
