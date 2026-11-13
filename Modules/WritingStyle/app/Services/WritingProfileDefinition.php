<?php

namespace Modules\WritingStyle\Services;

use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Định nghĩa & tiện ích DNA văn phong.
 * =====================================================================
 *
 * - `ruleKeys()` — mảng khóa DNA (đọc `config('writingstyle.rule_keys')`).
 * - `detectTier()` — phân tầng 4 cấp độ độ dài (micro|short|medium|long)
 *   theo số câu/số từ của văn bản mẫu.
 * - `countSentences()` / `countWords()` — đếm câu/từ (htmlToText giữ \n).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : văn bản mẫu.
 * - OUTPUT: tier + thống kê câu/từ.
 * =====================================================================
 */
class WritingProfileDefinition
{
    /* =====================================================================
     * 16 RULE_KEYS — thứ tự chuẩn (đọc config, fallback const).
     * ===================================================================== */

    /**
     * CHỨC NĂNG: Mảng khóa DNA theo thứ tự spec (rule_keys trong config chung).
     * INPUT: không. OUTPUT: string[].
     */
    public static function ruleKeys(): array
    {
        return config('writingstyle.rule_keys', WritingProfile::RULE_KEYS);
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

        // Ngưỡng phân tầng đọc từ config('writingstyle.tiers'); fallback
        // về đúng giá trị mặc định khi config chưa nạp (unit test, artisan).
        $t = (array) config('writingstyle.tiers', []);
        $maxS = static fn (string $tier, int $default): int =>
            (int) ($t[$tier]['max_sentences'] ?? $default);
        $maxW = static fn (string $tier, int $default): int =>
            (int) ($t[$tier]['max_words'] ?? $default);

        $microMaxS  = $maxS(WritingProfile::TIER_MICRO, 3);
        $shortMaxS  = $maxS(WritingProfile::TIER_SHORT, 10);
        $mediumMaxS = $maxS(WritingProfile::TIER_MEDIUM, 30);
        $microMaxW  = $maxW(WritingProfile::TIER_MICRO, 59);
        $shortMaxW  = $maxW(WritingProfile::TIER_SHORT, 180);
        $mediumMaxW = $maxW(WritingProfile::TIER_MEDIUM, 600);

        // Khi không tách được câu (0/1 câu do không dấu câu) nhưng text rất
        // dài theo từ — phân theo từ để tránh ép vào micro sai.
        // Ngưỡng bùng nổ = "đủ lớn để không còn là micro" (micro.max_words+1).
        if ($sentences <= 1 && $words > $microMaxW) {
            return match (true) {
                $words <= $shortMaxW  => WritingProfile::TIER_SHORT,
                $words <= $mediumMaxW => WritingProfile::TIER_MEDIUM,
                default               => WritingProfile::TIER_LONG,
            };
        }

        // Phân theo số câu — đường biên khớp config('writingstyle.tiers').
        return match (true) {
            $sentences <= $microMaxS  => WritingProfile::TIER_MICRO,
            $sentences <= $shortMaxS  => WritingProfile::TIER_SHORT,
            $sentences <= $mediumMaxS => WritingProfile::TIER_MEDIUM,
            default                    => WritingProfile::TIER_LONG,
        };
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

}
