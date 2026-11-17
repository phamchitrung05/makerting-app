<?php

namespace Modules\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Modules\CoreAi\Traits\HasAiProvenance;
use Modules\Identity\Models\ChannelGroup;
use Modules\Identity\Models\User;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model `media_assets` — lớp metadata chủ của file media.
 * =====================================================================
 *
 * `MediaAsset` là model nghiệp vụ (metadata) bọc file vật lý Spatie
 * MediaLibrary (collection 'library'): kind (image|video|audio|document|
 * archive), folder, visibility, channel_group_id, checksum, size, alt_text,
 * created_by, timestamps, softDeletes.
 *
 * `usages()` morphToMany qua bảng `media_asset_usages` — gán asset vào
 * Domain Model theo `slot` (post.thumbnail, social_variant.media...).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - usages()           : HasMany media_asset_usages
 * - channelGroup()     : belongsTo ChannelGroup
 * - creator()          : belongsTo User (created_by)
 * - scopeKind()/scopeVisibility()/scopePublic()/scopePrivate()
 * - scopeFolder()/scopeChannelGroup()
 * - registerMediaConversions() : thumb/featured/og cho ảnh
 * - isImage()/isVideo()/isAudio()/isDocument()/isArchive()
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : metadata nghiệp vụ + file qua Spatie MediaLibrary.
 * - OUTPUT: model chủ đọc/ghi/attach/detach theo slot.
 * =====================================================================
 */
class MediaAsset extends Model implements HasMedia
{
    use HasAiProvenance, InteractsWithMedia, SoftDeletes;

    protected $table = 'media_assets';

    /**
     * Tên collection Spatie mặc định gắn file gốc vào model.
     */
    public const COLLECTION_LIBRARY = 'library';

    /**
     * Danh sách kind hợp lệ của media asset.
     */
    public const KIND_IMAGE = 'image';
    public const KIND_VIDEO = 'video';
    public const KIND_AUDIO = 'audio';
    public const KIND_DOCUMENT = 'document';
    public const KIND_ARCHIVE = 'archive';
    public const KINDS = [
        self::KIND_IMAGE, self::KIND_VIDEO, self::KIND_AUDIO,
        self::KIND_DOCUMENT, self::KIND_ARCHIVE,
    ];

    /**
     * Visibility công khai (URL trực tiếp) hoặc private (stream ký quyền).
     */
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITIES = [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE];

    protected $fillable = [
        'channel_group_id', 'folder', 'kind', 'title', 'alt_text',
        'description', 'tags',
        'visibility', 'width', 'height', 'duration_ms', 'checksum_sha256',
        'created_by',
    ];

    protected $casts = [
        'channel_group_id' => 'integer',
        'tags' => 'array',
        'width' => 'integer',
        'height' => 'integer',
        'duration_ms' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * Giữ nguyên ký tự Unicode (tiếng Việt có dấu) khi serialize JSON xuống DB
     * để query tìm kiếm LIKE theo tags hoạt động chính xác.
     */
    protected function asJson($value, $flags = 0)
    {
        return parent::asJson($value, $flags | JSON_UNESCAPED_UNICODE);
    }

    /* =====================================================================
     * Quan hệ
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Các bản ghi usage gán asset vào domain model theo slot.
     * INPUT: không. OUTPUT: HasMany MediaAssetUsage. SIDE EFFECT: không.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaAssetUsage::class, 'media_asset_id');
    }

    /**
     * CHỨC NĂNG: Nhóm kênh chủ sở hữu asset (nullable = dùng chung).
     * INPUT: không. OUTPUT: BelongsTo ChannelGroup. SIDE EFFECT: không.
     */
    public function channelGroup(): BelongsTo
    {
        return $this->belongsTo(ChannelGroup::class, 'channel_group_id');
    }

    /**
     * CHỨC NĂNG: Người tạo asset.
     * INPUT: không. OUTPUT: BelongsTo User (created_by). SIDE EFFECT: không.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =====================================================================
     * Scopes
     * ===================================================================== */

    /** CHỨC NĂNG: Lọc theo kind (image|video|audio|document|archive). */
    public function scopeKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }

    /** CHỨC NĂNG: Lọc theo visibility (public|private). */
    public function scopeVisibility($query, string $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    /** CHỨC NĂNG: Chỉ asset công khai (URL trực tiếp). */
    public function scopePublic($query)
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }

