<?php

namespace Modules\WritingStyle\Services;

use Illuminate\Support\Facades\Validator;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\WritingStyle\Models\WritingProfile;

final class WritingStyleAnalysisV3
{
    private readonly array $blocks;

    public function __construct(string $sample)
    {
        $this->blocks = array_values(array_filter(array_map('trim', preg_split('/\R+/u', $sample) ?: []), 'strlen'));
    }

    public function request(): AiStructuredRequest
    {
        $system = (string) config('writingstyle.v3.analyzer');
        $prompt = PromptV3::json([
            'feature_definitions' => config('writingstyle.v3.questions'),
            'axis_definitions' => config('writingstyle.v3.axes'),
            'source_blocks' => array_map(
                static fn (string $text, int $index) => ['id' => $index + 1, 'text' => $text],
                $this->blocks,
                array_keys($this->blocks),
            ),
        ]);
        PromptV3::checkContext($system, $prompt);

        return AiStructuredRequest::fromPrompt(
            $prompt,
            system: $system,
            schema: $this->schema(),
            options: ['temperature' => 0.2, 'max_tokens' => 8192],
            module: 'writingstyle',
            operation: 'analyze_v3',
        );
    }

    private function schema(): array
    {
        $string = ['type' => 'string'];
        $strings = ['type' => 'array', 'items' => $string];
        $score = ['type' => ['integer', 'null']];
        $feature = PromptV3::objectSchema([
            'state' => ['type' => 'string', 'enum' => ['observed', 'inferred', 'unknown']],
            'description' => $string,
            'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
            'evidence_ids' => $strings,
        ]);

        return PromptV3::objectSchema([
            'features' => PromptV3::objectSchema(array_fill_keys(array_keys(config('writingstyle.v3.questions')), $feature)),
            'evidence' => ['type' => 'array', 'items' => PromptV3::objectSchema([
                'id' => $string,
                'kind' => ['type' => 'string', 'enum' => ['excerpt', 'structure_observation']],
                'block_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'text' => $string,
            ])],
            'signature_language' => PromptV3::objectSchema(array_fill_keys(['self_reference', 'audience_reference', 'particles'], $strings)),
            'source_specific_items' => $strings,
            'uncertainties' => $strings,
            'style_instructions' => $string,
            'voice_texture_instructions' => $string,
            'voice_texture_sliders_json' => PromptV3::objectSchema(array_fill_keys(WritingProfile::VOICE_TEXTURE_KEYS, $score)),
            'tone_sliders_json' => PromptV3::objectSchema(array_fill_keys(WritingProfile::TONE_AXIS_KEYS, $score)),
        ]);
    }

    public function normalize(array $data): array
    {
        $keys = array_keys(config('writingstyle.v3.questions'));
        $rules = [
            'features' => ['required', 'array:'.implode(',', $keys), 'required_array_keys:'.implode(',', $keys)],
            'features.*' => ['required', 'array:state,description,confidence,evidence_ids'],
            'features.*.state' => ['required', 'in:observed,inferred,unknown'],
            'features.*.description' => ['present', 'string', 'max:500'],
            'features.*.confidence' => ['required', 'in:low,medium,high'],
            'features.*.evidence_ids' => ['present', 'array', 'max:20'],
            'features.*.evidence_ids.*' => ['string', 'max:40'],
            'evidence' => ['present', 'array', 'max:20'],
            'evidence.*' => ['array:id,kind,block_ids,text'],
            'evidence.*.id' => ['required', 'string', 'max:40', 'distinct'],
            'evidence.*.kind' => ['required', 'in:excerpt,structure_observation'],
            'evidence.*.block_ids' => ['required', 'array', 'max:20'],
            'evidence.*.block_ids.*' => ['integer', 'min:1'],
            'evidence.*.text' => ['required', 'string', 'max:500'],
            'signature_language' => ['required', 'array:self_reference,audience_reference,particles'],
            'source_specific_items' => ['present', 'array', 'max:30'],
            'source_specific_items.*' => ['string', 'max:200'],
            'uncertainties' => ['present', 'array', 'max:15'],
            'uncertainties.*' => ['string', 'max:500'],
            'style_instructions' => ['present', 'string', 'max:2500'],
            'voice_texture_instructions' => ['present', 'string', 'max:2500'],
        ];
        foreach (['self_reference', 'audience_reference', 'particles'] as $key) {
            $rules['signature_language.'.$key] = ['present', 'array', 'max:8'];
            $rules['signature_language.'.$key.'.*'] = ['string', 'max:60'];
        }
        foreach (['voice_texture_sliders_json' => WritingProfile::VOICE_TEXTURE_KEYS, 'tone_sliders_json' => WritingProfile::TONE_AXIS_KEYS] as $field => $axes) {
            $rules[$field] = ['required', 'array:'.implode(',', $axes)];
            foreach ($axes as $key) {
                $rules[$field.'.'.$key] = ['present', 'nullable', 'integer', 'between:0,100'];
            }
        }
        $data = Validator::make($data, $rules)->validate();

        $evidence = [];
        foreach ($data['evidence'] as $item) {
            $blocks = array_intersect_key($this->blocks, array_flip(array_map(static fn (int $id) => $id - 1, $item['block_ids'])));
            if (count($blocks) !== count($item['block_ids'])) {
                continue;
            }
            if ($item['kind'] === 'excerpt' && ! str_contains(implode("\n", $blocks), $item['text'])) {
                continue;
            }
            $evidence[$item['id']] = $item;
        }

        $legacyRules = ['uncertainties' => $data['uncertainties']];
        $legacyEvidence = [];
        foreach ($data['features'] as $key => &$feature) {
            $feature['scope'] = 'sample';
            $feature['description'] = trim($feature['description']);
            $feature['evidence_ids'] = array_values(array_unique(array_intersect($feature['evidence_ids'], array_keys($evidence))));
            if ($feature['state'] === 'unknown' || $feature['description'] === '' || ($feature['state'] === 'observed' && $feature['evidence_ids'] === [])) {
                $feature['state'] = 'unknown';
                $feature['description'] = '';
                $feature['confidence'] = 'low';
                $feature['evidence_ids'] = [];
                continue;
            }
            if ($feature['state'] === 'inferred') {
                $feature['confidence'] = 'low';
            }
            $legacyRules[$key] = $feature['description'];
            foreach ($feature['evidence_ids'] as $id) {
                if ($evidence[$id]['kind'] === 'excerpt') {
                    $legacyEvidence[] = ['rule_key' => $key, 'excerpt' => $evidence[$id]['text'], 'note' => ''];
                }
            }
        }
        unset($feature);

        return [
            'rules_json' => $legacyRules,
            'evidence_json' => $legacyEvidence,
            'style_analysis_json' => [
                'schema_version' => 3,
                'features' => $data['features'],
                'evidence' => array_values($evidence),
                'signature_language' => $data['signature_language'],
                'source_specific_items' => $data['source_specific_items'],
                'uncertainties' => $data['uncertainties'],
            ],
            ...array_intersect_key($data, array_flip(['style_instructions', 'voice_texture_instructions', 'voice_texture_sliders_json', 'tone_sliders_json'])),
        ];
    }
}
