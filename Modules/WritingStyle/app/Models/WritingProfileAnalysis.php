<?php

namespace Modules\WritingStyle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model `writing_profile_analyses` — lịch sử giải mã DNA.
 * =====================================================================
 *
 * Một bản ghi = 1 lần chạy "Giải mã văn phong" (AnalyzeWritingProfileJob):
 * nguồn mẫu (paste/url/file + reference_text + source_hash), trạng thái
 * pipeline (pending|running|succeeded|failed + ai_run_id + error_*), kết
 * quả (result_json + detected_tier) và profile được gắn khi người dùng Lưu
 * (draft_profile_id — phân tích KHÔNG tự tạo profile).
 *
 * `expires_at` cho phép tự hủy bản ghi (bảo mật reference_text mẫu).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - draftProfile()                : profile được tạo ra từ phân tích này.
 * - markRunning()/markSucceeded()/markFailed() : chuyển trạng thái.
 * - isCompleted()/isFailed()       : status helpers.
 * - sourceHashOf()                : sha256 của reference_text (dedupe).
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : attributes (source_type, reference_text, ...).
 * - OUTPUT: WritingProfileAnalysis model + transition helpers.
 * =====================================================================
 */
class WritingProfileAnalysis extends Model
{
    /* =====================================================================
     * HẰNG SỐ — Nguồn mẫu & trạng thái pipeline.
     * ===================================================================== */

    public const SOURCE_PASTE = 'paste';
    public const SOURCE_URL   = 'url';
    public const SOURCE_FILE  = 'file';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED    = 'failed';

    /** UUID PK (chuỗi không tăng tự). */
    protected $keyType = 'string';
    public $incrementing = false;

    protected $table = 'writing_profile_analyses';

    protected $fillable = [
        'id',
        'created_by',
        'name',
        'source_type',
        'source_url',
        'reference_text',
        'detected_tier',
        'source_hash',
        'status',
        'ai_run_id',
        'result_json',
        'draft_profile_id',
        'error_code',
        'error_message',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'result_json' => 'array',
            'expires_at'  => 'datetime',
        ];
    }

    /* =====================================================================
     * BOOT — gán uuid cho bản ghi mới.
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Gán uuid tự động khi creating (id có thể truyền sẵn).
     * INPUT: không. OUTPUT: void.
     */
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->id)) {
                $m->id = (string) Str::uuid();
            }
            // Gán source_hash nếu thiếu (dedupe ngay khi tạo).
            if (empty($m->source_hash) && ! empty($m->reference_text)) {
                $m->source_hash = self::sourceHashOf($m->reference_text);
            }
        });
    }

    /* =====================================================================
     * QUAN HỆ
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Profile được gắn khi người dùng Lưu trên trang create.
     * Phân tích KHÔNG tự tạo profile — `draft_profile_id` được điền bởi
     * `WritingProfileController@store` khi payload Lưu kèm analysis_id.
     * INPUT: không. OUTPUT: BelongsTo.
     */
    public function draftProfile(): BelongsTo
    {
        return $this->belongsTo(WritingProfile::class, 'draft_profile_id');
    }

    /* =====================================================================
     * TRANSITION HELPERS
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Chuyển trạng thái sang running.
     * INPUT: không. OUTPUT: self.
     */
    public function markRunning(): self
    {
        $this->status = self::STATUS_RUNNING;
        $this->save();

        return $this;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển trạng thái sang succeeded + kết quả.
     * INPUT: resultJson, ?detectedTier, ?aiRunId, ?draftProfileId.
     * OUTPUT: self.
     * =====================================================================
     */
    public function markSucceeded(
        array $resultJson,
        ?string $detectedTier = null,
        ?string $aiRunId = null,
        ?int $draftProfileId = null,
    ): self {
        $this->status = self::STATUS_SUCCEEDED;
        $this->result_json = $resultJson;
        if ($detectedTier !== null) {
            $this->detected_tier = $detectedTier;
        }
        if ($aiRunId !== null) {
            $this->ai_run_id = $aiRunId;
        }
        if ($draftProfileId !== null) {
            $this->draft_profile_id = $draftProfileId;
        }
        $this->save();

        return $this;
    }

    /**
     * CHỨC NĂNG: Chuyển trạng thái sang failed + lỗi.
     * INPUT: errorCode, errorMessage. OUTPUT: self.
     */
    public function markFailed(string $errorCode, string $errorMessage): self
    {
        $this->status = self::STATUS_FAILED;
        $this->error_code = $errorCode;
        $this->error_message = $errorMessage;
        $this->save();

        return $this;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghi lỗi TẠM THỜI nhưng GIỮ status `running` để queue retry.
     * =====================================================================
     * Khác `markFailed()`: không chuyển sang `failed`. Lỗi transient
     * (network/timeout/rate_limit/provider_error) sẽ được worker chạy lại
     * theo `$tries` của AnalyzeWritingProfileJob, nên bản ghi CHƯA kết thúc.
     *
     * Nếu ghi `failed` ngay tại đây, FE poll (`waitForAnalysis` coi `failed`
     * là trạng thái cuối) sẽ báo thất bại sai trong lúc attempt sau vẫn
     * đang chạy và có thể thành công — đồng thời mất `draft_profile_id`.
     * Chỉ hook `failed()` của job (hết lượt retry) mới được markFailed.
     *
     * INPUT:
     * - string $errorCode    : mã lỗi lần thử vừa rồi (để chẩn đoán).
     * - string $errorMessage : thông điệp lỗi lần thử vừa rồi.
     *
     * OUTPUT: self.
     * SIDE EFFECT: UPDATE error_code/error_message; status giữ nguyên `running`.
     * =====================================================================
     */
    public function markTransientError(string $errorCode, string $errorMessage): self
    {
        $this->status = self::STATUS_RUNNING;
        $this->error_code = $errorCode;
        $this->error_message = $errorMessage;
        $this->save();

        return $this;
    }

    /* =====================================================================
     * HELPERS
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Phân tích đã kết thúc thành công.
     * INPUT: không. OUTPUT: bool.
     */
    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    /**
     * CHỨC NĂNG: Phân tích đã kết thúc thất bại.
     * INPUT: không. OUTPUT: bool.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Hash sha256 của reference_text để dedupe.
     * =====================================================================
     * INPUT: reference_text. OUTPUT: string(64).
     * =====================================================================
     */
    public static function sourceHashOf(string $referenceText): string
    {
        return hash('sha256', trim($referenceText));
    }
}
