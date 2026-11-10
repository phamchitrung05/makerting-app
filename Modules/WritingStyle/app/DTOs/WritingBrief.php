<?php

namespace Modules\WritingStyle\DTOs;

use Illuminate\Validation\ValidationException;
use Modules\WritingStyle\Services\PromptV3;

final readonly class WritingBrief
{
    private function __construct(public array $data) {}

    public static function rules(): array
    {
        return [
            'brief' => ['nullable', 'array:topic,platform,content_type,audience,objective,provided_facts,required_details,required_fields,offer,cta,length,format,constraints'],
            'brief.topic' => ['nullable', 'string', 'min:3', 'max:2000'],
            'brief.platform' => ['nullable', 'string', 'max:80'],
            'brief.content_type' => ['nullable', 'in:sales_post,announcement,storytelling,technical_article,blog'],
            'brief.audience' => ['nullable', 'string', 'max:500'],
            'brief.objective' => ['nullable', 'string', 'max:500'],
            'brief.provided_facts' => ['nullable', 'array', 'max:30'],
            'brief.provided_facts.*' => ['array:id,text,source'],
            'brief.provided_facts.*.id' => ['required', 'string', 'max:40', 'distinct', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'brief.provided_facts.*.text' => ['required', 'string', 'max:500'],
            'brief.provided_facts.*.source' => ['sometimes', 'in:user'],
            'brief.required_details' => ['nullable', 'array', 'max:30'],
            'brief.required_details.*' => ['string', 'max:500', 'distinct'],
            'brief.required_fields' => ['nullable', 'array', 'max:7'],
            'brief.required_fields.*' => ['in:audience,objective,offer,cta,provided_facts,platform,content_type', 'distinct'],
            'brief.offer' => ['nullable', 'string', 'max:500'],
            'brief.cta' => ['nullable', 'string', 'max:500'],
            'brief.length' => ['nullable', 'array:unit,min,max'],
            'brief.length.unit' => ['required_with:brief.length', 'in:words,sentences'],
            'brief.length.min' => ['required_with:brief.length', 'integer', 'between:1,2000'],
            'brief.length.max' => ['required_with:brief.length', 'integer', 'gte:brief.length.min', 'between:1,2000'],
            'brief.format' => ['nullable', 'array:headings,bullets'],
            'brief.format.headings' => ['sometimes', 'boolean'],
            'brief.format.bullets' => ['sometimes', 'boolean'],
            'brief.constraints' => ['nullable', 'array', 'max:12'],
            'brief.constraints.*' => ['string', 'max:300'],
        ];
    }

    public static function fromInput(string $topic, string $tier, array $overrides): self
    {
        $input = $overrides['brief'] ?? [];
        $topic = trim($topic);
        if (isset($input['topic']) && trim($input['topic']) !== $topic && $topic !== '') {
            throw ValidationException::withMessages(['brief.topic' => 'Chủ đề trong brief khác chủ đề của yêu cầu.']);
        }
        $topic = $input['topic'] ?? $topic;
        $length = config('writingstyle.v3.lengths.'.$tier, config('writingstyle.v3.lengths.medium'));
        if (isset($input['length'])) {
            $length = ['unit' => $input['length']['unit'], 'min' => (int) $input['length']['min'], 'max' => (int) $input['length']['max']];
        }
        if (($sentences = (int) ($overrides['target_sentences'] ?? 0)) > 0) {
            $explicit = ['unit' => 'sentences', 'min' => $sentences, 'max' => $sentences];
            if (isset($input['length']) && $explicit !== $length) {
                throw ValidationException::withMessages(['brief.length' => 'Khoảng độ dài xung đột với số câu được yêu cầu.']);
            }
            $length = $explicit;
        }
        if ($length['unit'] === 'sentences' && $length['max'] > 200) {
            throw ValidationException::withMessages(['brief.length.max' => 'Số câu tối đa là 200.']);
        }

        $facts = array_map(static fn (array $fact) => ['id' => $fact['id'], 'text' => $fact['text'], 'source' => 'user'], array_values($input['provided_facts'] ?? []));
        usort($facts, static fn (array $a, array $b) => strcmp($a['id'], $b['id']));
        $data = [
            'topic' => $topic,
            'platform' => $input['platform'] ?? null,
            'content_type' => $input['content_type'] ?? null,
            'audience' => $input['audience'] ?? null,
            'objective' => $input['objective'] ?? null,
            'provided_facts' => $facts,
            'required_details' => array_values($input['required_details'] ?? []),
            'offer' => $input['offer'] ?? null,
            'cta' => $input['cta'] ?? null,
            'length' => $length,
            'format' => array_filter([
                'headings' => isset($input['format']['headings']) ? (bool) $input['format']['headings'] : null,
                'bullets' => isset($input['format']['bullets']) ? (bool) $input['format']['bullets'] : null,
            ], static fn ($value) => $value !== null),
            'constraints' => array_values($input['constraints'] ?? []),
        ];
        $missing = array_values(array_filter($input['required_fields'] ?? [], static fn (string $key) => blank($data[$key] ?? null)));
        $supplied = implode("\n", [$topic, $data['offer'] ?? '', $data['cta'] ?? '', ...array_column($facts, 'text')]);
        foreach ($data['required_details'] as $detail) {
            if (mb_stripos($supplied, $detail) === false) {
                $missing[] = $detail;
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages(['missing_fields' => $missing]);
        }
        PromptV3::checkContext(PromptV3::json($data));

        return new self($data);
    }

    public function lengthHint(): string
    {
        $length = $this->data['length'];
        $range = $length['min'] === $length['max'] ? (string) $length['min'] : $length['min'].'–'.$length['max'];

        return $range.' '.($length['unit'] === 'words' ? 'từ' : 'câu');
    }
}
