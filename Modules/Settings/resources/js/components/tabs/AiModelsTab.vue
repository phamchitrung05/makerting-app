<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab "AI & Mô hình" trong trang Cài đặt (không phải route).
  =====================================================================
  Đây là component tab nội bộ — được render động bên trong
  pages/general/index.vue khi bấm mục tương ứng trên sidebar.

  GIAO DIỆN TĨNH (static preview) — dữ liệu demo reactive cục bộ, chưa nối
  backend (ai_providers/ai_models/CoreAi triển khai ở P2-01). Chuẩn Vuexy:
  tabler-* icons + Vuetify components + typography/mật độ/rounded mặc định.

  CÁC HÀM/METHOD TRONG FILE: openProviderDialog/configureProvider/saveProvider
  (dialog Thêm+Sửa nhà cung cấp inline), openModelDialog/addModel (dialog Thêm mô hình tách
  riêng tại ../dialogs/AddAiModelDialog.vue), goToTab (chuyển tab nội bộ).
  INPUT/OUTPUT CỦA CLASS (tổng thể): không — render UI tĩnh.
  SIDE EFFECT: không.
  =====================================================================
-->
<script setup>
import { computed, onMounted, reactive, ref, useId, watch } from 'vue'
import { useAiConfigStore } from '@modules/CoreAi/resources/js/stores/aiConfig'
import AddAiModelDialog from '../dialogs/AddAiModelDialog.vue'
import { isConflictError, firstErrorMessage } from '@/utils/api'

// Store cấu hình AI (providers/models) — nguồn data thật cho panel Nhà cung cấp.
const ai = useAiConfigStore()

// ---- Tab ngang bên trong phần AI & Mô hình (không phải route) ----
const activeTab = ref('overview')

const horizontalTabs = [
  { value: 'overview', label: 'Tổng quan', icon: 'tabler-dashboard' },
  { value: 'providers', label: 'Nhà cung cấp', icon: 'tabler-server' },
  { value: 'models', label: 'Mô hình AI', icon: 'tabler-cpu' },
  { value: 'prompts', label: 'Mẫu Prompt', icon: 'tabler-file-text' },
  { value: 'writing-styles', label: 'Phong cách viết', icon: 'tabler-pencil' },
  { value: 'image-styles', label: 'Phong cách hình ảnh', icon: 'tabler-photo' },
  { value: 'content-gen', label: 'Tạo nội dung', icon: 'tabler-wand' },
  { value: 'advanced', label: 'Cài đặt nâng cao', icon: 'tabler-adjustments' },
]

// ---- Dữ liệu tĩnh cho Tab Tổng quan ----
const overviewKpis = [
  { title: 'Nhà cung cấp', value: '6', subtext: 'Đang kết nối', icon: 'tabler-server', color: 'primary' },
  { title: 'Mô hình AI', value: '18', subtext: 'Đang sử dụng', icon: 'tabler-cpu', color: 'primary' },
  { title: 'Lượt tạo nội dung', value: '1,256', trend: '+12.5%', trendColor: 'text-success', subtext: '7 ngày qua', icon: 'tabler-file-analytics', color: 'info' },
  { title: 'Chi phí ước tính', value: '$12.34', trend: '+8.2%', trendColor: 'text-error', subtext: 'Tháng này', icon: 'tabler-credit-card', color: 'error' },
]

// Mỗi model là object { name, type, pinging, latencyText, ok } để có nút Test Ping riêng.
const M = (name, type) => ({ name, type, pinging: false, latencyText: '—', ok: null })

// Provider hiển thị ở panel trái — lấy từ store (data thật ai_providers).
const activeProviders = computed(() => ai.providersForUI)

// ---- Provider đang chọn ở panel trái -> panel phải hiển thị model của nó ----
const selectedProvider = computed(() => {
  const p = ai.selectedProvider
  if (!p) {
    return { name: '—', active: false, statusText: 'Chưa có provider', modelCount: '0 mô hình', models: [], latencyText: '—', icon: 'tabler-server', bg: '#eef2ff', color: '#4285F4', pinging: false, syncing: false }
  }

  const style = ai.providersForUI.find(x => x.id === p.id) ?? {}

  return {
    ...p,
    icon: style.icon, bg: style.bg, color: style.color,
    pinging: ai.testingId === p.id,
    syncing: ai.syncingId === p.id,
    _syncError: p._syncError ?? null,
    active: p.is_active,
    latencyText: p._latencyText ?? '—',
    statusText: p.test_status === 'ok' ? 'Đang hoạt động' : (p.test_status === 'failed' ? 'Lỗi kết nối' : 'Đã cấu hình'),
    modelCount: `${p.models_count ?? (p.models?.length ?? 0)} mô hình`,

    // Model đã gán capabilities xếp trước, chưa gán xuống sau (giữ thứ tự trong nhóm).
    models: (p.models ?? [])
      .slice()
      .sort((a, b) => ((b.capabilities?.length ?? 0) > 0 ? 1 : 0) - ((a.capabilities?.length ?? 0) > 0 ? 1 : 0))
      .map(m => ({
        id: m.id,
        name: m.label ?? m.remote_model_id,
        remoteModelId: m.remote_model_id,
        capabilities: m.capabilities ?? [],
        type: capLabel(m.capabilities?.[0]),
        isEnabled: m.is_enabled !== false,
        pinging: ai.testingModelId === m.id,
        latencyText: m._latencyText ?? '—',
        ok: m._testOk ?? null,
        _raw: m,
      })),
  }
})

// Nhãn loại tiếng Việt cho capability đầu của model.
// Màu trạng thái theo test_status: failed->đỏ, ok->xanh, khác->vàng.
function statusColorClass(p) {
  const s = p?.test_status ?? p?._raw?.test_status

  return s === 'failed' ? 'text-error' : (s === 'ok' ? 'text-success' : 'text-warning')
}
function statusDotColor(p) {
  const s = p?.test_status ?? p?._raw?.test_status

  return s === 'failed' ? 'rgb(var(--v-theme-error))' : (s === 'ok' ? 'rgb(var(--v-theme-success))' : 'rgb(var(--v-theme-warning))')
}

function capLabel(cap) {
  return {
    'text_generation': 'Văn bản (LLM)', 'structured_output': 'Văn bản (LLM)',
    'vision': 'Hình ảnh', 'image_generation': 'Hình ảnh',
    'tts': 'Âm thanh', 'stt': 'Âm thanh',
  }[cap] ?? 'Văn bản (LLM)'
}

function selectProvider(prov) {
  ai.selectProvider(prov.id)
}

const syncingAll = ref(false)

const quickTools = [
  { title: 'Kiểm tra kết nối API', desc: 'Kiểm tra trạng thái các nhà cung cấp', icon: 'tabler-plug-connected', color: 'success', targetTab: 'providers' },
  { title: 'Xem nhật ký sử dụng', desc: 'Xem chi tiết lượt sử dụng và chi phí', icon: 'tabler-file-analytics', color: 'primary', targetTab: 'models' },
  { title: 'Quản lý mẫu Prompt', desc: 'Tạo, chỉnh sửa và quản lý mẫu', icon: 'tabler-file-text', color: 'primary', targetTab: 'prompts' },
  { title: 'Thư viện phong cách', desc: 'Quản lý phong cách viết và hình ảnh', icon: 'tabler-palette', color: 'error', targetTab: 'writing-styles' },
  { title: 'Xóa cache mô hình', desc: 'Xóa dữ liệu tạm, làm mới cấu hình', icon: 'tabler-refresh', color: 'warning', targetTab: 'advanced' },
]

/*
 * Form "Cài đặt mặc định" — bind model ID (không phải tên) để khớp
 * validationRule exists:ai_models,id của AiSettings backend.
 * Options lấy từ getter lọc theo capability — không hardcode.
 */
const defaults = reactive({
  textModel: null,
  imageModel: null,
  videoModel: null,
  language: 'vi',
})

const defaultsSaving = ref(false)
const defaultsConflict = ref(false)

/*
 * Cờ "đã có dữ liệu model để dựng nhãn": providers nạp KÈM models xong.
 * fetchAiSettings() (1 GET) luôn về trước ensureLoaded({withModels:true})
 * (1 + N request) nên trong khoảng đó providers còn rỗng — không được
 * hiện id thô "Mô hình #13" mà phải báo đang tải.
 */
const modelsReady = computed(() => ai.providersModelsAttempted && !ai.loadingProviders)

/*
 * Tra nhãn model theo id trong MỌI provider — không lọc capability hay
 * is_enabled, để giá trị đang chọn luôn hiện đúng tên dù bị filter khỏi
 * danh sách option (model đã tắt, driver ngoài whitelist...).
 * INPUT: id (number). OUTPUT: {value,title} | null.
 */
function findModelOption(id) {
  for (const p of ai.providers) {
    const m = (p.models ?? []).find(x => x.id === id)
    if (m)
      return { value: m.id, title: `${m.label ?? m.remote_model_id} · ${p.name}` }
  }

  return null
}

/*
 * Option giữ chỗ cho id đã lưu nhưng chưa tra được nhãn:
 * - providers chưa nạp xong -> "Đang tải…" (hết nháy "Mô hình #13");
 * - nạp xong mà vẫn không thấy -> model đã bị xóa, nói rõ cho admin.
 * INPUT: id (number). OUTPUT: {value,title}.
 */
