/*
 * =====================================================================
 * CHỨC NĂNG FILE: Service gọi API module CoreAi (providers + models).
 * =====================================================================
 * Bọc $api (ofetch, tự unwrap BaseResponse.data) cho endpoint:
 * - GET    /v1/admin/core-ai/providers              -> providers() dataTable
 * - GET    /v1/admin/core-ai/providers/drivers      -> drivers()
 * - POST   /v1/admin/core-ai/providers/{id}/test    -> testProvider() ping
 * - CRUD   /v1/admin/core-ai/providers              -> create/update/delete
 * - GET    /v1/admin/core-ai/models                 -> models() dataTable
 * - GET    /v1/admin/core-ai/models/options         -> modelOptions()
 * - GET    /v1/admin/core-ai/models/capabilities    -> capabilities()
 * - CRUD   /v1/admin/core-ai/models                 -> create/update/delete
 * =====================================================================
 */
import { $api } from '@/utils/api'

const BASE = '/v1/admin/core-ai'

export const coreAiApi = {
  /* ============================ PROVIDERS ============================ */

  /* Danh sách provider phân trang + filter {search,kind,driver,is_active,...}. */
  providers(params = {}) {
    return $api(`${BASE}/providers`, { params })
  },

  /* Tất cả provider (không phân trang) — cho panel Tổng quan. */
  allProviders() {
    return $api(`${BASE}/providers`, { params: { itemsPerPage: 100 } })
  },

  /* Chi tiết provider + models. */
  provider(id) {
    return $api(`${BASE}/providers/${id}`)
  },

  /* Mảng driver slug đã đăng ký + kinds (cho dropdown form). */
  drivers() {
    return $api(`${BASE}/providers/drivers`)
  },

  /* Tạo provider mới (api_key plaintext -> server mã hóa). */
  createProvider(payload) {
    return $api(`${BASE}/providers`, { method: 'POST', body: payload })
  },

  /* Cập nhật provider (partial — api_key rỗng giữ cũ). */
  updateProvider(id, payload) {
    return $api(`${BASE}/providers/${id}`, { method: 'PUT', body: payload })
  },

  /* Xóa provider (cascade models). */
  deleteProvider(id) {
    return $api(`${BASE}/providers/${id}`, { method: 'DELETE' })
  },

  /* Ping kết nối -> {ok,latency_ms,message,models?} + ghi test_status. */
  testProvider(id) {
    return $api(`${BASE}/providers/${id}/test`, { method: 'POST' })
  },

  /* Đồng bộ model từ provider -> {ok,synced,created,updated,models[]}. */
  syncProviderModels(id) {
    return $api(`${BASE}/providers/${id}/sync-models`, { method: 'POST' })
  },

  /* ============================ MODELS ============================ */

  /* Danh sách model phân trang + filter {ai_provider_id,capability,is_enabled,search}. */
  models(params = {}) {
    return $api(`${BASE}/models`, { params })
  },

  /* Options nhẹ cho dropdown — {ai_provider_id,capability}. */
  modelOptions(params = {}) {
    return $api(`${BASE}/models/options`, { params })
  },

  /* Mảng CAP_* cho checkbox UI. */
  capabilities() {
    return $api(`${BASE}/models/capabilities`)
  },

  /* Tạo model mới. */
  createModel(payload) {
    return $api(`${BASE}/models`, { method: 'POST', body: payload })
  },

  /* Cập nhật model (partial). */
  updateModel(id, payload) {
    return $api(`${BASE}/models/${id}`, { method: 'PUT', body: payload })
  },

  /* Xóa model. */
  deleteModel(id) {
    return $api(`${BASE}/models/${id}`, { method: 'DELETE' })
  },

  /* Ping 1 model cụ thể -> {ok,latency_ms,message,is_available}. */
  testModel(id) {
    return $api(`${BASE}/models/${id}/test`, { method: 'POST' })
  },
}
