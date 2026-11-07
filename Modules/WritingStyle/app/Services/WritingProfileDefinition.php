<?php

namespace Modules\WritingStyle\Services;

use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Định nghĩa & tiện ích DNA văn phong.
 * =====================================================================
 *
 * Lõi "Giải mã DNA Văn phong" (PLAN.md §298-311):
 * - 16 `RULE_KEYS` (đọc từ `config('writingstyle.rule_keys')`).
 * - `detectTier()` — phân tầng 4 cấp độ độ dài (micro|short|medium|long)
 *   theo số câu/số từ của văn bản mẫu.
 * - `applicableKeys(tier)` — khóa áp dụng cho tier (micro đẩy `headings`/
 *   `bullets`/`paragraph_rhythm`/`structure_patterns` vào `uncertainties`).
 * - `tierLabel(tier)` — nhãn ngữ nghĩa cho System Prompt ("2–3 câu",
 *   "3–4 câu", "20–30 câu", "bài dài").
 * - `validateEvidence()` — kiểm chứng excerpt có `str_contains` nguyên văn
 *   trong reference_text (chống AI bịa trích dẫn).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : văn bản mẫu / tier / evidence_json.
 * - OUTPUT: tier, keys áp dụng, label, kết quả kiểm chứng.
 * =====================================================================
 */
class WritingProfileDefinition
{
    /* =====================================================================
     * 16 RULE_KEYS — thứ tự chuẩn (đọc config, fallback const).
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Mảng 16 khóa DNA theo thứ tự spec.
     * INPUT: không. OUTPUT: string[].
     */
    public static function ruleKeys(): array
    {
        return config('writingstyle.rule_keys', WritingProfile::RULE_KEYS);
    }

    /**
     * CHỨC NĂNG: Mô tả câu hỏi giải mã của 1 khóa.
     * INPUT: rule_key. OUTPUT: string.
     */
    public static function ruleQuestion(string $ruleKey): string
    {
        return config("writingstyle.rules.{$ruleKey}", "Yếu tố {$ruleKey} trong bài mẫu là gì?");
    }