function placeholderOption(id) {
  return modelsReady.value
    ? { value: id, title: `Mô hình #${id} (không còn tồn tại)` }
    : { value: id, title: 'Đang tải…' }
}

/*
 * Dựng list option model ĐANG BẬT theo capability — chỉ model đã được gán
 * đúng capability mới xuất hiện (không đoán theo remote_model_id). Giá trị
 * đang chọn mà không khớp capability sẽ được unshift với nhãn "(…)" để
 * admin thấy mà chọn lại.
 * INPUT: caps string[] (các CAP_* chấp nhận được), selectedId number|null.
 * OUTPUT: [{value,title}].
 */
function buildOptionsByCaps(caps, selectedId) {
  const options = []
  const seen = new Set()

  for (const p of ai.providers) {
    for (const m of p.models ?? []) {
      if (m.is_enabled === false) continue
      if ((m.capabilities ?? []).some(c => caps.includes(c))) {
        seen.add(m.id)
        options.push({ value: m.id, title: `${m.label ?? m.remote_model_id} · ${p.name}` })
      }
    }
  }

  // Giá trị đang lưu không có capability đúng (hoặc model đã tắt/xóa) ->
  // vẫn hiện để admin biết và chọn lại, gắn nhãn để không lẫn.
  if (selectedId != null && !seen.has(selectedId)) {
    const found = findModelOption(selectedId)
    if (found)
      options.unshift({ value: found.value, title: `${found.title} (không khớp capability)` })
    else
      options.unshift(placeholderOption(selectedId))
  }

  return options
}

/* Văn bản: model có capability sinh text hoặc xuất có cấu trúc. */
const textModelOptions = computed(() =>
  buildOptionsByCaps(['text_generation', 'structured_output'], defaults.textModel),
)

/* Hình ảnh: model có capability sinh ảnh. (vision = hiểu ảnh, không sinh) */
const imageModelOptions = computed(() =>
  buildOptionsByCaps(['image_generation'], defaults.imageModel),
)

/*
 * Video: backend CHƯA có capability 'video_generation' (AiModel::CAPABILITIES
 * chỉ có text/structured/vision/image/tts/stt) nên không thể lọc theo
 * capability như 2 field trên. Tạm nhận diện theo remote_model_id
 * (veo/seedance/kling/runway...) — khi backend thêm capability video thì
 * đổi sang buildOptionsByCaps(['video_generation'], ...).
 */
const VIDEO_HINTS = ['video', 'veo', 'seedance', 'pixverse', 'kling', 'runway', 'ltx', 'wan']

const videoModelOptions = computed(() => {
  const options = []
  const seen = new Set()

  for (const p of ai.providers) {
    for (const m of p.models ?? []) {
      if (m.is_enabled === false) continue

      const idLower = (m.remote_model_id || '').toLowerCase()
      if (!VIDEO_HINTS.some(h => idLower.includes(h))) continue

      seen.add(m.id)
      options.push({ value: m.id, title: `${m.label ?? m.remote_model_id} · ${p.name}` })
    }
  }

  if (defaults.videoModel != null && !seen.has(defaults.videoModel))
    options.unshift(findModelOption(defaults.videoModel) ?? placeholderOption(defaults.videoModel))

  return options
})

const languageOptions = [
  { value: 'vi', title: 'Tiếng Việt' },
  { value: 'en', title: 'English' },
  { value: 'ja', title: '日本語' },
]

/*
 * Đổ cấu hình 'ai' đã nạp vào form — gọi sau fetchAiSettings() và mỗi lần
 * load lại (force sau save/409). Dùng values.* làm nguồn; giữ nguyên
 * khi backend trả null cho field chưa chọn.
 */
function applyAiSettings() {
  const v = ai.aiSettings.values || {}

  defaults.textModel = v.default_text_model_id != null && v.default_text_model_id !== '' ? Number(v.default_text_model_id) : null
  defaults.imageModel = v.default_image_model_id != null && v.default_image_model_id !== '' ? Number(v.default_image_model_id) : null
  defaults.videoModel = v.default_video_model_id != null && v.default_video_model_id !== '' ? Number(v.default_video_model_id) : null
  defaults.language = v.default_language || 'vi'
}

/*
 * Các id mặc định đã lưu nhưng không còn trong danh sách model — chỉ xét
 * SAU khi providers nạp xong models, tránh báo động nhầm lúc đang tải.
 * OUTPUT: string[] nhãn trường bị mồ côi (rỗng = không có vấn đề).
 */
const staleDefaultLabels = computed(() => {
  if (!modelsReady.value)
    return []

  const checks = [
    ['Văn bản', defaults.textModel],
    ['Hình ảnh', defaults.imageModel],
    ['Video', defaults.videoModel],
  ]

  return checks
    .filter(([, id]) => id != null && findModelOption(id) === null)
    .map(([label]) => label)
})

/*
 * Lưu card Cài đặt mặc định qua PUT /settings/ai (khóa version).
 * 409 -> cờ conflict + hỏi tải lại; 422 -> snackbar thông báo.
 */
async function saveDefaults() {
  defaultsSaving.value = true
  defaultsConflict.value = false
  try {
    /*
     * Dọn id mặc định đã trỏ tới model không còn tồn tại -> gửi null để
     * backend xóa, tránh giữ id mồ côi và cho phép nút Lưu tự sửa lỗi.
     * Chỉ dọn sau khi models nạp xong, không thì xóa nhầm lúc đang tải.
     */
    const clean = id => {
      if (id == null || id === '')
        return null

      // Model đã bị xóa khỏi hệ thống -> không gửi id mồ côi lên backend
      // (sẽ fail rule exists:ai_models,id và chặn cả lần lưu).
      if (modelsReady.value && findModelOption(Number(id)) === null)
        return null

      return Number(id)
    }

    await ai.saveAiSettings({
      'default_text_model_id': clean(defaults.textModel),
      'default_image_model_id': clean(defaults.imageModel),
      'default_video_model_id': clean(defaults.videoModel),
      'default_language': defaults.language || 'vi',
    })
    notify('Đã lưu cài đặt mặc định thành công')
    
    return true
  } catch (e) {
    const status = e?.status ?? e?.data?.status ?? e?.response?.status

    if (status === 409 || isConflictError(e)) {
      defaultsConflict.value = true
      await ai.fetchAiSettings({ force: true })
      applyAiSettings()
      notify('Cấu hình vừa bị người khác sửa — đã tải bản mới nhất.', 'warning')
    } else {
      notify(firstErrorMessage(e) || e?.data?.message || e?.message || 'Lưu thất bại', 'error')
    }
    
    return false
  } finally {
    defaultsSaving.value = false
  }
}
const limits = reactive({ dailyCostUsd: '10', rpm: '60', costControl: true, alert80: true, autoPause: false, detailedLogs: true })

// ---- Dữ liệu Tab Nhà cung cấp ----
const providerDetails = ref([
  { name: 'OpenAI', latency: '320ms', modelsCount: 3, enabled: true, apiKey: 'sk-proj-98a7sdf987a98sdf798a7sdf', endpoint: 'https://api.openai.com/v1', monthlyCap: '50', showKey: false, icon: 'tabler-sparkles', bg: '#10a37f', color: 'white' },
  { name: 'Anthropic (Claude)', latency: '410ms', modelsCount: 2, enabled: true, apiKey: 'sk-ant-api03-876sdf876sdf876s', endpoint: 'https://api.anthropic.com/v1', monthlyCap: '40', showKey: false, icon: 'tabler-brain', bg: '#f3e5d8', color: '#18181b' },
  { name: 'Google AI (Gemini)', latency: '240ms', modelsCount: 4, enabled: true, apiKey: 'AIzaSyB9876sdf876sdf876sdf87', endpoint: 'https://generativelanguage.googleapis.com', monthlyCap: '30', showKey: false, icon: 'tabler-star-four-points', bg: '#eef2ff', color: '#4285F4' },
  { name: 'RunwayML Video', latency: '1,200ms', modelsCount: 3, enabled: true, apiKey: 'rw-live-987sdf987sdf987sdf', endpoint: 'https://api.runwayml.com/v1', monthlyCap: '80', showKey: false, icon: 'tabler-video', bg: '#18181b', color: 'white' },
])

