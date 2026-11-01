<?php

namespace Modules\CoreAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model `ai_run_steps` — bước con idempotent trong ai_run.
 * =====================================================================
 *
 * Pipeline nhiều bước (analyze → draft → edit...) ghi từng bước vào đây.
 * `step_key` định danh bước; `input_hash` = sha256(input của bước) — unique
 * cùng (ai_run_id, step_key) cho phép resume: bước đã chạy với input y
 * hệt → reuse `output_json`, không gọi lại AI (idempotent, tiết kiệm).
 *
 * `latency_ms` + `diagnostics_json` phục vụ quan sát hiệu năng từng bước.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - run()                  : BelongsTo AiRun.
 * - scopeRun()/scopeStep() : lọc theo run/step_key.
 * - hashInput()            : sha256 một mảng input (helper tĩnh).
 * - findCached()           : tìm step đã ready cho (run, step, input) —
 *                          dùng để reuse thay vì gọi lại AI.
 * - markRunning()/markReady()/markFailed()/markSkipped() : chuyển trạng thái.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : ai_run_id + step_key + input (để hash) + output khi xong.
 * - OUTPUT: bản ghi idempotent + diagnostics cho resume/audit.
 * =====================================================================
 */
class AiRunStep extends Model
{
    protected $table = 'ai_run_steps';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'ai_run_id', 'step_key', 'input_hash', 'status',
        'output_json', 'diagnostics_json', 'latency_ms',
    ];

    protected $casts = [
        'output_json' => 'array',
        'diagnostics_json' => 'array',
        'latency_ms' => 'integer',
    ];

    /** INPUT: không. OUTPUT: BelongsTo AiRun. */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /** INPUT: query + ai_run_id. OUTPUT: query đã lọc. */
    public function scopeRun($query, string $runId)
    {
        return $query->where('ai_run_id', $runId);
    }

    /** INPUT: query + step_key. OUTPUT: query đã lọc. */
    public function scopeStep($query, string $stepKey)
    {
        return $query->where('step_key', $stepKey);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: sha256 một mảng input — chuẩn chung cho mọi step.
     * INPUT: array input. OUTPUT: string 64 hex.
     * =====================================================================
     */
    public static function hashInput(array $input): string
    {
        return hash('sha256', json_encode($input));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tìm step đã hoàn thành cho bộ (run, step_key, input) —
     * nếu có thì reuse output, không cần gọi AI lại.
     * =====================================================================
     * INPUT: runId + stepKey + input array.
     * OUTPUT: ?AiRunStep — bản ghi ready nếu tồn tại, null nếu chưa.
     * SIDE EFFECT: SELECT ai_run_steps.
     * =====================================================================
     */
    public static function findCached(string $runId, string $stepKey, array $input): ?self
    {
        return static::query()
            ->where('ai_run_id', $runId)
            ->where('step_key', $stepKey)
            ->where('input_hash', self::hashInput($input))
            ->where('status', self::STATUS_READY)
            ->first();
    }

    /** INPUT: không. OUTPUT: void — status=running. */
    public function markRunning(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->save();
    }

    /**
     * INPUT: array output + ?latencyMs. OUTPUT: void — status=ready.
     * SIDE EFFECT: UPDATE output_json/latency_ms/status.
     */
    public function markReady(array $output, ?int $latencyMs = null): void
    {
        $this->output_json = $output;
        if ($latencyMs !== null) {
            $this->latency_ms = $latencyMs;
        }
        $this->status = self::STATUS_READY;
        $this->save();
    }

    /** INPUT: ?array diagnostics. OUTPUT: void — status=failed. */
    public function markFailed(?array $diagnostics = null): void
    {
        if ($diagnostics !== null) {
            $this->diagnostics_json = $diagnostics;
        }
        $this->status = self::STATUS_FAILED;
        $this->save();
    }

    /** INPUT: ?array diagnostics. OUTPUT: void — status=skipped. */
    public function markSkipped(?array $diagnostics = null): void
    {
        if ($diagnostics !== null) {
            $this->diagnostics_json = $diagnostics;
        }
        $this->status = self::STATUS_SKIPPED;
        $this->save();
    }
}