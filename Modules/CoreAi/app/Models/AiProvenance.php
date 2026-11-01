<?php

namespace Modules\CoreAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model `ai_provenances` — truy xuất nguồn gốc nội dung AI.
 * =====================================================================
 *
 * Khi AI sinh và "áp" một giá trị vào trường của Domain Model (post.title,
 * post.excerpt, social_variant.body...), ghi 1 dòng provenance để biết
 * trường đó do AI tạo, từ ai_run nào, và người dùng có sửa lại không.
 *
 * `value_hash` = sha256(giá trị đã áp). So sánh với sha256(giá trị hiện
 * tại của field) → khác nhau nghĩa là biên tập viên đã sửa tay (edited).
 *
 * `applied_by` nullable: null khi áp tự động (pipeline), có id khi người
 * dùng nhấn "Áp dụng" trong UI.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - run()                : BelongsTo AiRun (nullable).
 * - target()             : resolve model chủ qua morph map (target_type+id).
 * - scopeForTarget()     : provenance của một model cụ thể.
 * - scopeForField()      : provenance của một field cụ thể.
 * - hashValue()          : sha256 một giá trị (helper tĩnh).
 * - isEdited()           : giá trị hiện tại đã khác lúc áp không.
 * - record()             : tạo provenance mới (helper tĩnh cho trait).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : target_type/target_id/field_name/value (+run, +applied_by).
 * - OUTPUT: bản ghi truy xuất — cho UI badge "AI" + audit + detect edit.
 * =====================================================================
 */
class AiProvenance extends Model
{
    protected $table = 'ai_provenances';

    protected $fillable = [
        'target_type', 'target_id', 'field_name',
        'ai_run_id', 'value_hash', 'applied_by',
    ];

    protected $casts = [
        'target_id' => 'integer',
    ];

    /** INPUT: không. OUTPUT: BelongsTo AiRun (nullable). */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve model chủ (target) của provenance.
     * =====================================================================
     * INPUT: không. OUTPUT: ?Model — model thực qua morph map, null nếu
     *        target_type không resolve được / bản ghi đã xóa.
     * SIDE EFFECT: truy vấn model target (1 SELECT).
     * =====================================================================
     */
    public function target(): ?Model
    {
        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($this->target_type)
            ?: $this->target_type;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::find($this->target_id);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Provenance của một model cụ thể.
     * INPUT: query + model. OUTPUT: query đã lọc (dùng getMorphClass cho
     *        tôn trọng morph map).
     * =====================================================================
     */
    public function scopeForTarget($query, Model $model)
    {
        return $query->where('target_type', $model->getMorphClass())
            ->where('target_id', $model->getKey());
    }

    /** INPUT: query + field name. OUTPUT: query đã lọc. */
    public function scopeForField($query, string $field)
    {
        return $query->where('field_name', $field);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: sha256 một giá trị — chuẩn chung để so sánh edit.
     * INPUT: string. OUTPUT: string 64 hex.
     * =====================================================================
     */
    public static function hashValue(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra nội dung hiện tại của field đã bị sửa tay không —
     * so sánh sha256(giá trị hiện tại) với value_hash lúc áp.
     * =====================================================================
     * INPUT: không. OUTPUT: ?bool — null nếu không resolve được target/field,
     *        true = đã sửa, false = còn nguyên bản AI.
     * SIDE EFFECT: truy vấn target (nếu chưa load).
     * =====================================================================
     */
    public function isEdited(): ?bool
    {
        try {
            $target = $this->target();
        } catch (\Throwable) {
            return null;
        }
        if (! $target) {
            return null;
        }

        $current = (string) ($target->getAttribute($this->field_name) ?? '');

        return self::hashValue($current) !== $this->value_hash;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghi một provenance mới (helper tĩnh cho HasAiProvenance).
     * =====================================================================
     * INPUT: target Model + field + value đã áp + ?AiRun + ?appliedBy user id.
     * OUTPUT: AiProvenance mới.
     * SIDE EFFECT: INSERT ai_provenances.
     * =====================================================================
     */
    public static function record(
        Model $target,
        string $field,
        string $value,
        ?AiRun $run = null,
        ?int $appliedBy = null,
    ): self {
        return static::create([
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
            'field_name' => $field,
            'ai_run_id' => $run?->id,
            'value_hash' => self::hashValue($value),
            'applied_by' => $appliedBy,
        ]);
    }
}