// ---- Dữ liệu Tab Mô hình AI ----
const aiModelCatalog = ref([
  { id: 1, name: 'GPT-4o', provider: 'OpenAI', type: 'Văn bản (LLM)', typeColor: 'primary', context: '128K tokens', cost: '$2.50 / 1M', speed: 'Rất nhanh', isDefault: true, active: true },
  { id: 2, name: 'Claude 3.5 Sonnet', provider: 'Anthropic', type: 'Văn bản (LLM)', typeColor: 'primary', context: '200K tokens', cost: '$3.00 / 1M', speed: 'Nhanh', isDefault: false, active: true },
  { id: 3, name: 'Gemini 1.5 Flash', provider: 'Google AI', type: 'Văn bản (LLM)', typeColor: 'primary', context: '1M tokens', cost: '$0.35 / 1M', speed: 'Siêu tốc', isDefault: false, active: true },
  { id: 4, name: 'DALL-E 3 HD', provider: 'OpenAI', type: 'Hình ảnh', typeColor: 'info', context: '1024x1024px', cost: '$0.04 / ảnh', speed: 'Trung bình', isDefault: true, active: true },
  { id: 5, name: 'Stable Diffusion XL', provider: 'Stability AI', type: 'Hình ảnh', typeColor: 'info', context: '1024x1024px', cost: '$0.01 / ảnh', speed: 'Nhanh', isDefault: false, active: false },
  { id: 6, name: 'Runway Gen-3 Alpha', provider: 'Runway', type: 'Video', typeColor: 'warning', context: '10s 1080p', cost: '$0.50 / video', speed: 'Chậm', isDefault: true, active: true },
])

// ---- Dữ liệu Tab Mẫu Prompt ----
const promptTemplates = ref([
  { id: 1, category: 'Social Post', title: 'Bài đăng giới thiệu sản phẩm chuẩn chuyển đổi', uses: 342, template: 'Hãy đóng vai chuyên gia Content Marketing. Viết bài đăng cho {{platform}} giới thiệu sản phẩm {{product_name}} với giọng văn {{tone}}. Nhấn mạnh 3 lợi ích chính và kết thúc bằng CTA.', variables: ['{{platform}}', '{{product_name}}', '{{tone}}'] },
  { id: 2, category: 'Image Prompt', title: 'Tạo câu lệnh ảnh sản phẩm thương mại', uses: 218, template: 'A professional commercial product photography of {{subject}}, placed on {{surface}}, {{lighting}} lighting, shallow depth of field, 8k photorealistic.', variables: ['{{subject}}', '{{surface}}', '{{lighting}}'] },
])

// ---- Dữ liệu Tab Phong cách viết ----
const writingVoices = [
  { name: 'Thân thiện & Trẻ trung', desc: 'Gần gũi, bắt trend, dùng từ ngữ đời thường thu hút tương tác GenZ.', traits: ['Gần gũi', 'Bắt trend', 'Có Emoji'], icon: 'tabler-mood-smile', bg: 'primary', color: 'primary', isDefault: true },
  { name: 'Chuyên gia & Tin cậy', desc: 'Rõ ràng, mạch lạc, dẫn chứng số liệu thuyết phục khách hàng B2B.', traits: ['Chuyên sâu', 'Trang trọng', 'Số liệu'], icon: 'tabler-briefcase', bg: 'info', color: 'info', isDefault: false },
  { name: 'Bán hàng Trực diện (Direct Sales)', desc: 'Đi thẳng vào ưu đãi, đánh trúng tâm lý FOMO và thúc đẩy chốt đơn.', traits: ['Chốt đơn', 'FOMO', 'CTA mạnh'], icon: 'tabler-speakerphone', bg: 'success', color: 'success', isDefault: false },
]

// ---- Dữ liệu Tab Phong cách hình ảnh ----
const imagePresetConfig = reactive({
  styleSuffix: 'photorealistic, 8k resolution, cinematic lighting, commercial photography, sharp focus, natural warm tones',
  negativePrompt: 'blurry, low quality, distorted hands, extra fingers, watermark, text, oversaturated, ugly',
  autoUpscale: true,
  autoWatermark: false,
  convertWebp: true,
})

// ---- Dữ liệu Tab Tạo nội dung ----
const contentPipeline = reactive({
  hashtagCount: '5 – 10 Hashtags',
  emojiLevel: 'Vừa phải (2–4 emoji/đoạn)',
  autoCta: true,
  policyCheck: true,
  autoTranslate: false,
})

// ---- Dữ liệu Tab Cài đặt nâng cao ----
const advancedAi = reactive({
  temperature: 0.7,
  fallbackModel: 'Claude 3.5 Sonnet (Anthropic)',
  webhookUrl: 'https://api.yourdomain.com/webhooks/ai-completed',
  semanticCache: true,
})

const modelTypeFilter = ref('Tất cả loại')

// ---- Test ping / Sync models cho một provider (nối API CoreAi thật) ----
async function testPing(prov) {
  if (!prov?.id) return
  try {
    const data = await ai.testProvider(prov.id)

    prov._latencyText = data.latency_ms != null ? `${data.latency_ms}ms` : '—'
  } catch {
    // lỗi đã lưu trong test_status qua store; UI đọc lại.
  }
}

// Thông báo kết quả sync/ping (snackbar).
const snack = ref({ show: false, text: '', color: 'success' })
function notify(text, color = 'success') {
  snack.value = { show: true, text, color }
}

// Sync models — gọi endpoint sync-models (backend upsert ai_models + delete stale).
async function syncProviderModels(prov) {
  if (!prov?.id) return
  try {
    const data = await ai.syncProvider(prov.id)

    // Hiện chi tiết: model mới + model cũ đã dọn (Task 2 backend trả deleted).
    const created = data?.created ?? 0
    const deleted = data?.deleted ?? 0
    const synced = data?.synced ?? 0

    notify(`Đồng bộ xong: ${synced} model${created ? `, ${created} mới` : ''}${deleted ? `, dọn ${deleted} cũ` : ''}`)
  } catch (e) {
    notify(e?.data?.message ?? e?.message ?? 'Đồng bộ thất bại', 'error')
  }
}

// Test ping riêng cho một model — gọi POST /models/{id}/test thật.
async function testModelPing(model) {
  if (!model?.id || !ai.selectedProvider?.id) return
  await ai.testModel(ai.selectedProvider.id, model.id)

  const p = ai.providers.find(x => x.id === ai.selectedProvider.id)
  const m = p?.models?.find(x => x.id === model.id)
  if (m) {
    model.ok = m._testOk ?? model.ok
    model.latencyText = m._latencyText ?? '—'
  }
}

// ---- Dialog chỉnh capabilities của model ----
const capDialog = ref(false)
const capSaving = ref(false)
const capError = ref('')
const capEditing = ref(null)   // model đang sửa
const capSelected = ref([])

// Nhãn capability lấy từ backend (slug + tiếng Việt); fallback nếu chưa nạp.
const CAPABILITY_OPTIONS = computed(() =>
  ai.capabilities.length
    ? ai.capabilities
    : [
      { value: 'text_generation', label: 'Sinh văn bản', icon: 'tabler-file-text', color: 'primary' },
      { value: 'structured_output', label: 'Xuất có cấu trúc (JSON)', icon: 'tabler-braces', color: 'info' },
      { value: 'vision', label: 'Hiểu ảnh (Vision)', icon: 'tabler-eye', color: 'success' },
      { value: 'image_generation', label: 'Sinh ảnh', icon: 'tabler-photo', color: 'warning' },
      { value: 'tts', label: 'Chuyển chữ thành giọng (TTS)', icon: 'tabler-microphone', color: 'error' },
      { value: 'stt', label: 'Chuyển giọng thành chữ (STT)', icon: 'tabler-headphones', color: 'secondary' },
    ],
)

// Meta của 1 capability slug (icon/label tiếng Việt) — dùng render icon.
function capMeta(cap) {
  return CAPABILITY_OPTIONS.value.find(c => c.value === cap) ?? null
}

function openCapabilitiesDialog(model) {
  capEditing.value = model
  capSelected.value = [...(model.capabilities ?? [])]
  capError.value = ''
  capDialog.value = true
}

async function saveCapabilities() {
  if (!capEditing.value?.id || !ai.selectedProvider?.id) return
  capSaving.value = true
  capError.value = ''
  try {
    await ai.saveModelCapabilities(ai.selectedProvider.id, capEditing.value.id, capSelected.value)
    capEditing.value.capabilities = [...capSelected.value]
    capEditing.value.type = capSelected.value.length ? capLabel(capSelected.value[0]) : ''
    capDialog.value = false
  } catch (e) {
    const errs = e?.data?.errors ?? e?.errors

    capError.value = errs ? Object.values(errs).flat()[0] : (e?.data?.message ?? e?.message ?? 'Không lưu được')
  } finally {
    capSaving.value = false
  }
}

function syncAllProviders() {
  syncingAll.value = true
  ai.testAllProviders().finally(() => {
    syncingAll.value = false
  })
}

// ---- Dialog Thêm nhà cung cấp (chuẩn 3 vùng AppDialogLayout, §4 docs) ----
const providerDialog = ref(false)
const providerSaving = ref(false)
const providerFormId = useId()

const blankProvider = () => ({
  preset: 'openai',
  key: '',
  name: '',
  driver: 'openai',
  apiKey: '',
  showKey: false,
  endpoint: '',
  extraKeysText: '',
  enabled: true,
})

const providerForm = ref(blankProvider())
const providerEditing = ref(null) // provider đang sửa — null = chế độ Thêm mới
const providerDialogMode = computed(() => (providerEditing.value ? 'edit' : 'create'))

