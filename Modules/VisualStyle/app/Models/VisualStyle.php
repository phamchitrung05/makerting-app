<?php

namespace Modules\VisualStyle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\HasSlug;
use Modules\Media\Models\MediaAsset;
use Modules\CoreAi\Traits\HasAiProvenance;
use Modules\Media\Traits\HasMediaSlots;
use Modules\Taxonomy\Traits\HasTaxonomy;
use Modules\VisualStyle\Enums\VisualStyleStatus;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model DNA ảnh nhiếp ảnh cùng góc chụp và media tham chiếu.
 * =====================================================================
 *
 * Một visual_style là "bộ gen ảnh" giải mã từ 1 ảnh tham chiếu bằng Vision
 * AI, gồm `dna_json` 5 nhóm enum bắt buộc (composition, camera, lighting,
 * environment, color_mood) + `subject_rules` + `style_constraints` +
 * `confidence` + `taxonomy_suggestion`. Kèm `compiled_prompt_preview`
 * (prompt 7 lớp đã ghép), `negative_prompt` và `reference_media_id` trỏ tới
 * MediaAsset ảnh mẫu.
 *
 * `version` tăng mỗi lần sửa; `status` theo vòng đời draft→analyzing→
 * analyzed→active→archived (failed nếu job lỗi). Chỉ 'active' + enabled
 * được dùng để sinh ảnh.
 *
 * Traits:
 * - HasSlug     : slug tự sinh từ `name` (source override `slugSource`).
 * - HasTaxonomy : scope 'visual_style' cho phân loại chéo.
 * - HasMediaSlots('visual_style.reference') : gán ảnh tham chiếu qua slot.
 * - HasAiProvenance: truy vết trường DNA do AI tạo (ai_run_id).
 *
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - booted(): Xóa sạch slug khi style bị xóa (giải phóng slug).
 * - casts(): Khai báo kiểu cast cho dna_json, version, analyzed_at.
 * - shots(): Khai báo quan hệ các góc chụp con.
 * - referenceMedia(): Khai báo quan hệ media ảnh tham chiếu.
 * - scopeActive(): Lọc style status=active và is_enabled=true.
 * - scopeEnabled(): Lọc style is_enabled=true.
 * - isActive(): Kiểm tra đồng thời status active và is_enabled.
 * - isUsable(): Kiểm tra đã có DNA (analyzed/active) để sinh ảnh.
 * - markAnalyzing(): Chuyển status sang analyzing (job đã nhận).
 * - markAnalyzed(): Chuyển status sang analyzed + analyzed_at.
 * - markFailed(): Chuyển status sang failed.
 * - markActive(): Kích hoạt style sẵn sàng dùng.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Thuộc tính visual_styles, dna_json và quan hệ Eloquent.
 * - OUTPUT: Model, query scopes và hàm trạng thái đọc/ghi.
 *
 * BỐI CẢNH SỬ DỤNG: Controller CRUD, analyzer job, prompt builder và
 * snapshot service đọc/ghi DNA; module Tầng 3 gọi VisualStyleSnapshotService
 * để lấy DNA đã lọc trường nội bộ.
 * =====================================================================
 */
class VisualStyle extends Model
{
    use HasTaxonomy, HasSlug, HasMediaSlots, HasAiProvenance;

    /** Scope taxonomy riêng của module. */
    protected $taxonomyScope = 'visual_style';

    /** Cột nguồn slug = 'name'. */
    protected string $slugSource = 'name';

    /** Map alias slot media tham chiếu DNA. */
    protected array $mediaSlots = [
        'reference' => 'visual_style.reference',
    ];

    protected $fillable = [
        'name',
        'description',
        'reference_media_id',
        'dna_json',
        'schema_version',
        'compiled_prompt_preview',
        'negative_prompt',
        'analysis_meta_json',
        'version',
        'status',
        'is_enabled',
        'analyzed_at',
        'created_by',
        'updated_by',
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo kiểu cast cho dna_json, version và analyzed_at
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
            'dna_json'    => 'array',
            'analysis_meta_json' => 'array',
            'is_enabled'  => 'boolean',
            'version'     => 'integer',
            'analyzed_at' => 'datetime',
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đăng ký hook xóa sạch slug khi style bị xóa
     * =====================================================================
     *
     * Chi tiết triển khai:
     * =====================================================================
     * XÓA CỨNG THẬT — giải phóng slug ngay khi xóa style.
     * =====================================================================
     * Giống WritingProfile: giải phóng slug để tái sử dụng khi style bị xóa.
     * Hook deleted() của HasSlug chỉ hạ is_primary; listener này chạy sau
     * (xóa hết row slugs).
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
        static::deleted(function (VisualStyle $model): void {
            $model->slugs()->delete();
        });
    }

    /* =====================================================================
     * QUAN HỆ
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo quan hệ các góc chụp con
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Danh sách góc chụp (shots) của style, sắp theo sort_order.
     * INPUT: không. OUTPUT: HasMany.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - HasMany theo visual_style_id
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function shots(): HasMany
    {
        return $this->hasMany(VisualStyleShot::class, 'visual_style_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo quan hệ media ảnh tham chiếu
     * =====================================================================
     *
     * Chi tiết triển khai:
     * CHỨC NĂNG: Ảnh tham chiếu DNA (MediaAsset) gắn vào style.
     * INPUT: không. OUTPUT: BelongsTo.
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - BelongsTo theo reference_media_id
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function referenceMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'reference_media_id');
    }

    /* =====================================================================
     * SCOPE
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Lọc style status=active và is_enabled=true
     * =====================================================================
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
    public function scopeActive($q)
    {
        return $q->where('status', VisualStyleStatus::Active->value)
            ->where('is_enabled', true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Lọc style is_enabled=true
     * =====================================================================
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

    /* =====================================================================
     * LOGIC TRẠNG THÁI
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra đồng thời status active và is_enabled
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool trạng thái có thể dùng để sinh
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
        return $this->status === VisualStyleStatus::Active->value && $this->is_enabled;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra đã có DNA (analyzed/active) để sinh ảnh
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool trạng thái có DNA khả dụng
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function isUsable(): bool
    {
        return in_array($this->status, [
            VisualStyleStatus::Analyzed->value,
            VisualStyleStatus::Active->value,
        ], true) && $this->is_enabled;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển status sang analyzing (job đã nhận)
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool kết quả lưu
     *
     * SIDE EFFECT:
     * - Cập nhật cột status lên DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi DB truyền về caller.
     * =====================================================================
     */
    public function markAnalyzing(): bool
    {
        $this->status = VisualStyleStatus::Analyzing->value;
        return $this->save();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển status sang analyzed + analyzed_at
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool kết quả lưu
     *
     * SIDE EFFECT:
     * - Cập nhật cột status + analyzed_at lên DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi DB truyền về caller.
     * =====================================================================
     */
    public function markAnalyzed(): bool
    {
        $this->status = VisualStyleStatus::Analyzed->value;
        $this->analyzed_at = now();
        return $this->save();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển status sang failed
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool kết quả lưu
     *
     * SIDE EFFECT:
     * - Cập nhật cột status lên DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi DB truyền về caller.
     * =====================================================================
     */
    public function markFailed(): bool
    {
        $this->status = VisualStyleStatus::Failed->value;
        return $this->save();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kích hoạt style sẵn sàng dùng (status → active)
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - bool kết quả lưu
     *
     * SIDE EFFECT:
     * - Cập nhật cột status lên DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi DB truyền về caller.
     * =====================================================================
     */
    public function markActive(): bool
    {
        $this->status = VisualStyleStatus::Active->value;
        return $this->save();
    }
}
