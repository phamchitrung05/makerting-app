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
 * hành văn (`style_instructions`), cấp độ độ dài chủ đạo (`dominant_tier`)
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
        'rules_json',
        'evidence_json',
        'style_instructions',
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
            'rules_json'             => 'array',
            'evidence_json'          => 'array',
            'analysis_metadata_json' => 'array',
            'is_enabled'             => 'boolean',
            'version'                => 'integer',
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
     * Với tier `micro`, các khóa chỉ có nghĩa ở văn bản dài (`headings`,
     * `bullets`, `paragraph_rhythm`, `structure_patterns`) được đẩy vào
     * `uncertainties` (không ép AI bịa) — theo PLAN.md §304.
     * Tier khác trả về toàn bộ rules_json hiện có.
     *
     * INPUT: ?string $tier (null -> dominant_tier của profile).
     * OUTPUT: array rules_json đã lọc.
     * =====================================================================
     */
    public function applicableRules(?string $tier = null): array
    {
        $rules = $this->rules_json ?? [];
        $tier = $tier ?? $this->dominant_tier;

        // Các khóa chỉ có nghĩa ở văn bản dài — lọc theo tier + đẩy vào
        // uncertainties để AI biết "không có trong mẫu" (không bịa):
        //   micro : bỏ headings/bullets/paragraph_rhythm/structure_patterns.
        //   short : bỏ headings/bullets/structure_patterns (giữ paragraph_rhythm).
        $drop = match ($tier) {
            self::TIER_MICRO => ['headings', 'bullets', 'paragraph_rhythm', 'structure_patterns'],
            self::TIER_SHORT => ['headings', 'bullets', 'structure_patterns'],
            default          => [],
        };

        if ($drop !== []) {
            $uncertainties = (array) ($rules['uncertainties'] ?? []);

            foreach ($drop as $key) {
                if (array_key_exists($key, $rules)) {
                    $uncertainties[] = "{$key}: không áp dụng ở văn bản {$tier} (mẫu không có)";
                    unset($rules[$key]);
                }
            }
            $rules['uncertainties'] = $uncertainties;
        }

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