// Preset -> {driver, endpoint} điền sẵn; 'custom' = tự nhập.
const providerPresets = [
  { title: 'OpenAI', value: 'openai', driver: 'openai', endpoint: 'https://api.openai.com/v1' },

  // Anthropic: base_url là ORIGIN — driver tự nối 'v1/...' (không để /v1 ở đây kẻo /v1/v1).
  { title: 'Anthropic (Claude)', value: 'anthropic', driver: 'anthropic', endpoint: 'https://api.anthropic.com' },

  // Gemini: ORIGIN — driver tự nối 'v1beta/...'.
  { title: 'Google AI (Gemini)', value: 'gemini', driver: 'gemini', endpoint: 'https://generativelanguage.googleapis.com' },
  { title: 'OpenAI tương thích (Custom)', value: 'openai-compatible', driver: 'openai-compatible', endpoint: '' },
  { title: 'Anthropic tương thích (Custom)', value: 'anthropic-compatible', driver: 'anthropic-compatible', endpoint: '' },
  { title: 'ElevenLabs', value: 'elevenlabs', driver: 'elevenlabs', endpoint: 'https://api.elevenlabs.io/v1' },
  { title: 'XPIKI (ảnh/video)', value: 'xpiki', driver: 'xpiki', endpoint: 'https://api.xpiki.com/v1' },
]

function onProviderPresetChange(presetValue) {
  const preset = providerPresets.find(p => p.value === presetValue)
  if (!preset) return
  providerForm.value.driver = preset.driver
  if (preset.endpoint) providerForm.value.endpoint = preset.endpoint

  // Gợi ý key/name theo preset nếu đang trống.
  if (!providerForm.value.key) {
    providerForm.value.key = preset.value.replace(/[^a-z0-9-]/g, '-')
  }
  if (!providerForm.value.name) {
    providerForm.value.name = preset.title.replace(/\s*\(Custom\)/, '')
  }
}

function openProviderDialog() {
  providerEditing.value = null
  providerForm.value = blankProvider()
  providerError.value = ''
  providerDialog.value = true
}

/*
 * Mở dialog ở chế độ Cấu hình cho provider có sẵn — đổ dữ liệu không bí mật
 * vào form; apiKey/extraKeys để TRỐNG (server giữ nguyên khi không gửi).
 */
function configureProvider(prov) {
  const p = prov?._raw ?? prov

  providerEditing.value = p
  providerError.value = ''
  providerForm.value = {
    preset: p.driver ?? 'openai',
    key: p.key ?? '',
    name: p.name ?? '',
    driver: p.driver ?? 'openai',
    apiKey: '',
    showKey: false,
    endpoint: p.base_url ?? '',
    extraKeysText: '',
    enabled: p.is_active !== false,
  }

  providerDialog.value = true
}

function closeProviderDialog() {
  if (!providerSaving.value) providerDialog.value = false
}

// Reset form chỉ sau khi animation đóng hoàn tất (tránh giật trống) — chuẩn §4.2.
function resetProviderAfterLeave() {
  if (providerDialog.value) return
  providerForm.value = blankProvider()
  providerEditing.value = null
}

// Lỗi validate trả về từ API (422) hiển thị trong dialog.
const providerError = ref('')

/*
 * Lưu provider — dual-mode:
 * - create: POST toàn bộ form (key/name/kind=official/driver/endpoint/key/extra).
 * - edit  : PUT partial — api_key chỉ gửi khi nhập mới; extra_keys chỉ gửi khi
 *           người dùng gõ vào ô (để trống = giữ nguyên danh sách key phụ cũ).
 */
async function saveProvider() {
  providerSaving.value = true
  providerError.value = ''
  try {
    const f = providerForm.value

    const extraKeys = (f.extraKeysText || '')
      .split(/\r?\n|,/)
      .map(s => s.trim())
      .filter(Boolean)

    if (providerDialogMode.value === 'edit' && providerEditing.value?.id) {
      const payload = {
        key: f.key.trim(),
        name: f.name.trim(),
        kind: providerEditing.value.kind ?? 'official',
        driver: f.driver,
        base_url: f.endpoint.trim(), // eslint-disable-line camelcase
        is_active: f.enabled, // eslint-disable-line camelcase
      }

      if ((f.apiKey || '').trim() !== '') payload.api_key = f.apiKey.trim() // eslint-disable-line camelcase
      if ((f.extraKeysText || '').trim() !== '') payload.extra_keys = extraKeys // eslint-disable-line camelcase

      await ai.updateProvider(providerEditing.value.id, payload)
    } else {
      await ai.addProvider({
        key: f.key.trim(),
        name: f.name.trim(),
        kind: 'official',
        driver: f.driver,
        base_url: f.endpoint.trim(), // eslint-disable-line camelcase
        api_key: f.apiKey, // eslint-disable-line camelcase
        extra_keys: extraKeys, // eslint-disable-line camelcase
        is_active: f.enabled, // eslint-disable-line camelcase
      })
    }

    providerDialog.value = false
  } catch (e) {
    // Hiển thị thông điệp validate đầu tiên (422) hoặc lỗi chung.
    const errs = e?.data?.errors ?? e?.errors

    providerError.value = errs
      ? Object.values(errs).flat()[0]
      : (e?.data?.message ?? e?.message ?? 'Không lưu được nhà cung cấp')
  } finally {
    providerSaving.value = false
  }
}

// ---- Dialog Thêm mô hình AI (component riêng trong components/dialogs/) ----
const modelDialog = ref(false)

function openModelDialog() {
  modelDialog.value = true
}

// Nhận payload từ AddAiModelDialog -> push vào danh sách cục bộ (demo tĩnh).
function addModel(payload) {
  aiModelCatalog.value.push({
    id: Math.max(0, ...aiModelCatalog.value.map(m => m.id)) + 1,
    ...payload,
  })
}

function goToTab(tab) {
  if (tab) { activeTab.value = tab }
}

// Đồng bộ form khi aiSettings thay đổi
watch(
  () => ai.aiSettings.values,
  () => {
    applyAiSettings()
  },
  { deep: true },
)

// Nạp providers thật + metadata form (drivers/capabilities) + cài đặt AI khi tab mount.
onMounted(async () => {
  await Promise.allSettled([
    ai.ensureLoaded({ withModels: true }),
    ai.fetchFormMeta(),
    ai.fetchAiSettings(),
  ])
  applyAiSettings()
})

async function saveSettings() {
  return await saveDefaults()
}

