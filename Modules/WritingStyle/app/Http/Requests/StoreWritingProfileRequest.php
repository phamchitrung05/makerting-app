<?php

namespace Modules\WritingStyle\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: FormRequest validate dữ liệu tạo/sửa WritingProfile.
 * =====================================================================
 *
 * Kiểm tra tính hợp lệ của payload gửi lên:
 * - POST /v1/admin/writing-style/writing-profiles     (Tạo thủ công origin=manual)
 * - PUT  /v1/admin/writing-style/writing-profiles/{id} (Cập nhật hồ sơ hoặc hoàn tất bản nháp)
 *
 * Hỗ trợ đồng bộ Taxonomy (categories, tags, new_tags) qua HasTaxonomy trait.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - authorize(): Xác thực phân quyền request (kiểm tra qua permission middleware).
 * - rules(): Tập luật validation cho các trường thông tin hồ sơ và taxonomy.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : HTTP Request payload (name, dominant_tier, rules_json, tags, ...)
 * - OUTPUT: Mảng dữ liệu đã validate an toàn qua validated()
 * =====================================================================
 */
class StoreWritingProfileRequest extends FormRequest
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Xác thực quyền hạn của người dùng gửi request
     * =====================================================================
     *
     * INPUT:
     * - Không có
     *
     * OUTPUT:
     * - bool: true (phân quyền đã được kiểm tra bởi route middleware)
     *
     * SIDE EFFECT:
     * - Không có
     *
     * EXCEPTION/TRANSACTION:
     * - Không có
     * =====================================================================
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo các quy tắc kiểm tra tính hợp lệ của dữ liệu
     * =====================================================================
     *
     * INPUT:
     * - Không có
     *
     * OUTPUT:
     * - array: Mảng các quy tắc validation cho từng trường dữ liệu
     *
     * SIDE EFFECT:
     * - Không có
     *
     * EXCEPTION/TRANSACTION:
     * - Không ném exception trực tiếp (ném ValidationException nếu vi phạm)
     * =====================================================================
     */
    public function rules(): array
    {
        return [
            'name'                  => [$this->method() === 'POST' ? 'required' : 'sometimes', 'string', 'max:160'],
            'description'           => ['nullable', 'string', 'max:2000'],
            'dominant_tier'         => ['nullable', 'in:micro,short,medium,long'],
            // rules_json chặt theo 16 khóa DNA thật từ WritingProfileDefinition;
            // mỗi giá trị khóa có độ dài tối thiểu để chặn dữ liệu giả/trống.
            // 15 khóa văn bản là string; uncertainties là mảng chuỗi (doc GĐ1).
            'rules_json'            => ['nullable', 'array'],
            'rules_json.tone'       => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.pronouns'   => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.emotion'    => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.opening'    => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.sentence_rhythm' => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.paragraph_rhythm' => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.transitions' => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.vocabulary' => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.technical_terms' => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.structure_patterns' => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.headings'   => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.bullets'    => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.examples'   => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.ending'     => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.avoid'      => ['nullable', 'string', 'min:5', 'max:500'],
            'rules_json.uncertainties'   => ['nullable', 'array'],
            'rules_json.uncertainties.*' => ['string', 'min:5', 'max:500'],
            'evidence_json'         => ['nullable', 'array'],
            'evidence_json.*'       => ['array'],
            'evidence_json.*.rule_key' => ['required', 'string', 'max:50'],
            'evidence_json.*.excerpt' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_json.*.note'  => ['nullable', 'string', 'max:500'],
            'style_instructions'    => ['nullable', 'string', 'min:10', 'max:10000'],
            'status'                => ['nullable', 'in:draft,active'],
            'is_enabled'            => ['nullable', 'boolean'],

            // Slug tùy biến — tự sinh từ name nếu để trống; khớp chuẩn
            // HasSlug toàn cục. unique bảng slugs cột slug.
            'slug'                  => ['nullable', 'string', 'max:250', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],

            // Taxonomy (Categories & Tags) hỗ trợ trait HasTaxonomy (Gap G11)
            'categories'            => ['nullable', 'array'],
            'categories.*'          => [
                'integer',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'new_categories'        => ['nullable', 'array'],
            'new_categories.*'      => ['string', 'max:160'],
            'tags'                  => ['nullable', 'array'],
            'tags.*'                => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'new_tags'              => ['nullable', 'array'],
            'new_tags.*'            => ['string', 'max:120'],
        ];
    }
}
