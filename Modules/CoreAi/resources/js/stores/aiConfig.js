/*
 * =====================================================================
 * CHỨC NĂNG FILE: Pinia store cấu hình AI (providers + models).
 * =====================================================================
 * Chia sẻ state cho các tab trong Settings › AI & Mô hình và trang CoreAi:
 * - providers[]  : danh sách ai_providers (kèm models_count, test_status).
 * - models[]     : danh sách ai_models theo provider đang chọn.
 * - drivers[]    : slug driver đã đăng ký backend (cho form Thêm provider).
 * - capabilities[] : CAP_* cho form Thêm model.
 *
 * Actions bọc coreAiApi — bật/tắt loading, unwrap data, giữ state đồng bộ
 * để các tab không phải fetch trùng.
 * =====================================================================
 */
import { defineStore } from 'pinia'
import { coreAiApi } from '../services/coreAiApi'

/* Map driver slug -> {icon,bg,color} cho avatar provider trong UI. */
const DRIVER_STYLE = {
  'openai': { icon: 'tabler-sparkles', bg: '#10a37f', color: 'white' },
  'openai-compatible': { icon: 'tabler-sparkles', bg: '#10a37f', color: 'white' },
  'anthropic': { icon: 'tabler-brain', bg: '#f3e5d8', color: '#1f1f1f' },
  'anthropic-compatible': { icon: 'tabler-brain', bg: '#f3e5d8', color: '#1f1f1f' },
  'gemini': { icon: 'tabler-star-four-points', bg: '#eef2ff', color: '#4285F4' },
  'elevenlabs': { icon: 'tabler-microphone', bg: '#18181b', color: 'white' },
}

const DEFAULT_STYLE = { icon: 'tabler-server', bg: '#eef2ff', color: '#4285F4' }

/* Capability -> nhãn loại tiếng Việt cho cột "type" của model. */
const CAP_LABEL = {
  'text_generation': 'Văn bản (LLM)',
  'structured_output': 'Văn bản (LLM)',
  'vision': 'Hình ảnh',
  'image_generation': 'Hình ảnh',
  'tts': 'Âm thanh',
  'stt': 'Âm thanh',
}

