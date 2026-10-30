/*
 * =====================================================================
 * CHỨC NĂNG FILE: Pinia store quản lý tiến trình ngầm cho popup.
 * =====================================================================
 * - items     : danh sách task đang queued/running (từ /tasks/active).
 * - unread    : số cảnh báo chưa đọc.
 * - polling   : cờ bật/tắt polling (chỉ poll khi popup mở hoặc cần).
 * - startPolling/stopPolling : setInterval gọi refresh() theo poll_ms.
 *
 * Dùng trong <SystemTaskQueuePopup /> (góc dưới phải Admin) và icon chuông.
 * =====================================================================
 */
import { defineStore } from 'pinia'
import { taskCenterApi } from '../services/taskCenterApi'

const POLL_MS = 5000

export const useTaskCenterStore = defineStore('taskCenter', {
  state: () => ({
    items: [],
    unread: 0,
    loading: false,
    _timer: null,
  }),

  getters: {
    runningCount: state => state.items.filter(i => i.status === 'running').length,
    activeCount: state => state.items.length,
  },

  actions: {
    async refresh() {
      try {
        const res = await taskCenterApi.activeTasks()

        this.items = res.items || []
      } catch (e) {
        // im lặng — polling nền không nên spam lỗi.
      }
    },

    async refreshUnread() {
      try {
        const res = await taskCenterApi.unreadCount()

        this.unread = res.count || 0
      } catch (e) {
        // bỏ qua
      }
    },

    startPolling(interval = POLL_MS) {
      this.stopPolling()
      this.refresh()
      this._timer = setInterval(() => this.refresh(), interval)
    },

    stopPolling() {
      if (this._timer) {
        clearInterval(this._timer)
        this._timer = null
      }
    },

    async cancelTask(id) {
      await taskCenterApi.cancelTask(id)
      await this.refresh()
    },
  },
})
