/*
 * =====================================================================
 * CHỨC NĂNG FILE: Pinia store DNA văn phong (WritingStyle profiles).
 * =====================================================================
 * Load-once pattern aiConfig: profiles + options (tiers/rules) nạp 1 lần,
 * mọi component dùng chung; thêm/sửa/xóa cập nhật mảng in-place (không refetch).
 * Quản lý vòng lặp polling giải mã DNA có token chống xung đột phiên.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - fetchProfiles(params): nạp danh sách profile phân trang
 * - fetchOptions(): nạp 4 tiers và 16 câu hỏi giải mã
 * - ensureLoaded(): nạp đồng thời profiles và options
 * - createProfile(payload): tạo profile mới và thêm vào danh sách
 * - updateProfile(id, payload): sửa profile và cập nhật in-place
 * - deleteProfile(id): xóa profile khỏi server và local store
 * - fetchProfile(id): tải chi tiết một profile
 * - analyze(payload): gửi văn bản giải mã DNA (nhận 202)
 * - fetchAnalysis(id, opts): đọc 1 lần kết quả giải mã theo UUID (nhận AbortSignal)
 * - waitForAnalysis(id, opts): poll kết quả giải mã định kỳ kèm cơ chế hủy sạch
 *   (deadline timer + AbortController nên request treo vẫn bị cắt đúng hạn)
 * - stopPolling(): ngắt chu kỳ poll, abort request đang bay, giải phóng Promise
 * - sandbox(id, payload): gọi AI viết thử nghiệm theo DNA
 *
 * INPUT/OUTPUT CỦA STORE (tổng thể):
 * - INPUT : payload từ UI components qua writingStyleApi
 * - OUTPUT: state phản ứng (profiles, options, analysisResult, sandboxResult)
 * =====================================================================
 */
/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { writingStyleApi } from '@modules/WritingStyle/resources/js/services/writingStyleApi'

