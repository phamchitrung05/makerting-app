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
            'rules_json'            => ['nullable', 'array'],
            'evidence_json'         => ['nullable', 'array'],
            'style_instructions'    => ['nullable', 'string', 'max:10000'],
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
