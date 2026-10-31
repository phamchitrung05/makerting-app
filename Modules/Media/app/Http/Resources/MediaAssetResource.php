<?php

namespace Modules\Media\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Media\DTOs\MediaItemDto;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa MediaAsset trả về API Admin.
 * =====================================================================
 *
 * Dùng `MediaItemDto::fromModel()` để gom URL gốc + conversions +
 * metadata file Spatie; kèm `usages` (nếu eager loaded) và `creator`.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Request. OUTPUT: array chuẩn cho BaseResponse.data.
 * =====================================================================
 */
class MediaAssetResource extends JsonResource
{
    /**
     * CHỨC NĂNG: Chuyển MediaAsset -> array trả API.
     * INPUT: Request. OUTPUT: array — id/kind/visibility/folder/title/alt/url/
     *         mime/size/dimensions/duration/channel_group_id/created_by/
     *         checksum/conversions/usages_count/created_at/updated_at.
     * SIDE EFFECT: đọc getFirstMedia + generated conversions + eager loaded usages.
     */
    public function toArray(Request $request): array
    {
        $dto = MediaItemDto::fromModel($this->resource);

        return [
            'id' => $dto->id,
            'kind' => $dto->kind,
            'visibility' => $dto->visibility,
            'folder' => $dto->folder,
            'title' => $dto->title,
            'alt_text' => $dto->altText,
            'url' => $dto->url,
            'mime' => $dto->mime,
            'size' => $dto->size,
            'width' => $dto->width,
            'height' => $dto->height,
            'duration_ms' => $dto->durationMs,
            'channel_group_id' => $dto->channelGroupId,
            'created_by' => $dto->createdBy,
            'checksum_sha256' => $dto->checksumSha256,
            'conversions' => $dto->conversions,
            'usages_count' => $this->whenCounted('usages'),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'channel_group' => $this->whenLoaded('channelGroup', fn () => [
                'id' => $this->channelGroup?->id,
                'name' => $this->channelGroup?->name,
            ]),
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }
}