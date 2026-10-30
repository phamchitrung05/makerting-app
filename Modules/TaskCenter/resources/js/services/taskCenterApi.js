/*
 * =====================================================================
 * CHỨC NĂNG FILE: Service gọi API module TaskCenter.
 * =====================================================================
 * Bọc $api cho endpoint:
 * - GET /tasks/active        -> activeTasks()   danh sách đang chạy (popup)
 * - GET /tasks               -> tasks()         dataTable + filter
 * - POST /tasks/{id}/cancel  -> cancelTask()
 * - GET /alerts/unread-count -> unreadCount()
 * - GET /alerts              -> alerts()
 * - POST /alerts/{id}/read   -> markAlertRead()
 * - POST /alerts/read-all    -> markAllRead()
 * =====================================================================
 */
import { $api } from '@/utils/api'

const BASE = '/v1/admin/task-center'

export const taskCenterApi = {
  /* Danh sách task đang queued/running (cho popup). */
  activeTasks() {
    return $api(`${BASE}/tasks/active`)
  },

  /* Danh sách task phân trang + filter {status,module,task_type,search}. */
  tasks(params = {}) {
    return $api(`${BASE}/tasks`, { params })
  },

  /* Hủy một task đang queued/running. */
  cancelTask(id) {
    return $api(`${BASE}/tasks/${id}/cancel`, { method: 'POST' })
  },

  /* Số cảnh báo chưa đọc (chuông). */
  unreadCount() {
    return $api(`${BASE}/alerts/unread-count`)
  },

  /* Danh sách cảnh báo + filter {severity,category,unread}. */
  alerts(params = {}) {
    return $api(`${BASE}/alerts`, { params })
  },

  /* Đánh dấu 1 cảnh báo đã đọc. */
  markAlertRead(id) {
    return $api(`${BASE}/alerts/${id}/read`, { method: 'POST' })
  },

  /* Đánh dấu tất cả cảnh báo đã đọc. */
  markAllRead() {
    return $api(`${BASE}/alerts/read-all`, { method: 'POST' })
  },
}
