/*
 * =====================================================================
 * CHỨC NĂNG FILE: Service gọi API module Settings.
 * =====================================================================
 * Bọc $api (ofetch, tự unwrap BaseResponse.data) cho endpoint:
 * - GET  /v1/admin/settings          -> list()  danh sách group + meta
 * - GET  /v1/admin/settings/{group}  -> get()   payload (đã che secret)
 * - PUT  /v1/admin/settings/{group}  -> update() ghi kèm expected_version
 * =====================================================================
 * get()/update() trả object { group, version, values, secrets }.
 * values của trường bí mật chỉ có cờ '{name}_configured' => bool (không có
 * giá trị thật). update() ném lỗi 409 (isConflictError) khi expected_version
 * lệch — caller bắt để báo "đã bị người khác sửa".
 * =====================================================================
 */
import { $api } from '@/utils/api'

const BASE = '/v1/admin/settings'

export const settingsApi = {
  /*
   * CHỨC NĂNG: Lấy danh sách nhóm cài đặt + metadata (permission/secrets/defaults).
   * INPUT: không. OUTPUT: { groups: [...] }.
   */
  list() {
    return $api(`${BASE}`)
  },

  /*
   * CHỨC NĂNG: Lấy payload một nhóm (đã che secret).
   * INPUT: group string. OUTPUT: { group, version, values, secrets }.
   */
  get(group) {
    return $api(`${BASE}/${group}`)
  },

  /*
   * CHỨC NĂNG: Cập nhật một nhóm với khóa phiên bản lạc quan.
   * INPUT:
   * - group           : khóa nhóm.
   * - values          : map property=>giá trị mới (bỏ trống secret = giữ cũ).
   * - expectedVersion : version client tin là mới nhất.
   * OUTPUT: { group, version, values, secrets } phiên bản mới.
   * LỖI: ném 409 (isConflictError) khi version lệch.
   */
  update(group, values, expectedVersion) {
    return $api(`${BASE}/${group}`, {
      method: 'PUT',
      body: { 'expected_version': expectedVersion, values }, // eslint-disable-line camelcase
    })
  },
}
