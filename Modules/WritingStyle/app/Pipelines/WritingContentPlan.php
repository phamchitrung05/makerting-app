<?php

namespace Modules\WritingStyle\Pipelines;

use Illuminate\Support\Facades\Validator;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\DTOs\WritingProfileSnapshotDto;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;
use Modules\WritingStyle\Services\PromptPack;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Quản lý schema, fingerprint và kiểm tra dàn ý V3 trước khi cấp cho bước viết.
 * =====================================================================
 *
 * TopicOutlineStep tạo kế hoạch; SandboxPipelineRunner kiểm tra lại artifact do client gửi.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - schema(): Tạo JSON Schema cho thông điệp, cây mục, ngân sách độ dài và các cảnh báo.
 * - fingerprint(): Tạo dấu nhận diện đầu vào của kế hoạch từ profile/version, tier, style summary và brief.
 * - validate(): Kiểm tra kiểu dữ liệu, quan hệ cha, tham chiếu facts, headings và tổng độ dài.
 * - promptBlock(): Chỉ đưa thông điệp, góc nhìn, cấu trúc và sections sang writer.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Artifact dàn ý, snapshot văn phong và WritingBrief đã chuẩn hóa.
 * - OUTPUT: Schema, fingerprint SHA-256, kế hoạch hợp lệ hoặc prompt block đã chọn trường.
 *
 * BỐI CẢNH SỬ DỤNG: TopicOutlineStep tạo kế hoạch; SandboxPipelineRunner kiểm tra lại artifact do client gửi.
 * =====================================================================
 */
final class WritingContentPlan
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo JSON Schema cho thông điệp, cây mục, ngân sách độ dài và các cảnh báo
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array schema V3; heading_level và parent_id có thể null
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public static function schema(): array
    {
        $string = ['type' => 'string'];
        $strings = ['type' => 'array', 'items' => $string];

        return PromptPack::objectSchema([
            'central_message' => $string,
            'target_angle' => $string,
            'structure_type' => $string,
            'sections' => ['type' => 'array', 'items' => PromptPack::objectSchema([
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

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo dấu nhận diện đầu vào của kế hoạch từ profile/version, tier, style summary và brief
     * =====================================================================
     *
     * INPUT:
     * - WritingProfileSnapshotDto $snapshot, WritingBrief $brief
     *
     * OUTPUT:
     * - string SHA-256 để phát hiện dàn ý cũ; không dùng làm chữ ký xác thực
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public static function fingerprint(WritingProfileSnapshotDto $snapshot, WritingBrief $brief): string
    {
        return hash('sha256', PromptPack::json([
            'provenance' => $snapshot->provenance,
            'tier' => $snapshot->targetTier,
            'style' => $snapshot->styleSummary,
            'brief' => $brief->data,
        ]));
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Kiểm tra kiểu dữ liệu, quan hệ cha, tham chiếu facts, headings và tổng độ dài
     * =====================================================================
     *
     * INPUT:
     * - array $artifact, WritingBrief $brief
     *
     * OUTPUT:
     * - array kế hoạch chỉ chứa trường đã validate
     *
     * SIDE EFFECT:
     * - Chỉ duyệt dữ liệu trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException khi sai cấu trúc; InvalidPipelineException khi cây lỗi, fact ngoài brief, headings bị cấm hoặc tổng độ dài không hợp lệ. Không mở transaction.
     * =====================================================================
     */
    public static function validate(array $artifact, WritingBrief $brief): array
    {
        $p = config('writingstyle.pipeline.plan', []);
        $max = static fn (string $key, int $default): string => 'max:'.(int) ($p[$key] ?? $default);
        $maxLen = (int) ($p['meta_item_max'] ?? 500);
        $budgetMin = (int) ($p['length_budget']['min'] ?? 1);
        $budgetMax = (int) ($p['length_budget']['max'] ?? 2000);

        $data = Validator::make($artifact, [
            'central_message' => ['required', 'string', $max('central_message_max', 800)],
            'target_angle' => ['required', 'string', $max('target_angle_max', 800)],
            'structure_type' => ['required', 'string', $max('structure_type_max', 120)],
            'sections' => ['required', 'array', $max('sections_max', 12)],
            'sections.*' => ['required', 'array:id,parent_id,role,heading_level,title,key_points,fact_ids,length_budget'],
            'sections.*.id' => ['required', 'string', $max('section_id_max', 40), 'distinct'],
            'sections.*.parent_id' => ['present', 'nullable', 'string', $max('section_id_max', 40)],
            'sections.*.role' => ['required', 'string', $max('role_max', 80)],
            'sections.*.heading_level' => ['present', 'nullable', 'integer', 'in:2,3'],
            'sections.*.title' => ['required', 'string', $max('title_max', 200)],
            'sections.*.key_points' => ['required', 'array', $max('key_points_max', 8)],
            'sections.*.key_points.*' => ['string', $max('key_point_max', 350)],
            'sections.*.fact_ids' => ['present', 'array', $max('fact_ids_max', 30)],
            'sections.*.fact_ids.*' => ['string', $max('fact_id_max', 40)],
            'sections.*.length_budget' => ['required', 'integer', "between:{$budgetMin},{$budgetMax}"],
            'evidence_requests' => ['present', 'array', $max('evidence_requests_max', 12)],
            'missing_fields' => ['present', 'array', $max('missing_fields_max', 12)],
            'warnings' => ['present', 'array', $max('warnings_max', 12)],
        ])->validate();

        // Các trường metadata cảnh báo (không phải nội dung writer cần) —
        // AI có thể trả văn bản dài hơn giới hạn, nên truncate an toàn thay vì
        // chặn toàn bộ plan (user không thể sửa metadata này bằng tay được).
        foreach (['evidence_requests', 'missing_fields', 'warnings'] as $metaKey) {
            $items = (array) ($data[$metaKey] ?? []);
            Validator::make(['items' => $items], ['items.*' => ['string']])->validate();
            $data[$metaKey] = array_map(
                static fn ($item) => mb_substr((string) $item, 0, $maxLen),
                $items,
            );
        }

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

    /**
     * =====================================================================
     * CHỨC NĂNG: Chỉ đưa thông điệp, góc nhìn, cấu trúc và sections sang writer
     * =====================================================================
     *
     * INPUT:
     * - array $plan
     *
     * OUTPUT:
     * - string JSON; không đưa đề xuất dẫn chứng chưa xác minh vào block
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public static function promptBlock(array $plan): string
    {
        return PromptPack::json([
            'central_message' => $plan['central_message'],
            'target_angle' => $plan['target_angle'],
            'structure_type' => $plan['structure_type'],
            'sections' => $plan['sections'],
        ]);
    }
}