// Expose cho page header gọi save
defineExpose({
  saveSettings,
  saving: defaultsSaving,
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Banner giới thiệu -->
    <VCard border>
      <!-- 8 tabs ngang bên trong -->
      <VTabs
        v-model="activeTab"
        color="primary"
        density="comfortable"
        show-arrows
        class="border-b"
      >
        <VTab
          v-for="tab in horizontalTabs"
          :key="tab.value"
          :value="tab.value"
          class="text-none font-weight-medium text-caption"
        >
          <VIcon
            size="18"
            class="me-2"
            :icon="tab.icon"
          />
          {{ tab.label }}
        </VTab>
      </VTabs>
    </VCard>

    <!-- Nội dung từng tab -->
    <VWindow v-model="activeTab">
      <!-- TAB 1: TỔNG QUAN -->
      <VWindowItem value="overview">
        <div class="d-flex flex-column gap-4">
          <!-- Hàng 4 thẻ KPI -->
          <VRow dense>
            <VCol
              v-for="kpi in overviewKpis"
              :key="kpi.title"
              cols="12"
              sm="6"
              lg="3"
            >
              <VCard border>
                <VCardText class="d-flex align-center justify-space-between pa-4">
                  <div>
                    <div class="text-body-2 text-medium-emphasis mb-1">
                      {{ kpi.title }}
                    </div>
                    <div class="d-flex align-baseline gap-2 mb-1">
                      <span class="text-h5 font-weight-medium">{{ kpi.value }}</span>
                      <span
                        v-if="kpi.trend"
                        class="text-caption font-weight-medium"
                        :class="kpi.trendColor"
                      >
                        {{ kpi.trend }}
                      </span>
                    </div>
                    <div class="text-caption text-disabled">
                      {{ kpi.subtext }}
                    </div>
                  </div>
                  <VAvatar
                    :color="kpi.color"
                    variant="tonal"
                    rounded="lg"
                    size="40"
                  >
                    <VIcon
                      :color="kpi.color"
                      size="20"
                      :icon="kpi.icon"
                    />
                  </VAvatar>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- Nhà cung cấp + Kết nối — hai panel cùng chiều cao cố định, scroll khi dài -->
          <VRow
            dense
            align="stretch"
          >
            <VCol
              cols="12"
              lg="7"
            >
              <VCard
                title="Nhà cung cấp đang sử dụng"
                subtitle="Quản lý kết nối và trạng thái các nhà cung cấp AI."
                border
                class="h-100 d-flex flex-column"
              >
                <template #append>
                  <VBtn
                    size="small"
                    prepend-icon="tabler-plus"
                    @click="openProviderDialog"
                  >
                    Thêm nhà cung cấp
                  </VBtn>
                </template>
                <VCardText class="pa-0 flex-grow-1 overflow-y-auto provider-list-scroll panel-fixed-h">
                  <AppCardLoader
                    :loading="ai.loadingProviders"
                    :has-data="activeProviders.length > 0"
                  >
                    <div class="d-flex flex-column">
                      <div
                        v-for="(prov, idx) in activeProviders"
                        :key="prov.id"
                        class="provider-item d-flex align-center justify-space-between pa-4"
                        :class="{
                          'border-b': idx < activeProviders.length - 1,
                          'provider-item--selected': ai.selectedProviderId === prov.id,
                        }"
                        @click="selectProvider(prov)"
                      >
                        <div
                          class="d-flex align-center"
                          style="min-width: 195px;"
                        >
                          <VAvatar
                            :color="prov.bg"
                            size="36"
                            rounded="lg"
                            class="me-3 font-weight-bold"
                          >
                            <VIcon
                              v-if="prov.icon"
                              :color="prov.color"
                              size="20"
                              :icon="prov.icon"
                            />
                            <span
                              v-else
                              :style="{ color: prov.color, fontSize: '13px' }"
                            >{{ prov.shortText }}</span>
                          </VAvatar>
                          <div>
                            <div class="text-body-1 font-weight-medium">
                              {{ prov.name }}
                            </div>
                            <div class="d-flex align-center gap-1">
                              <span
                                class="status-dot"
                                :style="{ backgroundColor: statusDotColor(prov) }"
                              />
                              <span
                                class="text-caption"
                                :class="statusColorClass(prov)"
                              >
                                {{ prov.statusText }}
                              </span>
                            </div>
                          </div>
                        </div>
                        <div class="d-none d-sm-block text-left">
                          <div class="text-body-2 font-weight-medium">
                            {{ prov.modelCount }}
                          </div>
                          <div class="text-caption text-disabled">
                            {{ prov.pricing }}
                          </div>
                        </div>
                        <div
                          class="d-flex align-center gap-1"
                          @click.stop
                        >
                          <IconBtn
                            size="small"
                            color="primary"
                            :loading="prov.pinging"
                            :title="'Test ping ' + prov.name"
                            @click="testPing(prov)"
                          >
                            <VIcon icon="tabler-plug-connected" />
                          </IconBtn>
                          <IconBtn
                            size="small"
                            color="info"
                            :loading="prov.syncing"
                            :title="'Sync model ' + prov.name"
                            @click="syncProviderModels(prov)"
                          >
                            <VIcon icon="tabler-refresh" />
                          </IconBtn>
                          <VBtn
                            variant="tonal"
                            size="small"
                            @click="configureProvider(prov)"
                          >
                            Cấu hình
                          </VBtn>
                          <IconBtn size="small">
                            <VIcon icon="tabler-dots-vertical" />
                          </IconBtn>
                        </div>
                      </div>
                    </div>
                  </AppCardLoader>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              lg="5"
            >
              <VCard
                title="Kết nối nhà cung cấp"
                :subtitle="`Test ping, đồng bộ và xem model của ${selectedProvider.name}.`"
                border
                class="h-100 d-flex flex-column"
              >
                <template #append>
                  <VBtn
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-refresh"
                    :loading="syncingAll"
                    @click="syncAllProviders"
                  >
                    Đồng bộ tất cả
                  </VBtn>
                </template>
                <VCardText class="flex-grow-1 overflow-y-auto panel-fixed-h">
                  <AppCardLoader
                    :loading="ai.loadingModels"
                    :has-data="(selectedProvider.models?.length ?? 0) > 0"
                  >
                    <!-- Header provider đang chọn -->
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center">
                        <VAvatar
                          :color="selectedProvider.bg"
                          size="40"
                          rounded="lg"
                          class="me-3 font-weight-bold"
                        >
                          <VIcon
                            v-if="selectedProvider.icon"
                            :color="selectedProvider.color"
                            size="22"
                            :icon="selectedProvider.icon"
                          />
                          <span
                            v-else
                            :style="{ color: selectedProvider.color, fontSize: '13px' }"
                          >{{ selectedProvider.shortText }}</span>
                        </VAvatar>
                        <div>
                          <div class="text-body-1 font-weight-medium">
                            {{ selectedProvider.name }}
                          </div>
                          <div class="d-flex align-center gap-1">
                            <span
                              class="status-dot"
                              :style="{ backgroundColor: statusDotColor(selectedProvider) }"
                            />
                            <span
                              class="text-caption"
                              :class="statusColorClass(selectedProvider)"
                            >
                              {{ selectedProvider.statusText }}
                            </span>
                          </div>
                        </div>
                      </div>
                      <div class="text-end">
                        <div
                          class="text-caption"
                          :class="selectedProvider._syncError ? 'text-error font-weight-medium' : 'text-disabled'"
                        >
                          {{ selectedProvider._syncError ?? selectedProvider.modelCount }}
                        </div>
                        <div class="text-caption text-disabled font-mono">
                          {{ selectedProvider.latencyText }}
                        </div>
                      </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                      <VBtn
                        size="small"
                        variant="tonal"
                        color="primary"
                        prepend-icon="tabler-plug-connected"
                        :loading="selectedProvider.pinging"
                        class="flex-grow-1"
                        @click="testPing(selectedProvider)"
                      >
                        Test ping
                      </VBtn>
                      <VBtn
                        size="small"
                        variant="tonal"
                        color="info"
                        prepend-icon="tabler-refresh"
                        :loading="selectedProvider.syncing"
                        class="flex-grow-1"
                        @click="syncProviderModels(selectedProvider)"
                      >
                        Sync models
                      </VBtn>
                    </div>

                    <!-- Danh sách model của provider đang chọn (scroll khi dài) -->
                    <div class="mt-4">
                      <div class="text-caption text-disabled mb-2">
                        {{ selectedProvider.models.length }} mô hình của {{ selectedProvider.name }}
                      </div>
                      <div
                        class="d-flex flex-column model-list-scroll"
                        style="overflow-y: auto;"
                      >
                        <div
                          v-for="(m, i) in selectedProvider.models"
                          :key="m.name"
                          class="d-flex align-center justify-space-between py-2"
                          :class="{ 'border-b': i < selectedProvider.models.length - 1 }"
                        >
                          <div class="d-flex align-center">
                            <VAvatar
                              size="28"
                              rounded="lg"
                              color="primary"
                              variant="tonal"
                              class="me-3"
                            >
                              <VIcon
                                size="15"
                                color="primary"
                                icon="tabler-cpu"
                              />
                            </VAvatar>
                            <div>
                              <div class="text-body-2 font-weight-medium">
                                {{ m.name }}
                              </div>
                              <div class="d-flex align-center gap-1">
                                <!-- Icon capabilities đã chọn; rỗng -> text Không có capabilities -->
                                <template v-if="m.capabilities?.length">
                                  <VTooltip
                                    v-for="cap in m.capabilities"
                                    :key="cap"
                                    :text="capMeta(cap)?.label ?? cap"
                                    location="top"
                                  >
                                    <template #activator="{ props }">
                                      <VIcon
                                        v-bind="props"
                                        :icon="capMeta(cap)?.icon ?? 'tabler-cpu'"
                                        :color="capMeta(cap)?.color ?? 'primary'"
                                        size="15"
                                      />
                                    </template>
                                  </VTooltip>
                                </template>
                                <span
                                  v-else
                                  class="text-caption text-disabled"
                                >Chưa có capabilities</span>
                                <span
                                  v-if="m.latencyText !== '—'"
                                  class="text-caption font-mono ms-1"
                                  :class="m.ok === false ? 'text-error' : 'text-success'"
                                >
                                  {{ m.latencyText }}
                                </span>
                              </div>
                            </div>
                          </div>
                          <div class="d-flex align-center gap-1">
                            <IconBtn
                              size="small"
                              color="primary"
                              title="Chỉnh capabilities"
                              @click="openCapabilitiesDialog(m)"
                            >
                              <VIcon
                                size="16"
                                icon="tabler-adjustments"
                              />
                            </IconBtn>
                            <VBtn
                              size="x-small"
                              variant="tonal"
                              color="primary"
                              prepend-icon="tabler-plug-connected"
                              :loading="m.pinging"
                              @click="testModelPing(m)"
                            >
                              Test ping
                            </VBtn>
                          </div>
                        </div>
                      </div>
                    </div>
                  </AppCardLoader>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- 3 card dưới -->
          <VRow dense>
            <VCol
              cols="12"
              lg="4"
            >
              <VCard
                title="Cài đặt mặc định"
                subtitle="Cấu hình mặc định cho các tác vụ tạo nội dung."
                border
              >
                <VCardText>
                  <VAlert
                    v-if="defaultsConflict"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                    closable
                    @click:close="defaultsConflict = false"
                  >
                    Cấu hình vừa được cập nhật từ phiên bản mới nhất trên hệ thống.
                  </VAlert>

                  <VAlert
                    v-if="staleDefaultLabels.length"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                  >
                    Mô hình mặc định {{ staleDefaultLabels.join(', ') }} không còn tồn tại — vui lòng chọn lại rồi lưu.
                  </VAlert>

                  <VAlert
                    v-if="modelsReady && !textModelOptions.length && !imageModelOptions.length"
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                  >
                    Chưa có mô hình nào được gán capability. Vào tab
                    <strong>Mô hình AI</strong> để gán capability (Sinh văn bản,
                    Sinh ảnh…) cho từng mô hình trước khi chọn mặc định.
                  </VAlert>

                  <div class="mb-4">
                    <div class="text-body-2 font-weight-medium mb-2">
                      Mô hình mặc định (Văn bản)
                    </div>
                    <AppSelect
                      v-model="defaults.textModel"
                      :items="textModelOptions"
                      item-title="title"
                      item-value="value"
                      clearable
                      placeholder="Chọn mô hình văn bản mặc định"
                      :loading="ai.loadingProviders || ai.loadingAiSettings"
                      prepend-inner-icon="tabler-sparkles"
                      hide-details
                    />
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 font-weight-medium mb-2">
                      Mô hình mặc định (Hình ảnh)
                    </div>
                    <AppSelect
                      v-model="defaults.imageModel"
                      :items="imageModelOptions"
                      item-title="title"
                      item-value="value"
                      clearable
                      placeholder="Chọn mô hình hình ảnh mặc định"
                      :loading="ai.loadingProviders || ai.loadingAiSettings"
                      prepend-inner-icon="tabler-photo"
                      hide-details
                    />
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 font-weight-medium mb-2">
                      Mô hình mặc định (Video)
                    </div>
                    <AppSelect
                      v-model="defaults.videoModel"
                      :items="videoModelOptions"
                      item-title="title"
                      item-value="value"
                      clearable
                      placeholder="Chọn mô hình video mặc định"
                      :loading="ai.loadingProviders || ai.loadingAiSettings"
                      prepend-inner-icon="tabler-video"
                      hide-details
                    />
                  </div>
                  <div>
                    <div class="text-body-2 font-weight-medium mb-2">
                      Ngôn ngữ mặc định
                    </div>
                    <AppSelect
                      v-model="defaults.language"
                      :items="languageOptions"
                      item-title="title"
                      item-value="value"
                      prepend-inner-icon="tabler-language"
                      hide-details
                    />
                  </div>
                </VCardText>
                <VCardActions class="px-4 pb-4 pt-0 justify-end">
                  <VBtn
                    size="small"
                    prepend-icon="tabler-device-floppy"
                    :loading="defaultsSaving"
                    @click="saveDefaults"
                  >
                    Lưu mặc định
                  </VBtn>
                </VCardActions>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              lg="4"
            >
              <VCard
                title="Giới hạn và kiểm soát"
                subtitle="Thiết lập giới hạn sử dụng và kiểm soát chi phí."
                border
              >
                <VCardText>
                  <VRow
                    dense
                    class="mb-4"
                  >
                    <VCol cols="6">
                      <div class="text-body-2 font-weight-medium mb-2">
                        Giới hạn chi phí/ngày (USD)
                      </div>
                      <AppTextField
                        v-model="limits.dailyCostUsd"
                        hide-details
                      />
                    </VCol>
                    <VCol cols="6">
                      <div class="text-body-2 font-weight-medium mb-2">
                        Giới hạn yêu cầu/phút
                      </div>
                      <AppTextField
                        v-model="limits.rpm"
                        hide-details
                      />
                    </VCol>
                  </VRow>
                  <div class="d-flex flex-column gap-4">
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center text-body-2">
                        <VIcon
                          size="18"
                          color="primary"
                          class="me-2"
                          icon="tabler-cash"
                        />
                        <span>Bật kiểm soát chi phí</span>
                      </div>
                      <VSwitch
                        v-model="limits.costControl"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center text-body-2">
                        <VIcon
                          size="18"
                          color="primary"
                          class="me-2"
                          icon="tabler-bell-ringing"
                        />
                        <span>Cảnh báo khi vượt 80%</span>
                      </div>
                      <VSwitch
                        v-model="limits.alert80"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center text-body-2">
                        <VIcon
                          size="18"
                          class="me-2"
                          icon="tabler-pause-circle"
                        />
                        <span>Tự động tạm dừng khi vượt giới hạn</span>
                      </div>
                      <VSwitch
                        v-model="limits.autoPause"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center text-body-2">
                        <VIcon
                          size="18"
                          color="primary"
                          class="me-2"
                          icon="tabler-file-analytics"
                        />
                        <span>Ghi nhật ký chi tiết</span>
                      </div>
                      <VSwitch
                        v-model="limits.detailedLogs"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              lg="4"
            >
              <VCard
                title="Công cụ nhanh"
                subtitle="Các công cụ hỗ trợ quản lý AI."
                border
              >
                <VCardText>
                  <div class="d-flex flex-column gap-1">
                    <div
                      v-for="tool in quickTools"
                      :key="tool.title"
                      class="quick-tool-row pa-3 rounded-lg d-flex align-center justify-space-between cursor-pointer"
                      @click="goToTab(tool.targetTab)"
                    >
                      <div class="d-flex align-center">
                        <VAvatar
                          :color="tool.color"
                          variant="tonal"
                          size="32"
                          rounded="lg"
                          class="me-3"
                        >
                          <VIcon
                            :color="tool.color"
                            size="16"
                            :icon="tool.icon"
                          />
                        </VAvatar>
                        <div>
                          <div class="text-body-2 font-weight-medium">
                            {{ tool.title }}
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ tool.desc }}
                          </div>
                        </div>
                      </div>
                      <VIcon
                        size="16"
                        color="secondary"
                        icon="tabler-chevron-right"
                      />
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VWindowItem>

      <!-- TAB 2: NHÀ CUNG CẤP -->
      <VWindowItem value="providers">
        <VCard
          title="Cấu hình Nhà cung cấp AI (API Keys &amp; Endpoints)"
          subtitle="Thiết lập khóa xác thực API, kiểm tra độ trễ phản hồi và giới hạn ngân sách cho từng nhà cung cấp."
          border
        >
          <template #append>
            <div class="d-flex align-center gap-2">
              <VBtn
                prepend-icon="tabler-plug-connected"
                size="small"
              >
                Kiểm tra tất cả kết nối
              </VBtn>
              <VBtn
                prepend-icon="tabler-plus"
                size="small"
                @click="openProviderDialog"
              >
                Thêm nhà cung cấp
              </VBtn>
            </div>
          </template>
          <VCardText>
            <VRow>
              <VCol
                v-for="prov in providerDetails"
                :key="prov.name"
                cols="12"
                md="6"
              >
                <VCard
                  border
                  class="pa-4 h-100 d-flex flex-column justify-space-between"
                >
                  <div>
                    <div class="d-flex justify-space-between align-start mb-4">
                      <div class="d-flex align-center">
                        <VAvatar
                          :color="prov.bg"
                          size="40"
                          rounded="lg"
                          class="me-3 font-weight-bold"
                        >
                          <VIcon
                            :color="prov.color"
                            size="20"
                            :icon="prov.icon"
                          />
                        </VAvatar>
                        <div>
                          <div class="text-body-1 font-weight-medium">
                            {{ prov.name }}
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            Độ trễ TB: <strong>{{ prov.latency }}</strong> • {{ prov.modelsCount }} mô hình
                          </div>
                        </div>
                      </div>
                      <VSwitch
                        v-model="prov.enabled"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="mb-4">
                      <div class="text-body-2 font-weight-medium mb-2">
                        API Secret Key
                      </div>
                      <AppTextField
                        v-model="prov.apiKey"
                        :type="prov.showKey ? 'text' : 'password'"
                        :append-inner-icon="prov.showKey ? 'tabler-eye-off' : 'tabler-eye'"
                        hide-details
                        @click:append-inner="prov.showKey = !prov.showKey"
                      />
                    </div>
                    <VRow
                      dense
                      class="mb-4"
                    >
                      <VCol cols="7">
                        <div class="text-body-2 font-weight-medium mb-2">
                          Base URL / Endpoint
                        </div>
                        <AppTextField
                          v-model="prov.endpoint"
                          hide-details
                        />
                      </VCol>
                      <VCol cols="5">
                        <div class="text-body-2 font-weight-medium mb-2">
                          Hạn mức tháng ($)
                        </div>
                        <AppTextField
                          v-model="prov.monthlyCap"
                          prefix="$"
                          hide-details
                        />
                      </VCol>
                    </VRow>
                  </div>
                  <div class="d-flex justify-space-between align-center pt-3 border-t">
                    <VChip
                      size="small"
                      :color="prov.enabled ? 'success' : 'warning'"
                      variant="tonal"
                    >
                      {{ prov.enabled ? 'Đã xác thực API' : 'Chưa kích hoạt' }}
                    </VChip>
                    <VBtn
                      variant="text"
                      size="small"
                      prepend-icon="tabler-plug-connected"
                    >
                      Ping thử
                    </VBtn>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 3: MÔ HÌNH AI -->
      <VWindowItem value="models">
        <VCard
          title="Danh mục Mô hình AI"
          subtitle="Bật/tắt các mô hình được phép sử dụng trong hệ thống tạo nội dung, hình ảnh và video."
          border
        >
          <template #append>
            <div class="d-flex align-center gap-2">
              <AppSelect
                v-model="modelTypeFilter"
                :items="['Tất cả loại', 'Văn bản (LLM)', 'Hình ảnh', 'Video']"
                hide-details
                style="min-width: 160px;"
              />
              <VBtn
                prepend-icon="tabler-plus"
                size="small"
                @click="openModelDialog"
              >
                Thêm Model
              </VBtn>
            </div>
          </template>
          <VCardText class="pa-0">
            <VTable
              density="comfortable"
              class="border-t"
            >
              <thead>
                <tr class="bg-grey-lighten-5">
                  <th>Mô hình</th>
                  <th>Nhà cung cấp</th>
                  <th>Phân loại</th>
                  <th>Context / Độ phân giải</th>
                  <th>Chi phí</th>
                  <th>Tốc độ</th>
                  <th>Kích hoạt</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="m in aiModelCatalog"
                  :key="m.id"
                >
                  <td class="font-weight-medium">
                    {{ m.name }}
                    <VChip
                      v-if="m.isDefault"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                      class="ms-2"
                    >
                      Mặc định
                    </VChip>
                  </td>
                  <td>{{ m.provider }}</td>
                  <td>
                    <VChip
                      size="small"
                      :color="m.typeColor"
                      variant="tonal"
                    >
                      {{ m.type }}
                    </VChip>
                  </td>
                  <td class="font-mono">
                    {{ m.context }}
                  </td>
                  <td class="font-weight-medium">
                    {{ m.cost }}
                  </td>
                  <td>
                    <VChip
                      size="small"
                      color="success"
                      variant="outlined"
                    >
                      {{ m.speed }}
                    </VChip>
                  </td>
                  <td>
                    <VSwitch
                      v-model="m.active"
                      color="primary"
                      hide-details
                      inset
                    />
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 4: MẪU PROMPT -->
      <VWindowItem value="prompts">
        <VCard
          title="Thư viện Mẫu Prompt Hệ thống"
          subtitle="Quản lý các mẫu câu lệnh chuẩn có chứa biến động để AI tự động sinh nội dung đồng nhất."
          border
        >
          <template #append>
            <VBtn
              prepend-icon="tabler-plus"
              size="small"
            >
              Tạo mẫu Prompt mới
            </VBtn>
          </template>
          <VCardText>
            <VRow>
              <VCol
                v-for="tpl in promptTemplates"
                :key="tpl.id"
                cols="12"
                md="6"
              >
                <VCard
                  border
                  class="pa-4 h-100 d-flex flex-column justify-space-between bg-grey-lighten-5"
                >
                  <div>
                    <div class="d-flex justify-space-between align-center mb-2">
                      <VChip
                        size="small"
                        color="primary"
                        variant="tonal"
                      >
                        {{ tpl.category }}
                      </VChip>
                      <span class="text-caption text-disabled">{{ tpl.uses }} lượt dùng</span>
                    </div>
                    <div class="text-body-1 font-weight-medium mb-2">
                      {{ tpl.title }}
                    </div>
                    <div
                      class="pa-3 rounded-lg bg-white border text-caption font-mono mb-3"
                      style="line-height: 1.5;"
                    >
                      {{ tpl.template }}
                    </div>
                  </div>
                  <div class="d-flex justify-space-between align-center">
                    <div class="d-flex flex-wrap gap-1">
                      <VChip
                        v-for="v in tpl.variables"
                        :key="v"
                        size="x-small"
                        variant="outlined"
                        color="primary"
                        class="font-mono"
                      >
                        {{ v }}
                      </VChip>
                    </div>
                    <IconBtn size="small">
                      <VIcon icon="tabler-pencil" />
                    </IconBtn>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 5: PHONG CÁCH VIẾT -->
      <VWindowItem value="writing-styles">
        <VCard
          title="Cấu hình Phong cách viết (Brand Voice)"
          subtitle="Thiết lập giọng điệu thương hiệu, mật độ sử dụng Emoji và từ khóa cấm khi AI viết bài."
          border
        >
          <template #append>
            <VBtn
              prepend-icon="tabler-plus"
              size="small"
            >
              Thêm giọng văn
            </VBtn>
          </template>
          <VCardText>
            <VRow>
              <VCol
                v-for="voice in writingVoices"
                :key="voice.name"
                cols="12"
                md="4"
              >
                <VCard
                  border
                  class="pa-4 h-100"
                >
                  <div class="d-flex justify-space-between align-center mb-3">
                    <VAvatar
                      :color="voice.bg"
                      variant="tonal"
                      size="40"
                      rounded="lg"
                    >
                      <VIcon
                        :color="voice.color"
                        size="20"
                        :icon="voice.icon"
                      />
                    </VAvatar>
                    <VChip
                      v-if="voice.isDefault"
                      size="small"
                      color="success"
                      variant="tonal"
                    >
                      Mặc định
                    </VChip>
                  </div>
                  <div class="text-body-1 font-weight-medium mb-1">
                    {{ voice.name }}
                  </div>
                  <div class="text-caption text-medium-emphasis mb-3">
                    {{ voice.desc }}
                  </div>
                  <div class="d-flex flex-wrap gap-1">
                    <VChip
                      v-for="t in voice.traits"
                      :key="t"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ t }}
                    </VChip>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 6: PHONG CÁCH HÌNH ẢNH -->
      <VWindowItem value="image-styles">
        <VCard
          title="Cấu hình Phong cách Hình ảnh Mặc định"
          subtitle="Thiết lập hậu tố mỹ thuật (Style Suffix), Negative Prompt và đóng dấu Watermark tự động."
          border
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="7"
              >
                <div class="mb-4">
                  <div class="text-body-2 font-weight-medium mb-2">
                    Hậu tố phong cách mặc định (Global Style Suffix)
                  </div>
                  <AppTextarea
                    v-model="imagePresetConfig.styleSuffix"
                    rows="2"
                    hide-details
                  />
                </div>
                <div>
                  <div class="text-body-2 font-weight-medium mb-2">
                    Negative Prompt mặc định (Chi tiết cần loại bỏ)
                  </div>
                  <AppTextarea
                    v-model="imagePresetConfig.negativePrompt"
                    rows="2"
                    hide-details
                  />
                </div>
              </VCol>
              <VCol
                cols="12"
                md="5"
              >
                <VCard
                  border
                  class="pa-4 bg-grey-lighten-5 h-100"
                >
                  <div class="text-body-2 font-weight-medium mb-3">
                    Tùy chọn hậu kỳ ảnh AI
                  </div>
                  <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-body-2">Tự động Upscale 2x độ nét cao</span>
                      <VSwitch
                        v-model="imagePresetConfig.autoUpscale"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-body-2">Tự động chèn Logo Watermark góc ảnh</span>
                      <VSwitch
                        v-model="imagePresetConfig.autoWatermark"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-body-2">Chuyển đổi định dạng sang WebP tối ưu</span>
                      <VSwitch
                        v-model="imagePresetConfig.convertWebp"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 7: TẠO NỘI DUNG -->
      <VWindowItem value="content-gen">
        <VCard
          title="Quy trình Tự động Tạo Nội dung (Content Pipeline)"
          subtitle="Tùy chỉnh cấu trúc bài đăng mạng xã hội, số lượng Hashtag và bộ lọc an toàn nội dung."
          border
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <div class="mb-4">
                  <div class="text-body-2 font-weight-medium mb-2">
                    Số lượng Hashtag tự động mỗi bài
                  </div>
                  <AppSelect
                    v-model="contentPipeline.hashtagCount"
                    :items="['3 – 5 Hashtags', '5 – 10 Hashtags', 'Không dùng Hashtag']"
                    hide-details
                  />
                </div>
                <div>
                  <div class="text-body-2 font-weight-medium mb-2">
                    Mức độ sử dụng biểu tượng cảm xúc (Emoji)
                  </div>
                  <AppSelect
                    v-model="contentPipeline.emojiLevel"
                    :items="['Vừa phải (2–4 emoji/đoạn)', 'Nhiều & Sinh động', 'Tối giản (Chỉ ở tiêu đề)']"
                    hide-details
                  />
                </div>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <div class="d-flex flex-column gap-4">
                  <div class="d-flex align-center justify-space-between">
                    <span class="text-body-2">Tự động thêm câu kêu gọi hành động (CTA) cuối bài</span>
                    <VSwitch
                      v-model="contentPipeline.autoCta"
                      color="primary"
                      hide-details
                      inset
                    />
                  </div>
                  <div class="d-flex align-center justify-space-between">
                    <span class="text-body-2">Kiểm duyệt từ ngữ vi phạm chính sách quảng cáo</span>
                    <VSwitch
                      v-model="contentPipeline.policyCheck"
                      color="primary"
                      hide-details
                      inset
                    />
                  </div>
                  <div class="d-flex align-center justify-space-between">
                    <span class="text-body-2">Tự động dịch đa ngôn ngữ khi đăng chéo nền tảng</span>
                    <VSwitch
                      v-model="contentPipeline.autoTranslate"
                      color="primary"
                      hide-details
                      inset
                    />
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 8: CÀI ĐẶT NÂNG CAO -->
      <VWindowItem value="advanced">
        <VCard
          title="Cài đặt Kỹ thuật &amp; Siêu tham số (Hyperparameters)"
          subtitle="Điều chỉnh độ sáng tạo của mô hình (Temperature), cơ chế chuyển đổi dự phòng (Fallback) và bộ nhớ đệm."
          border
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <div class="mb-4">
                  <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-body-2 font-weight-medium">Độ sáng tạo (Temperature: {{ advancedAi.temperature }})</span>
                    <span class="text-caption text-medium-emphasis">0.0 = Chính xác • 1.0 = Sáng tạo</span>
                  </div>
                  <VSlider
                    v-model="advancedAi.temperature"
                    min="0"
                    max="1"
                    step="0.1"
                    color="primary"
                    hide-details
                  />
                </div>
                <div>
                  <div class="text-body-2 font-weight-medium mb-2">
                    Mô hình dự phòng khi quá tải (Fallback Model)
                  </div>
                  <AppSelect
                    v-model="advancedAi.fallbackModel"
                    :items="['Claude 3.5 Sonnet (Anthropic)', 'Gemini 1.5 Flash (Google)', 'GPT-4o Mini (OpenAI)']"
                    hide-details
                  />
                </div>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <div class="mb-4">
                  <div class="text-body-2 font-weight-medium mb-2">
                    Webhook nhận sự kiện hoàn tất tác vụ AI
                  </div>
                  <AppTextField
                    v-model="advancedAi.webhookUrl"
                    prepend-inner-icon="tabler-webhook"
                    hide-details
                  />
                </div>
                <div class="d-flex align-center justify-space-between">
                  <div>
                    <div class="text-body-2 font-weight-medium">
                      Bật Semantic Cache
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      Lưu đệm câu trả lời tương tự — tiết kiệm tới 35% chi phí token.
                    </div>
                  </div>
                  <VSwitch
                    v-model="advancedAi.semanticCache"
                    color="primary"
                    hide-details
                    inset
                  />
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>
    </VWindow>

    <!-- Dialog Thêm nhà cung cấp (chuẩn 3 vùng, §4 docs) -->
    <VDialog
      v-model="providerDialog"
      max-width="620"
      scrollable
      :persistent="providerSaving"
      @after-leave="resetProviderAfterLeave"
    >
      <AppDialogLayout
        :title="providerDialogMode === 'edit' ? 'Cấu hình nhà cung cấp AI' : 'Thêm nhà cung cấp AI'"
        :subtitle="providerDialogMode === 'edit'
          ? `Chỉnh sửa kết nối ${providerEditing?.name ?? ''} — để trống ô key nếu giữ nguyên.`
          : 'Kết nối nhà cung cấp mới bằng API key và endpoint.'"
        :close-disabled="providerSaving"
        @close="closeProviderDialog"
      >
        <VCardText>
          <VAlert
            v-if="providerError"
            color="error"
            variant="tonal"
            class="mb-4"
            closable
            @click:close="providerError = ''"
          >
            {{ providerError }}
          </VAlert>
          <VForm
            :id="providerFormId"
            @submit.prevent="saveProvider"
          >
            <VRow>
              <VCol cols="12">
                <AppSelect
                  v-model="providerForm.preset"
                  :items="providerPresets"
                  item-title="title"
                  item-value="value"
                  label="Nhà cung cấp"
                  placeholder="Chọn loại provider"
                  prepend-inner-icon="tabler-server"
                  :hint="providerDialogMode === 'edit' ? 'Đổi loại provider sẽ đổi driver xử lý request' : ''"
                  @update:model-value="onProviderPresetChange"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="providerForm.key"
                  label="Key định danh (unique)"
                  placeholder="openai, gemini, azure-custom..."
                  prepend-inner-icon="tabler-hash"
                  hint="Chữ thường, số, dấu gạch — duy nhất toàn hệ thống"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="providerForm.name"
                  label="Tên hiển thị"
                  placeholder="VD: OpenAI chính, Azure OpenAI..."
                  prepend-inner-icon="tabler-pencil"
                  autocomplete="off"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="providerForm.apiKey"
                  :label="providerDialogMode === 'edit' ? 'API Secret Key (để trống = giữ key hiện tại)' : 'API Secret Key'"
                  :placeholder="providerDialogMode === 'edit' ? 'Nhập key mới nếu muốn thay' : 'sk-...'"
                  :type="providerForm.showKey ? 'text' : 'password'"
                  prepend-inner-icon="tabler-key"
                  :append-inner-icon="providerForm.showKey ? 'tabler-eye-off' : 'tabler-eye'"
                  autocomplete="new-password"
                  @click:append-inner="providerForm.showKey = !providerForm.showKey"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="providerForm.endpoint"
                  label="Base URL / Endpoint"
                  placeholder="https://api.example.com/v1"
                  prepend-inner-icon="tabler-link"
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  v-model="providerForm.extraKeysText"
                  :label="providerDialogMode === 'edit'
                    ? `API key phụ (xoay vòng) — đã lưu ${providerEditing?.extra_keys_count ?? 0} key; nhập để THAY TOÀN BỘ`
                    : 'API key phụ (xoay vòng) — mỗi dòng 1 key'"
                  placeholder="sk-phụ-1&#10;sk-phụ-2"
                  rows="2"
                  prepend-inner-icon="tabler-key"
                />
              </VCol>
              <VCol cols="12">
                <VSwitch
                  v-model="providerForm.enabled"
                  :label="providerDialogMode === 'edit' ? 'Đang kích hoạt' : 'Kích hoạt ngay sau khi thêm'"
                  color="primary"
                  hide-details
                  inset
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <template #footer>
          <VCardActions class="justify-end">
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="providerSaving"
              @click="closeProviderDialog"
            >
              Hủy
            </VBtn>
            <VBtn
              type="submit"
              :form="providerFormId"
              variant="flat"
              color="primary"
              :loading="providerSaving"
            >
              {{ providerDialogMode === 'edit' ? 'Lưu cấu hình' : 'Thêm nhà cung cấp' }}
            </VBtn>
          </VCardActions>
        </template>
      </AppDialogLayout>
    </VDialog>

    <!-- Dialog Thêm mô hình AI (component riêng) -->
    <AddAiModelDialog
      v-model="modelDialog"
      @saved="addModel"
    />

    <!-- Snackbar thông báo sync/ping -->
    <VSnackbar
      v-model="snack.show"
      :color="snack.color"
      location="bottom right"
      :timeout="4000"
    >
      {{ snack.text }}
    </VSnackbar>

    <!-- Dialog chỉnh capabilities của model -->
    <VDialog
      v-model="capDialog"
      max-width="560"
      scrollable
      :persistent="capSaving"
    >
      <AppDialogLayout
        title="Capabilities của mô hình"
        :subtitle="capEditing ? `${capEditing.name} (${capEditing.remoteModelId})` : ''"
        :close-disabled="capSaving"
        @close="capDialog = false"
      >
        <VCardText>
          <VAlert
            v-if="capError"
            color="error"
            variant="tonal"
            class="mb-4"
            closable
            @click:close="capError = ''"
          >
            {{ capError }}
          </VAlert>
          <div class="text-body-2 text-medium-emphasis mb-3">
            Chọn các tác vụ mô hình này có thể thực hiện (dùng để resolve model khi chạy AI).
          </div>
          <VCheckbox
            v-for="cap in CAPABILITY_OPTIONS"
            :key="cap.value"
            v-model="capSelected"
            :value="cap.value"
            :label="cap.label"
            :color="cap.color"
            hide-details
            density="compact"
            class="mb-1"
          >
            <template #prepend>
              <VIcon
                :color="cap.color"
                size="18"
                class="me-2"
                :icon="cap.icon"
              />
            </template>
          </VCheckbox>
        </VCardText>
        <template #footer>
          <VCardActions class="justify-end">
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="capSaving"
              @click="capDialog = false"
            >
              Hủy
            </VBtn>
            <VBtn
              variant="flat"
              color="primary"
              :loading="capSaving"
              @click="saveCapabilities"
            >
              Lưu capabilities
            </VBtn>
          </VCardActions>
        </template>
      </AppDialogLayout>
    </VDialog>
  </div>
