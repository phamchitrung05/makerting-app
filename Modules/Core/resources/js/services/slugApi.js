/*
 * =====================================================================
 * CHỨC NĂNG FILE: Service gọi API Slug toàn cục (module Core).
 * =====================================================================
 * Bọc $api (ofetch, tự unwrap BaseResponse.data) cho endpoint:
 * - POST /v1/admin/core/slug-suggest  -> gợi ý slug khả dụng cho 1 tài nguyên.
 *
 * Dùng chung cho mọi module: mỗi module store truyền sluggable_type riêng
 * (alias morph, vd 'writing_profile', 'post', 'category'), component chỉ
 * nhận props trạng thái và không tự gọi API.
 * =====================================================================
 */
import { $api } from '@/utils/api'

const BASE = '/v1/admin/core'

export const slugApi = {
  /**
   * =====================================================================
   * CHỨC NĂNG: Gợi ý slug khả dụng khi user nhập tiêu đề/tên (không ghi DB).
   * =====================================================================
   *
   * INPUT:
   * - payload (Object):
   *   - source        (string, required): tiêu đề/tên làm nguồn sinh slug.
   *   - sluggable_type(string, required): alias morph ('writing_profile', ...).
   *   - sluggable_id  (number|null):      ID bản ghi đang sửa; nếu != null thì
   *     loại trừ slug của chính nó khi check trùng.
   *   - locale        (string, optional): mã ngôn ngữ, mặc định 'vi'.
   *
   * OUTPUT:
   * - Promise<Object>: { slug, available, taken_by } —
   *   slug      = chuỗi sẽ dùng (đã hậu tố -1,-2 nếu base trùng),
   *   available = base slugify(source) còn trống không,
   *   taken_by  = base đang bị record khác chiếm (khi available=false).
   *
   * SIDE EFFECT:
   * - POST /v1/admin/core/slug-suggest (chỉ đọc, không ghi bảng slugs).
   *
   * EXCEPTION:
   * - ApiError 401 khi chưa xác thực; 422 khi payload sai schema.
   * =====================================================================
   */
  suggest(payload) {
    return $api(`${BASE}/slug-suggest`, { method: 'POST', body: payload })
  },
}