export const useAiConfigStore = defineStore('aiConfig', {
  state: () => ({
    providers: [],          // AiProviderResource[]
    models: [],             // AiModelResource[] của provider đang chọn
    drivers: [],
    capabilities: [],
    loadingProviders: false,
    loadingModels: false,
    saving: false,
    testingId: null,        // id provider đang ping
    testingModelId: null,   // id model đang ping
    syncingId: null,        // id provider đang sync models
    selectedProviderId: null,
    lastError: null,
  }),

  getters: {
    /* Số provider đang active (cho KPI). */
    activeProviderCount: state => state.providers.filter(p => p.is_active).length,

    /* Tổng số model usable (cho KPI). */
    totalModelCount: state => state.providers.reduce((n, p) => n + (p.models_count ?? 0), 0),

    /* Provider đang chọn (cho panel phải Tổng quan). */
    selectedProvider: state =>
      state.providers.find(p => p.id === state.selectedProviderId) ?? state.providers[0] ?? null,

    /* Provider kèm style avatar + flag UI (cho panel trái Tổng quan). */
    providersForUI: state =>
      state.providers.map(p => ({
        ...p,
        ...DRIVER_STYLE[p.driver] ?? DEFAULT_STYLE,
        active: p.is_active,
        pinging: state.testingId === p.id,
        syncing: state.syncingId === p.id,
        latencyText: p._latencyText ?? '—',
        modelCount: `${p.models_count ?? (p.models?.length ?? 0)} mô hình`,
        pricing: '—',
        statusText: p.test_status === 'ok'
          ? 'Đang hoạt động'
          : (p.test_status === 'failed' ? 'Lỗi kết nối' : (p.is_active ? 'Đã cấu hình' : 'Chưa kích hoạt')),
        models: p.models ?? [],
      })),
  },

  actions: {
    /*
     * CHỨC NĂNG: Nạp toàn bộ provider (+models eager khi cần).
     * INPUT: {withModels:boolean} — true để eager models (panel Tổng quan).
     * OUTPUT: providers[] đã nạp.
     * SIDE EFFECT: GET /providers + ghi providers[] + selectedProviderId.
     */
    async fetchProviders({ withModels = false } = {}) {
      this.loadingProviders = true
      try {
        // $api đã unwrap data -> dataTable trả {items, itemsLength} top-level.
        const res = await coreAiApi.allProviders()
        const items = res?.items ?? (Array.isArray(res) ? res : [])

        // Nếu cần models per-provider (panel chọn model), nạp chi tiết từng cái.
        this.providers = withModels
          ? await Promise.all(items.map(async p => {
            try {
              const detail = await coreAiApi.provider(p.id)
              
              return { ...p, models: detail?.models ?? detail?.data?.models ?? [] }
            } catch {
              return { ...p, models: [] }
            }
          }))
          : items

        if (!this.selectedProviderId && this.providers.length)
          this.selectedProviderId = this.providers[0].id

        return this.providers
      } finally {
        this.loadingProviders = false
      }
    },

    /*
     * CHỨC NĂNG: Nạp models (có thể lọc theo provider).
     * INPUT: {ai_provider_id?, capability?}.
     * OUTPUT: models[] đã nạp.
     * SIDE EFFECT: GET /models + ghi models[].
     */
    async fetchModels(params = {}) {
      this.loadingModels = true
      try {
        const res = await coreAiApi.models({ itemsPerPage: 200, ...params })

        this.models = res?.items ?? (Array.isArray(res) ? res : [])

        return this.models
      } finally {
        this.loadingModels = false
      }
    },

    /*
     * CHỨC NĂNG: Chọn provider hiển thị ở panel phải + nạp models của nó.
     * INPUT: providerId. OUTPUT: void.
     * SIDE EFFECT: ghi selectedProviderId + fetchModels lọc provider.
     */
    async selectProvider(id) {
      this.selectedProviderId = id
      if (id) await this.fetchModels({ 'ai_provider_id': id }) // eslint-disable-line camelcase
    },

    /*
     * CHỨC NĂNG: Ping một provider -> cập nhật test_status/latency.
     * INPUT: providerId. OUTPUT: {ok,latency_ms,message,...}.
     * SIDE EFFECT: POST /providers/{id}/test + refresh provider trong list.
     */
    async testProvider(id) {
      this.testingId = id
      try {
        // $api đã unwrap BaseResponse.data -> res là payload {ok,latency_ms,...}.
        const data = await coreAiApi.testProvider(id) ?? {}

        // Cập nhật test_status trong list mà không refetch toàn bộ.
        const p = this.providers.find(x => x.id === id)
        if (p) {
          p.test_status = data.test_status ?? (data.ok ? 'ok' : 'failed') // eslint-disable-line camelcase
          p.last_tested_at = new Date().toISOString() // eslint-disable-line camelcase
          p._latencyText = data.latency_ms != null ? `${data.latency_ms}ms` : '—'
          p._testMessage = data.message
        }

        return data
      } finally {
        this.testingId = null
      }
    },

    /*
     * CHỨC NĂNG: Đồng bộ models từ provider -> upsert ai_models, refresh list.
     * INPUT: providerId. OUTPUT: {ok,synced,created,updated,models[]}.
     * SIDE EFFECT: POST /providers/{id}/sync-models + refresh provider.models.
     */
    async syncProvider(id) {
      this.syncingId = id
      try {
        const data = await coreAiApi.syncProviderModels(id) ?? {}

        // Nạp lại chi tiết provider để panel Kết nối có models mới nhất.
        try {
          const detail = await coreAiApi.provider(id)
          const models = detail?.models ?? detail?.data?.models ?? []
          const p = this.providers.find(x => x.id === id)
          if (p) {
            p.models = models
            p.models_count = models.length // eslint-disable-line camelcase
            p.test_status = data.test_status ?? p.test_status // eslint-disable-line camelcase
          }
        } catch { /* giữ models cũ nếu detail lỗi */ }

        return data
      } finally {
        this.syncingId = null
      }
    },

    /*
     * CHỨC NĂNG: Ping tất cả provider tuần tự (cho nút Kiểm tra tất cả).
     * INPUT: không. OUTPUT: void.
     */
    async testAllProviders() {
      for (const p of this.providers)
        await this.testProvider(p.id)
    },

    /*
     * CHỨC NĂNG: Ping 1 model cụ thể -> cập nhật is_available + latency.
     * INPUT: {providerId, modelId}. OUTPUT: {ok,latency_ms,is_available}.
     * SIDE EFFECT: POST /models/{id}/test + cập nhật model trong providers[].
     */
    async testModel(providerId, modelId) {
      this.testingModelId = modelId
      try {
        const data = await coreAiApi.testModel(modelId) ?? {}

        // Cập nhật is_available + latency của model trong provider tương ứng.
        const p = this.providers.find(x => x.id === providerId)
        const m = p?.models?.find(x => x.id === modelId)
        if (m) {
          m.is_available = data.is_available ?? m.is_available // eslint-disable-line camelcase
          m._latencyText = data.latency_ms != null ? `${data.latency_ms}ms` : '—'
          m._testOk = data.ok ?? null
        }

        return data
      } finally {
        this.testingModelId = null
      }
    },

    /*
     * CHỨC NĂNG: Cập nhật capabilities của model (checkbox trong dialog).
     * INPUT: {providerId, modelId, capabilities:string[]}.
     * OUTPUT: model đã cập nhật.
     * SIDE EFFECT: PUT /models/{id} + ghi lại capabilities trong providers[].
     */
    async saveModelCapabilities(providerId, modelId, capabilities) {
      const data = await coreAiApi.updateModel(modelId, { capabilities }) ?? {}

      const p = this.providers.find(x => x.id === providerId)
      const m = p?.models?.find(x => x.id === modelId)
      if (m && data.capabilities) m.capabilities = data.capabilities

      return data
    },

    /*
     * CHỨC NĂNG: Tạo provider mới -> unshift vào providers[] (không refetch).
     * INPUT: payload {key,name,kind,driver,base_url,api_key,...}.
     * OUTPUT: provider vừa tạo.
     * SIDE EFFECT: POST /providers + ghi vào đầu providers[].
     */
    async addProvider(payload) {
      const data = await coreAiApi.createProvider(payload) ?? {}

      this.providers.unshift({ ...data, models: data.models ?? [] })
      if (!this.selectedProviderId) this.selectedProviderId = data.id
      
      return data
    },

    /*
     * CHỨC NĂNG: Nạp drivers + capabilities cho các dialog form.
     * INPUT: không. OUTPUT: void.
     */
    async fetchFormMeta() {
      try {
        const [d, c] = await Promise.all([
          coreAiApi.drivers().catch(() => null),
          coreAiApi.capabilities().catch(() => null),
        ])

        this.drivers = d?.drivers ?? d?.data?.drivers ?? []

        // capabilities() trả mảng [{value,label,icon,color}] trực tiếp.
        this.capabilities = Array.isArray(c) ? c : (c?.data ?? [])
      } catch {
        // để trống — dialog fallback options cứng.
      }
    },
  },
})
