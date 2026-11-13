<?php

namespace Modules\WritingStyle\DTOs;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\WritingStyle\Services\PromptPack;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Chuẩn hóa yêu cầu bài viết thành brief bất biến dùng chung cho planner và writer.
 * =====================================================================
 *
 * FormRequest dùng rules(); controller và các bước pipeline gọi fromInput() sau validation.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Giữ dữ liệu brief đã chuẩn hóa trong DTO readonly.
 * - rules(): Cấp validation cho brief lồng trong các API sandbox.
 * - fromInput(): Hợp nhất chủ đề, preset độ dài và brief rồi kiểm tra dữ kiện bắt buộc.
 * - lengthHint(): Diễn đạt khoảng độ dài của brief bằng tiếng Việt.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Chủ đề, tier, brief và runtime overrides đã qua validation cấu trúc.
 * - OUTPUT: WritingBrief chứa dữ kiện, format và một khoảng độ dài có hiệu lực.
 *
 * BỐI CẢNH SỬ DỤNG: FormRequest dùng rules(); controller và các bước pipeline gọi fromInput() sau validation.
 * =====================================================================
 */
final readonly class WritingBrief
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Giữ dữ liệu brief đã chuẩn hóa trong DTO readonly
     * =====================================================================
     *
     * INPUT:
     * - public array $data
     *
     * OUTPUT:
     * - WritingBrief
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    private function __construct(public array $data) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Cấp validation cho brief lồng trong các API sandbox
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array quy tắc giới hạn trường, loại nội dung, facts, định dạng và độ dài
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public static function rules(): array
    {
        $topic = self::topicLimits();
        $fields = (array) self::config('fields', []);
        $facts = (array) self::config('provided_facts', []);
        $details = (array) self::config('required_details', []);
        $requiredFields = (array) self::config('required_fields.allowed_keys', []);
        $constraints = (array) self::config('constraints', []);
        $lengthMin = (int) self::config('length.min', 1);
        $lengthMax = (int) self::config('length.max_words', 2000);
        $field = static fn (string $key, int $default) => ['nullable', 'string', 'max:'.(int) ($fields[$key] ?? $default)];

        return [
            'brief' => ['nullable', 'array:topic,platform,content_type,audience,objective,provided_facts,required_details,required_fields,offer,cta,length,format,constraints'],
            'brief.topic' => ['nullable', 'string', 'min:'.$topic['min'], 'max:'.$topic['max']],
            'brief.platform' => $field('platform', 80),
            'brief.content_type' => ['nullable', Rule::in(self::contentTypes())],
            'brief.audience' => $field('audience', 500),
            'brief.objective' => $field('objective', 500),
            'brief.provided_facts' => ['nullable', 'array', 'max:'.(int) ($facts['max_items'] ?? 30)],
            'brief.provided_facts.*' => ['array:id,text,source'],
            'brief.provided_facts.*.id' => ['required', 'string', 'max:'.(int) ($facts['id_max'] ?? 40), 'distinct', 'regex:'.($facts['id_regex'] ?? '/^[a-zA-Z0-9_-]+$/')],
            'brief.provided_facts.*.text' => ['required', 'string', 'max:'.(int) ($facts['text_max'] ?? 500)],
            'brief.provided_facts.*.source' => ['sometimes', 'in:user'],
            'brief.required_details' => ['nullable', 'array', 'max:'.(int) ($details['max_items'] ?? 30)],
            'brief.required_details.*' => ['string', 'max:'.(int) ($details['item_max'] ?? 500), 'distinct'],
            'brief.required_fields' => ['nullable', 'array', 'max:'.count($requiredFields)],
            'brief.required_fields.*' => [Rule::in($requiredFields), 'distinct'],
            'brief.offer' => $field('offer', 500),
            'brief.cta' => $field('cta', 500),
            'brief.length' => ['nullable', 'array:unit,min,max'],
            'brief.length.unit' => ['required_with:brief.length', Rule::in((array) self::config('length.units', ['words', 'sentences']))],
            'brief.length.min' => ['required_with:brief.length', 'integer', "between:{$lengthMin},{$lengthMax}"],
            'brief.length.max' => ['required_with:brief.length', 'integer', 'gte:brief.length.min', "between:{$lengthMin},{$lengthMax}"],
            'brief.format' => ['nullable', 'array:headings,bullets'],
            'brief.format.headings' => ['sometimes', 'boolean'],
            'brief.format.bullets' => ['sometimes', 'boolean'],
            'brief.constraints' => ['nullable', 'array', 'max:'.(int) ($constraints['max_items'] ?? 12)],
            'brief.constraints.*' => ['string', 'max:'.(int) ($constraints['item_max'] ?? 300)],
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Đọc một giá trị trong config/brief.php
     * =====================================================================
     * INPUT : string $key (dot notation dưới writingstyle.brief), mixed $default.
     * OUTPUT: mixed giá trị cấu hình hoặc $default.
     * SIDE EFFECT: chỉ đọc config.
     * =====================================================================
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        return config('writingstyle.brief.'.$key, $default);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Danh sách loại bài hợp lệ (brief.content_type)
     * =====================================================================
     * OUTPUT: string[] — đọc writingstyle.brief.content_types.
     * =====================================================================
     */
    public static function contentTypes(): array
    {
        return array_values((array) self::config('content_types', ['sales_post', 'announcement', 'storytelling', 'technical_article', 'blog']));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Giới hạn số ký tự của chủ đề
     * =====================================================================
     * OUTPUT: array{min:int, max:int} — đọc writingstyle.brief.fields.topic.
     * =====================================================================
     */
    public static function topicLimits(): array
    {
        $topic = (array) self::config('fields.topic', []);

        return [
            'min' => (int) ($topic['min'] ?? 3),
            'max' => (int) ($topic['max'] ?? 2000),
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Rule dùng chung cho `topic` và `target_sentences` của các
     * FormRequest sandbox/brief.
     * =====================================================================
     * INPUT : array $topicPresence — tiền tố rule có mặt cho topic
     *         (vd ['required'] hoặc ['required_without:brief.topic', 'nullable']).
     * OUTPUT: array rule cho 'topic' và 'target_sentences'.
     * =====================================================================
     */
    public static function requestRules(array $topicPresence): array
    {
        $topic = self::topicLimits();
        $ts = (array) self::config('length.target_sentences', []);

        return [
            'topic' => [...$topicPresence, 'string', 'min:'.$topic['min'], 'max:'.$topic['max']],
            'target_sentences' => [
                'nullable',
                'integer',
                'min:'.(int) ($ts['min'] ?? 1),
                'max:'.(int) ($ts['max'] ?? 200),
            ],
        ];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Hợp nhất chủ đề, preset độ dài và brief rồi kiểm tra dữ kiện bắt buộc
     * =====================================================================
     *
     * INPUT:
     * - string $topic, string $tier, array $overrides
     *
     * OUTPUT:
     * - WritingBrief đã sắp facts theo ID và giữ các cờ định dạng
     *
     * SIDE EFFECT:
     * - Xử lý trong bộ nhớ và đọc preset cấu hình.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException khi chủ đề/độ dài xung đột, quá 200 câu, thiếu dữ kiện bắt buộc hoặc quá giới hạn context. Không mở transaction. Caller phải validate cấu trúc bằng rules() trước.
     * =====================================================================
     */
    public static function fromInput(string $topic, string $tier, array $overrides): self
    {
        $input = $overrides['brief'] ?? [];
        $topic = trim($topic);
        if (isset($input['topic']) && trim($input['topic']) !== $topic && $topic !== '') {
            throw ValidationException::withMessages(['brief.topic' => 'Chủ đề trong brief khác chủ đề của yêu cầu.']);
        }
        $topic = $input['topic'] ?? $topic;
        $lengths = (array) (PromptPack::dict('lengths', $input['content_type'] ?? null) ?? []);
        $length = $lengths[$tier] ?? $lengths['medium'];
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
        // Trần riêng cho đơn vị câu (rule tĩnh chỉ chặn theo max_words) —
        // xem config/brief.php → length.max_sentences.
        $maxSentences = (int) self::config('length.max_sentences', 200);
        if ($length['unit'] === 'sentences' && $length['max'] > $maxSentences) {
            throw ValidationException::withMessages(['brief.length.max' => "Số câu tối đa là {$maxSentences}."]);
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
        // $supplied = nội dung "có" — gồm topic + offer + cta + text facts +
        // required_details (chi tiết người dùng khai báo bắt buộc xuất hiện
        // trong bài). Cho phép người dùng gõ thêm chi tiết mới trực tiếp
        // ở ô "Chi tiết bắt buộc xuất hiện" thay vì chỉ khóa lại chi tiết
        // đã nêu ở facts/offer/cta.
        $supplied = implode("\n", [$topic, $data['offer'] ?? '', $data['cta'] ?? '', ...array_column($facts, 'text'), ...$data['required_details']]);
        foreach ($data['required_details'] as $detail) {
            if (mb_stripos($supplied, $detail) === false) {
                $missing[] = $detail;
            }
        }
        if ($missing !== []) {
            throw ValidationException::withMessages(['missing_fields' => $missing]);
        }
        PromptPack::checkContext(PromptPack::json($data));

        return new self($data);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Diễn đạt khoảng độ dài của brief bằng tiếng Việt
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - string số từ hoặc số câu, dùng một số khi min=max
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function lengthHint(): string
    {
        $length = $this->data['length'];
        $range = $length['min'] === $length['max'] ? (string) $length['min'] : $length['min'].'–'.$length['max'];

        return $range.' '.($length['unit'] === 'words' ? 'từ' : 'câu');
    }
}
