<?php

namespace Modules\Taxonomy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model pivot `taggables` — liên kết Tag ↔ model.
 * =====================================================================
 */
class Taggable extends Model
{
    protected $table = 'taggables';

    protected $fillable = [
        'tag_id', 'taggable_type', 'taggable_id',
    ];

    /** Tag của dòng pivot. */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }

    /** Model được gán (Post, VisualStyle, Campaign...). */
    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }
}
