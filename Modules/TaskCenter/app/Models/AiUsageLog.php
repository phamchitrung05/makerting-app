<?php

namespace Modules\TaskCenter\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model AiUsageLog — 1 dòng nhật ký chi phí AI.
 * =====================================================================
 * Append-only (chỉ created_at). capability: text|vision|image|tts|stt.
 * =====================================================================
 */
class AiUsageLog extends Model
{
    protected $table = 'ai_usage_logs';

    /** Bảng log chỉ có created_at — tắt timestamps mặc định. */
    public $timestamps = false;

    public const CAP_TEXT = 'text';
    public const CAP_VISION = 'vision';
    public const CAP_IMAGE = 'image';
    public const CAP_TTS = 'tts';
    public const CAP_STT = 'stt';

    protected $fillable = [
        'ai_run_id', 'channel_group_id', 'social_account_id',
        'provider', 'model', 'capability',
        'input_units', 'output_units', 'estimated_cost_usd',
    ];

    protected $casts = [
        'input_units' => 'integer',
        'output_units' => 'integer',
        'estimated_cost_usd' => 'decimal:6',
        'created_at' => 'datetime',
    ];
}
