<?php

namespace Modules\WritingStyle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\HasSlug;
use Modules\Taxonomy\Traits\HasTaxonomy;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model hồ sơ văn phong cùng quan hệ phân tích, taxonomy và slug.
 * =====================================================================
 *
 * Một profile = "bộ gen" 16 khóa thuộc tính văn phong (`rules_json`) trả
 * lời câu hỏi "yếu tố này trong bài mẫu là gì" — kèm trích dẫn thực
 * (`evidence_json`, chỉ excerpt có thật trong reference_text), hướng dẫn
 * hành văn (`style_instructions`), chỉ dẫn chất văn (`voice_texture_instructions`
 * + `voice_texture_sliders_json`), cấp độ độ dài chủ đạo (`dominant_tier`)
 * và nguồn gốc (`origin` manual/reference).
 *
 * `version` tăng mỗi lần sửa; `status` chỉ 'active' mới được dùng để sinh.
 * `HasTaxonomy` scope 'writing_profile' cho phân loại chéo.
 *
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - booted(): Đăng ký hook xóa sạch slug khi profile bị xóa.
 * - casts(): Khai báo kiểu cast để đọc/ghi JSON, số và trạng thái model.
 * - analyses(): Khai báo quan hệ các analysis gắn với profile.
 * - scopeActive(): Giới hạn query ở profile status=active và is_enabled=true.
 * - scopeEnabled(): Giới hạn query ở profile is_enabled=true.
 * - scopeOfTier(): Giới hạn query theo dominant_tier.
 * - isActive(): Kiểm tra đồng thời status active và is_enabled.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Thuộc tính writing_profiles, JSON V3 và quan hệ Eloquent.
 * - OUTPUT: Model, query scopes, rules được lọc theo tier hoặc trạng thái active.
 *
 * BỐI CẢNH SỬ DỤNG: CRUD lưu các giá trị này; compiler đọc hồ sơ để xây prompt.
 * =====================================================================
 */
class WritingProfile extends Model
{
    use HasTaxonomy, HasSlug;

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký hook xóa sạch slug khi profile bị xóa
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * XÓA CỨNG THẬT — giải phóng slug ngay khi xóa profile.
     * =====================================================================
     * Yêu cầu nghiệp vụ (doc §10.5): KHÔNG giữ lịch sử slug — khi profile
     * bị xóa, slug phải được giải phóng để tái sử dụng. Hook deleted() mặc
     * định của HasSlug chỉ hạ is_primary (thiết kế cho redirect 301 của nội
     * dung public), trái yêu cầu này — nên đăng ký thêm listener DELETE HẾT
     * row slugs. Listener của trait chạy trước (hạ primary), listener này
     * chạy sau (xóa sạch) — kết quả cuối: bảng slugs không còn row nào.
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Đăng ký listener deleted; lúc xóa model sẽ DELETE slugs sau hook trait.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi DB ở callback truyền lên. Hook không tự mở transaction.
     * =====================================================================
     */
    protected static function booted(): void
    {
        static::deleted(function (WritingProfile $model): void {
            // Mass-delete qua builder — không bắn model event cho từng Slug.
            $model->slugs()->delete();
        });
    }

    /* =====================================================================
     * HẰNG SỐ — 16 khóa thuộc tính DNA văn phong (PLAN.md §300).
     * ===================================================================== */

    public const RULE_KEYS = [
        'tone',
        'pronouns',
        'emotion',
        'opening',
        'sentence_rhythm',
        'paragraph_rhythm',
        'transitions',
        'vocabulary',
        'technical_terms',
        'structure_patterns',
        'headings',
        'bullets',
        'examples',
        'ending',
        'avoid',
        'uncertainties',
    ];

    /* =====================================================================
     * HẰNG SỐ — Cấp độ độ dài (LengthTier) theo PLAN.md §302-307.
     * ===================================================================== */

    public const TIER_MICRO  = 'micro';   // 1–3 câu, < 60 từ (status, caption)
    public const TIER_SHORT  = 'short';   // 4–10 câu, 60–180 từ (FB ngắn)
    public const TIER_MEDIUM = 'medium';  // 11–30 câu, 180–600 từ (storytelling)
    public const TIER_LONG   = 'long';    // > 600 từ (blog, kịch bản dài)