</template>

<style scoped>
/* Chấm trạng thái nhà cung cấp. */
.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
}

/* Hàng công cụ nhanh. */
.quick-tool-row {
  transition: background-color 0.15s ease;
}
.quick-tool-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.06);
}

/* Item provider trong panel "Nhà cung cấp đang sử dụng". */
.provider-item {
  cursor: pointer;
  transition: background-color 0.15s ease;
}
.provider-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
.provider-item--selected {
  background-color: rgba(var(--v-theme-primary), 0.09);
  box-shadow: inset 3px 0 0 rgb(var(--v-theme-primary));
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* Hai panel provider/kết nối — cùng chiều cao cố định, scroll khi dài. */
.panel-fixed-h {
  height: 420px;
  min-height: 420px;
}

/* Danh sách model trong panel Kết nối — thanh scroll rất mảnh, mờ. */
.model-list-scroll,
.provider-list-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(var(--v-theme-primary), 0.14) transparent;
}
.model-list-scroll::-webkit-scrollbar,
.provider-list-scroll::-webkit-scrollbar {
  width: 3px;
}
.model-list-scroll::-webkit-scrollbar-thumb,
.provider-list-scroll::-webkit-scrollbar-thumb {
  background: rgba(var(--v-theme-primary), 0.14);
  border-radius: 2px;
}
.model-list-scroll::-webkit-scrollbar-track,
.provider-list-scroll::-webkit-scrollbar-track {
  background: transparent;
}
</style>
