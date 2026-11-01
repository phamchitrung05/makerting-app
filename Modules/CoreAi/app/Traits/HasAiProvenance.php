<?php

namespace Modules\CoreAi\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CoreAi\Models\AiProvenance;
use Modules\CoreAi\Models\AiRun;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Trait gán truy xuất nguồn gốc AI vào Domain Model.
 * =====================================================================
 *
 * Model `use HasAiProvenance` (Post, SocialPostVariant, VideoScriptScene...)
 * có thể:
 * - Ghi nhận một trường vừa do AI tạo/áp: `applyAiValue('title', $text, $run)`
 *   → tự động tạo AiProvenance với value_hash để sau này phát hiện edit tay.
 * - Hỏi "trường X do AI tạo không?": `isAiGenerated('title')`.
 * - Hỏi "trường X đã bị người sửa sau khi AI tạo chưa?": `aiValueEdited('title')`.
 * - Liệt kê toàn bộ provenance: `aiProvenances()` / `aiProvenanceFor('title')`.
 *
 * `ai_provenances` dùng morph (target_type/target_id) qua getMorphClass —
 * nếu model đăng ký morph map alias, alias sẽ được dùng.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - aiProvenances()        : HasMany provenance của model này.
 * - aiProvenanceFor()      : provenance mới nhất của 1 field.
 * - applyAiValue()         : ghi giá trị vào field + tạo provenance.
 * - applyAiValues()        : applyAiValue cho nhiều field 1 lúc.
 * - isAiGenerated()        : field đã có provenance chưa.
 * - aiValueEdited()        : field đã bị sửa tay sau khi AI tạo chưa.
 * - clearAiProvenance()    : xóa provenance của 1 field / toàn bộ.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : model có trait + field name + value + ?AiRun + ?appliedBy.
 * - OUTPUT: AiProvenance|bool — bản ghi truy xuất hoặc kết quả kiểm tra.
 * =====================================================================
 */
trait HasAiProvenance
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Quan hệ HasMany tới ai_provenances (morph thủ công).
     * =====================================================================
     * INPUT: không. OUTPUT: HasMany AiProvenance — mọi provenance của model.
     * =====================================================================
     */
    public function aiProvenances(): HasMany
    {
        return $this->hasMany(AiProvenance::class, 'target_id')
            ->where('target_type', $this->getMorphClass());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Provenance mới nhất của một field cụ thể.
     * =====================================================================
     * INPUT: string $field. OUTPUT: ?AiProvenance.
     * SIDE EFFECT: SELECT ai_provenances.
     * =====================================================================
     */
    public function aiProvenanceFor(string $field): ?AiProvenance
    {
        return $this->aiProvenances()
            ->where('field_name', $field)
            ->latest('id')
            ->first();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghi giá trị AI vào một field + tạo provenance.
     * =====================================================================
     * INPUT :
     *   - string $field        — tên thuộc tính của model ('title', 'body'...).
     *   - string $value        — nội dung AI sinh ra (sẽ gán vào field).
     *   - ?AiRun $run          — run đã sinh (nullable).
     *   - ?int $appliedBy      — user id nếu người dùng nhấn áp dụng.
     *   - bool $save           — true = save model ngay (mặc định), false =
     *                            chỉ gán vào attribute (caller tự save sau).
     *
     * OUTPUT: AiProvenance đã tạo.
     * SIDE EFFECT: gán $this->{$field} = $value; INSERT ai_provenances;
     *              nếu $save → UPDATE model.
     * =====================================================================
     */
    public function applyAiValue(
        string $field,
        string $value,
        ?AiRun $run = null,
        ?int $appliedBy = null,
        bool $save = true,
    ): AiProvenance {
        $this->setAttribute($field, $value);
        if ($save) {
            $this->save();
        }

        return AiProvenance::record($this, $field, $value, $run, $appliedBy);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Áp nhiều giá trị AI một lúc.
     * =====================================================================
     * INPUT: array<string,string> $values {field => value} + ?run + ?appliedBy.
     * OUTPUT: int — số provenance đã tạo.
     * SIDE EFFECT: gán các field + INSERT provenance từng field + save 1 lần.
     * =====================================================================
     */
    public function applyAiValues(
        array $values,
        ?AiRun $run = null,
        ?int $appliedBy = null,
    ): int {
        $count = 0;
        foreach ($values as $field => $value) {
            $this->setAttribute($field, $value);
        }
        $this->save();

        foreach ($values as $field => $value) {
            AiProvenance::record($this, $field, (string) $value, $run, $appliedBy);
            $count++;
        }

        return $count;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Field này đã do AI tạo (có provenance) chưa.
     * INPUT: string $field. OUTPUT: bool.
     * =====================================================================
     */
    public function isAiGenerated(string $field): bool
    {
        return $this->aiProvenanceFor($field) !== null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Field do AI tạo đã bị người sửa tay chưa (value_hash khác).
     * =====================================================================
     * INPUT: string $field. OUTPUT: ?bool — null nếu field chưa có provenance,
     *        true = đã sửa, false = còn nguyên bản AI.
     * SIDE EFFECT: SELECT ai_provenances.
     * =====================================================================
     */
    public function aiValueEdited(string $field): ?bool
    {
        $prov = $this->aiProvenanceFor($field);
        if (! $prov) {
            return null;
        }

        return $prov->isEdited();
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Xóa provenance của một field (hoặc toàn bộ nếu null).
     * =====================================================================
     * INPUT: ?string $field. OUTPUT: int — số dòng đã xóa.
     * SIDE EFFECT: DELETE ai_provenances.
     * =====================================================================
     */
    public function clearAiProvenance(?string $field = null): int
    {
        $q = $this->aiProvenances();
        if ($field !== null) {
            $q->where('field_name', $field);
        }

        return $q->delete();
    }
}