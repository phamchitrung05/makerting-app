<?php

namespace Modules\WritingStyle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\HasSlug;
use Modules\Taxonomy\Traits\HasTaxonomy;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model `writing_profiles` — hồ sơ DNA văn phong.
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
 * CÁC HÀM/METHOD TRONG FILE:
 * - rules()/evidence()/analyses() : casts + quan hệ.
 * - applicableRules(?tier)        : rules_json lọc theo cấp độ áp dụng.
 * - snapshotRules()                : rules_json loại bỏ evidence (dùng khi
 *                                   cấp prompt cho AI — tránh đạo văn).
 * - isActive()                     : status==='active' && is_enabled.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : attributes (name, rules_json, evidence_json, ...).
 * - OUTPUT: WritingProfile model + derived snapshot.
 * =====================================================================
 */
class WritingProfile extends Model
{
    use HasTaxonomy, HasSlug;

    /**
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
        'origin',
        'source_hash',
        'analysis_metadata_json',
        'version',
        'status',
        'is_enabled',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rules_json'                 => 'array',
            'evidence_json'              => 'array',
            'voice_texture_sliders_json' => 'array',
            'tone_sliders_json'          => 'array',
            'analysis_metadata_json'     => 'array',
            'is_enabled'                 => 'boolean',
            'version'                    => 'integer',
            'temperature'                => 'float',
        ];
    }

    /* =====================================================================
     * QUAN HỆ
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Danh sách phân tích đã tạo ra profile này.
     * INPUT: không. OUTPUT: HasMany.
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(WritingProfileAnalysis::class, 'draft_profile_id');
    }

    /* =====================================================================
     * SCOPE
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Chỉ profile đang dùng được (status active + enabled).
     * INPUT: query. OUTPUT: query đã lọc.
     */
    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE)
            ->where('is_enabled', true);
    }

    /**
     * CHỨC NĂNG: Profile đang bật (bỏ qua status).
     * INPUT: query. OUTPUT: query đã lọc.
     */
    public function scopeEnabled($q)
    {
        return $q->where('is_enabled', true);
    }

    /**
     * CHỨC NĂNG: Profile theo cấp độ độ dài chủ đạo.
     * INPUT: query + tier. OUTPUT: query đã lọc.
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
     * CHỨC NĂNG: Lấy rules_json lọc theo cấp độ độ dài áp dụng.
     * =====================================================================
     * Lọc theo config('writingstyle.tier_drop_rules') (PLAN-WRITING-STYLE-
     * PROMPT-V2 §3): micro giữ 7 lõi, short 10, medium 14, long 15.
     * Các khóa bị lược bỏ KHÔNG đưa vào rules trả về — `uncertainties` cũng
     * được tách ra khỏi output (chỉ là báo cáo cho người đọc lúc phân tích;
     * nhúng vào prompt sinh bài sẽ làm loãng sự tập trung của LLM và ép AI
     * viết cẩn trọng/công thức).
     *
     * INPUT: ?string $tier (null -> dominant_tier của profile).
     * OUTPUT: array rules_json đã lọc (không chứa uncertainties).
     * =====================================================================
     */
    public function applicableRules(?string $tier = null): array
    {
        $rules = $this->rules_json ?? [];
        $tier = $tier ?? $this->dominant_tier;

        $drop = (array) (config('writingstyle.tier_drop_rules', [])[$tier] ?? []);
        if ($drop !== []) {
            foreach ($drop as $key) {
                unset($rules[$key]);
            }
        }

        // `uncertainties` là ghi chú báo cáo — không bao giờ vào prompt sinh bài.
        unset($rules['uncertainties']);

        return $rules;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Snapshot an toàn để cấp cho AI sinh văn bản.
     * =====================================================================
     * Trả rules_json đã lọc theo tier + loại bỏ mọi trường chứa trích dẫn
     * mẫu (`evidence_json`) — AI học cách hành văn nhưng KHÔNG BAO GIỜ nhìn
     * thấy câu chữ thật để đạo văn (PLAN.md §309-310).
     *
     * INPUT: ?string $tier. OUTPUT: array — rules_json sạch.
     * =====================================================================
     */
    public function snapshotRules(?string $tier = null): array
    {
        return $this->applicableRules($tier);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Profile có đang dùng được (active + enabled).
     * INPUT: không. OUTPUT: bool.
     * =====================================================================
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->is_enabled;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Helper lấy số câu/từ từ analysis_metadata (để detectTier
     * không phải đếm lại nếu đã lưu).
     * INPUT: không. OUTPUT: array{word_count:int,sentence_count:int}.
     * =====================================================================
     */
    public function metadataStats(): array
    {
        $meta = $this->analysis_metadata_json ?? [];

        return [
            'word_count'     => (int) ($meta['word_count'] ?? 0),
            'sentence_count' => (int) ($meta['sentence_count'] ?? 0),
        ];
    }
}
