<?php

namespace Modules\CoreAi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model `ai_models` — một model AI thuộc một provider.
 * =====================================================================
 *
 * `remote_model_id` là ID phía nhà cung cấp (gpt-4o, claude-3-5-sonnet...),
 * `label` là tên hiển thị. `capabilities_json` khai báo model làm được gì:
 * text_generation | structured_output | vision | image_generation | tts | stt.
 * `pricing_json` (nullable) để ước tính chi phí ghi vào ai_usage_logs.
 *
 * `is_enabled` = admin cho phép; `is_available` = trạng thái thực tế sau
 * lần sync/ping gần nhất — model bị provider rút/thay tên sẽ bị đánh dấu
 * unavailable nhưng vẫn giữ lại cho dữ liệu lịch sử.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - provider()                  : BelongsTo AiProvider.
 * - scopeEnabled()/scopeAvailable()/scopeUsable() : lọc trạng thái.
 * - scopeWithCapability()       : model có 1 capability cụ thể.
 * - capabilities()/hasCapability() : đọc/kiểm tra capabilities_json.
 * - inputPricePerMtok()/outputPricePerMtok() : đọc bảng giá.
 * - estimateCostUsd()           : ước tính chi phí 1 lượt (đơn vị = token).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : remote_model_id + capabilities + pricing từ provider.
 * - OUTPUT: ModelResolver chọn theo capability; AiUsageLog tính cost.
 * =====================================================================
 */
class AiModel extends Model
{
    protected $table = 'ai_models';

    public const CAP_TEXT_GENERATION = 'text_generation';
    public const CAP_STRUCTURED_OUTPUT = 'structured_output';
    public const CAP_VISION = 'vision';
    public const CAP_IMAGE_GENERATION = 'image_generation';
    public const CAP_TTS = 'tts';
    public const CAP_STT = 'stt';
    public const CAPABILITIES = [
        self::CAP_TEXT_GENERATION,
        self::CAP_STRUCTURED_OUTPUT,
        self::CAP_VISION,
        self::CAP_IMAGE_GENERATION,
        self::CAP_TTS,
        self::CAP_STT,
    ];

    protected $fillable = [
        'ai_provider_id',
        'remote_model_id',
        'label',
        'capabilities_json',
        'pricing_json',
        'is_enabled',
        'is_available',
    ];

    protected $casts = [
        'capabilities_json' => 'array',
        'pricing_json' => 'array',
        'is_enabled' => 'boolean',
        'is_available' => 'boolean',
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Provider chủ của model.
     * INPUT: không. OUTPUT: BelongsTo AiProvider.
     * =====================================================================
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    /** INPUT: query. OUTPUT: query is_enabled = true. */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /** INPUT: query. OUTPUT: query is_available = true. */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Model dùng được = enabled + available + provider active.
     * =====================================================================
     * INPUT: query. OUTPUT: query đã lọc cả 3 điều kiện.
     * =====================================================================
     */
    public function scopeUsable($query)
    {
        return $query->enabled()->available()
            ->whereHas('provider', fn ($q) => $q->active());
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Model hỗ trợ một capability cụ thể.
     * =====================================================================
     * INPUT: query + capability string. OUTPUT: query đã lọc JSON contains.
     * =====================================================================
     */
    public function scopeWithCapability($query, string $capability)
    {
        return $query->whereJsonContains('capabilities_json', $capability);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Mảng capability của model.
     * INPUT: không. OUTPUT: string[].
     * =====================================================================
     */
    public function capabilities(): array
    {
        $caps = $this->capabilities_json;
        return is_array($caps) ? array_values($caps) : [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Model có capability cho trước không.
     * INPUT: string. OUTPUT: bool.
     * =====================================================================
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Giá mỗi 1 triệu token đầu vào (USD) — đọc pricing_json.
     * INPUT: không. OUTPUT: ?float.
     * =====================================================================
     */
    public function inputPricePerMtok(): ?float
    {
        $v = $this->pricing_json['input_per_mtok'] ?? null;
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Giá mỗi 1 triệu token đầu ra (USD).
     * INPUT: không. OUTPUT: ?float.
     * =====================================================================
     */
    public function outputPricePerMtok(): ?float
    {
        $v = $this->pricing_json['output_per_mtok'] ?? null;
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ước tính chi phí 1 lượt chạy theo số token.
     * =====================================================================
     * INPUT: int inputUnits, int outputUnits (token).
     * OUTPUT: ?float USD — null nếu không có pricing_json.
     * SIDE EFFECT: không.
     * =====================================================================
     */
    public function estimateCostUsd(int $inputUnits, int $outputUnits): ?float
    {
        $in = $this->inputPricePerMtok();
        $out = $this->outputPricePerMtok();
        if ($in === null && $out === null) {
            return null;
        }

        $cost = (($in ?? 0.0) * $inputUnits + ($out ?? 0.0) * $outputUnits) / 1_000_000;

        return round($cost, 6);
    }
}
