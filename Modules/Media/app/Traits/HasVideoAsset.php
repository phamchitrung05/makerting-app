<?php

namespace Modules\Media\Traits;

use Modules\Media\DTOs\MediaItemDto;
use Modules\Media\Models\MediaAsset;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Trait gán 1 file video chính vào Domain Model
 * qua slot 'video'.
 * =====================================================================
 *
 * Kế thừa `HasMediaSlots` với slot mặc định `'video'` —
 * `MediaSlotRegistry` khai báo allowedKinds=['video'], maxItems=1,
 * visibility='private' (stream ký quyền cho publisher/renderer).
 *
 * Model `use HasVideoAsset` có sẵn:
 * - video()           : MediaItemDto|null video chính.
 * - videoUrl()        : URL video (nếu public; private dùng openReadStream).
 * - videoDurationMs() : duration_ms nếu có.
 * - setVideo()        : attach 1 asset video vào slot.
 * - clearVideo()      : gỡ video khỏi slot.
 * - hasVideo()        : bool.
 * - openVideoStream() : stream đọc file vật lý cho FFmpeg/upload.
 *
 * Override `$videoSlot` trong model khi slot khác (vd 'post.video_file').
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : MediaAsset|id + slot alias.
 * - OUTPUT: MediaItemDto|string|int|bool|resource.
 * =====================================================================
 */
trait HasVideoAsset
{
    use HasMediaSlots;

    /**
     * =====================================================================
     * CHỨC NĂNG: Lấy video chính của model
     * =====================================================================
     * INPUT: không. OUTPUT: ?MediaItemDto.
     * =====================================================================
     */
    public function video(): ?MediaItemDto
    {
        return $this->mediaItem($this->videoSlotName());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: URL video (chỉ trả khi asset public; private -> null)
     * =====================================================================
     * INPUT: không. OUTPUT: ?string.
     * =====================================================================
     */
    public function videoUrl(): ?string
    {
        $item = $this->video();

        return $item?->url;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Thời lượng video tính bằng ms (nếu đã lưu)
     * =====================================================================
     * INPUT: không. OUTPUT: ?int.
     * =====================================================================
     */
    public function videoDurationMs(): ?int
    {
        return $this->video()?->durationMs;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gán 1 asset video (thay thế cái cũ — maxItems=1)
     * =====================================================================
     * INPUT: MediaAsset|id. OUTPUT: MediaAssetUsage.
     * =====================================================================
     */
    public function setVideo(MediaAsset|int $asset)
    {
        return $this->attachMedia($asset, $this->videoSlotName(), null, true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Gỡ video khỏi slot
     * =====================================================================
     * INPUT: không. OUTPUT: int.
     * =====================================================================
     */
    public function clearVideo(): int
    {
        return $this->detachAllMedia($this->videoSlotName());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Có video không
     * =====================================================================
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function hasVideo(): bool
    {
        return $this->mediaCount($this->videoSlotName()) > 0;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Mở stream đọc file video cho FFmpeg/upload MXH
     * =====================================================================
     * INPUT: không. OUTPUT: resource PHP stream (caller fclose()).
     * EXCEPTION: RuntimeException khi không có video hoặc không đọc được.
     * =====================================================================
     */
    public function openVideoStream()
    {
        $item = $this->video();
        if (! $item) {
            throw new \RuntimeException('Model chưa có video để stream.');
        }

        return $this->mediaService()->openReadStream($item->id);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tên slot video — override bằng $videoSlot trong model
     * =====================================================================
     * INPUT: không. OUTPUT: string slot.
     * =====================================================================
     */
    protected function videoSlotName(): string
    {
        return property_exists($this, 'videoSlot')
            ? (string) $this->videoSlot
            : 'video';
    }
}
