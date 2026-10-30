<?php

namespace Modules\TaskCenter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model SystemTaskRun — 1 dòng nhật ký tiến trình ngầm.
 * =====================================================================
 *
 * UUID PK, trạng thái queued/running/completed/partial/failed/cancelled,
 * progress 0-100, morph taskable về model gốc + meta_json linh hoạt.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - taskable()      : morphTo model gốc (Post/VideoProject/SocialPost...).
 * - scopeRunning()  : lọc status running.
 * - scopeActive()   : lọc queued+running (cho popup).
 * - isRunning()     : bool đang chạy.
 * - isFinished()    : bool kết thúc (completed/partial/failed/cancelled).
 * INPUT/OUTPUT: bản ghi system_task_runs.
 * =====================================================================
 */
class SystemTaskRun extends Model
{
    /** Bảng và khóa chính UUID. */
    protected $table = 'system_task_runs';
    protected $keyType = 'string';
    public $incrementing = false;

    /** Trạng thái hợp lệ của tiến trình. */
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_COMPLETED,
        self::STATUS_PARTIAL, self::STATUS_FAILED, self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'id', 'dedupe_key', 'module', 'task_type', 'title', 'status',
        'progress', 'channel_group_id', 'taskable_type', 'taskable_id',
        'initiated_by', 'meta_json', 'error_code', 'error_message',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'meta_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Tự gán UUID khi tạo nếu chưa có id. */
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->id)) { $m->id = (string) Str::uuid(); }
        });
    }

    /**
     * CHỨC NĂNG: Quan hệ morph về model gốc của task.
     * INPUT: không. OUTPUT: MorphTo. SIDE EFFECT: không.
     */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * CHỨC NĂNG: Scope lọc các task đang queued hoặc running (cho popup).
     * INPUT: query. OUTPUT: Builder. SIDE EFFECT: không.
     */
    public function scopeActive($q)
    {
        return $q->whereIn('status', [self::STATUS_QUEUED, self::STATUS_RUNNING]);
    }

    /**
     * CHỨC NĂNG: Scope lọc task đang chạy.
     * INPUT: query. OUTPUT: Builder.
     */
    public function scopeRunning($q)
    {
        return $q->where('status', self::STATUS_RUNNING);
    }

    /**
     * CHỨC NĂNG: Task đang chạy hay xếp hàng?
     * INPUT: không. OUTPUT: bool.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    /**
     * CHỨC NĂNG: Task đã kết thúc (mọi trạng thái cuối)?
     * INPUT: không. OUTPUT: bool.
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED, self::STATUS_PARTIAL,
            self::STATUS_FAILED, self::STATUS_CANCELLED,
        ], true);
    }
}