    /** CHỨC NĂNG: Chỉ asset riêng tư (signed stream). */
    public function scopePrivate($query)
    {
        return $query->where('visibility', self::VISIBILITY_PRIVATE);
    }

    /** CHỨC NĂNG: Lọc theo folder (root|gallery|blog|social...). */
    public function scopeFolder($query, string $folder)
    {
        return $query->where('folder', $folder);
    }

    /** CHỨC NĂNG: Lọc theo nhóm kênh (null = dùng chung toàn hệ thống). */
    public function scopeChannelGroup($query, ?int $channelGroupId)
    {
        return $query->where('channel_group_id', $channelGroupId);
    }

    /* =====================================================================
     * Helpers
     * ===================================================================== */

    /** CHỨC NĂNG: Asset là ảnh? INPUT: không. OUTPUT: bool. */
    public function isImage(): bool
    {
        return $this->kind === self::KIND_IMAGE;
    }

    /** CHỨC NĂNG: Asset là video? */
    public function isVideo(): bool
    {
        return $this->kind === self::KIND_VIDEO;
    }

    /** CHỨC NĂNG: Asset là âm thanh? */
    public function isAudio(): bool
    {
        return $this->kind === self::KIND_AUDIO;
    }

    /** CHỨC NĂNG: Asset là tài liệu? */
    public function isDocument(): bool
    {
        return $this->kind === self::KIND_DOCUMENT;
    }

    /** CHỨC NĂNG: Asset là file nén? */
    public function isArchive(): bool
    {
        return $this->kind === self::KIND_ARCHIVE;
    }

    /**
     * CHỨC NĂNG: Trả về tên disk Spatie tương ứng với visibility.
     * INPUT: string $visibility ('public'|'private'). OUTPUT: string.
     */
    public static function diskForVisibility(string $visibility): string
    {
        return $visibility === self::VISIBILITY_PRIVATE
            ? 'media_private'
            : 'media_public';
    }

    /** CHỨC NĂNG: Lấy file media gốc Spatie (collection 'library'). */
    public function mediaFile(): ?Media
    {
        return $this->getFirstMedia(self::COLLECTION_LIBRARY);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Di chuyển file Spatie + conversions sang disk đích.
     * =====================================================================
     * Dùng chung cho MediaAssetController::update() và MediaUsageService::applyVisibility().
     * Spatie Media::move() tạo bản ghi Media mới và xóa bản ghi cũ, nên cần
     * xóa relation cache để các lần đọc mediaFile() tiếp theo thấy bản ghi mới.
     *
     * INPUT: string $newDisk ('media_public' | 'media_private').
     * OUTPUT: bool (true nếu đã đúng disk hoặc move thành công; false khi lỗi).
     * SIDE EFFECT: Di chuyển file vật lý + conversions hoặc ghi Log::warning.
     * =====================================================================
     */
    public function moveMediaFileToDisk(string $newDisk): bool
    {
        $media = $this->mediaFile();
        if (! $media || $media->disk === $newDisk) {
            return true;
        }

        try {
            $newMedia = $media->move($this, self::COLLECTION_LIBRARY, $newDisk);
            $this->unsetRelation('media');

            return $newMedia->disk === $newDisk;
        } catch (\Throwable $e) {
            Log::warning(
                "Không chuyển được file media {$media->id} sang {$newDisk}: {$e->getMessage()}",
            );

            return false;
        }
    }

    /* =====================================================================
     * Spatie MediaLibrary — đăng ký conversions
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo conversions cho ảnh (thumb/featured/og WebP).
     * =====================================================================
     * - thumb   : 320×240  — thumbnail danh sách/preview.
     * - featured: 1200×675 WebP — ảnh đại diện bài/trang.
     * - og      : 1200×630 WebP — Open Graph social share.
     * INPUT: ?Media $media (Spatie file đang add).
     * OUTPUT: void — khai báo conversions chỉ chạy trên mime ảnh.
     * SIDE EFFECT: chỉ đăng ký, không chạy (queue/sync khi addMedia()).
     * =====================================================================
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Chỉ áp conversion lên file ảnh; video/audio/document/archive bỏ qua.
        if (! $media || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $this->addMediaConversion('thumb')
            ->width(320)->height(240)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('featured')
            ->width(1200)->height(675)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('og')
            ->width(1200)->height(630)
            ->format('webp')
            ->nonQueued();
    }
}
