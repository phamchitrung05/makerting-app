/*
 * =====================================================================
 * CHỨC NĂNG FILE: Service gọi API module WritingStyle (DNA văn phong).
 * =====================================================================
 * Bọc $api (ofetch, tự unwrap BaseResponse.data) cho endpoint:
 * - GET    /v1/admin/writing-style/writing-profiles            -> dataTable
 * - GET    /v1/admin/writing-style/writing-profiles/options    -> tiers+rules
 * - CRUD   /v1/admin/writing-style/writing-profiles            -> create/upd/del
 * - POST   /v1/admin/writing-style/writing-profiles/analyze    -> 202 dispatch
 * - POST   /v1/admin/writing-style/writing-profiles/{id}/sandbox -> DNA Sandbox
 * =====================================================================
 */
import { $api } from '@/utils/api'

const BASE = '/v1/admin/writing-style'

export const writingStyleApi = {
  /* Danh sách profile phân trang + filter {search,tier,origin,status}. */
  profiles(params = {}) {
    return $api(`${BASE}/writing-profiles`, { params })
  },

  /* Chi tiết profile (rules + evidence đầy đủ). */
  profile(id) {
    return $api(`${BASE}/writing-profiles/${id}`)
  },

  /* tiers + 16 rule keys + câu hỏi (cho Edit + analyze preview). */
  options() {
    return $api(`${BASE}/writing-profiles/options`)
  },

  /* Tạo profile thủ công (origin=manual). */
  createProfile(payload) {
    return $api(`${BASE}/writing-profiles`, { method: 'POST', body: payload })
  },

  /* Cập nhật profile (partial). */
  updateProfile(id, payload) {
    return $api(`${BASE}/writing-profiles/${id}`, { method: 'PUT', body: payload })
  },

  /* Xóa profile. */
  deleteProfile(id) {
    return $api(`${BASE}/writing-profiles/${id}`, { method: 'DELETE' })
  },

  /* Giải mã DNA -> 202 {analysis_id, task_run_id}. */
  analyze(payload) {
    return $api(`${BASE}/writing-profiles/analyze`, { method: 'POST', body: payload })
  },

  /* DNA Sandbox -> {text, target_tier, snapshot, ai_run_id}. */
  sandbox(id, payload) {
    return $api(`${BASE}/writing-profiles/${id}/sandbox`, { method: 'POST', body: payload })
  },
}
