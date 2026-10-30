<?php

namespace Modules\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model pivot `media_asset_usages` — gán MediaAsset vào
 * Domain Model theo `slot` (post.thumbnail, social_variant.media...).
 * =====================================================================
 *
 * Cột `slot` là khóa logic vai trò media trên model, được `MediaSlotRegistry`
 * kiểm soát (`allowedKinds`, `maxItems`, `visibility`).
 * `sort_order` định thứ tự gallery/multi-media trong cùng slot.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - mediaAsset() : belongsTo MediaAsset.
 * - linkable()   : morphTo model được gán (Post/VideoProject/VisualStyle...).
 * INPUT/OUTPUT: bản ghi media_asset_usages.
 * =====================================================================
 */
class MediaAssetUsage extends Model
{
    protected $table = 'media_asset_usages';

    protected $fillable = [
        'media_asset_id', 'linkable_type', 'linkable_id', 'slot', 'sort_order',
    ];

    protected $casts = [
        'media_asset_id' => 'integer',
        'linkable_id' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * CHỨC NĂNG: MediaAsset được gán (FK cascade khi xóa).
     * INPUT: không. OUTPUT: BelongsTo MediaAsset. SIDE EFFECT: không.
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /**
     * CHỨC NĂNG: Model được gán media (đa hình — Post, VisualStyle...).
     * INPUT: không. OUTPUT: MorphTo model. SIDE EFFECT: không.
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
