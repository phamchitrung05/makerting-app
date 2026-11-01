<?php

namespace Modules\CoreAi\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO mốc thời gian một từ/cụm trong audio TTS/STT.
 * =====================================================================
 *
 * Phục vụ dựng phụ đề động (.ass) cho VideoStudio: mỗi phần tử gắn một
 * đoạn text với khoảng thời gian [startMs, endMs] trong audio.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : text, startMs, endMs, confidence?, index.
 * - OUTPUT: readonly object → AiSpeechResponse/AiTranscribeResponse chứa
 *          mảng WordTimingDto[].
 * =====================================================================
 */
final readonly class WordTimingDto
{
    /**
     * CHỨC NĂNG: khởi tạo word timing.
     * INPUT: text, startMs, endMs, confidence (0..1), index.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public string $text,
        public int $startMs,
        public int $endMs,
        public ?float $confidence = null,
        public int $index = 0,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo từ mảng provider trả về (tolerant với key khác nhau).
     * INPUT: array — chấp nhận 'text'|'word', 'start_ms'|'start'|'startMs',
     *        'end_ms'|'end'|'endMs', 'confidence'?, index.
     * OUTPUT: WordTimingDto.
     * =====================================================================
     */
    public static function fromArray(array $item, int $index = 0): self
    {
        $text = (string) ($item['text'] ?? $item['word'] ?? $item['token'] ?? '');
        $start = (int) round((float) ($item['start_ms'] ?? $item['start'] ?? $item['startMs'] ?? 0));
        $end = (int) round((float) ($item['end_ms'] ?? $item['end'] ?? $item['endMs'] ?? $start));
        $conf = $item['confidence'] ?? $item['score'] ?? null;

        return new self(
            text: $text,
            startMs: $start,
            endMs: $end,
            confidence: is_numeric($conf) ? (float) $conf : null,
            index: $index,
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Serialize thành mảng lưu DB/output.
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'start_ms' => $this->startMs,
            'end_ms' => $this->endMs,
            'confidence' => $this->confidence,
            'index' => $this->index,
        ];
    }
}