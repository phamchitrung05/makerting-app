<?php

namespace Modules\WritingStyle\Pipelines;

use Illuminate\Support\Facades\Validator;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\DTOs\WritingProfileSnapshotDto;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;
use Modules\WritingStyle\Services\PromptV3;

final class WritingContentPlan
{
    public static function schema(): array
    {
        $string = ['type' => 'string'];
        $strings = ['type' => 'array', 'items' => $string];

        return PromptV3::objectSchema([
            'central_message' => $string,
            'target_angle' => $string,
            'structure_type' => $string,
            'sections' => ['type' => 'array', 'items' => PromptV3::objectSchema([
                'id' => $string,
                'parent_id' => ['type' => ['string', 'null']],
                'role' => $string,
                'heading_level' => ['type' => ['integer', 'null'], 'enum' => [2, 3, null]],
                'title' => $string,
                'key_points' => $strings,
                'fact_ids' => $strings,
                'length_budget' => ['type' => 'integer'],
            ])],
            'evidence_requests' => $strings,
            'missing_fields' => $strings,
            'warnings' => $strings,
        ]);
    }

    public static function fingerprint(WritingProfileSnapshotDto $snapshot, WritingBrief $brief): string
    {
        return hash('sha256', PromptV3::json([
            'provenance' => $snapshot->provenance,
            'tier' => $snapshot->targetTier,
            'style' => $snapshot->styleSummary,
            'brief' => $brief->data,
        ]));
    }

    public static function validate(array $artifact, WritingBrief $brief): array
    {
        $data = Validator::make($artifact, [
            'central_message' => ['required', 'string', 'max:800'],
            'target_angle' => ['required', 'string', 'max:800'],
            'structure_type' => ['required', 'string', 'max:80'],
            'sections' => ['required', 'array', 'max:12'],
            'sections.*' => ['required', 'array:id,parent_id,role,heading_level,title,key_points,fact_ids,length_budget'],
            'sections.*.id' => ['required', 'string', 'max:40', 'distinct'],
            'sections.*.parent_id' => ['present', 'nullable', 'string', 'max:40'],
            'sections.*.role' => ['required', 'string', 'max:80'],
            'sections.*.heading_level' => ['present', 'nullable', 'integer', 'in:2,3'],
            'sections.*.title' => ['required', 'string', 'max:200'],
            'sections.*.key_points' => ['required', 'array', 'max:8'],
            'sections.*.key_points.*' => ['string', 'max:350'],
            'sections.*.fact_ids' => ['present', 'array', 'max:30'],
            'sections.*.fact_ids.*' => ['string', 'max:40'],
            'sections.*.length_budget' => ['required', 'integer', 'between:1,2000'],
            'evidence_requests' => ['present', 'array', 'max:12'],
            'evidence_requests.*' => ['string', 'max:300'],
            'missing_fields' => ['present', 'array', 'max:12'],
            'missing_fields.*' => ['string', 'max:100'],
            'warnings' => ['present', 'array', 'max:12'],
            'warnings.*' => ['string', 'max:300'],
        ])->validate();

        $sections = array_column($data['sections'], null, 'id');
        $facts = array_column($brief->data['provided_facts'], 'id');
        foreach ($sections as $section) {
            $visited = [$section['id'] => true];
            $parent = $section['parent_id'];
            while ($parent !== null) {
                if (! isset($sections[$parent]) || isset($visited[$parent])) {
                    throw new InvalidPipelineException('Dàn ý có mục cha không tồn tại hoặc tạo vòng lặp.');
                }
                $visited[$parent] = true;
                $parent = $sections[$parent]['parent_id'];
            }
            if (array_diff($section['fact_ids'], $facts) !== []) {
                throw new InvalidPipelineException('Dàn ý tham chiếu dữ kiện ngoài brief.');
            }
            if ($section['heading_level'] !== null && ($brief->data['format']['headings'] ?? null) === false) {
                throw new InvalidPipelineException('Brief yêu cầu bài viết không có tiêu đề mục.');
            }
        }

        $budget = array_sum(array_column($sections, 'length_budget'));
        if ($budget < $brief->data['length']['min'] || $budget > $brief->data['length']['max']) {
            throw new InvalidPipelineException('Tổng độ dài các mục của dàn ý nằm ngoài khoảng trong brief.');
        }
        $data['sections'] = array_values($data['sections']);

        return $data;
    }

    public static function promptBlock(array $plan): string
    {
        return PromptV3::json([
            'central_message' => $plan['central_message'],
            'target_angle' => $plan['target_angle'],
            'structure_type' => $plan['structure_type'],
            'sections' => $plan['sections'],
        ]);
    }
}
