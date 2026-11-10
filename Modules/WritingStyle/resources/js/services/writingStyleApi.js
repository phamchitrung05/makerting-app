/*
 * =====================================================================
 * CHỨC NĂNG FILE: Service gọi API module WritingStyle (DNA văn phong).
 * =====================================================================
 * Bọc $api (ofetch, tự unwrap BaseResponse.data) cho endpoint:
 * - GET    /v1/admin/writing-style/writing-profiles            -> dataTable
 * - GET    /v1/admin/writing-style/writing-profiles/options    -> tiers+rules
 * - CRUD   /v1/admin/writing-style/writing-profiles            -> create/upd/del
 * - POST   /v1/admin/writing-style/writing-profiles/analyze    -> 202 dispatch
 * - GET    /v1/admin/writing-style/writing-profiles/analyses/{id} -> trạng thái + kết quả
 * - POST   /v1/admin/writing-style/writing-profiles/{id}/sandbox -> DNA Sandbox
 * =====================================================================
 */
import { $api } from '@/utils/api'

const BASE = '/v1/admin/writing-style'

export const writingStyleApi = {
  /**
   * =====================================================================
   * CHỨC NĂNG: Lấy danh sách hồ sơ văn phong phân trang kèm bộ lọc
   * =====================================================================
   *
   * INPUT:
   * - params (Object): { search, tier, origin, status, page, per_page }
   *
   * OUTPUT:
   * - Promise<Object>: DataTable response { items: WritingProfileResource[], itemsLength: number }
   *
   * SIDE EFFECT:
   * - Gửi request GET /v1/admin/writing-style/writing-profiles
   *
   * EXCEPTION:
   * - ApiError khi lỗi xác thực 401 hoặc lỗi hệ thống 500
   * =====================================================================
   */
  profiles(params = {}) {
    return $api(`${BASE}/writing-profiles`, { params })
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Lấy thông tin chi tiết một hồ sơ văn phong theo ID
   * =====================================================================
   *
   * INPUT:
   * - id (number|string): ID hồ sơ cần lấy
   *
   * OUTPUT:
   * - Promise<Object>: WritingProfileResource chi tiết (kèm rules_json & evidence_json)
   *
   * SIDE EFFECT:
   * - Gửi request GET /v1/admin/writing-style/writing-profiles/{id}
   *
   * EXCEPTION:
   * - ApiError 404 nếu không tìm thấy hồ sơ
   * =====================================================================
   */
  profile(id) {
    return $api(`${BASE}/writing-profiles/${id}`)
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Lấy danh mục cấu hình cấp độ (tiers) và 16 câu hỏi giải mã
   * =====================================================================
   *
   * INPUT:
   * - Không có
   *
   * OUTPUT:
   * - Promise<Object>: { tiers: Array, rules: Array<{ key, question }> }
   *
   * SIDE EFFECT:
   * - Gửi request GET /v1/admin/writing-style/writing-profiles/options
   *
   * EXCEPTION:
   * - ApiError 403 nếu thiếu quyền xem
   * =====================================================================
   */
  options() {
    return $api(`${BASE}/writing-profiles/options`)
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Tạo mới một hồ sơ văn phong thủ công (origin=manual)
   * =====================================================================
   *
   * INPUT:
   * - payload (Object): { name, description, dominant_tier, rules_json, ... }
   *
   * OUTPUT:
   * - Promise<Object>: WritingProfileResource bản ghi vừa tạo
   *
   * SIDE EFFECT:
   * - Gửi request POST /v1/admin/writing-style/writing-profiles
   *
   * EXCEPTION:
   * - ApiError 422 nếu dữ liệu không hợp lệ
   * =====================================================================
   */
  createProfile(payload) {
    return $api(`${BASE}/writing-profiles`, { method: 'POST', body: payload })
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Cập nhật thông tin hồ sơ văn phong (PUT partial)
   * =====================================================================
   *
   * INPUT:
   * - id (number|string): ID hồ sơ cần sửa
   * - payload (Object): Các trường cần cập nhật
   *
   * OUTPUT:
   * - Promise<Object>: WritingProfileResource sau khi sửa
   *
   * SIDE EFFECT:
   * - Gửi request PUT /v1/admin/writing-style/writing-profiles/{id}
   *
   * EXCEPTION:
   * - ApiError 404 nếu hồ sơ không tồn tại, 422 nếu validate lỗi
   * =====================================================================
   */
  updateProfile(id, payload) {
    return $api(`${BASE}/writing-profiles/${id}`, { method: 'PUT', body: payload })
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Xóa một hồ sơ văn phong khỏi hệ thống
   * =====================================================================
   *
   * INPUT:
   * - id (number|string): ID hồ sơ cần xóa
   *
   * OUTPUT:
   * - Promise<void>
   *
   * SIDE EFFECT:
   * - Gửi request DELETE /v1/admin/writing-style/writing-profiles/{id}
   *
   * EXCEPTION:
   * - ApiError 403 nếu thiếu quyền manage, 404 nếu không tìm thấy
   * =====================================================================
   */
  deleteProfile(id) {
    return $api(`${BASE}/writing-profiles/${id}`, { method: 'DELETE' })
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Gửi văn bản mẫu yêu cầu AI giải mã DNA văn phong trong nền
   * =====================================================================
   *
   * INPUT:
   * - payload (Object): { source_type: 'paste', reference_text: string, name?: string }
   *
   * OUTPUT:
   * - Promise<Object>: 202 Accepted { analysis_id, task_run_id, status: 'pending' }
   *
   * SIDE EFFECT:
   * - Gửi request POST /v1/admin/writing-style/writing-profiles/analyze
   *
   * EXCEPTION:
   * - ApiError 422 nếu reference_text dưới 50 ký tự
   * =====================================================================
   */
  analyze(payload) {
    return $api(`${BASE}/writing-profiles/analyze`, { method: 'POST', body: payload })
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Đọc trạng thái và kết quả phân tích theo ID (cho FE poll)
   * =====================================================================
   *
   * INPUT:
   * - id (string): UUID của bản ghi phân tích
   * - opts (Object): { signal?: AbortSignal } — truyền xuống ofetch để có thể
   *   ngắt request giữa chừng (polling bị hủy / server treo không trả lời).
   *
   * OUTPUT:
   * - Promise<Object>: WritingAnalysisResource (kèm status, result_json, draft_profile)
   *
   * SIDE EFFECT:
   * - Gửi request GET /v1/admin/writing-style/writing-profiles/analyses/{id}
   *
   * EXCEPTION:
   * - ApiError 404 nếu không tìm thấy phiên giải mã
   * - DOMException AbortError nếu `opts.signal` bị abort giữa chừng
   * =====================================================================
   */
  analysis(id, { signal } = {}) {
    return $api(`${BASE}/writing-profiles/analyses/${id}`, { signal })
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Chạy thử nghiệm viết bài mới (Sandbox) theo DNA hồ sơ
   * =====================================================================
   *
   * INPUT:
   * - id (number|string): ID hồ sơ văn phong cần viết thử
   * - payload (Object): { topic: string, target_tier: string, target_sentences?: number }
   *
   * OUTPUT:
   * - Promise<Object>: { text: string, target_tier: string, snapshot: Object, ai_run_id: string }
   *
   * SIDE EFFECT:
   * - Gửi request POST /v1/admin/writing-style/writing-profiles/{id}/sandbox
   *
   * EXCEPTION:
   * - ApiError khi AI gateway gặp lỗi hoặc hồ sơ không tồn tại
   * =====================================================================
   */
  sandbox(id, payload) {
    return $api(`${BASE}/writing-profiles/${id}/sandbox`, { method: 'POST', body: payload })
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Chạy 1 bước (step) trong quy trình Sandbox Pipeline theo tier
   * =====================================================================
   *
   * INPUT:
   * - id (number|string): ID hồ sơ văn phong
   * - step (string): Mã bước cần chạy (vd: 'topic_outline', 'article_writer')
   * - payload (Object): { topic, target_tier, artifacts, tone_overrides, ... }
   *
   * OUTPUT:
   * - Promise<Object>: { step: string, artifact: Object }
   * =====================================================================
   */
  runSandboxStep(id, step, payload) {
    return $api(`${BASE}/writing-profiles/${id}/sandbox/steps/${step}`, { method: 'POST', body: payload })
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Lưu (Ghim) 1 bản viết thử vào Database của hồ sơ văn phong
   * =====================================================================
   */
  saveSandboxSample(id, payload) {
    return $api(`${BASE}/writing-profiles/${id}/sandbox-samples`, { method: 'POST', body: payload })
  },

  /**
   * =====================================================================
   * CHỨC NĂNG: Xóa 1 bản viết thử đã lưu khỏi hồ sơ văn phong
   * =====================================================================
   */
  deleteSandboxSample(id, sampleId) {
    return $api(`${BASE}/writing-profiles/${id}/sandbox-samples/${sampleId}`, { method: 'DELETE' })
  },
}
