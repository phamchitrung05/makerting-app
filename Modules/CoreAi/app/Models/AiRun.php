<?php

namespace Modules\CoreAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Identity\Models\User;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model `ai_runs` — một lượt chạy AI end-to-end.
 * =====================================================================
 *
 * UUID PK cho phép client/hàng đợi biết id trước khi insert. `parent_id`
 * + `session_id` nhóm các lần "tạo lại" (regenerate); `generation_no`
 * đếm số lần regenerate trong cùng session. `module`/`operation` ghi module
 * gọi (blog.write, visual_style.analyze...) để truy vết và lọc báo cáo.
 *
 * `connection_snapshot_json` chụp cấu hình provider+model lúc chạy —
 * KHÔNG chứa api_key/extra_keys. `expires_at` cho phép GC run nháp.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - steps()/children()/parent()/initiator() : quan hệ.
 * - scopeStatus()/scopeModule()/scopeSession()/scopeExpired() : lọc.
 * - markRunning()/markReady()/markPartial()/markApplied()/markFailed()
 *   /markCancelled()/markExpired() : chuyển trạng thái an toàn.
 * - isTerminal()/inputHash()/nextGeneration() : tiện ích.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : module/operation/input + connection snapshot tại lúc tạo.
 * - OUTPUT: trạng thái + output + diagnostics cho caller và audit.
 * =====================================================================
 */
class AiRun extends Model
{
    protected $table = 'ai_runs';

    public $incrementing = false;
    protected $keyType = 'string';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_READY = 'ready';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUSES = [
        self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_READY,
        self::STATUS_PARTIAL, self::STATUS_APPLIED, self::STATUS_FAILED,
        self::STATUS_CANCELLED, self::STATUS_EXPIRED,
    ];

    /** Trạng thái kết thúc — không chuyển tiếp được nữa. */
    public const TERMINAL_STATUSES = [
        self::STATUS_APPLIED, self::STATUS_FAILED,
        self::STATUS_CANCELLED, self::STATUS_EXPIRED,
    ];

    /** Mặc định status/progress/generation_no khi tạo run mới. */
    protected $attributes = [
        'status' => self::STATUS_QUEUED,
        'progress' => 0,
        'generation_no' => 1,
    ];

    protected $fillable = [
        'id', 'parent_id', 'session_id', 'module', 'operation',
        'generation_no', 'status', 'progress',
        'connection_snapshot_json', 'input_json', 'output_json',
        'diagnostics_json', 'error_code', 'error_message',
        'initiated_by', 'expires_at',
    ];

    protected $casts = [
        'generation_no' => 'integer',
        'progress' => 'integer',
        'connection_snapshot_json' => 'array',
        'input_json' => 'array',
        'output_json' => 'array',
        'diagnostics_json' => 'array',
        'expires_at' => 'datetime',
    ];
    /**
     * =====================================================================
     * CHỨC NĂNG: Tự sinh UUID + session_id khi tạo (nếu chưa cung cấp).
     * =====================================================================
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: gán id/session_id nếu trống.
     * =====================================================================
     */
    protected static function booted(): void
    {
        static::creating(function (AiRun $run): void {
            if (! $run->id) {
                $run->id = (string) Str::uuid();
            }
            if (! $run->session_id) {
                $run->session_id = (string) Str::uuid();
            }
        });
    }

    /** INPUT: không. OUTPUT: HasMany AiRunStep. */
    public function steps(): HasMany
    {
        return $this->hasMany(AiRunStep::class, 'ai_run_id');
    }

    /** INPUT: không. OUTPUT: HasMany AiRun (các run con regenerate). */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** INPUT: không. OUTPUT: BelongsTo AiRun (run cha). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** INPUT: không. OUTPUT: BelongsTo User. */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** INPUT: query + status. OUTPUT: query đã lọc. */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /** INPUT: query + module. OUTPUT: query đã lọc. */
    public function scopeModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /** INPUT: query + session uuid. OUTPUT: query đã lọc. */
    public function scopeSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /** INPUT: query. OUTPUT: query các run quá hạn nhưng chưa mark expired. */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNotIn('status', self::TERMINAL_STATUSES);
    }
    /** INPUT: không. OUTPUT: void — status=running, progress>=1. */
    public function markRunning(): void
    {
        $this->transition(self::STATUS_RUNNING, max(1, (int) $this->progress));
    }

    /** INPUT: ?array output. OUTPUT: void — status=ready, progress=100. */
    public function markReady(?array $output = null): void
    {
        if ($output !== null) {
            $this->output_json = $output;
        }
        $this->transition(self::STATUS_READY, 100);
    }

    /** INPUT: array output + progress. OUTPUT: void — status=partial. */
    public function markPartial(array $output, int $progress): void
    {
        $this->output_json = $output;
        $this->transition(self::STATUS_PARTIAL, $progress);
    }

    /** INPUT: không. OUTPUT: void — status=applied. */
    public function markApplied(): void
    {
        $this->transition(self::STATUS_APPLIED, 100);
    }

    /** INPUT: code + message. OUTPUT: void — status=failed + error fields. */
    public function markFailed(string $code, string $message): void
    {
        $this->error_code = $code;
        $this->error_message = $message;
        $this->transition(self::STATUS_FAILED, $this->progress);
    }

    /** INPUT: không. OUTPUT: void — status=cancelled. */
    public function markCancelled(): void
    {
        $this->transition(self::STATUS_CANCELLED, $this->progress);
    }

    /** INPUT: không. OUTPUT: void — status=expired. */
    public function markExpired(): void
    {
        $this->transition(self::STATUS_EXPIRED, $this->progress);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Run đã ở trạng thái kết thúc chưa.
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: sha256(input_json) — khóa idempotent cho step con.
     * INPUT: không. OUTPUT: string 64 hex.
     * =====================================================================
     */
    public function inputHash(): string
    {
        return hash('sha256', json_encode($this->input_json ?? []));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo run con regenerate cùng session, generation_no = max+1.
     * =====================================================================
     * INPUT: array $overrides (ghi đè input/operation nếu cần).
     * OUTPUT: AiRun mới (chưa chạy, status=queued).
     * SIDE EFFECT: INSERT ai_runs mới.
     * =====================================================================
     */
    public function nextGeneration(array $overrides = []): self
    {
        return self::create(array_merge([
            'parent_id' => $this->id,
            'session_id' => $this->session_id,
            'module' => $this->module,
            'operation' => $this->operation,
            'generation_no' => (int) static::query()
                ->where('session_id', $this->session_id)
                ->max('generation_no') + 1,
            'status' => self::STATUS_QUEUED,
            'progress' => 0,
            'connection_snapshot_json' => $this->connection_snapshot_json,
            'input_json' => $this->input_json,
            'initiated_by' => $this->initiated_by,
        ], $overrides));
    }

    /**
     * Ghi status + progress an toàn (không cho lùi status terminal).
     * INPUT: status + progress. OUTPUT: void.
     * SIDE EFFECT: UPDATE status/progress.
     */
    protected function transition(string $status, ?int $progress = null): void
    {
        if ($this->isTerminal()) {
            return;
        }

        $this->status = $status;
        if ($progress !== null) {
            $this->progress = max(0, min(100, $progress));
        }
        $this->save();
    }
}