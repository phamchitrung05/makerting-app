/*
 * =====================================================================
 * CHỨC NĂNG FILE: Service gọi API module Identity.
 * =====================================================================
 * Bọc $api (ofetch, tự unwrap BaseResponse) cho các endpoint:
 * - auth: login/logout/me/password
 * - channel-groups: CRUD + /options
 * - roles + permission matrix
 * =====================================================================
 */
import { $api } from '@/utils/api'

const BASE = '/v1/admin/identity'

export const identityApi = {
  /*
   * Đăng nhập admin -> { accessToken, userData, userAbilityRules }.
   */
  login(payload) {
    return $api(`${BASE}/auth/login`, { method: 'POST', body: payload })
  },

  logout() {
    return $api(`${BASE}/auth/logout`, { method: 'POST' })
  },

  me() {
    return $api(`${BASE}/auth/me`)
  },

  updatePassword(payload) {
    return $api(`${BASE}/auth/password`, { method: 'PUT', body: payload })
  },

  // ----- Channel groups -----
  channelGroups(params = {}) {
    return $api(`${BASE}/channel-groups`, { params })
  },

  channelGroupOptions() {
    return $api(`${BASE}/channel-groups/options`)
  },

  createChannelGroup(payload) {
    return $api(`${BASE}/channel-groups`, { method: 'POST', body: payload })
  },

  updateChannelGroup(id, payload) {
    return $api(`${BASE}/channel-groups/${id}`, { method: 'PUT', body: payload })
  },

  deleteChannelGroup(id) {
    return $api(`${BASE}/channel-groups/${id}`, { method: 'DELETE' })
  },

  // ----- Roles & permissions -----
  permissionMatrix() {
    return $api(`${BASE}/permissions/matrix`)
  },

  roles(params = {}) {
    return $api(`${BASE}/roles`, { params })
  },

  createRole(payload) {
    return $api(`${BASE}/roles`, { method: 'POST', body: payload })
  },

  updateRole(id, payload) {
    return $api(`${BASE}/roles/${id}`, { method: 'PUT', body: payload })
  },

  deleteRole(id) {
    return $api(`${BASE}/roles/${id}`, { method: 'DELETE' })
  },
}