export const useWritingStyleStore = defineStore('writingStyle', {
  state: () => ({
    profiles: [],            // WritingProfileResource[]
    total: 0,                // itemsLength từ server (dataTable)
    options: { tiers: [], rules: [] },
    profilesLoaded: false,
    optionsLoaded: false,
    loadingProfiles: false,
    saving: false,
    analyzing: false,
    sandboxing: false,
    lastAnalysis: null,      // {analysis_id, task_run_id} của lần analyze gần nhất
    analysisResult: null,    // WritingAnalysisResource của lần giải mã đã đọc xong
    analysisPolling: false,
    analysisTimer: null,     // handle setTimeout của vòng poll (để hủy)
    _pollToken: 0,           // định danh phiên poll (tránh race condition giữa các lần gọi)
    _pollWakeup: null,       // hàm đánh thức Promise chờ khi stopPolling() được gọi
    _pollAbort: null,        // AbortController của request poll đang bay (hủy fetch giữa chừng)
    _pollDeadlineTimer: null, // setTimeout hết hạn — cắt cả request treo không trả
    sandboxResult: null,     // SandboxResultDto gần nhất
  }),

  getters: {
    tiers: state => state.options.tiers ?? [],
    ruleKeys: state => state.options.rules ?? [],
    enabledProfiles: state => state.profiles.filter(p => p.is_enabled),

    /* Số lượng theo nhóm trạng thái UI:
     * - active   : status='active' + đang bật.
     * - draft    : status='draft'  + đang bật.
     * - archived : tắt (is_enabled=false) — spec chỉ có draft|active,
     *              "lưu trữ" quy ước là profile bị tắt. */
    statusCounts: state => {
      const list = state.profiles

      return {
        all: list.length,
        active: list.filter(p => p.status === 'active' && p.is_enabled).length,
        draft: list.filter(p => p.status === 'draft' && p.is_enabled).length,
        archived: list.filter(p => !p.is_enabled).length,
      }
    },
  },

  actions: {
    /**
     * =====================================================================
     * CHỨC NĂNG: Nạp danh sách profile từ API theo tiêu chí lọc
     * =====================================================================
     *
     * INPUT:
     * - opts (Object): { force = false, ...params (search/tier/origin/status) }
     *
     * OUTPUT:
     * - Promise<Array>: Danh sách profiles
     *
     * SIDE EFFECT:
     * - Cập nhật state profiles, total, profilesLoaded, loadingProfiles
     *
     * EXCEPTION:
     * - ApiError được re-throw cho caller xử lý
     * =====================================================================
     */
    async fetchProfiles({ force = false, ...params } = {}) {
      if (this.profilesLoaded && !force && !Object.keys(params).length) return this.profiles
      this.loadingProfiles = true
      try {
        const res = await writingStyleApi.profiles({ itemsPerPage: 100, ...params })

        this.profiles = res?.items ?? (Array.isArray(res) ? res : [])
        this.total = res?.itemsLength ?? this.profiles.length
        this.profilesLoaded = true

        return this.profiles
      } finally {
        this.loadingProfiles = false
      }
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Nạp danh mục cấp độ độ dài (tiers) và 16 câu hỏi giải mã
     * =====================================================================
     *
     * INPUT:
     * - opts (Object): { force = false }
     *
     * OUTPUT:
     * - Promise<Object>: { tiers, rules }
     *
     * SIDE EFFECT:
     * - Cập nhật state options, optionsLoaded
     *
     * EXCEPTION:
     * - ApiError được re-throw cho caller xử lý
     * =====================================================================
     */
    async fetchOptions({ force = false } = {}) {
      if (this.optionsLoaded && !force) return this.options
      const res = await writingStyleApi.options()

      this.options = res ?? { tiers: [], rules: [] }
      this.optionsLoaded = true

      return this.options
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Đảm bảo cả profiles và options đều được nạp sẵn sàng
     * =====================================================================
     *
     * INPUT:
     * - opts (Object): Tùy chọn nạp cho fetchProfiles và fetchOptions
     *
     * OUTPUT:
     * - Promise<[Array, Object]>: Kết quả của Promise.all
     *
     * SIDE EFFECT:
     * - Nạp cả 2 tập dữ liệu song song
     *
     * EXCEPTION:
     * - Re-throw nếu một trong hai promise thất bại
     * =====================================================================
     */
    ensureLoaded(opts = {}) {
      return Promise.all([this.fetchProfiles(opts), this.fetchOptions(opts)])
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Tạo mới hồ sơ văn phong thủ công và chèn vào đầu danh sách
     * =====================================================================
     *
     * INPUT:
     * - payload (Object): Dữ liệu hồ sơ theo StoreWritingProfileRequest
     *
     * OUTPUT:
     * - Promise<Object>: WritingProfileResource vừa tạo
     *
     * SIDE EFFECT:
     * - Unshift bản ghi mới vào state profiles, bật/tắt saving
     *
     * EXCEPTION:
     * - ApiError khi validation thất bại hoặc quyền không đủ
     * =====================================================================
     */
    async createProfile(payload) {
      this.saving = true
      try {
        const res = await writingStyleApi.createProfile(payload)

        this.profiles.unshift(res)

        return res
      } finally {
        this.saving = false
      }
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Cập nhật thông tin hồ sơ và thay thế in-place trong store
     * =====================================================================
     *
     * INPUT:
     * - id (number|string): ID hồ sơ cần cập nhật
     * - payload (Object): Các trường cần thay đổi
     *
     * OUTPUT:
     * - Promise<Object>: WritingProfileResource sau cập nhật
     *
     * SIDE EFFECT:
     * - Cập nhật phần tử tương ứng trong state profiles, bật/tắt saving
     *
     * EXCEPTION:
     * - ApiError khi 404 hoặc lỗi validation 422
     * =====================================================================
     */
    async updateProfile(id, payload) {
      this.saving = true
      try {
        const res = await writingStyleApi.updateProfile(id, payload)
        const i = this.profiles.findIndex(p => p.id === id)
        if (i >= 0) this.profiles[i] = res

        return res
      } finally {
        this.saving = false
      }
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Xóa hồ sơ trên server và loại bỏ khỏi mảng state profiles
     * =====================================================================
     *
     * INPUT:
     * - id (number|string): ID hồ sơ cần xóa
     *
     * OUTPUT:
     * - Promise<void>
     *
     * SIDE EFFECT:
     * - Lọc bỏ item khỏi state profiles
     *
     * EXCEPTION:
     * - ApiError khi 403 hoặc 404
     * =====================================================================
     */
    async deleteProfile(id) {
      await writingStyleApi.deleteProfile(id)
      this.profiles = this.profiles.filter(p => p.id !== id)
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Tải thông tin chi tiết một hồ sơ (kèm rules và evidence)
     * =====================================================================
     *
     * INPUT:
     * - id (number|string): ID hồ sơ
     *
     * OUTPUT:
     * - Promise<Object>: WritingProfileResource chi tiết
     *
     * SIDE EFFECT:
     * - Gọi GET /writing-profiles/{id}
     *
     * EXCEPTION:
     * - ApiError khi 404
     * =====================================================================
     */
    async fetchProfile(id) {
      return writingStyleApi.profile(id)
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Gửi văn bản bài mẫu yêu cầu backend tạo phiên giải mã DNA
     * =====================================================================
     *
     * INPUT:
     * - payload (Object): { source_type: 'paste', reference_text, name? }
     *
     * OUTPUT:
     * - Promise<Object>: 202 Accepted { analysis_id, task_run_id, status }
     *
     * SIDE EFFECT:
     * - Bật/tắt analyzing, lưu kết quả vào state lastAnalysis
     *
     * EXCEPTION:
     * - ApiError 422 nếu văn bản dưới 50 ký tự
     * =====================================================================
     */
    async analyze(payload) {
      this.analyzing = true
      try {
        const res = await writingStyleApi.analyze(payload)

        this.lastAnalysis = res

        return res
      } finally {
        this.analyzing = false
      }
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Đọc trạng thái và kết quả 1 lần giải mã DNA theo UUID
     * =====================================================================
     *
     * INPUT:
     * - id (string): UUID phiên phân tích
     *
     * OUTPUT:
     * - Promise<Object>: WritingAnalysisResource
     *
     * SIDE EFFECT:
     * - Lưu resource vào state analysisResult
     *
     * EXCEPTION:
     * - ApiError khi không tìm thấy phiên giải mã
     * =====================================================================
     */
    async fetchAnalysis(id, { signal } = {}) {
      const res = await writingStyleApi.analysis(id, { signal })

      this.analysisResult = res

      return res
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Thăm dò định kỳ (Poll) cho tới khi phiên giải mã AI hoàn tất
     * =====================================================================
     *
     * INPUT:
     * - id (string): UUID phiên giải mã
     * - opts (Object): { intervalMs = 3000, timeoutMs = 420000 }
     *
     * OUTPUT:
     * - Promise<Object|null>: Resource khi succeeded/failed; null nếu bị stop sớm
     *   (stopPolling / unmount / request bị abort)
     *
     * SIDE EFFECT:
     * - Bật analysisPolling, quản lý timer hẹn giờ (interval + deadline), bộ
     *   đếm _pollToken và AbortController của request đang bay
     *
     * EXCEPTION:
     * - Ném Error khi quá thời hạn timeoutMs mà job chưa kết thúc
     * - Bubble ApiError nếu request lỗi thật và đã hết hạn chờ
     * - KHÔNG ném AbortError: abort được coi là hủy sạch -> trả null
     * =====================================================================
     */
    /*
     * timeoutMs mặc định 420s: bao trọn trường hợp xấu nhất của job ai-text —
     * timeout 300s × tries 2 = 600s là trần cứng của worker, nhưng thực tế
     * attempt 1 gần như luôn xong < 300s; cộng đệm cho đường mạng. Trước đây
     * 300s ngắn hơn một nhánh retry đang chạy hợp lệ nên FE báo timeout sớm.
     */
    async waitForAnalysis(id, { intervalMs = 3000, timeoutMs = 420000 } = {}) {
      this.stopPolling()

      const token = ++this._pollToken

      this.analysisPolling = true

      const deadline = Date.now() + timeoutMs

      // Hết hạn một lần duy nhất — bắt cả trường hợp request fetch bị treo mãi
      // (server giữ connection không trả lời) mà vòng while không kịp chạy để
      // tự check `Date.now() >= deadline`. timedOut phân biệt abort-do-hết-hạn
      // (phải báo lỗi) với abort-do-hủy (unmount/stopPolling -> trả null).
      let timedOut = false

      this._pollDeadlineTimer = setTimeout(() => {
        this._pollDeadlineTimer = null
        if (!this.analysisPolling || this._pollToken !== token) return

        timedOut = true
        this.analysisPolling = false

        if (this._pollAbort) this._pollAbort.abort()

        if (typeof this._pollWakeup === 'function') {
          const wake = this._pollWakeup

          this._pollWakeup = null
          wake()
        }
      }, timeoutMs)

      const TIMEOUT_MSG = 'Hết thời gian chờ giải mã (queue worker đang chạy không?)'

      const isAbortErr = e =>
        e?.name === 'AbortError' ||
        e?.name === 'DOMException' && e?.code === 20 ||
        /abort/i.test(e?.message ?? '')

      try {
        while (this.analysisPolling && this._pollToken === token) {
          this._pollAbort = new AbortController()
          try {
            const res = await this.fetchAnalysis(id, { signal: this._pollAbort.signal })

            if (!this.analysisPolling || this._pollToken !== token)
              return null

            if (res?.status === 'succeeded' || res?.status === 'failed')
              return res
          } catch (err) {
            if (isAbortErr(err)) {
              // Abort do hết hạn -> báo lỗi; abort do hủy (unmount/stopPolling)
              // -> dừng sạch, trả null.
              if (timedOut) throw new Error(TIMEOUT_MSG)

              return null
            }

            // Lỗi thật (mạng, 5xx, 404...): nếu vẫn còn hạn thì ngủ rồi thử lại,
            // hết hạn thì bubble ra cho caller xử lý thay vì treo vòng lặp.
            if (timedOut || Date.now() >= deadline) throw err
          } finally {
            this._pollAbort = null
          }

          if (timedOut || Date.now() >= deadline)
            throw new Error(TIMEOUT_MSG)

          // Ngủ intervalMs nhưng có thể bị stopPolling()/hết hạn đánh thức lập tức
          await new Promise(resolve => {
            this._pollWakeup = resolve
            this.analysisTimer = setTimeout(() => {
              this._pollWakeup = null
              this.analysisTimer = null
              resolve()
            }, intervalMs)
          })
        }

        // Đến đây khi polling bị tắt từ bên ngoài (unmount/stopPolling) hoặc
        // deadline timer đã tắt analysisPolling -> chỉ báo lỗi khi do hết hạn.
        if (timedOut) throw new Error(TIMEOUT_MSG)

        return null
      } finally {
        // Chỉ dọn state nếu đây vẫn là phiên poll hiện tại (không ghi đè phiên mới)
        if (this._pollToken === token) {
          this.analysisPolling = false

          if (this.analysisTimer) { clearTimeout(this.analysisTimer); this.analysisTimer = null }
          if (this._pollDeadlineTimer) { clearTimeout(this._pollDeadlineTimer); this._pollDeadlineTimer = null }
          if (this._pollAbort) { this._pollAbort = null }
          this._pollWakeup = null
        }
      }
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Ngắt vòng lặp polling đang chạy và giải phóng Promise lập tức
     * =====================================================================
     *
     * INPUT:
     * - Không có
     *
     * OUTPUT:
     * - void
     *
     * SIDE EFFECT:
     * - Tăng _pollToken, tắt analysisPolling, clearTimeout, abort request đang
     *   bay và kích hoạt _pollWakeup
     *
     * EXCEPTION:
     * - Không có
     * =====================================================================
     */
    stopPolling() {
      this._pollToken++
      this.analysisPolling = false

      if (this.analysisTimer) {
        clearTimeout(this.analysisTimer)
        this.analysisTimer = null
      }

      if (this._pollDeadlineTimer) {
        clearTimeout(this._pollDeadlineTimer)
        this._pollDeadlineTimer = null
      }

      // Ngắt request fetch đang bay (nếu có) — bắt lỗi AbortError ở vòng poll.
      if (this._pollAbort) {
        try { this._pollAbort.abort() } catch { /* abort đã chạy rồi */ }
        this._pollAbort = null
      }

      // Đánh thức Promise đang ngủ để waitForAnalysis kết thúc ngay lập tức
      if (typeof this._pollWakeup === 'function') {
        const wake = this._pollWakeup

        this._pollWakeup = null
        wake()
      }
    },

    /**
     * =====================================================================
     * CHỨC NĂNG: Chạy thử nghiệm viết bài theo DNA văn phong (DNA Sandbox)
     * =====================================================================
     *
     * INPUT:
     * - id (number|string): ID hồ sơ văn phong
     * - payload (Object): { topic, target_tier, target_sentences? }
     *
     * OUTPUT:
     * - Promise<Object>: { text, target_tier, snapshot, ai_run_id }
     *
     * SIDE EFFECT:
     * - Bật/tắt sandboxing, lưu kết quả vào sandboxResult
     *
     * EXCEPTION:
     * - ApiError khi gọi AI thất bại hoặc hồ sơ chưa sẵn sàng
     * =====================================================================
     */
    async sandbox(id, payload) {
      this.sandboxing = true
      try {
        const res = await writingStyleApi.sandbox(id, payload)

        this.sandboxResult = res

        return res
      } finally {
        this.sandboxing = false
      }
    },
  },
})
