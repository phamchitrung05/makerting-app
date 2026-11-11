<?php

namespace Modules\WritingStyle\Services;

use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\WritingProfileSnapshotDto;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Biên dịch hồ sơ văn phong thành system prompt (Prompt Pack).
 * =====================================================================
 * Đây là 'compiler' duy nhất cấp DNA văn phong cho AI. Mọi module sinh chữ
 * đi qua service này thay vì đọc profile trực tiếp:
 *
 *  1. Đọc rules/features/guidance/sliders của profile + áp dụng format trong brief.
 *  2. Không đính evidence_json vào snapshot để giảm nguy cơ chép mẫu.
 *     Ô 1/2 và rules vẫn cần được kiểm tra khi phân tích.
 *  3. Biên dịch system_prompt theo độ dài đích (brief.lengthHint) để AI
 *     bắt chước đúng khẩu khí/nhịp câu ở đúng độ dài yêu cầu.
 *  4. Kết hợp Ô 1 (style_instructions) + Ô 2 (voice_texture_instructions)
 *     + Ô 3 (tone_instructions), 5 thanh trượt Chất văn và 8 thanh trượt Cảm xúc.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - buildSnapshot(): Chuẩn hóa tier và ủy cho buildV3Snapshot.
 * - buildV3Snapshot(): Ghép đặc điểm, Ô 1–3 và slider có nguồn gốc vào prompt.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : WritingProfile, tier đích, số câu và overrides.
 * - OUTPUT: WritingProfileSnapshotDto chứa prompt, rules, length hint và metadata phiên bản.
 *
 * BỐI CẢNH SỬ DỤNG: Compiler được các bước sandbox dùng để áp dụng DNA và runtime overrides.
 * =====================================================================
 */
class WritingProfileSnapshotService implements WritingStyleCompilerContract
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Biên dịch DNA profile thành system prompt (Prompt Pack V3)
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $profile, string $targetTier, int $targetSentences = 0, array $overrides = [],
     *
     * OUTPUT:
     * - WritingProfileSnapshotDto
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function buildSnapshot(
        WritingProfile $profile,
        string $targetTier,
        int $targetSentences = 0,
        array $overrides = [],
    ): WritingProfileSnapshotDto {
        // Chuẩn hóa tier: rỗng/sai -> dùng dominant_tier của profile.
        $tier = in_array($targetTier, WritingProfile::TIERS, true)
            ? $targetTier
            : ($profile->dominant_tier ?? WritingProfile::TIER_MEDIUM);

        return $this->buildV3Snapshot($profile, $tier, $targetSentences, $overrides);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghép đặc điểm, Ô 1–3 và slider có nguồn gốc vào prompt V3
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $profile, string $tier, int $targetSentences, array $overrides
     *
     * OUTPUT:
     * - Snapshot gồm styleSummary và provenance để kiểm tra dàn ý cũ
     *
     * SIDE EFFECT:
     * - Đọc profile/config; bỏ rule heading/bullet bị brief cấm, gộp override slider theo từng khóa và bỏ null, khử dòng chỉ dẫn trùng.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException từ chuẩn hóa brief và JsonException từ mã hóa prompt. Không mở transaction.
     * =====================================================================
     */
    private function buildV3Snapshot(WritingProfile $profile, string $tier, int $targetSentences, array $overrides): WritingProfileSnapshotDto
    {
        $brief = WritingBrief::fromInput('', $tier, array_replace($overrides, ['target_sentences' => $targetSentences]));
        $analysis = $profile->style_analysis_json ?? [];
        $rules = array_intersect_key($profile->rules_json ?? [], (array) PromptPack::dict('questions'));
        $format = $brief->data['format'];
        foreach (['headings', 'bullets'] as $key) {
            if (($format[$key] ?? null) === false) {
                unset($rules[$key]);
            }
        }

        $features = [];
        foreach ($rules as $key => $text) {
            if (! is_string($text) || trim($text) === '') {
                unset($rules[$key]);
                continue;
            }
            $observed = $analysis['features'][$key] ?? null;
            $state = ($observed['description'] ?? null) === $text ? $observed['state'] : 'profile';
            if ($state !== 'unknown') {
                $features[$key] = ['description' => $text, 'state' => $state];
            }
        }

        $guidance = [];
        $seen = array_fill_keys(array_column($features, 'description'), true);
        foreach (['style_instructions', 'voice_texture_instructions', 'tone_instructions'] as $key) {
            $isOverride = array_key_exists($key, $overrides);
            $value = $isOverride ? $overrides[$key] : $profile->{$key};
            foreach (preg_split('/\R/u', (string) $value) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '' && ! isset($seen[$line])) {
                    $guidance[$key]['lines'][] = $line;
                    $guidance[$key]['source'] = $isOverride ? 'current_request' : 'profile';
                    $seen[$line] = true;
                }
            }
        }

        $axes = [];
        $definitions = (array) PromptPack::dict('axes');
        foreach (['voice_texture_sliders_json' => 'voice_texture_sliders_json', 'tone_overrides' => 'tone_sliders_json'] as $key => $field) {
            $stored = (array) $profile->{$field};
            $current = array_key_exists($key, $overrides)
                ? ($overrides[$key] === null ? [] : array_replace($stored, $overrides[$key]))
                : $stored;
            $axisKeys = $key === 'tone_overrides' ? WritingProfile::TONE_AXIS_KEYS : WritingProfile::VOICE_TEXTURE_KEYS;
            foreach (array_intersect_key($current, array_flip($axisKeys)) as $axis => $score) {
                if ($score !== null && isset($definitions[$axis])) {
                    $axes[$axis] = [
                        'score' => (int) $score,
                        'anchors' => $definitions[$axis],
                        'source' => isset($overrides[$key]) && array_key_exists($axis, $overrides[$key]) ? 'current_request' : 'profile',
                    ];
                }
            }
        }

        $style = ['features' => $features, 'guidance' => $guidance, 'axes' => $axes];
        $system = PromptPack::role('compiler', $brief->data['content_type'] ?? null);
        $prompt = $system."\n\nPhong cách hiệu lực:\n".PromptPack::json($style);

        return new WritingProfileSnapshotDto(
            profileId: (int) $profile->id,
            name: (string) $profile->name,
            targetTier: $tier,
            systemPrompt: $prompt,
            rulesJson: $rules,
            sentenceHint: $brief->lengthHint(),
            evidenceStripped: true,
            styleSummary: [
                'features' => array_intersect_key($features, array_flip(['tone', 'pronouns', 'opening', 'structure_patterns', 'transitions', 'ending', 'bullets', 'headings'])),
                'guidance' => array_intersect_key($guidance, array_flip(['style_instructions', 'tone_instructions'])),
            ],
            provenance: ['prompt_version' => 3, 'schema_version' => $analysis['schema_version'] ?? 'legacy', 'compiler_version' => 3, 'profile_id' => $profile->id, 'profile_version' => $profile->version],
        );
    }

}
