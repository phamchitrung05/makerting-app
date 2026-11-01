<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO usage token/don-vi cho 1 lượt gọi AI.
 * =====================================================================
 *
 * Chuẩn hóa usage trả về của mọi provider về một schema thống nhất để
 * ghi vào `ai_usage_logs` (input_units/output_units/estimated_cost_usd).
 * Provider trả `prompt_tokens`/`input_tokens`/`characters`/`images`...
 * → map về inputUnits/outputUnits ở đây.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : inputUnits, outputUnits, ?estimatedCostUsd, ?capability,
 *          ?rawUsage (nguyên bản provider).
 * - OUTPUT: readonly object → AiUsageLog + diagnostics.
 * =====================================================================
 */
final readonly class AiUsageDto
{
    /**
     * CHỨC NĂNG: khởi tạo usage DTO.
     * INPUT: inputUnits, outputUnits, ?costUsd, ?capability, ?raw.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public int $inputUnits = 0,
        public int $outputUnits = 0,
        public ?float $estimatedCostUsd = null,
        public ?string $capability = null,
        public array $raw = [],
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Tổng đơn vị đã dùng (input+output).
     * INPUT: không. OUTPUT: int.
     * =====================================================================
     */
    public function totalUnits(): int
    {
        return $this->inputUnits + $this->outputUnits;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo usage từ mảng provider trả (tolerant với nhiều key).
     * INPUT: array — chấp nhận input_tokens|prompt_tokens|input_units,
     *        output_tokens|completion_tokens|output_units|characters.
     * OUTPUT: AiUsageDto.
     * =====================================================================
     */
    public static function fromArray(array $usage, ?string $capability = null): self
    {
        $in = (int) ($usage['input_tokens']
            ?? $usage['prompt_tokens']
            ?? $usage['input_units']
            ?? $usage['characters']
            ?? 0);
        $out = (int) ($usage['output_tokens']
            ?? $usage['completion_tokens']
            ?? $usage['output_units']
            ?? $usage['images']
            ?? 0);
        $cost = $usage['estimated_cost_usd'] ?? $usage['cost_usd'] ?? null;

        return new self(
            inputUnits: $in,
            outputUnits: $out,
            estimatedCostUsd: is_numeric($cost) ? (float) $cost : null,
            capability: $capability,
            raw: $usage,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize cho ghi ai_usage_logs.
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toArray(): array
    {
        return [
            'input_units' => $this->inputUnits,
            'output_units' => $this->outputUnits,
            'estimated_cost_usd' => $this->estimatedCostUsd,
            'capability' => $this->capability,
        ];
    }
}