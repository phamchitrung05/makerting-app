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
    providersInitialized: false,  // đã nạp providers (ít nhất 1 lần) chưa
    providersModelsLoaded: false, // đã nạp providers kèm models chưa
    /*
     * Đã THỬ nạp providers kèm models xong (kể cả thất bại) — khác với
     * providersModelsLoaded (chỉ true khi thành công). UI cần cờ này để
     * biết khi nào ngừng hiện "Đang tải…" mà chuyển sang trạng thái lỗi,
     * thay vì treo spinner vĩnh viễn khi request fail.
     */
    providersModelsAttempted: false,
    formMetaInitialized: false,   // đã nạp drivers+capabilities chưa
    aiSettings: {                 // payload nhóm 'ai' từ /settings/ai
      version: 0,
      /* eslint-disable camelcase -- key phải khớp property của AiSettings backend */
      values: {                   // khớp key của AiSettings backend
        default_text_model_id: null,
        default_image_model_id: null,
        default_video_model_id: null,
        default_language: 'vi',
      },
      /* eslint-enable camelcase */
    },
    aiSettingsLoaded: false,      // đã nạp settings 'ai' chưa
    loadingAiSettings: false,     // đang tải settings 'ai'
    savingAiSettings: false,
    _providersPromise: null,      // in-flight promise dedup nạp providers
    _providersPromiseWithModels: false, // promise đang bay có kèm models không
    _formMetaPromise: null,       // in-flight promise dedup nạp form meta
    _aiSettingsPromise: null,     // in-flight promise dedup nạp settings
  }),

  getters: {
    /* Số provider đang active (cho KPI). */
    activeProviderCount: state => state.providers.filter(p => p.is_active).length,

    /* Tổng số model usable (cho KPI). */
    totalModelCount: state => state.providers.reduce((n, p) => n + (p.models_count ?? 0), 0),

    /* Provider đang chọn (cho panel phải Tổng quan). */
    selectedProvider: state =>
      state.providers.find(p => p.id === state.selectedProviderId) ?? state.providers[0] ?? null,

    /*
     * Toàn bộ model của mọi provider, phẳng hóa kèm tên provider.
     * Dùng làm nguồn cho các getter options theo capability bên dưới.
     */
    allModels: state =>
      state.providers.flatMap(p =>
        (p.models ?? []).map(m => ({
          id: m.id,
          label: m.label ?? m.remote_model_id,
          remoteModelId: m.remote_model_id,
          providerName: p.name,
          providerId: p.id,
          capabilities: m.capabilities ?? [],
          isEnabled: m.is_enabled !== false,
        })),
      ),

    /*
     * Options dropdown model theo capability — {value,title,subtitle}.
     * Chỉ lấy model đang bật (is_enabled); title gắn tên provider để admin
     * phân biệt khi 2 provider có cùng remote_model_id.
     */
    modelOptionsByCapability: state => capability =>
      state.providers.flatMap(p =>
        (p.models ?? [])
          .filter(m => m.is_enabled !== false && (m.capabilities ?? []).includes(capability))
          .map(m => ({
            value: m.id,
            title: `${m.label ?? m.remote_model_id} · ${p.name}`,
          })),
      ),

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
    /*
     * CHỨC NĂNG: Nạp toàn bộ provider — CHỈ 1 LẦN (trừ khi force).
     * INPUT: {withModels:boolean, force:boolean} — force=true để refetch.
     * OUTPUT: providers[].
     * SIDE EFFECT: GET /providers (1 lần) + ghi providers[] + cờ init.
     *
     * Deduplication: nếu đã initialized và !force -> trả cache ngay (0ms,
     * không gọi API) khi remount tab. Nếu đang có request dở (_providersPromise)
     * -> mọi caller cùng await chung promise đó, không bắn request trùng.
     */
    async fetchProviders({ withModels = false, force = false } = {}) {
      // Đã nạp đủ rồi và không bắt refetch -> trả state hiện có.
      if (this.providersInitialized && !force && (!withModels || this.providersModelsLoaded))
        return this.providers

      /*
       * Đang có request dở -> chờ chung, tránh gọi trùng. NHƯNG chỉ khi
       * promise đó nạp ĐỦ thứ ta cần: caller xin withModels mà promise
       * đang bay lại là bản không models thì phải chạy request riêng,
       * nếu không providersModelsLoaded vĩnh viễn false và các dropdown
       * phụ thuộc models sẽ treo ở trạng thái "Đang tải…".
       */
      if (this._providersPromise && (!withModels || this._providersPromiseWithModels))
        return this._providersPromise

      this.loadingProviders = true

      const run = (async () => {
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

          if (withModels) this.providersModelsLoaded = true
          this.providersInitialized = true

          if (!this.selectedProviderId && this.providers.length)
            this.selectedProviderId = this.providers[0].id

          return this.providers
        } finally {
          this.loadingProviders = false
          this._providersPromise = null
          this._providersPromiseWithModels = false

          // Đánh dấu đã thử (dù thành công hay lỗi) để UI thoát "Đang tải…".
          if (withModels) this.providersModelsAttempted = true
        }
      })()

      this._providersPromise = run
      this._providersPromiseWithModels = withModels

      return run
    },

    /*
     * CHỨC NĂNG: Đảm bảo providers đã nạp — gọi ở onMounted của mọi tab.
     * INPUT: {withModels, force}. force=true chỉ dùng khi bấm "Làm mới/Đồng bộ".
     * OUTPUT: providers[] (cache nếu đã nạp, fetch nếu chưa).
     */
    ensureLoaded(opts = {}) {
      return this.fetchProviders(opts)
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

      // Models đã eager-load sẵn trong provider (ensureLoaded withModels)
      // -> không cần gọi lại GET /models mỗi lần bấm chọn provider.
      const p = this.providers.find(x => x.id === id)
      if (p && Array.isArray(p.models) && p.models.length) {
        this.models = p.models
      } else if (id && ! this.providersModelsLoaded) {
        // Chỉ fetch khi chưa từng nạp models (providers nạp không kèm models).
        await this.fetchModels({ 'ai_provider_id': id }) // eslint-disable-line camelcase
      }
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
          p._syncError = null // ping lại ok/fail đều xóa cờ lỗi đồng bộ cũ
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

      const p = this.providers.find(x => x.id === id)
      try {
        const data = await coreAiApi.syncProviderModels(id) ?? {}

        // Sync thành công -> xóa cờ lỗi cũ.
        if (p) p._syncError = null

        // Nạp lại chi tiết provider để panel Kết nối có models mới nhất.
        try {
          const detail = await coreAiApi.provider(id)
          const models = detail?.models ?? detail?.data?.models ?? []
          if (p) {
            p.models = models
            p.models_count = models.length // eslint-disable-line camelcase
            p.test_status = data.test_status ?? p.test_status // eslint-disable-line camelcase
          }
        } catch { /* giữ models cũ nếu detail lỗi */ }

        return data // data giờ có {ok,synced,created,updated,deleted,models}
      } catch (e) {
        // Sync thất bại (502 ping fail / mạng): message lấy từ backend, gắn cờ
        // để panel Kết nối hiển thị "Lỗi Đồng bộ Model" thay "N mô hình".
        if (p) {
          p._syncError = e?.data?.message ?? e?.message ?? 'Đồng bộ thất bại'
          p.test_status = 'failed' // eslint-disable-line camelcase
        }
        throw e
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

      // Xóa kết quả cũ ngay khi bắt đầu -> UI không hiện latency/lần ping trước.
      const pm = this.providers.find(x => x.id === providerId)
      const mm = pm?.models?.find(x => x.id === modelId)
      if (mm) { mm._latencyText = '…'; mm._testOk = null }

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
     * CHỨC NĂNG: Cập nhật provider (partial) -> ghi đè tại chỗ trong providers[].
     * INPUT: id + payload {key?,name?,driver?,base_url?,api_key?,extra_keys?,is_active?}.
     * OUTPUT: provider đã cập nhật (Resource).
     * SIDE EFFECT: PUT /providers/{id} + Object.assign vào providers[] (không refetch).
     * Lưu ý: Resource không trả key — chỉ flag; khi client vừa gửi api_key mới
     * thì set api_key_configured=true, vừa gửi extra_keys thì cập nhật count.
     */
    async updateProvider(id, payload) {
      const data = await coreAiApi.updateProvider(id, payload) ?? {}

      const p = this.providers.find(x => x.id === id)
      const sentNewKey = typeof payload?.api_key === 'string' && payload.api_key.trim() !== '' // eslint-disable-line camelcase
      const sentExtraKeys = Object.prototype.hasOwnProperty.call(payload ?? {}, 'extra_keys')

      if (p) {
        const prevModels = p.models ?? []

        Object.assign(p, data)
        if (sentNewKey) {
          // Đổi key -> backend xóa model cũ; clear UI để không hiện model cũ.
          p.models = []
          p.models_count = 0 // eslint-disable-line camelcase
          p.api_key_configured = true // eslint-disable-line camelcase
        } else if (!data.models || data.models.length === 0) {
          // Không đổi key + response không eager-load models -> giữ models cũ
          // (Object.assign(p, data) với data.models=[] sẽ xóa nhầm nếu không guard).
          p.models = prevModels
        }
        if (sentExtraKeys) p.extra_keys_count = (payload.extra_keys ?? []).length // eslint-disable-line camelcase
      }

      // Đổi API key -> backend xóa model cũ -> tự động sync lấy model của key
      // mới lên UI ngay (không cần bấm Sync; bắt kể provider trước có 0 model).
      if (sentNewKey) {
        try {
          await this.syncProvider(id)
        } catch { /* sync fail giữ _syncError do syncProvider ghi */ }
      }

      return data
    },

    /*
     * CHỨC NĂNG: Nạp nhóm cài đặt 'ai' — CHỈ 1 LẦN (dedup in-flight).
     * INPUT: {force:boolean} — force=true để refetch sau khi lưu/conflict.
     * OUTPUT: aiSettings {version, values}.
     * SIDE EFFECT: GET /settings/ai + ghi aiSettings + cờ aiSettingsLoaded.
     *
     * Lỗi 403 (thiếu quyền settings.ai.view) không throw — giữ giá trị mặc
     * định để card vẫn render read-only thay vì vỡ cả tab.
     */
    async fetchAiSettings({ force = false } = {}) {
      if (this.aiSettingsLoaded && !force) return this.aiSettings
      if (this._aiSettingsPromise) return this._aiSettingsPromise

      this.loadingAiSettings = true

      const run = (async () => {
        try {
          const res = await coreAiApi.getAiSettings()

          this.aiSettings = {
            version: res?.version ?? 0,
            values: { ...this.aiSettings.values, ...(res?.values ?? {}) },
          }
          this.aiSettingsLoaded = true

          return this.aiSettings
        } catch (e) {
          // Thiếu quyền/chưa seed -> giữ defaults, không chặn render tab.
          this.lastError = e?.data?.message ?? e?.message ?? null

          return this.aiSettings
        } finally {
          this.loadingAiSettings = false
          this._aiSettingsPromise = null
        }
      })()

      this._aiSettingsPromise = run

      return run
    },

    /*
     * CHỨC NĂNG: Lưu nhóm cài đặt 'ai' với khóa lạc quan version.
     * INPUT: values — map partial {default_text_model_id, default_language,...}.
     * OUTPUT: {version, values} sau khi ghi.
     * SIDE EFFECT: PUT /settings/ai + cập nhật aiSettings tại chỗ.
     * EXCEPTION: ném lỗi 409 (version lệch) / 422 để component hiển thị;
     *            caller nên gọi fetchAiSettings({force:true}) khi 409.
     */
    async saveAiSettings(values) {
      this.savingAiSettings = true
      try {
        // Đảm bảo đã có version mới nhất trước khi lưu để tránh 409
        if (!this.aiSettingsLoaded || !this.aiSettings.version) {
          await this.fetchAiSettings({ force: true })
        }

        const payload = { ...this.aiSettings.values, ...values }

        // Bỏ key version khỏi values vì version được gửi qua expected_version
        delete payload.version

        const res = await coreAiApi.updateAiSettings(payload, this.aiSettings.version)

        this.aiSettings = {
          version: res?.version ?? (this.aiSettings.version + 1),
          values: { ...this.aiSettings.values, ...(res?.values ?? payload) },
        }
        this.aiSettingsLoaded = true

        return this.aiSettings
      } finally {
        this.savingAiSettings = false
      }
    },

    /*
     * CHỨC NĂNG: Nạp drivers + capabilities — CHỈ 1 LẦN (dedup in-flight).
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: GET drivers + capabilities (1 lần) + ghi drivers/capabilities.
     */
    async fetchFormMeta() {
      if (this.formMetaInitialized) return
      if (this._formMetaPromise) return this._formMetaPromise

      const run = (async () => {
        try {
          const [d, c] = await Promise.all([
            coreAiApi.drivers().catch(() => null),
            coreAiApi.capabilities().catch(() => null),
          ])

          this.drivers = d?.drivers ?? d?.data?.drivers ?? []

          // capabilities() trả mảng [{value,label,icon,color}] trực tiếp.
          this.capabilities = Array.isArray(c) ? c : (c?.data ?? [])
          this.formMetaInitialized = true
        } finally {
          this._formMetaPromise = null
        }
      })()

      this._formMetaPromise = run

      return run
    },
  },
})
