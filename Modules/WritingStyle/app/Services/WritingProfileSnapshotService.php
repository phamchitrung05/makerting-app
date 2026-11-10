<?php

namespace Modules\WritingStyle\Services;

use Modules\WritingStyle\Contracts\WritingStyleCompilerContract;
use Modules\WritingStyle\DTOs\WritingProfileSnapshotDto;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Biên dịch WritingProfile -> WritingProfileSnapshotDto.
 * =====================================================================
 * Đây là 'compiler' duy nhất cấp DNA văn phong cho AI (PLAN.md §310).
 * Mọi module sinh chữ đi qua service này thay vì đọc profile trực tiếp:
 *
 *  1. Lọc rules theo cấp độ độ dài đích (applicableKeys / applicableRules).
 *  2. LOẠI BỎ hoàn toàn evidence_json — AI không bao giờ thấy câu chữ mẫu
 *     thật nên không thể đạo văn.
 *  3. Biên dịch system_prompt theo tierLabel(targetTier) để AI bắt chước
 *     đúng khẩu khí/nhịp câu ở đúng độ dài yêu cầu.
 *  4. Kết hợp Ô 1 (style_instructions) + Ô 2 (voice_texture_instructions)
 *     + 5 thanh trượt Chất văn (voice_texture_sliders_json) + tone_overrides.
 * =====================================================================
 */
class WritingProfileSnapshotService implements WritingStyleCompilerContract
{
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

        if (PromptV3::enabled()) {
            return $this->buildV3Snapshot($profile, $tier, $targetSentences, $overrides);
        }

        // Lọc rules theo tier + loại bỏ evidence.
        $rules = $profile->snapshotRules($tier);

        $sentenceHint = WritingProfileDefinition::tierLabel($tier);
        if ($targetSentences > 0) {
            $sentenceHint = $targetSentences . ' câu';
        }

        // Ô 1: style_instructions
        $styleText = array_key_exists('style_instructions', $overrides)
            ? trim((string) ($overrides['style_instructions'] ?? ''))
            : (string) ($profile->style_instructions ?? '');

        // Ô 2: voice_texture_instructions
        $voiceTextureText = array_key_exists('voice_texture_instructions', $overrides)
            ? trim((string) ($overrides['voice_texture_instructions'] ?? ''))
            : trim((string) ($profile->voice_texture_instructions ?? ''));

        // Ô 3: tone_instructions (Prompt bổ sung 3 - Chỉ dẫn Sắc thái cảm xúc)
        $toneInstructionsText = array_key_exists('tone_instructions', $overrides)
            ? trim((string) ($overrides['tone_instructions'] ?? ''))
            : trim((string) ($profile->tone_instructions ?? ''));

        // 5 Thanh trượt Chất văn: voice_texture_sliders_json
        $voiceTextureSliders = array_key_exists('voice_texture_sliders_json', $overrides) && is_array($overrides['voice_texture_sliders_json'])
            ? $overrides['voice_texture_sliders_json']
            : (is_array($profile->voice_texture_sliders_json) ? $profile->voice_texture_sliders_json : []);

        $prompt = $this->compilePrompt(
            $profile,
            $rules,
            $tier,
            $sentenceHint,
            $styleText,
            $voiceTextureText,
            $voiceTextureSliders,
            $toneInstructionsText,
        );

        // 8 Thanh trượt Sắc thái cảm xúc (Sandbox cảm xúc): ưu tiên runtime override,
        // nếu không có thì đọc trực tiếp từ cột tone_sliders_json đã lưu trong DB.
        $toneSliders = ! empty($overrides['tone_overrides']) && is_array($overrides['tone_overrides'])
            ? $overrides['tone_overrides']
            : (is_array($profile->tone_sliders_json) ? $profile->tone_sliders_json : []);

        if (! empty($toneSliders)) {
            $toneSection = $this->renderToneSection($toneSliders);
            if ($toneSection !== '') {
                $prompt .= "\n\n" . $toneSection;
            }
        }

