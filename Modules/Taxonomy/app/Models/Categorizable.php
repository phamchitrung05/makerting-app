<?php

namespace Modules\Taxonomy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model pivot `categorizables` — liên kết Category ↔ model.
 * =====================================================================
 */
class Categorizable extends Model
{
    protected $table = 'categorizables';

    protected $fillable = [
        'category_id', 'categorizable_type', 'categorizable_id', 'sort_order',
    ];

    /** Category của dòng pivot. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /** Model được gán (Post, VisualStyle, Campaign...). */
    public function categorizable(): MorphTo
    {
        return $this->morphTo();
    }
}
