/*
 * =====================================================================
 * CHỨC NĂNG FILE: Service gọi API module Taxonomy.
 * =====================================================================
 * Bọc $api (ofetch, tự unwrap BaseResponse) cho các endpoint:
 * - categories: CRUD + /options + /tree
 * - tags: CRUD + /options
 * =====================================================================
 */
import { $api } from '@/utils/api'

const BASE = '/v1/admin/taxonomy'

export const taxonomyApi = {
  // ----- Categories -----
  /* Danh sách danh mục phân trang + filter {scope,search,status}. */
  categories(params = {}) {
    return $api(`${BASE}/categories`, { params })
  },

  /* Options nhẹ cho dropdown — params {scope} lọc phân vùng. */
  categoryOptions(params = {}) {
    return $api(`${BASE}/categories/options`, { params })
  },

  /* Cây cha–con nested — params {scope}. */
  categoryTree(params = {}) {
    return $api(`${BASE}/categories/tree`, { params })
  },

  createCategory(payload) {
    return $api(`${BASE}/categories`, { method: 'POST', body: payload })
  },

  updateCategory(id, payload) {
    return $api(`${BASE}/categories/${id}`, { method: 'PUT', body: payload })
  },

  deleteCategory(id) {
    return $api(`${BASE}/categories/${id}`, { method: 'DELETE' })
  },

  // ----- Tags -----
  /* Danh sách thẻ phân trang + filter {scope,search,status}. */
  tags(params = {}) {
    return $api(`${BASE}/tags`, { params })
  },

  /* Options nhẹ cho dropdown — params {scope}. */
  tagOptions(params = {}) {
    return $api(`${BASE}/tags/options`, { params })
  },

  createTag(payload) {
    return $api(`${BASE}/tags`, { method: 'POST', body: payload })
  },

  updateTag(id, payload) {
    return $api(`${BASE}/tags/${id}`, { method: 'PUT', body: payload })
  },

  deleteTag(id) {
    return $api(`${BASE}/tags/${id}`, { method: 'DELETE' })
  },
}