        return new WritingProfileSnapshotDto(
            profileId: (int) $profile->id,
            name: (string) $profile->name,
            targetTier: $tier,
            systemPrompt: $prompt,
            rulesJson: $rules,
            sentenceHint: $sentenceHint,
            evidenceStripped: true,
        );
    }

    private function buildV3Snapshot(WritingProfile $profile, string $tier, int $targetSentences, array $overrides): WritingProfileSnapshotDto
    {
        $brief = WritingBrief::fromInput('', $tier, array_replace($overrides, ['target_sentences' => $targetSentences]));
        $analysis = $profile->style_analysis_json ?? [];
        $rules = array_intersect_key($profile->rules_json ?? [], config('writingstyle.v3.questions'));
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
        $definitions = config('writingstyle.v3.axes');
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
        $system = (string) config('writingstyle.v3.compiler');
        $prompt = $system."\n\nPhong cách hiệu lực:\n".PromptV3::json($style);

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

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghép system_prompt từ rules đã lọc + Ô 1 + Ô 2 + 5 sliders.
     * =====================================================================
     */
    protected function compilePrompt(
        WritingProfile $profile,
        array $rules,
        string $tier,
        string $sentenceHint,
        string $styleText,
        string $voiceTextureText = '',
        array $voiceTextureSliders = [],
        string $toneInstructionsText = '',
    ): string {
        $cfg = config('writingstyle.prompts.compiler', []);

        $fill = fn (string $tpl) => strtr($tpl, [
            '{name}' => (string) $profile->name,
            '{sentence_hint}' => $sentenceHint,
        ]);

        $lines = [];
        $lines[] = $fill((string) ($cfg['role_template'] ?? 'Bạn là người viết nội dung có văn phong "{name}".'));
        $lines[] = $fill((string) ($cfg['task_template'] ?? 'Hãy viết {sentence_hint} đúng 100% khẩu khí, nhịp câu và cách hành văn của văn phong này.'));
        $lines[] = (string) ($cfg['anti_plagiarism'] ?? 'TUYỆT ĐỐI không sao chép nguyên văn bất kỳ câu nào từ bài mẫu — chỉ bắt chước đặc trưng văn phong.');
        $lines[] = '';

        foreach ((array) ($cfg['transformation_directives'] ?? []) as $directive) {
            $lines[] = $directive;
        }
        $lines[] = '';

        $lines[] = (string) ($cfg['rules_header'] ?? 'Đặc trưng văn phong cần tuân thủ:');
        foreach ($rules as $key => $value) {
            $text = is_array($value)
                ? implode('; ', array_filter(array_map('strval', $value)))
                : trim((string) $value);
            if ($text === '') {
                continue;
            }
            $lines[] = '- ' . $key . ': ' . $text;
        }

        // Ô 1: Hướng dẫn hành văn & Chân dung người viết (style_instructions)
        $extra = trim($styleText);
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = (string) ($cfg['style_instructions_label'] ?? 'Hướng dẫn bổ sung:') . ' ' . $extra;
        }

        // Ô 2: Chỉ dẫn Chất văn & Nghệ thuật Nhấn – Lướt (voice_texture_instructions)
        $voiceExtra = trim($voiceTextureText);
        if ($voiceExtra !== '') {
            $lines[] = '';
            $lines[] = (string) ($cfg['voice_texture_label'] ?? 'Chỉ dẫn Chất văn & Nghệ thuật Nhấn – Lướt (Ô 2):') . ' ' . $voiceExtra;
        }

        // Ô 3: Chỉ dẫn Sắc thái & Phối âm cảm xúc (tone_instructions)
        $toneExtra = trim($toneInstructionsText);
        if ($toneExtra !== '') {
            $lines[] = '';
            $lines[] = (string) ($cfg['tone_instructions_label'] ?? 'Chỉ dẫn Sắc thái & Phối âm cảm xúc (Ô 3):') . ' ' . $toneExtra;
        }

        // 5 Thanh trượt Chất văn (voice_texture_sliders_json)
        $voiceSlidersSection = $this->renderVoiceTextureSlidersSection($voiceTextureSliders);
        if ($voiceSlidersSection !== '') {
            $lines[] = '';
            $lines[] = $voiceSlidersSection;
        }

        return implode("\n", $lines);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Render 5 thanh trượt Chất văn thành khối chỉ đạo định lượng.
     * =====================================================================
     */
    protected function renderVoiceTextureSlidersSection(array $sliders): string
    {
        if (empty($sliders)) {
            return '';
        }

        $axes = config('writingstyle.voice_texture_axes', []);
        $lines = ['THÔNG SỐ CHẤT VĂN & ĐỘ TỰ NHIÊN (VOICE TEXTURE CALIBRATION):'];

        foreach ($axes as $key => $meta) {
            if (! isset($sliders[$key])) {
                continue;
            }

            $percent = max(0, min(100, (int) $sliders[$key]));
            $label = (string) ($meta['label'] ?? $key);
            $lowPole = (string) ($meta['low_pole'] ?? '');
            $highPole = (string) ($meta['high_pole'] ?? '');

            if ($percent >= 70) {
                $direction = "nghiêng mạnh về cực [{$highPole}]";
            } elseif ($percent <= 35) {
                $direction = "nghiêng về cực [{$lowPole}]";
            } else {
                $direction = "cân bằng giữa [{$lowPole}] và [{$highPole}]";
            }

            $lines[] = "  * {$label}: {$percent}% — {$direction}";
        }

        return count($lines) <= 1 ? '' : implode("\n", $lines);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Render tone_overrides thành section TONE CALIBRATION.
     * =====================================================================
     */
    protected function renderToneSection(array $toneOverrides): string
    {
        $axes = config('writingstyle.tone_axes', []);

        $lines = ['HIỆU CHỈNH SẮC THÁI CẢM XÚC THỜI GIAN THỰC (TONE CALIBRATION):'];

        foreach ($axes as $key => $label) {
            if (! isset($toneOverrides[$key])) {
                continue;
            }

            $percent = (int) $toneOverrides[$key];
            $percent = max(0, min(100, $percent));

            $diff = $percent - 50;
            $diffLabel = $diff === 0
                ? 'điểm cân bằng'
                : ($diff > 0 ? '+' . $diff . '% so với điểm cân bằng' : $diff . '% so với điểm cân bằng');

            $lines[] = '  * ' . $label . ': ' . $percent . '% (' . $diffLabel . ')';
        }

        return count($lines) <= 1 ? '' : implode("\n", $lines);
    }
}