    public const TIERS = [
        self::TIER_MICRO,
        self::TIER_SHORT,
        self::TIER_MEDIUM,
        self::TIER_LONG,
    ];

    /* =====================================================================
     * HẰNG SỐ — 5 trục thuộc tính Chất văn (Voice Texture Axes) & 8 trục Sắc thái (Tone Axes).
     * ===================================================================== */

    public const VOICE_TEXTURE_KEYS = [
        'voice_timbre',
        'vernacular_flow',
        'emotional_nuance',
        'rhythm_variation',
        'emphasis_glide',
    ];

    public const TONE_AXIS_KEYS = [
        'humor',
        'empathy',
        'assertiveness',
        'formality',
        'inspiration',
        'candor',
        'depth',
        'urgency',
    ];

    /* =====================================================================
     * HẰNG SỐ — Nguồn gốc & trạng thái.
     * ===================================================================== */

    public const ORIGIN_MANUAL    = 'manual';
    public const ORIGIN_REFERENCE = 'reference';

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_ACTIVE = 'active';

    /** Scope taxonomy riêng của module. */
    protected $taxonomyScope = 'writing_profile';

    /** Cột nguồn slug = 'name' (trait mặc định 'title' — không có cột đó). */
    protected string $slugSource = 'name';

    protected $fillable = [
        'name',
        'description',
        'dominant_tier',
        'temperature',
        'rules_json',
        'evidence_json',
        'style_instructions',
        'voice_texture_instructions',
        'voice_texture_sliders_json',
        'tone_sliders_json',
        'tone_instructions',
        'sandbox_samples_json',
        'origin',
        'source_hash',
        'analysis_metadata_json',
        'style_analysis_json',
        'slider_preferences_json',
        'version',
        'status',
        'is_enabled',
        'created_by',
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
            'rules_json'                 => 'array',
            'evidence_json'              => 'array',
            'voice_texture_sliders_json' => 'array',
            'tone_sliders_json'          => 'array',
            'sandbox_samples_json'       => 'array',
            'analysis_metadata_json'     => 'array',
            'style_analysis_json'        => 'array',
            'slider_preferences_json'    => 'array',
            'is_enabled'                 => 'boolean',
            'version'                    => 'integer',
            'temperature'                => 'float',
        ];
    }

    /* =====================================================================
     * QUAN HỆ
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo quan hệ các analysis gắn với profile
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Danh sách phân tích đã tạo ra profile này.
     * INPUT: không. OUTPUT: HasMany.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - HasMany theo draft_profile_id
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(WritingProfileAnalysis::class, 'draft_profile_id');
    }

    /* =====================================================================
     * SCOPE
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Giới hạn query ở profile status=active và is_enabled=true
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Chỉ profile đang dùng được (status active + enabled).
     * INPUT: query. OUTPUT: query đã lọc.
     *
     * INPUT:
     * - $q
     *
     * OUTPUT:
     * - Query builder đã lọc, không thực thi truy vấn
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE)
            ->where('is_enabled', true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Giới hạn query ở profile is_enabled=true
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Profile đang bật (bỏ qua status).
     * INPUT: query. OUTPUT: query đã lọc.
     *
     * INPUT:
     * - $q
     *
     * OUTPUT:
     * - Query builder đã lọc
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function scopeEnabled($q)
    {
        return $q->where('is_enabled', true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Giới hạn query theo dominant_tier
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Profile theo cấp độ độ dài chủ đạo.
     * INPUT: query + tier. OUTPUT: query đã lọc.
     *
     * INPUT:
     * - $q, string $tier
     *
     * OUTPUT:
     * - Query builder đã lọc
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function scopeOfTier($q, string $tier)
    {
        return $q->where('dominant_tier', $tier);
    }

    /* =====================================================================
     * LOGIC
     * ===================================================================== */


    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra đồng thời status active và is_enabled
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * CHỨC NĂNG: Profile có đang dùng được (active + enabled).
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool trạng thái có thể sử dụng
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->is_enabled;
    }
}