    /* =====================================================================
     * Chuẩn hóa HTML -> text — giữ nguyên \n giữa các block để đếm câu
     * chính xác khi văn bản có nhiều đoạn (strip_tags thô sẽ mất \n ẩn
     * trong </p><p>, <br>, </li>, </h1-6>, </div>...).
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển HTML (Tiptap) thành plain text, giữ newline block.
     * =====================================================================
     * Thay tag đóng/mở block bằng "\n\n", thay <br> bằng "\n", rồi strip
     * tag + decode entity. Kết quả: text có \n thật giữa đoạn — bắt buộc
     * cho countSentences tách theo \n{2,} (PLAN.md §304).
     *
     * INPUT: string $html (có thể là HTML của Tiptap hoặc text thuần).
     * OUTPUT: string plain text (vẫn có \n, tab — chưa gộp whitespace).
     * =====================================================================
     */
    public static function htmlToText(string $html): string
    {
        // Đóng tag block -> xuống 2 dòng (đoạn mới).
        $t = preg_replace('/<\/(p|li|h[1-6]|div|blockquote|pre|tr|table|ul|ol)>/i', "\n\n", $html);
        // <br>, <hr> -> xuống 1 dòng.
        $t = preg_replace('/<(br|hr)\s*\/?\s*>/i', "\n", $t);
        // Mở tag block (an toàn cho trường hợp HTML thiếu đóng).
        $t = preg_replace('/<(p|li|h[1-6]|div|blockquote|pre|tr|table|ul|ol)[^>]*>/i', "\n\n", $t);
        // Bỏ mọi tag còn lại (inline <b>, <i>, <a>, <span>, entity...).
        $t = strip_tags($t);
        // Decode &nbsp; &amp; &lt; ... về ký tự thật.
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) $t);
    }

    /* =====================================================================
     * LengthTier — phân tầng 4 cấp độ độ dài.
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Tự động phân loại văn bản mẫu theo cấp độ độ dài.
     * =====================================================================
     * Quy tắc (PLAN.md §304):
     * - micro : 1–3 câu  HOẶC < 60 từ  (status 1 dòng, caption, hook).
     * - short : 4–10 câu HOẶC 60–180 từ (FB ngắn, Threads, ad copy).
     * - medium: 11–30 câu HOẶC 181–600 từ (storytelling, kịch bản 60s).
     * - long  : > 30 câu HOẶC > 600 từ (blog, báo chí, kịch bản dài).
     *
     * Ưu tiên số câu trước (nhịp điệu); nếu không tách được câu rõ ràng
     * (text không dấu câu) thì fallback theo số từ.
     *
     * INPUT: string $referenceText.
     * OUTPUT: 'micro'|'short'|'medium'|'long'.
     * =====================================================================
     */
    public static function detectTier(string $referenceText): string
    {
        $sentences = self::countSentences($referenceText);
        $words = self::countWords($referenceText);

        // Khi không tách được câu (0/1 câu do không dấu câu) nhưng text rất
        // dài theo từ — phân theo từ để tránh ép vào micro sai.
        if ($sentences <= 1 && $words >= 60) {
            return match (true) {
                $words <= 180  => WritingProfile::TIER_SHORT,
                $words <= 600  => WritingProfile::TIER_MEDIUM,
                default        => WritingProfile::TIER_LONG,
            };
        }

        // Phân theo số câu — đường biên khớp config('writingstyle.tiers').
        return match (true) {
            $sentences <= 3  => WritingProfile::TIER_MICRO,
            $sentences <= 10 => WritingProfile::TIER_SHORT,
            $sentences <= 30 => WritingProfile::TIER_MEDIUM,
            default          => WritingProfile::TIER_LONG,
        };
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Khóa áp dụng cho 1 cấp độ độ dài.
     * =====================================================================
     * - micro : bỏ `headings`/`bullets`/`paragraph_rhythm`/`structure_patterns`
     *   (đẩy vào `uncertainties` — không ép AI bịa).
     * - short : còn lại trừ `headings`/`bullets`/`structure_patterns`
     *   (paragraph_rhythm đã kích hoạt theo §305).
     * - medium/long : đủ 16 (long = trọn vẹn).
     *
     * INPUT: tier. OUTPUT: string[] rule_keys áp dụng.
     * =====================================================================
     */
    public static function applicableKeys(string $tier): array
    {
        $all = self::ruleKeys();
        $longOnly = config('writingstyle.long_only_rule_keys', [
            'headings', 'bullets', 'paragraph_rhythm', 'structure_patterns',
        ]);

        return match ($tier) {
            WritingProfile::TIER_MICRO =>
                array_values(array_diff($all, $longOnly)),
            WritingProfile::TIER_SHORT =>
                array_values(array_diff($all, ['headings', 'bullets', 'structure_patterns'])),
            default => $all, // medium | long — đủ 16.
        };
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Nhãn câu cho System Prompt theo cấp độ độ dài.
     * =====================================================================
     * Dùng khi biên dịch System Prompt trong SnapshotService (§310):
     * "viết 2–3 câu" / "3–4 câu" / "20–30 câu" / "bài dài".
     *
     * INPUT: tier. OUTPUT: string nhãn mô tả độ dài.
     * =====================================================================
     */
    public static function tierLabel(string $tier): string
    {
        return config("writingstyle.tiers.{$tier}.sentence_hint", 'văn bản phù hợp');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Số câu của văn bản (ước tính bằng dấu câu kết thúc).
     * =====================================================================
     * Tách theo [.!?…] + xuống dòng đôi; câu rỗng bỏ qua. Không dùng NLP
     * nặng — chỉ cần phân tầng hợp lý cho DNA.
     *
     * INPUT: text. OUTPUT: int số câu (tối thiểu 1).
     * =====================================================================
     */
    public static function countSentences(string $text): int
    {
        // Giữ \n giữa block trước — strip_tags thô sẽ mất newline ẩn trong
        // </p><p>/<br>/</li>... khiến cả bài dính thành 1 cục đếm thiếu câu.
        $t = self::htmlToText($text);
        if ($t === '') {
            return 0;
        }

        // Tách theo dấu kết câu + xuống dòng; lọc phần tử rỗng.
        $parts = preg_split('/[.!?…]+|\n{2,}|—\s*/u', $t, -1, PREG_SPLIT_NO_EMPTY);

        return max(1, count($parts));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Số từ của văn bản (đếm theo khoảng trắng unicode).
     * INPUT: text. OUTPUT: int.
     * =====================================================================
     */
    public static function countWords(string $text): int
    {
        $t = self::htmlToText($text);
        if ($t === '') {
            return 0;
        }

        // \S+ theo unicode — tách theo khoảng trắng bất kỳ.
        return count(preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /* =====================================================================
     * Kiểm chứng trích dẫn (chống AI bịa excerpt).
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm chứng evidence_json — mọi excerpt phải xuất hiện
     * nguyên văn trong reference_text (PLAN.md §309).
     * =====================================================================
     * Chuẩn hóa khoảng trắng/HTML trước khi `str_contains`:
     * - strip_tags, decode &entity;, gộp mọi chuỗi whitespace unicode
     *   thành 1 khoảng trắng đơn (xử lý &nbsp;, newline lẫn trong câu).
     * - excerpt được chuẩn hóa giống hệt.
     *
     * INPUT: string $referenceText, array $evidence (mảng {rule_key,excerpt,note}).
     * OUTPUT: array{
     *   valid:bool,
     *   checked:int,
     *   failed:string[],          // excerpt không tồn tại nguyên văn
     *   by_rule:array<string,int> // số excerpt hợp lệ theo rule_key
     * }
     * =====================================================================
     */
    public static function validateEvidence(string $referenceText, array $evidence): array
    {
        $haystack = self::normalizeForMatch($referenceText);
        $checked = 0;
        $failed = [];
        $byRule = [];

        foreach ($evidence as $item) {
            if (! is_array($item)) {
                continue;
            }
            $excerpt = (string) ($item['excerpt'] ?? '');
            $ruleKey = (string) ($item['rule_key'] ?? 'unknown');
            $checked++;

            $needle = self::normalizeForMatch($excerpt);
            if ($needle === '' || str_contains($haystack, $needle)) {
                $byRule[$ruleKey] = ($byRule[$ruleKey] ?? 0) + 1;
            } else {
                $failed[] = $excerpt;
            }
        }

        return [
            'valid'   => $failed === [],
            'checked' => $checked,
            'failed'  => $failed,
            'by_rule' => $byRule,
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa văn bản để so khớp excerpt.
     * =====================================================================
     * strip_tags → html_entity_decode → thay &nbsp;/whitespace unicode thành
     * 1 khoảng trắng → trim. Để excerpt có xuống dòng/tab trong DB vẫn khớp.
     *
     * INPUT: text. OUTPUT: string chuẩn hóa.
     * =====================================================================
     */
    private static function normalizeForMatch(string $text): string
    {
        // Giữ \n giữa block trước — excerpt trong DB có thể cách đoạn bằng \n\n.
        $t = self::htmlToText($text);
        // NBSP và mọi whitespace unicode (kể cả \n\n) -> 1 space.
        $t = preg_replace('/[\xC2\xA0\s]+/u', ' ', $t);

        return trim((string) $t);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Loại excerpt không kiểm chứng được khỏi evidence_json.
     * =====================================================================
     * Dùng sau khi `validateEvidence()` — giữ lại chỉ excerpt `str_contains`
     * thật (theo §309 "tự động loại bỏ trích dẫn do AI suy diễn").
     *
     * INPUT: reference_text + evidence_json.
     * OUTPUT: array evidence_json đã lọc (vẫn đủ cấu trúc {rule_key,excerpt,note}).
     * =====================================================================
     */
    public static function filterValidEvidence(string $referenceText, array $evidence): array
    {
        $haystack = self::normalizeForMatch($referenceText);
        $kept = [];

        foreach ($evidence as $item) {
            if (! is_array($item)) {
                continue;
            }
            $excerpt = self::normalizeForMatch((string) ($item['excerpt'] ?? ''));
            if ($excerpt === '') {
                continue;
            }
            if (str_contains($haystack, $excerpt)) {
                $kept[] = $item;
            }
        }

        return array_values($kept);
    }
}
