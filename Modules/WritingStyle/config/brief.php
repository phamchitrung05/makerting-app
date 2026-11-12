<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Quy tắc validation của WritingBrief (yêu cầu bài viết).
 * =====================================================================
 *
 * `WritingBrief::rules()` trả nguyên khối này cho các FormRequest nơi brief
 * có thể xuất hiện: SandboxRequest, RunSandboxStepRequest, SuggestBriefRequest
 * và WritingSandboxController::saveSample.
 *
 * Chỉnh giới hạn trường hoặc enum loại bài TẠI ĐÂY — mọi endpoint dùng
 * brief tự cập nhật mà không sửa code.
 *
 * Khung key:
 *   - content_types : enum loại nội dung — dùng chung bởi rules (chuỗi in:)
 *                     và WritingBriefSuggestService (schema AI + whitelist).
 *   - rules         : map trường => mảng rule Laravel.
 * =====================================================================
 */

$contentTypes = 'sales_post,announcement,storytelling,technical_article,blog';

return [
    // Enum loại nội dung — một nơi duy nhất để thêm loại mới (vd 'facebook_status').
    'content_types' => explode(',', $contentTypes),

    'rules' => [
        'brief' => ['nullable', 'array:topic,platform,content_type,audience,objective,provided_facts,required_details,required_fields,offer,cta,length,format,constraints'],
        'brief.topic' => ['nullable', 'string', 'min:3', 'max:2000'],
        'brief.platform' => ['nullable', 'string', 'max:80'],
        'brief.content_type' => ['nullable', 'in:'.$contentTypes],
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
    ],
];
