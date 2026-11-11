<?php

namespace Modules\WritingStyle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model lịch sử giải mã với phiên bản prompt được ghim theo run.
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
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - casts(): Khai báo kiểu cast để đọc/ghi JSON, số và trạng thái model.
 * - booted(): Đăng ký hook gán UUID và source_hash trước khi tạo analysis.
 * - draftProfile(): Khai báo profile được gắn khi người dùng lưu kết quả phân tích.
 * - markRunning(): Chuyển analysis sang running và lưu DB.
 * - markSucceeded(): Lưu kết quả, detected tier, AI run ID và trạng thái succeeded.
 * - markFailed(): Lưu mã/thông báo lỗi và chốt trạng thái failed.
 * - markTransientError(): Ghi lỗi tạm thời và giữ running để job được retry.
 * - isSucceeded(): Kiểm tra analysis đã succeeded.
 * - isFailed(): Kiểm tra analysis đã failed.
 * - sourceHashOf(): Chuẩn hóa khoảng trắng và tạo hash nhận diện nguồn.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Thuộc tính analysis, reference_text, prompt_version và kết quả JSON.
 * - OUTPUT: Model trạng thái pending/running/succeeded/failed và quan hệ profile.
 *
 * BỐI CẢNH SỬ DỤNG: Worker cập nhật trạng thái; controller gắn draftProfile khi lưu hồ sơ.
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
        'prompt_version',
        'draft_profile_id',
        'error_code',
        'error_message',
        'expires_at',
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo kiểu cast để đọc/ghi JSON, số và trạng thái model
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array tên thuộc tính => kiểu cast
     *
     * SIDE EFFECT:
     * - Được Eloquent dùng khi truy cập thuộc tính; không tự truy vấn DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    protected function casts(): array
    {
        return [
            'result_json' => 'array',
            'prompt_version' => 'integer',
            'expires_at'  => 'datetime',
        ];
    }

    /* =====================================================================
     * BOOT — gán uuid cho bản ghi mới.
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký hook gán UUID và source_hash trước khi tạo analysis
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Gán uuid tự động khi creating (id có thể truyền sẵn).
     * INPUT: không. OUTPUT: void.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Đăng ký listener creating; callback bổ sung thuộc tính khi model được tạo.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi tạo model/DB có thể truyền lên. Không mở transaction.
     * =====================================================================
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
     * =====================================================================
     * CHỨC NĂNG: Khai báo profile được gắn khi người dùng lưu kết quả phân tích
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Profile được gắn khi người dùng Lưu trên trang create.
     * Phân tích KHÔNG tự tạo profile — `draft_profile_id` được điền bởi
     * `WritingProfileController@store` khi payload Lưu kèm analysis_id.
     * INPUT: không. OUTPUT: BelongsTo.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - BelongsTo theo draft_profile_id
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function draftProfile(): BelongsTo
    {
        return $this->belongsTo(WritingProfile::class, 'draft_profile_id');
    }

    /* =====================================================================
     * TRANSITION HELPERS
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển analysis sang running và lưu DB
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Chuyển trạng thái sang running.
     * INPUT: không. OUTPUT: self.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - self
     *
     * SIDE EFFECT:
     * - UPDATE analysis.status.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi DB truyền lên. Không mở transaction hoặc lock.
     * =====================================================================
     */
    public function markRunning(): self
    {
        $this->status = self::STATUS_RUNNING;
        $this->save();

        return $this;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lưu kết quả, detected tier, AI run ID và trạng thái succeeded
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Chuyển trạng thái sang succeeded + kết quả.
     * INPUT: resultJson, ?detectedTier, ?aiRunId, ?draftProfileId.
     * OUTPUT: self.
     * =====================================================================
     *
     * INPUT:
     * - array $resultJson, ?string $detectedTier = null, ?string $aiRunId = null, ?int $draftProfileId = null,
     *
     * OUTPUT:
     * - self
     *
     * SIDE EFFECT:
     * - UPDATE analysis, có thể gắn draftProfile nếu caller cung cấp.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi DB truyền lên. Không mở transaction hoặc lock.
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
     * =====================================================================
     * CHỨC NĂNG: Lưu mã/thông báo lỗi và chốt trạng thái failed
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Chuyển trạng thái sang failed + lỗi.
     * INPUT: errorCode, errorMessage. OUTPUT: self.
     *
     * INPUT:
     * - string $errorCode, string $errorMessage
     *
     * OUTPUT:
     * - self
     *
     * SIDE EFFECT:
     * - UPDATE status và error fields.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi DB truyền lên. Không mở transaction.
     * =====================================================================
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
     * CHỨC NĂNG: Ghi lỗi tạm thời và giữ running để job được retry
     * =====================================================================
     *
     * Chi tiết triển khai:
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
     *
     * INPUT:
     * - string $errorCode, string $errorMessage
     *
     * OUTPUT:
     * - self
     *
     * SIDE EFFECT:
     * - UPDATE error_code/error_message, giữ analysis chưa kết thúc.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi DB truyền lên. Không mở transaction.
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
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra analysis đã succeeded
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Phân tích đã kết thúc thành công.
     * INPUT: không. OUTPUT: bool.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra analysis đã failed
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Phân tích đã kết thúc thất bại.
     * INPUT: không. OUTPUT: bool.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa khoảng trắng và tạo hash nhận diện nguồn
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Hash sha256 của reference_text để dedupe.
     * =====================================================================
     * INPUT: reference_text. OUTPUT: string(64).
     * =====================================================================
     *
     * INPUT:
     * - string $referenceText
     *
     * OUTPUT:
     * - string SHA-256
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public static function sourceHashOf(string $referenceText): string
    {
        return hash('sha256', trim($referenceText));
    }
}
