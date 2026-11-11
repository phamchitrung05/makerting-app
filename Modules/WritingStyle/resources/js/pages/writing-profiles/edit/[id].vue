<!--
  =====================================================================
  CHỨC NĂNG FILE: Chỉnh sửa Writing Profile, chỉ dẫn Ô 1–3 và vận hành sandbox nhiều bước.
  =====================================================================

  Route: /admin/writing-style/writing-profiles/edit/:id
  Tự nạp qua file-based routing — tên 'admin-writing-style-writing-profiles-edit-id'.

  Nối API thật:
  - onMounted: store.fetchProfile(id) -> GET /writing-profiles/{id}
  (kèm categories/tags/analyses eager-load từ controller show()).
  - Map dữ liệu vào 9 tab, gồm Phòng thử DNA:
  Banner+Tổng quan: name/version/description/is_enabled/slug/status
  Đặc điểm văn phong: rules_json.tone|emotion|pronouns|vocabulary + style_instructions
  Cấu trúc nội dung: rules_json.opening|structure_patterns|transitions|ending
  Quy tắc ngôn ngữ: rules_json.pronouns + editorialRules(avoid+vocabulary)
  Mẫu nội dung: evidence_json
  Từ vựng & CTA: rules_json.vocabulary/avoid
  Cài đặt nâng cao: style_instructions + ai_model_used (hiển thị)
  Lịch sử: analyses (đã load) + version hiện tại
  - saveProfile() -> PUT /writing-profiles/{id}: chỉ merge các khóa rules_json
  user đã sửa trên UI (giữ nguyên khóa chưa sửa) + categories/tags +
  status/is_enabled + slug + description + Ô 1–3 + slider/baseline/preset và temperature.

  UI-only giữ nguyên (KHÔNG map, không lưu — doc §10.3):
  radar 6 trục, expressionLevel, sampleQuotes, displayChannels,
  overviewForm.industry/primaryGoal/targetAudience/brandPillars, avatar,
  emojiDensity, ctaList, autoScoreCheck, nút "Khôi phục" lịch sử.

  CHUẨN UI (docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §3.5):
  PascalCase V*/App*, icon tabler-*, màu theme, spacing logical me/ms,
  không inline font-size, không mồi dữ liệu giả cho phần đã có API.

  V3 lấy preset từ options, giữ slider null, lưu baseline riêng với lựa chọn.
  Sandbox lưu brief/artifacts theo bản thử; duyệt dàn ý trước khi viết và
  cho phép lập lại dàn ý khi fingerprint không còn khớp. Không tự lưu profile
  khi chỉ thay đổi các ô trên form.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - prevQuote(): Chuyển sang trích dẫn trước và quay vòng khi ở đầu danh sách.
  - nextQuote(): Chuyển sang trích dẫn sau và quay vòng khi ở cuối danh sách.
  - addSampleQuote(): Thêm trích dẫn với nội dung giữ chỗ để chỉnh trên giao diện.
  - ruleLabel(): Đổi khóa DNA sang nhãn hiển thị tiếng Việt, fallback về khóa gốc.
  - addPowerWord(): Thêm từ ưu tiên chưa có vào danh sách rồi xóa ô nhập.
  - addNegativeWord(): Thêm từ cần tránh chưa có vào danh sách rồi xóa ô nhập.
  - recalculateVoiceTextureFromCheckboxes(): Tính chất văn theo baseline và các lựa chọn, đồng bộ directive vào Ô 2.
  - toggleVoiceStyleOption(): Bật/tắt lựa chọn chất văn rồi tính lại slider và chỉ dẫn Ô 2.
  - recalculateToneFromSelect(): Tính sắc thái theo baseline và lựa chọn, đồng bộ directive vào Ô 3.
  - resetToneSliders(): Khôi phục slider cảm xúc về baseline, bỏ lựa chọn và directive preset.
  - resetVoiceTextureSliders(): Khôi phục slider chất văn về baseline, bỏ lựa chọn và directive preset.
  - openSandboxSampleDialog(): Mở dialog xem bản thử được chọn.
  - getPipelineStepsForTier(): Lấy danh sách các bước (steps) từ config cho 1 cấp độ độ dài (tier).
  - cleanStepLabel(): Rút gọn nhãn bước bằng cách bỏ tiền tố Bước kèm số.
  - normalizeSandboxSample(): Chuẩn hóa sample cũ/mới cho giao diện pipeline.
  - applyTonePreset(): Áp dụng preset cảm xúc, đồng bộ lựa chọn, slider và chỉ dẫn Ô 3.
  - resetToneToBaseline(): Chọn preset default để trả cảm xúc về baseline.
  - buildSandboxBrief(): Đóng gói form sandbox thành brief V3 theo chủ đề.
  - buildCurrentSandboxOverrides(): Lấy thay đổi Ô 1–3 và slider để gửi từng bước sandbox.
  - runSandboxDemo(): Tải options và chạy bước đầu để tạo bản thử theo tier.
  - runNextSandboxStep(): Gửi bước kế tiếp cùng artifact và xác nhận duyệt dàn ý.
  - refreshSandboxOutline(): Lập lại dàn ý của bản thử với brief đã lưu và style hiện tại.
  - addOutlineSection(): Thêm một mục khung H2/H3 vào artifact V2 đang chỉnh.
  - removeOutlineSection(): Xóa mục khỏi dàn ý đang chỉnh khi còn hơn một mục.
  - persistSampleToDb(): Upsert sample đang dở hoặc hoàn chỉnh vào hồ sơ.
  - saveCurrentSandboxSample(): Lưu bản thử với thông báo lỗi cho thao tác lưu thủ công.
  - removeSandboxSample(): Xóa 1 bản viết thử (khỏi danh sách tạm hoặc khỏi Database).
  - copyText(): Sao chép văn bản vào clipboard và thông báo kết quả.
  - extractQuotedWords(): Lấy các cụm trong dấu ngoặc kép để tạo danh sách từ ưu tiên.
  - splitPronouns(): Tách mô tả xưng hô thành cách xưng của thương hiệu và cách gọi khách.
  - joinPronouns(): Ghép hai ô xưng hô thành mô tả để lưu rules_json.pronouns.
  - avoidToRules(): Tách mô tả điều tránh thành các quy tắc cấm cho form.
  - vocabToRules(): Chuyển cụm từ trong ngoặc kép thành quy tắc từ ngữ được khuyến khích.
  - applyProfileToForm(): Nạp hồ sơ vào form, baseline slider, presets và danh sách sample.
  - readEditableRules(): Đọc các ô chỉnh DNA thành một mảng khóa thống nhất để so với bản ban đầu.
  - buildRulesJsonPayload(): Tạo rules_json từ các ô đã sửa, giữ nguyên nội dung chưa đổi.
  - buildEvidenceJsonPayload(): Chuẩn bị bằng chứng trích dẫn cho payload lưu hồ sơ.
  - saveProfile(): Lưu form hồ sơ, DNA và baseline/preset slider qua PUT.
  - runSlugSuggest(): Xin slug khả dụng theo nguồn nhập và ID hồ sơ hiện tại.
  - onNameBlur(): Gợi ý slug từ tên hồ sơ khi người dùng rời ô tên.
  - onSlugBlur(): Kiểm tra và gợi ý slug từ ô slug khi mất focus.
  - onSlugInput(): Ghi nhận slug người dùng tự sửa để chỉ gửi trường này khi thay đổi.
  - updateAdvancedCardHeight(): Căn chiều cao panel nâng cao theo viewport và footer.
  - Computed: dominantTierMeta, VOICE_STYLE_OPTIONS, voiceStyleSelectItems, hasModifiedVoiceSliders, TONE_STYLE_OPTIONS, toneStyleSelectItems, hasModifiedToneSliders, usesPromptV3, currentTierPipelineSteps, activeSandboxSample.
  - Watcher: theo dõi activeTab/loading để đo lại panel sau render.
  - onMounted: nạp profile/options, bind resize; onBeforeUnmount: gỡ listener và class body.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : Route, form và state useWritingStyleStore; không có props/emits riêng.
  - OUTPUT: Render UI, gọi store/API, cập nhật snackbar và điều hướng theo thao tác.
  =====================================================================
-->
<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SlugField from '@modules/Core/resources/js/components/SlugField.vue'
import TaxonomyField from '@modules/Taxonomy/resources/js/components/TaxonomyField.vue'
import SandboxSampleDetailDialog from '../../../components/SandboxSampleDetailDialog.vue'
import SandboxWritingBrief from '../../../components/SandboxWritingBrief.vue'
import { mergeRuleEdits } from '../../../utils/sampleText.js'
import { firstErrorMessage } from '@/utils/api'
import { useWritingStyleStore } from '../../../stores/writingStyle'

definePage({ meta: { layout: 'default' } })

const route = useRoute()
const router = useRouter()
const store = useWritingStyleStore()

const profile = ref(null)
let initialRuleEdits = {}
const loading = ref(true)
const saving = ref(false)
const loadError = ref('')

const activeTab = ref('style-traits')
const snack = reactive({ show: false, text: '', color: 'success' })

/* Slug realtime — trạng thái đổ vào SlugField (giống trang create). */
const slugAvailable = ref(null)
const slugTakenBy = ref(null)
const slugDirty = ref(false) // user đã chỉnh slug tay -> chỉ gửi khi dirty

/* ---------------------------------------------------------------------
 * BANNER + TAB 1: TỔNG QUAN
 * ------------------------------------------------------------------- */
const profileData = reactive({
  name: '',
  version: 'v1',
  isActive: false,      // is_enabled
  status: 'draft',
  shortDesc: '',        // description
  slug: '',
})

const overviewForm = reactive({
  // UI-only — giữ nguyên layout, không map DB (không có cột).
  industry: '',
  primaryGoal: '',
  targetAudience: '',
  brandPillars: [],
})

/* Taxonomy — TaxonomyField nhận id[] cho categories, (id|name)[] cho tags. */
const categories = ref([])
const tags = ref([])
const newTags = ref([]) // tên tag mới do user gõ (không phải id)

/* ---------------------------------------------------------------------
 * TAB 2: ĐẶC ĐIỂM VĂN PHONG — map rules_json + style_instructions
 * ------------------------------------------------------------------- */
const traitSummaryList = ref([]) // render từ rules_json.tone/emotion/pronouns/vocabulary


const currentQuoteIdx = ref(0)
const sampleQuotes = ref([])     // UI-only — giữ nguyên mẫu, không map DB

const displayChannels = ref([
  { name: 'Facebook', icon: 'tabler-brand-facebook', color: '#1877F2', enabled: true },
  { name: 'YouTube', icon: 'tabler-brand-youtube', color: '#FF0000', enabled: true },
  { name: 'TikTok', icon: 'tabler-brand-tiktok', color: '#18181b', enabled: true },
  { name: 'Blog', icon: 'tabler-pencil', color: '#7367F0', enabled: true },
  { name: 'Instagram', icon: 'tabler-brand-instagram', color: '#E4405F', enabled: true },
  { name: 'Website', icon: 'tabler-world', color: '#6366f1', enabled: true },
  { name: 'Email', icon: 'tabler-mail', color: '#6366f1', enabled: false },
  { name: 'Khác', icon: 'tabler-dots', color: '#4b5563', enabled: false },
])

/**
 * =====================================================================
 * CHỨC NĂNG: Chuyển sang trích dẫn trước và quay vòng khi ở đầu danh sách
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Cập nhật currentQuoteIdx trong trang edit.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
const prevQuote = () => {
  currentQuoteIdx.value = (currentQuoteIdx.value - 1 + sampleQuotes.value.length) % sampleQuotes.value.length
}

/**
 * =====================================================================
 * CHỨC NĂNG: Chuyển sang trích dẫn sau và quay vòng khi ở cuối danh sách
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Cập nhật currentQuoteIdx trong trang edit.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
const nextQuote = () => {
  currentQuoteIdx.value = (currentQuoteIdx.value + 1) % sampleQuotes.value.length
}

/**
 * =====================================================================
 * CHỨC NĂNG: Thêm trích dẫn với nội dung giữ chỗ để chỉnh trên giao diện
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Thêm phần tử sampleQuotes và chuyển chỉ mục hiện tại; dữ liệu này chỉ ở UI.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
const addSampleQuote = () => {
  sampleQuotes.value.push({ text: 'Nội dung mẫu mới…', image: '', tags: [] })
  currentQuoteIdx.value = sampleQuotes.value.length - 1
}

/* ---------------------------------------------------------------------
 * TAB 3: CẤU TRÚC NỘI DUNG — render từ rules_json
 * ------------------------------------------------------------------- */
const structureSections = ref([]) // [{title, weight, sentences, guideline, sample}]

/* Chip hiển thị tier độ dài của profile — giống list page (icon + label
 * tiếng Việt + màu). Đọc từ options.tiers (GET /options) + TIER_META local. */
const TIER_META = {
  micro: { icon: 'tabler-letter-s',   color: 'error'   },
  short: { icon: 'tabler-file-text',  color: 'warning' },
  medium: { icon: 'tabler-article',   color: 'info'    },
  long: { icon: 'tabler-book',        color: 'primary' },
}

const dominantTierMeta = computed(() => {
  const tierKey = profile.value?.dominant_tier ?? 'medium'
  const meta = TIER_META[tierKey] ?? TIER_META.medium
  const option = (store.options?.tiers ?? []).find(t => t.value === tierKey)

  return {
    key: tierKey,
    icon: meta.icon,
    color: meta.color,
    label: option?.label ?? tierKey,
    sentenceHint: option?.sentence_hint ?? '',
  }
})

/* ---------------------------------------------------------------------
 * TỪ ĐIỂN TÊN TIẾNG VIỆT CHO 16 KHÓA DNA
 * ------------------------------------------------------------------- */
const RULE_LABELS = {
  tone: 'Giọng điệu & Tâm thế',
  pronouns: 'Đại từ xưng hô',
  emotion: 'Cảm xúc chủ đạo',
  opening: 'Cách mở bài',
  sentence_rhythm: 'Nhịp thở câu văn',
  paragraph_rhythm: 'Nhịp phân đoạn',
  transitions: 'Nghệ thuật chuyển ý',
  vocabulary: 'Chất liệu từ vựng',
  technical_terms: 'Thuật ngữ chuyên ngành',
  structure_patterns: 'Khung cấu trúc bài',
  headings: 'Tiêu đề con (Heading)',
  bullets: 'Gạch đầu dòng (Bullet)',
  examples: 'Dẫn chứng & Ví dụ',
  ending: 'Kết bài & CTA',
  avoid: 'Điều kiêng kỵ (Avoid)',
  uncertainties: 'Điểm chưa xác định',
}

/**
 * =====================================================================
 * CHỨC NĂNG: Đổi khóa DNA sang nhãn hiển thị tiếng Việt, fallback về khóa gốc
 * =====================================================================
 *
 * INPUT:
 * - key
 *
 * OUTPUT:
 * - string nhãn của quy tắc
 *
 * SIDE EFFECT:
 * - Tra bảng nhãn, không đổi dữ liệu.
 *
 * EXCEPTION:
 * - Không bắt hoặc ném lỗi chủ động.
 * =====================================================================
 */
function ruleLabel(key) {
  return RULE_LABELS[key] || key
}

/* ---------------------------------------------------------------------
 * TAB 4: QUY TẮC NGÔN NGỮ — render từ rules_json.pronouns/avoid/vocabulary
 * ------------------------------------------------------------------- */
const languageConfig = reactive({
  brandPronoun: '',
  customerPronoun: '',
  emojiDensity: '', // UI-only
  editorialRules: [], // [{text, allowed}]
})

/* ---------------------------------------------------------------------
 * TAB 5: MẪU NỘI DUNG — render từ evidence_json
 * ------------------------------------------------------------------- */
const fullPostSamples = ref([]) // [{channel, date, title, content}]

/* ---------------------------------------------------------------------
 * TAB 6: TỪ VỰNG & CTA — render từ rules_json.vocabulary/avoid/ending
 * ------------------------------------------------------------------- */
const vocabConfig = reactive({
  vocabularyDesc: '',
  powerWords: [],
  negativeWords: [],
  ctaPhilosophy: '',
  newPowerWord: '',
  newNegativeWord: '',
})

/**
 * =====================================================================
 * CHỨC NĂNG: Thêm từ ưu tiên chưa có vào danh sách rồi xóa ô nhập
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Sửa vocabConfig trên form; bỏ qua từ rỗng hoặc trùng.
 *
 * EXCEPTION:
 * - Không gọi API; lưu khi người dùng lưu profile.
 * =====================================================================
 */
function addPowerWord() {
  const w = vocabConfig.newPowerWord.trim()
  if (w && !vocabConfig.powerWords.includes(w)) {
    vocabConfig.powerWords.push(w)
    vocabConfig.newPowerWord = ''
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Thêm từ cần tránh chưa có vào danh sách rồi xóa ô nhập
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Sửa vocabConfig trên form; bỏ qua từ rỗng hoặc trùng.
 *
 * EXCEPTION:
 * - Không gọi API; lưu khi người dùng lưu profile.
 * =====================================================================
 */
function addNegativeWord() {
  const w = vocabConfig.newNegativeWord.trim()
  if (w && !vocabConfig.negativeWords.includes(w)) {
    vocabConfig.negativeWords.push(w)
    vocabConfig.newNegativeWord = ''
  }
}

/* ---------------------------------------------------------------------
 * TAB 7: CÀI ĐẶT NÂNG CAO — Ô 1 (style_instructions) + Ô 2 (voice_texture) + 5 sliders
 * ------------------------------------------------------------------- */
const advancedConfig = reactive({
  systemPrompt: '',
  voiceTexturePrompt: '',
  tonePrompt: '', // Ô 3 - Chỉ dẫn Sắc thái & Phối âm cảm xúc (tone_instructions)
  preferredModel: '',
  temperature: 0.75,
  autoScoreCheck: true,
})

/* 5 Thanh trượt canh chỉnh Chất văn (lưu cố định vào writing_profiles.voice_texture_sliders_json) */
const voiceTextureSliders = reactive([
  {
    key: 'voice_timbre',
    label: '1. Giọng văn',
    lowPole: 'Khách quan, đĩnh đạc',
    highPole: 'Trầm ấm, thủ thỉ tâm tình',
    baseline: 50,
    defaultVal: 50,
    value: 70,
    color: 'primary',
    icon: 'tabler-microphone-2',
    hint: '0%: Sắc sảo, chuẩn mực chuyên gia ↔ 100%: Thủ thỉ gần gũi như đang tâm tình 1-1.',
  },
  {
    key: 'vernacular_flow',
    label: '2. Tính dân dã tự nhiên',
    lowPole: 'Chuẩn mực, trau chuốt',
    highPole: 'Đời thường, thuận tai người Việt',
    baseline: 50,
    defaultVal: 50,
    value: 65,
    color: 'success',
    icon: 'tabler-message-circle',
    hint: '0%: Văn phong gọt giũa, học thuật ↔ 100%: Từ thuần Việt dung dị, khẩu ngữ đời thường tự nhiên.',
  },
  {
    key: 'emotional_nuance',
    label: '3. Màu sắc cảm xúc',
    lowPole: 'Tiết chế, điềm tĩnh',
    highPole: 'Sâu lắng, giàu dư âm',
    baseline: 50,
    defaultVal: 50,
    value: 75,
    color: 'error',
    icon: 'tabler-heart-spark',
    hint: '0%: Khách quan, trung lập ↔ 100%: Giàu chiều sâu nội tâm, gợi cảm xúc qua chi tiết & khoảng lặng.',
  },
  {
    key: 'rhythm_variation',
    label: '4. Biến thiên nhịp điệu hành văn',
    lowPole: 'Nhịp đều, êm ả',
    highPole: 'Co giãn mạnh (câu dài xen câu cực ngắn)',
    baseline: 50,
    defaultVal: 50,
    value: 70,
    color: 'warning',
    icon: 'tabler-wave-sine',
    hint: '0%: Nhịp câu ổn định, thư thái ↔ 100%: Đan xen câu ghép dài với câu đơn 3–6 chữ tạo điểm rơi.',
  },
  {
    key: 'emphasis_glide',
    label: '5. Tính nhấn và lướt',
    lowPole: 'Phân bổ đồng đều',
    highPole: 'Lướt nhanh ý phụ, zoom-in chi tiết đắt',
    baseline: 50,
    defaultVal: 50,
    value: 80,
    color: 'info',
    icon: 'tabler-focus-2',
    hint: '0%: Triển khai đều các ý ↔ 100%: Lướt qua thông tin nền trong 1 câu, dừng lại khắc họa sâu chi tiết đắt giá.',
  },
])

/* Danh sách Checkbox các dạng hành văn xoay quanh 5 thuộc tính Chất văn.
 * Khi người dùng tích chọn / bỏ chọn -> tự động cộng hưởng tính điểm cho 5 thanh trượt
 * và đồng bộ gợi ý vào Ô 2 (giống cơ chế Sandbox cảm xúc). */
const VOICE_STYLE_OPTIONS = computed(() => store.options.presets?.voice ?? [])

const selectedVoiceOptionIds = ref([])

/**
 * =====================================================================
 * CHỨC NĂNG COMPUTED: Danh sách items cho AppSelect gợi ý chất văn & nhịp điệu
 * =====================================================================
 * Chuyển đổi 8 dạng hành văn VOICE_STYLE_OPTIONS thành format item cho AppSelect.
 *
 * INPUT/OUTPUT:
 * - INPUT : Hằng số VOICE_STYLE_OPTIONS
 * - OUTPUT: Mảng item [{ value, title, attributeLabel, desc, icon, color }]
 * =====================================================================
 */
const voiceStyleSelectItems = computed(() =>
  VOICE_STYLE_OPTIONS.value.map(opt => ({
    value: opt.id,
    title: opt.title,
    attributeLabel: opt.attributeLabel,
    desc: opt.desc,
    icon: opt.icon,
    color: opt.color,
  })),
)

/** Tab dọc đang chọn trong Tab 7 (Cài đặt nâng cao): 'prompt1' | 'prompt2' | 'prompt3' */
const activeAdvancedPromptTab = ref('prompt1')

/** Quản lý trạng thái đóng / mở của collapse 5 thanh trượt Chất văn (mặc định đóng null) */
const voiceTexturePanel = ref(null)

/** Kiểm tra xem có thanh trượt Chất văn nào đang khác baseline (50%) hay không */
const hasModifiedVoiceSliders = computed(() =>
  voiceTextureSliders.some(s => s.value !== s.baseline),
)

/**
 * =====================================================================
 * CHỨC NĂNG: Tính chất văn theo baseline và các lựa chọn, đồng bộ directive vào Ô 2
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Đổi giá trị slider và voiceTexturePrompt theo presets từ options.
 *
 * EXCEPTION:
 * - Trục chưa xác định giữ null khi tổng delta bằng 0.
 * =====================================================================
 */
function recalculateVoiceTextureFromCheckboxes() {
  const checkedItems = VOICE_STYLE_OPTIONS.value.filter(opt =>
    selectedVoiceOptionIds.value.includes(opt.id),
  )

  // 1. Tính lại điểm 5 thanh trượt từ baseline + tổng delta các ô đã chọn
  voiceTextureSliders.forEach(slider => {
    const base = slider.baseline
    if (!checkedItems.length) {
      slider.value = base
      
      return
    }

    const totalDelta = checkedItems.reduce(
      (sum, item) => sum + (Number(item.deltas?.[slider.key]) || 0),
      0,
    )

    slider.value = totalDelta === 0 ? base : Math.max(0, Math.min(100, (base ?? 50) + totalDelta))
  })

  // 2. Đồng bộ các dòng chỉ đạo đã tích chọn vào Ô 2 (giữ nguyên phần văn bản gốc của AI)
  const allDirectives = new Set(VOICE_STYLE_OPTIONS.value.map(o => `- ${o.directive}`))

  const currentLines = String(advancedConfig.voiceTexturePrompt || '')
    .split('\n')
    .map(l => l.trim())
    .filter(l => l !== '' && !allDirectives.has(l))

  const selectedLines = checkedItems.map(o => `- ${o.directive}`)

  advancedConfig.voiceTexturePrompt = [...currentLines, ...selectedLines].join('\n')
}

/**
 * =====================================================================
 * CHỨC NĂNG: Bật/tắt lựa chọn chất văn rồi tính lại slider và chỉ dẫn Ô 2
 * =====================================================================
 *
 * INPUT:
 * - optId
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Sửa selectedVoiceOptionIds và gọi recalculateVoiceTextureFromCheckboxes.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function toggleVoiceStyleOption(optId) {
  const idx = selectedVoiceOptionIds.value.indexOf(optId)
  if (idx >= 0) {
    selectedVoiceOptionIds.value.splice(idx, 1)
  } else {
    selectedVoiceOptionIds.value.push(optId)
  }
  recalculateVoiceTextureFromCheckboxes()
}


/* Danh sách Gợi ý Sắc thái & Phối âm cảm xúc xoay quanh 8 trục cảm xúc (Ô 3).
 * Khi người dùng chọn / bỏ chọn -> tự động cộng hưởng tính điểm cho 8 thanh trượt
 * và đồng bộ gợi ý vào Ô 3 (tone_instructions). */
const TONE_STYLE_OPTIONS = computed(() => store.options.presets?.tone ?? [])

/** ID các tùy chọn sắc thái / phối âm cảm xúc đang được chọn trong AppSelect */
const selectedToneOptionIds = ref([])

/**
 * =====================================================================
 * CHỨC NĂNG COMPUTED: Danh sách items cho AppSelect gợi ý sắc thái cảm xúc
 * =====================================================================
 * Chuyển đổi 8 dạng sắc thái TONE_STYLE_OPTIONS thành format item cho AppSelect.
 *
 * INPUT/OUTPUT:
 * - INPUT : Hằng số TONE_STYLE_OPTIONS
 * - OUTPUT: Mảng item [{ value, title, attributeLabel, desc, icon, color }]
 * =====================================================================
 */
const toneStyleSelectItems = computed(() =>
  TONE_STYLE_OPTIONS.value.map(opt => ({
    value: opt.id,
    title: opt.title,
    attributeLabel: opt.attributeLabel,
    desc: opt.desc,
    icon: opt.icon,
    color: opt.color,
  })),
)

/** Quản lý trạng thái đóng / mở của collapse 8 thanh trượt Cảm xúc (mặc định đóng null) */
const toneSlidersPanel = ref(null)

/** Kiểm tra xem có thanh trượt Cảm xúc nào đang khác baseline (50%) hay không */
const hasModifiedToneSliders = computed(() =>
  toneSliders.some(t => t.value !== t.baseline),
)

/**
 * =====================================================================
 * CHỨC NĂNG: Tính sắc thái theo baseline và lựa chọn, đồng bộ directive vào Ô 3
 * =====================================================================
 *
 * Chi tiết triển khai:
 * =====================================================================
 * CHỨC NĂNG HÀM: Tính lại điểm 8 thanh trượt và đồng bộ chỉ đạo vào Ô 3
 * =====================================================================
 * Được gọi khi người dùng chọn / bỏ chọn sắc thái trong AppSelect gợi ý.
 *
 * INPUT/OUTPUT:
 * - INPUT : selectedToneOptionIds (ref mảng ID đã chọn)
 * - OUTPUT: Cập nhật trực tiếp toneSliders (8 sliders) và advancedConfig.tonePrompt
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Đổi slider và tonePrompt; bỏ dòng directive preset cũ trước khi thêm dòng mới.
 *
 * EXCEPTION:
 * - Trục chưa xác định giữ null khi tổng delta bằng 0.
 * =====================================================================
 */
function recalculateToneFromSelect() {
  const checkedItems = TONE_STYLE_OPTIONS.value.filter(opt =>
    selectedToneOptionIds.value.includes(opt.id),
  )

  // 1. Tính lại điểm 8 thanh trượt từ baseline + tổng delta các ô đã chọn
  toneSliders.forEach(slider => {
    const base = slider.baseline
    if (!checkedItems.length) {
      slider.value = base

      return
    }

    const totalDelta = checkedItems.reduce(
      (sum, item) => sum + (Number(item.deltas?.[slider.key]) || 0),
      0,
    )

    slider.value = totalDelta === 0 ? base : Math.max(0, Math.min(100, (base ?? 50) + totalDelta))
  })

  // 2. Đồng bộ các dòng chỉ đạo đã tích chọn vào Ô 3 (giữ nguyên phần văn bản gốc)
  const allDirectives = new Set(TONE_STYLE_OPTIONS.value.map(o => `- ${o.directive}`))

  const currentLines = String(advancedConfig.tonePrompt || '')
    .split('\n')
    .map(l => l.trim())
    .filter(l => l !== '' && !allDirectives.has(l))

  const selectedLines = checkedItems.map(o => `- ${o.directive}`)

  advancedConfig.tonePrompt = [...currentLines, ...selectedLines].join('\n')
}

/**
 * =====================================================================
 * CHỨC NĂNG: Khôi phục slider cảm xúc về baseline, bỏ lựa chọn và directive preset
 * =====================================================================
 *
 * Chi tiết triển khai:
 * =====================================================================
 * CHỨC NĂNG HÀM: Reset 8 thanh trượt Cảm xúc về baseline và xóa gợi ý đã chọn
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Giữ baseline có thể null, sửa Ô 3 và hiển thị snackbar.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function resetToneSliders() {
  selectedToneOptionIds.value = []
  toneSliders.forEach(t => {
    t.value = t.baseline
  })

  const allDirectives = new Set(TONE_STYLE_OPTIONS.value.map(o => `- ${o.directive}`))

  advancedConfig.tonePrompt = String(advancedConfig.tonePrompt || '')
    .split('\n')
    .map(l => l.trim())
    .filter(l => l !== '' && !allDirectives.has(l))
    .join('\n')

  snack.text = 'Đã khôi phục 8 thanh trượt Sắc thái cảm xúc về cân bằng'
  snack.color = 'info'
  snack.show = true
}

/**
 * =====================================================================
 * CHỨC NĂNG: Khôi phục slider chất văn về baseline, bỏ lựa chọn và directive preset
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Giữ baseline có thể null, sửa Ô 2 và hiển thị snackbar.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function resetVoiceTextureSliders() {
  selectedVoiceOptionIds.value = []
  voiceTextureSliders.forEach(s => {
    s.value = s.baseline
  })

  const allDirectives = new Set(VOICE_STYLE_OPTIONS.value.map(o => `- ${o.directive}`))

  advancedConfig.voiceTexturePrompt = String(advancedConfig.voiceTexturePrompt || '')
    .split('\n')
    .map(l => l.trim())
    .filter(l => l !== '' && !allDirectives.has(l))
    .join('\n')

  snack.text = 'Đã khôi phục 5 thanh trượt Chất văn và bỏ chọn các gợi ý'
  snack.color = 'info'
  snack.show = true
}

/* ---------------------------------------------------------------------
 * TAB 8: LỊCH SỬ — render từ analyses + version hiện tại
 * ------------------------------------------------------------------- */
const versionHistory = ref([])

/* ---------------------------------------------------------------------
 * TAB 9 (MỚI): PHÒNG THỬ DNA — sandbox pipeline nhiều bước + tone calibration
 * ------------------------------------------------------------------- */
const briefForm = ref({})
const suggestingBrief = ref(false) // đang gọi AI sinh brief (nút ✨ ô chủ đề)

const sandboxForm = reactive({
  topic: '',
  targetTier: 'medium',
})

/** Danh sách các bản viết thử (gồm cả bản đang chạy theo từng bước & bản đã hoàn thành lưu DB) */
const savedSandboxSamples = ref([])
const savingSandboxSample = ref(false)
const savingSampleId = ref(null)
const deletingSandboxSampleId = ref(null)
const runningSampleStepId = ref(null)
const expandedSamplePrompts = reactive({})
const expandedSampleSteps = reactive({})
const sandboxDetailDialogOpen = ref(false)
const activeDialogSample = ref(null)

/**
 * =====================================================================
 * CHỨC NĂNG: Mở dialog xem bản thử được chọn
 * =====================================================================
 *
 * INPUT:
 * - sample
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Đặt activeDialogSample và bật sandboxDetailDialogOpen.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function openSandboxSampleDialog(sample) {
  activeDialogSample.value = sample
  sandboxDetailDialogOpen.value = true
}

/** Fallback sơ đồ pipeline theo config khi API /options chưa kịp trả về */
const DEFAULT_TIER_PIPELINES = {
  micro: [
    {
      step: 'article_writer',
      label: 'Bước 1: Áp dụng DNA & Viết toàn bài',
      description: 'Áp dụng toàn bộ DNA văn phong để sinh bài viết hoàn chỉnh.',
      icon: 'tabler-writing',
      stage: 100,
      is_terminal: true,
      depends_on: [],
    },
  ],
  short: [
    {
      step: 'article_writer',
      label: 'Bước 1: Áp dụng DNA & Viết toàn bài',
      description: 'Áp dụng toàn bộ DNA văn phong để sinh bài viết hoàn chỉnh.',
      icon: 'tabler-writing',
      stage: 100,
      is_terminal: true,
      depends_on: [],
    },
  ],
  medium: [
    {
      step: 'article_writer',
      label: 'Bước 1: Áp dụng DNA & Viết toàn bài',
      description: 'Áp dụng toàn bộ DNA văn phong để sinh bài viết hoàn chỉnh.',
      icon: 'tabler-writing',
      stage: 100,
      is_terminal: true,
      depends_on: [],
    },
  ],
  long: [
    {
      step: 'topic_outline',
      label: 'Bước 1: Phân tích Chủ đề & Lập Dàn ý',
      description: 'Rút luận điểm cốt lõi, góc tiếp cận độc giả và cây dàn ý H2/H3 thực chiến trước khi viết.',
      icon: 'tabler-binary-tree',
      stage: 20,
      is_terminal: false,
      depends_on: [],
    },
    {
      step: 'article_writer',
      label: 'Bước 2: Áp dụng DNA & Viết toàn bài',
      description: 'Áp dụng toàn bộ DNA văn phong + kết quả phân tích các bước trước để sinh bài viết hoàn chỉnh.',
      icon: 'tabler-writing',
      stage: 100,
      is_terminal: true,
      depends_on: ['topic_outline'],
    },
  ],
}

/**
 * =====================================================================
 * CHỨC NĂNG: Lấy danh sách các bước (steps) từ config cho 1 cấp độ độ dài (tier).
 * =====================================================================
 *
 * Chi tiết triển khai:
 * Lấy danh sách các bước (steps) từ config cho 1 cấp độ độ dài (tier).
 * Tự động đọc động từ `store.tierPipelines[tier].steps` (từ config.php).
 *
 * INPUT:
 * - tier
 *
 * OUTPUT:
 * - Kết quả hiển thị hoặc cập nhật state theo mô tả chi tiết.
 *
 * SIDE EFFECT:
 * - Xử lý state hoặc dữ liệu của component theo luồng đang gọi.
 *
 * EXCEPTION:
 * - Các lỗi API được xử lý ở hàm nghiệp vụ gọi helper này.
 * =====================================================================
 */
function getPipelineStepsForTier(tier) {
  const t = tier || 'medium'
  const fromStore = store.tierPipelines?.[t]?.steps
  if (Array.isArray(fromStore) && fromStore.length > 0) {
    return fromStore.map(s => ({ ...s }))
  }

  return (DEFAULT_TIER_PIPELINES[t] || DEFAULT_TIER_PIPELINES.medium).map(s => ({ ...s }))
}

/** Danh sách các bước của Tier đang chọn trên form "Chủ đề thử nghiệm mới" */
const currentTierPipelineSteps = computed(() =>
  getPipelineStepsForTier(sandboxForm.targetTier || profile.value?.dominant_tier || 'medium'),
)

/**
 * =====================================================================
 * CHỨC NĂNG: Rút gọn nhãn bước bằng cách bỏ tiền tố Bước kèm số
 * =====================================================================
 *
 * Chi tiết triển khai:
 *
 * INPUT:
 * - stepObj
 *
 * OUTPUT:
 * - string nhãn đã trim hoặc rỗng khi không có step
 *
 * SIDE EFFECT:
 * - Chỉ xử lý chuỗi.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function cleanStepLabel(stepObj) {
  if (!stepObj) return ''

  return String(stepObj.label || stepObj.step || '').replace(/^Bước\s+\d+\s*:\s*/i, '').trim()
}

/**
 * =====================================================================
 * CHỨC NĂNG: Chuẩn hóa sample cũ/mới cho giao diện pipeline
 * =====================================================================
 *
 * Chi tiết triển khai:
 *
 * INPUT:
 * - raw
 *
 * OUTPUT:
 * - Object sample gồm brief, steps, trạng thái, artifacts và nội dung
 *
 * SIDE EFFECT:
 * - Tạo object từ raw, dùng pipeline options làm fallback.
 *
 * EXCEPTION:
 * - Không xác minh tính hợp lệ của artifact ở đây; backend kiểm tra khi chạy.
 * =====================================================================
 */
function normalizeSandboxSample(raw) {
  const tier = raw.target_tier || profile.value?.dominant_tier || 'medium'
  const steps = Array.isArray(raw.steps) && raw.steps.length > 0
    ? raw.steps
    : getPipelineStepsForTier(tier)

  const hasText = Boolean(raw.text && String(raw.text).trim() !== '')
  const currentStepIndex = typeof raw.current_step_index === 'number'
    ? raw.current_step_index
    : (hasText ? steps.length - 1 : 0)

  const status = raw.status || (hasText || currentStepIndex >= steps.length - 1 ? 'completed' : 'in_progress')

  return {
    id: raw.id || `local-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    topic: raw.topic || 'Chủ đề thử nghiệm',
    brief: raw.brief ?? null,
    target_tier: tier,
    steps,
    current_step_index: currentStepIndex,
    status,
    artifacts: raw.artifacts && typeof raw.artifacts === 'object' ? raw.artifacts : {},
    text: raw.text || '',
    system_prompt: raw.system_prompt || '',
    saved_to_db: raw.saved_to_db ?? Boolean(raw.id && !String(raw.id).startsWith('local-')),
    created_at: raw.created_at || new Date().toISOString(),
  }
}

/** Quản lý trạng thái mở/đóng các panel trong Tab 9 Phòng thử DNA (chế độ accordion đơn, mặc định đóng cả 2):
 * null: Đóng cả 2 panel mặc định
 * 'tone': Mở Sandbox sắc thái (tự đóng Chất văn)
 * 'voice': Mở Chất văn & Nghệ thuật Nhấn – Lướt (tự đóng Sandbox sắc thái)
 */
const sandboxPanels = ref(null)

/* 8 trục sắc thái cảm xúc — baseline 50 (điểm cân bằng) để render diff.
 * (Trên create baseline khác nhau theo profile; ở edit dùng chuẩn 50 đơn giản.) */
const toneSliders = reactive([
  { key: 'humor',         category: 'emotion', label: 'Vui vẻ & Hóm hỉnh',        baseline: 50, value: 50, color: 'warning',  icon: 'tabler-mood-smile',   hint: 'Chêm xen ví dụ dí dỏm, chơi chữ tinh tế, tạo không khí nhẹ nhàng' },
  { key: 'empathy',       category: 'emotion', label: 'Trân trọng & Đồng cảm',    baseline: 50, value: 50, color: 'error',    icon: 'tabler-heart',        hint: 'Ghi nhận nỗ lực độc giả, thấu hiểu khó khăn, giọng điệu ấm áp' },
  { key: 'assertiveness', category: 'tone',    label: 'Sắc sảo & Quyết đoán',     baseline: 50, value: 50, color: 'primary',  icon: 'tabler-bolt',         hint: 'Luận điểm đanh thép, lập trường dứt khoát, giảm từ ngập ngừng' },
  { key: 'formality',     category: 'tone',    label: 'Trang trọng vs Thân mật',  baseline: 50, value: 50, color: 'info',     icon: 'tabler-scale',        hint: '0%: Thân tình như bạn bè | 100%: Chuẩn mực học thuật' },
  { key: 'inspiration',   category: 'emotion', label: 'Nhiệt huyết & Truyền lửa', baseline: 50, value: 50, color: 'warning',  icon: 'tabler-flame',        hint: 'Tiết tấu hào sảng, thắp sáng động lực, khơi gợi niềm tin' },
  { key: 'candor',        category: 'emotion', label: 'Chân thực & Bộc trực',     baseline: 50, value: 50, color: 'success',  icon: 'tabler-shield-check', hint: 'Kể chuyện thực tế, dám nói sự thật gai góc, không né tránh' },
  { key: 'depth',         category: 'tone',    label: 'Trí tuệ & Chuyên sâu',     baseline: 50, value: 50, color: 'primary',  icon: 'tabler-brain',        hint: 'Phân tích bản chất, cơ chế hoạt động ngầm, số liệu, kiến trúc' },
  { key: 'urgency',       category: 'tone',    label: 'Khẩn thiết & Kêu gọi',     baseline: 50, value: 50, color: 'error',    icon: 'tabler-alarm',        hint: 'Nhấn mạnh cấp thiết của thời điểm, thôi thúc hành động ngay' },
])

/**
 * =====================================================================
 * CHỨC NĂNG: Đóng gói form sandbox thành brief V3 theo chủ đề
 * =====================================================================
 *
 * INPUT:
 * - topic
 *
 * OUTPUT:
 * - Object brief chuẩn V3
 *
 * SIDE EFFECT:
 * - Đọc dữ liệu từ briefForm.
 *
 * EXCEPTION:
 * - Validation nghiệp vụ do backend xử lý.
 * =====================================================================
 */
function buildSandboxBrief(topic) {
  const form = briefForm.value
  const lines = text => String(text ?? '').split(/\r?\n/).map(line => line.trim()).filter(Boolean)

  return {
    topic,
    content_type: form.contentType || null,
    platform: form.platform?.trim() || null,
    audience: form.audience?.trim() || null,
    objective: form.objective?.trim() || null,
    provided_facts: lines(form.facts).map((text, index) => ({ id: `fact_${index + 1}`, text, source: 'user' })),
    required_details: [...new Set(lines(form.requiredDetails))],
    offer: form.offer?.trim() || null,
    cta: form.cta?.trim() || null,
    length: form.lengthUnit ? { unit: form.lengthUnit, min: form.lengthMin || null, max: form.lengthMax || null } : null,
    format: {
      ...(form.headings == null ? {} : { headings: form.headings }),
      ...(form.bullets == null ? {} : { bullets: form.bullets }),
    },
    constraints: lines(form.constraints),
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Gọi AI sinh gợi ý Brief từ chủ đề rồi đổ vào form (ghi đè)
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc sandboxForm.topic + profile + store.
 *
 * OUTPUT:
 * - Promise<void>; cập nhật briefForm và snackbar.
 *
 * SIDE EFFECT:
 * - Gọi POST /sandbox/brief qua store; briefForm được thay thế toàn bộ.
 *   requiredDetails/length giữ nguyên của user — backend không sinh chúng
 *   (đây là ý định người dùng, AI đoán dễ khiến writer lỗi missing_fields).
 *
 * EXCEPTION:
 * - Snack warning khi chủ đề trống; snack error từ firstErrorMessage(e).
 *   Regenerate khi bấm lại — brief cũ bị ghi đè hoàn toàn.
 * =====================================================================
 */
async function generateBriefWithAI() {
  const topic = sandboxForm.topic.trim()
  if (!topic) {
    snack.text = 'Vui lòng nhập chủ đề trước khi AI sinh gợi ý'
    snack.color = 'warning'
    snack.show = true

    return
  }
  if (!profile.value?.id) {
    snack.text = 'Chưa tải được hồ sơ — hãy đợi nạp xong'
    snack.color = 'warning'
    snack.show = true

    return
  }

  suggestingBrief.value = true
  try {
    const data = await store.suggestBrief(profile.value.id, {
      topic,
      target_tier: sandboxForm.targetTier || profile.value.dominant_tier || 'medium',
    })

    briefForm.value = {
      contentType: data.content_type || null,
      platform: data.platform || '',
      audience: data.audience || '',
      objective: data.objective || '',
      facts: (data.provided_facts || []).map(f => f.text).join('\n'),
      requiredDetails: briefForm.value.requiredDetails || '',
      offer: data.offer || '',
      cta: data.cta || '',
      headings: data.format?.headings ?? null,
      bullets: data.format?.bullets ?? null,
      lengthUnit: briefForm.value.lengthUnit || null,
      lengthMin: briefForm.value.lengthMin || null,
      lengthMax: briefForm.value.lengthMax || null,
      constraints: (data.constraints || []).join('\n'),
    }

    const factCount = (data.provided_facts || []).length
    snack.text = factCount > 0
      ? `Đã sinh gợi ý brief với ${factCount} dữ kiện — kiểm tra lại trước khi chạy`
      : 'Đã sinh gợi ý brief — kiểm tra lại trước khi chạy'
    snack.color = 'success'
    snack.show = true
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  } finally {
    suggestingBrief.value = false
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Lấy thay đổi Ô 1–3 và slider để gửi từng bước sandbox
 * =====================================================================
 *
 * Chi tiết triển khai:
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - Object overrides; V3 chỉ gửi nội dung khác hồ sơ, null thể hiện xóa
 *
 * SIDE EFFECT:
 * - Đọc profile và form, không lưu DB.
 *
 * EXCEPTION:
 * - Hàm được gọi sau khi profile đã tải.
 * =====================================================================
 */
function buildCurrentSandboxOverrides() {
  const current = profile.value
  const instructions = {
    style_instructions: advancedConfig.systemPrompt?.trim() || null,
    voice_texture_instructions: advancedConfig.voiceTexturePrompt?.trim() || null,
    tone_instructions: advancedConfig.tonePrompt?.trim() || null,
  }

  const changedAxes = (sliders, stored) => Object.fromEntries(
    sliders.filter(s => (s.value ?? null) !== (stored?.[s.key] ?? null))
      .map(s => [s.key, s.value ?? null]),
  )

  return {
    ...Object.fromEntries(Object.entries(instructions).filter(([key, value]) => value !== (current[key]?.trim() || null))),
    voice_texture_sliders_json: changedAxes(voiceTextureSliders, current.voice_texture_sliders_json),
    tone_overrides: changedAxes(toneSliders, current.tone_sliders_json),
    temperature: Number(advancedConfig.temperature),
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Tải options và chạy bước đầu để tạo bản thử theo tier
 * =====================================================================
 *
 * Chi tiết triển khai:
 * =====================================================================
 * CHỨC NĂNG: Bắt đầu tạo 1 bản ghi bài văn thử nghiệm mới (chạy Bước 1)
 * =====================================================================
 * - Với văn phong/cấp độ có nhiều step (vd: 'long' có Bước 1: Phân tích dàn ý -> Bước 2: Viết bài):
 *   Tạo ngay 1 bản ghi ở Bước 1 trong VCard "Các bài văn thử nghiệm" bên dưới,
 *   hiển thị kết quả Bước 1 và hiện nút "Tiếp tục phân tích lần thứ 2...".
 * - Với văn phong/cấp độ chỉ có 1 step ('article_writer'):
 *   Chạy bước viết bài, tạo bản ghi ở trạng thái "Hoàn thành" và tự động lưu vào hồ sơ.
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi API step, thêm sample vào state và lưu sample cả khi mới có dàn ý.
 *
 * EXCEPTION:
 * - Bắt lỗi API, hiển thị snackbar; kiểm tra profile/chủ đề trước khi chạy.
 * =====================================================================
 */
async function runSandboxDemo() {
  if (!profile.value?.id) {
    snack.text = 'Chưa tải được hồ sơ — hãy đợi nạp xong'
    snack.color = 'warning'
    snack.show = true

    return
  }

  const topic = sandboxForm.topic.trim()
  if (!topic) {
    snack.text = 'Vui lòng nhập chủ đề muốn viết thử nghiệm'
    snack.color = 'warning'
    snack.show = true

    return
  }

  try {
    await store.fetchOptions()
    const targetTier = sandboxForm.targetTier || profile.value.dominant_tier || 'medium'
    const steps = getPipelineStepsForTier(targetTier)
    const firstStep = steps[0]
    const brief = buildSandboxBrief(topic)
    const overrides = buildCurrentSandboxOverrides()

    const res = await store.runSandboxStep(profile.value.id, firstStep.step, {
      topic,
      brief,
      target_tier: targetTier,
      artifacts: {},
      ...overrides,
    })

    const artifact = res?.artifact || {}
    const isTerminal = Boolean(firstStep.is_terminal) || steps.length === 1

    const newSample = normalizeSandboxSample({
      id: `local-${Date.now()}`,
      topic,
      brief,
      target_tier: targetTier,
      steps,
      current_step_index: 0,
      status: isTerminal ? 'completed' : 'in_progress',
      artifacts: {
        [firstStep.step]: artifact,
      },
      text: isTerminal ? (artifact.text || '') : '',
      // artifact.snapshot?.system_prompt (writer) | artifact.system_prompt (planner) —
      // cả 2 step đều trả snapshot.system_prompt để bản thử bước dàn ý cũng thấy Ô 3.
      system_prompt: artifact.system_prompt || artifact.snapshot?.system_prompt || '',
      saved_to_db: false,
      created_at: new Date().toISOString(),
    })

    // Đưa bản ghi mới lên đầu danh sách trong VCard "Các bài văn thử nghiệm" bên dưới
    savedSandboxSamples.value.unshift(newSample)
    expandedSampleSteps[newSample.id] = true

    if (isTerminal) {
      await persistSampleToDb(newSample, true)

      snack.text = `Đã hoàn thành bài viết thử nghiệm ở cấp độ "${targetTier.toUpperCase()}"`
      snack.color = 'success'
    } else {
      // Lưu ngay cả khi mới xong Bước 1 (đang in_progress) để F5 không bị mất tiến trình
      await persistSampleToDb(newSample, true)

      snack.text = `Đã tạo và lưu bản ghi ở Bước 1/${steps.length} (${cleanStepLabel(firstStep)}). Bấm nút tiếp tục trên bản ghi để qua bước kế tiếp!`
      snack.color = 'info'
    }
    snack.show = true
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Gửi bước kế tiếp cùng artifact và xác nhận duyệt dàn ý
 * =====================================================================
 *
 * Chi tiết triển khai:
 * =====================================================================
 * CHỨC NĂNG: Chạy bước tiếp theo cho 1 bản ghi đang ở trạng thái in_progress
 * =====================================================================
 * Dựa theo mảng `sample.steps` từ config có bao nhiêu bước phân tích thì
 * chạy tuần tự từng bước (`current_step_index + 1`) cho tới bước viết văn
 * cuối cùng (`is_terminal = true`) thì chuyển trạng thái sang "Hoàn thành".
 * =====================================================================
 *
 * INPUT:
 * - sample
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi API, cập nhật tiến trình/text/snapshot và lưu sample. Backend kiểm tra fingerprint V3.
 *
 * EXCEPTION:
 * - Bắt lỗi API và giải phóng runningSampleStepId trong finally.
 * =====================================================================
 */
async function runNextSandboxStep(sample) {
  if (!profile.value?.id || !sample) return

  const nextIdx = sample.current_step_index + 1
  if (nextIdx >= sample.steps.length) return

  const nextStep = sample.steps[nextIdx]
  const overrides = buildCurrentSandboxOverrides()

  runningSampleStepId.value = sample.id
  try {
    const res = await store.runSandboxStep(profile.value.id, nextStep.step, {
      topic: sample.topic,
      brief: sample.brief,
      target_tier: sample.target_tier,
      artifacts: sample.artifacts || {},
      outline_approved: Boolean(sample.artifacts?.topic_outline),
      ...overrides,
    })

    const artifact = res?.artifact || {}
    if (!sample.artifacts || typeof sample.artifacts !== 'object') {
      sample.artifacts = {}
    }
    sample.artifacts[nextStep.step] = artifact
    sample.current_step_index = nextIdx

    const isLastStep = Boolean(nextStep.is_terminal) || nextIdx >= sample.steps.length - 1
    if (isLastStep) {
      sample.status = 'completed'
      sample.text = artifact.text || ''
      // artifact.snapshot?.system_prompt (writer) | artifact.system_prompt (planner/intermediate).
      sample.system_prompt = artifact.system_prompt || artifact.snapshot?.system_prompt || ''

      // Lưu bản hoàn thành vào DB
      await persistSampleToDb(sample, true)

      snack.text = `Hoàn thành toàn bộ ${sample.steps.length}/${sample.steps.length} bước — Đã sinh bài văn hoàn chỉnh!`
      snack.color = 'success'
    } else {
      // Cập nhật tiến trình bước trung gian vào DB
      await persistSampleToDb(sample, true)

      snack.text = `Đã hoàn tất Bước ${nextIdx + 1}/${sample.steps.length} (${cleanStepLabel(nextStep)}). Hãy bấm tiếp tục để sang bước kế tiếp!`
      snack.color = 'info'
    }
    snack.show = true
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  } finally {
    runningSampleStepId.value = null
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Lập lại dàn ý của bản thử với brief đã lưu và style hiện tại
 * =====================================================================
 *
 * INPUT:
 * - sample
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi topic_outline, thay artifacts của sample, lưu DB và cập nhật trạng thái loading/snackbar.
 *
 * EXCEPTION:
 * - Bắt lỗi API qua firstErrorMessage và giải phóng loading trong finally.
 * =====================================================================
 */
async function refreshSandboxOutline(sample) {
  if (!profile.value?.id || runningSampleStepId.value) return

  runningSampleStepId.value = sample.id
  try {
    const res = await store.runSandboxStep(profile.value.id, 'topic_outline', {
      topic: sample.topic,
      target_tier: sample.target_tier,
      brief: sample.brief,
      ...buildCurrentSandboxOverrides(),
    })

    sample.artifacts = { topic_outline: res.artifact }
    sample.current_step_index = 0
    await persistSampleToDb(sample)
  } catch (error) {
    snack.text = firstErrorMessage(error)
    snack.color = 'error'
    snack.show = true
  } finally {
    runningSampleStepId.value = null
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Upsert sample đang dở hoặc hoàn chỉnh vào hồ sơ
 * =====================================================================
 *
 * Chi tiết triển khai:
 * =====================================================================
 * CHỨC NĂNG: Lưu / Cập nhật (Upsert) bản viết thử vào Database của hồ sơ
 * =====================================================================
 * Lưu cả trạng thái đang chạy dở (Bước 1 in_progress + artifacts) lẫn
 * bản đã hoàn thành (completed + text) để khi F5/tải lại trang vẫn tiếp tục
 * phân tích bước 2 được bình thường.
 * =====================================================================
 *
 * INPUT:
 * - sample, silent = false
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi saveSandboxSample, cập nhật ID và cờ lưu; gửi cả brief/steps/artifacts.
 *
 * EXCEPTION:
 * - Bắt lỗi; chỉ hiển thị snackbar khi silent=false. Luôn giải phóng cờ đang lưu.
 * =====================================================================
 */
async function persistSampleToDb(sample, silent = false) {
  if (!profile.value?.id || !sample) return

  savingSampleId.value = sample.id
  savingSandboxSample.value = true
  try {
    const res = await store.saveSandboxSample(profile.value.id, {
      id: sample.saved_to_db ? sample.id : undefined,
      topic: sample.topic.trim() || 'Chủ đề thử nghiệm',
      brief: sample.brief,
      target_tier: sample.target_tier || profile.value.dominant_tier || 'medium',
      status: sample.status || 'in_progress',
      current_step_index: sample.current_step_index ?? 0,
      steps: sample.steps || [],
      artifacts: sample.artifacts || {},
      text: sample.text || '',
      system_prompt: sample.system_prompt || '',
    })

    if (res?.sample?.id) {
      const oldId = sample.id

      sample.id = res.sample.id
      sample.saved_to_db = true
      if (expandedSampleSteps[oldId]) {
        expandedSampleSteps[sample.id] = true
      }
    } else {
      sample.saved_to_db = true
    }

    if (!silent) {
      snack.text = 'Đã lưu bản viết thử vào hồ sơ để so sánh'
      snack.color = 'success'
      snack.show = true
    }
  } catch (e) {
    if (!silent) {
      snack.text = firstErrorMessage(e)
      snack.color = 'error'
      snack.show = true
    }
  } finally {
    savingSampleId.value = null
    savingSandboxSample.value = false
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Lưu bản thử với thông báo lỗi cho thao tác lưu thủ công
 * =====================================================================
 *
 * INPUT:
 * - sample
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi persistSampleToDb(sample, false) để upsert backend.
 *
 * EXCEPTION:
 * - Lỗi được persistSampleToDb bắt và hiển thị snackbar.
 * =====================================================================
 */
async function saveCurrentSandboxSample(sample) {
  await persistSampleToDb(sample, false)
}

/**
 * =====================================================================
 * CHỨC NĂNG: Xóa 1 bản viết thử (khỏi danh sách tạm hoặc khỏi Database)
 * =====================================================================
 *
 * Chi tiết triển khai:
 * =====================================================================
 * CHỨC NĂNG: Xóa 1 bản viết thử (khỏi danh sách tạm hoặc khỏi Database)
 * =====================================================================
 *
 * INPUT:
 * - sampleId
 *
 * OUTPUT:
 * - Kết quả hiển thị hoặc cập nhật state theo mô tả chi tiết.
 *
 * SIDE EFFECT:
 * - Xử lý state hoặc dữ liệu của component theo luồng đang gọi.
 *
 * EXCEPTION:
 * - Các lỗi API được xử lý ở hàm nghiệp vụ gọi helper này.
 * =====================================================================
 */
async function removeSandboxSample(sampleId) {
  if (!profile.value?.id || !sampleId) return

  const target = savedSandboxSamples.value.find(s => s.id === sampleId)
  if (target && (!target.saved_to_db || String(sampleId).startsWith('local-'))) {
    savedSandboxSamples.value = savedSandboxSamples.value.filter(s => s.id !== sampleId)
    snack.text = 'Đã xóa bản thử nghiệm'
    snack.color = 'info'
    snack.show = true

    return
  }

  deletingSandboxSampleId.value = sampleId
  try {
    await store.deleteSandboxSample(profile.value.id, sampleId)
    savedSandboxSamples.value = savedSandboxSamples.value.filter(s => s.id !== sampleId)
    snack.text = 'Đã xóa bản viết thử'
    snack.color = 'info'
    snack.show = true
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  } finally {
    deletingSandboxSampleId.value = null
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Sao chép văn bản vào clipboard và thông báo kết quả
 * =====================================================================
 *
 * INPUT:
 * - text, successMessage = 'Đã sao chép'
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi navigator.clipboard.writeText; thay snackbar.
 *
 * EXCEPTION:
 * - Bắt lỗi clipboard và báo sao chép thủ công.
 * =====================================================================
 */
async function copyText(text, successMessage = 'Đã sao chép') {
  try {
    await navigator.clipboard.writeText(text)
    snack.text = successMessage
    snack.color = 'success'
  } catch {
    snack.text = 'Không sao chép được — chọn và copy thủ công'
    snack.color = 'error'
  }
  snack.show = true
}

/* ---------------------------------------------------------------------
 * 9 Tabs điều hướng (thêm Phòng thử DNA ở cuối)
 * ------------------------------------------------------------------- */
const profileTabs = [
  { value: 'overview', label: 'Tổng quan', icon: 'tabler-file-text' },
  { value: 'style-traits', label: 'Đặc điểm văn phong', icon: 'tabler-adjustments-horizontal' },
  { value: 'content-structure', label: 'Cấu trúc nội dung', icon: 'tabler-layout-grid' },
  { value: 'language-rules', label: 'Quy tắc ngôn ngữ', icon: 'tabler-message-2' },
  { value: 'content-samples', label: 'Mẫu nội dung', icon: 'tabler-files' },
  { value: 'vocab-cta', label: 'Từ vựng & CTA', icon: 'tabler-heart' },
  { value: 'advanced', label: 'Cài đặt nâng cao', icon: 'tabler-settings' },
  { value: 'history', label: 'Lịch sử', icon: 'tabler-history' },
  { value: 'sandbox', label: 'Phòng thử DNA', icon: 'tabler-flask' },
]

/* ===================================================================
 * PARSE/MERGE RULES_JSON — map 2 chiều giữa UI và rules_json
 * =================================================================== */

/* Trích nhanh các từ in nháy trong vocabulary thành powerWords.
 * rules_json.vocabulary thường dạng: '...: "từ1", "từ2"...' */
/**
 * =====================================================================
 * CHỨC NĂNG: Lấy các cụm trong dấu ngoặc kép để tạo danh sách từ ưu tiên
 * =====================================================================
 *
 * INPUT:
 * - text
 *
 * OUTPUT:
 * - string[] cụm duy nhất không quá 40 ký tự
 *
 * SIDE EFFECT:
 * - Chỉ đọc chuỗi và tạo mảng mới.
 *
 * EXCEPTION:
 * - Không ném lỗi chủ động; đầu vào được chuyển qua String().
 * =====================================================================
 */
function extractQuotedWords(text) {
  const out = []
  const re = /["“”«»]([^"“”«»]+)["“”«»]/g
  let m
  while ((m = re.exec(String(text ?? ''))) !== null) {
    const w = m[1].trim()
    if (w && w.length <= 40 && !out.includes(w)) out.push(w)
  }

  return out
}

/* Tách pronouns thành "xưng của brand" và "gọi khách" theo các dấu phân
 * tách phổ biến trong mô tả AI ("xưng X, gọi Y", "X xưng A, gọi B"...). */
/**
 * =====================================================================
 * CHỨC NĂNG: Tách mô tả xưng hô thành cách xưng của thương hiệu và cách gọi khách
 * =====================================================================
 *
 * INPUT:
 * - text
 *
 * OUTPUT:
 * - Object brand/customer; ưu tiên tách ở từ gọi, fallback dấu phân cách
 *
 * SIDE EFFECT:
 * - Chỉ xử lý chuỗi; cách tách là heuristic để điền form.
 *
 * EXCEPTION:
 * - Không gọi API hoặc kiểm chứng ngữ nghĩa.
 * =====================================================================
 */
function splitPronouns(text) {
  const t = String(text ?? '').trim()
  if (!t) return { brand: '', customer: '' }

  // Ưu tiên tách bằng "gọi" — nửa sau là customer.
  const idx = t.toLowerCase().search(/gọi\s/)
  if (idx > 0) {
    return {
      brand: t.slice(0, idx).replace(/^[,:.\s]+|[,:.\s]+$/g, ''),
      customer: t.slice(idx).replace(/^[gG]ọi\s+/, '').replace(/^[,:.\s]+|[,:.\s]+$/g, ''),
    }
  }

  // Fallback tách theo dấu phẩy đầu — trước dấu phẩy = brand, sau = customer.
  const parts = t.split(/[,;|]/).map(s => s.trim()).filter(Boolean)
  if (parts.length >= 2) return { brand: parts[0], customer: parts.slice(1).join(', ') }

  return { brand: t, customer: '' }
}

/* Ghép lại pronouns từ 2 ô nhập — giữ cấu trúc "xưng X, gọi Y". */
/**
 * =====================================================================
 * CHỨC NĂNG: Ghép hai ô xưng hô thành mô tả để lưu rules_json.pronouns
 * =====================================================================
 *
 * INPUT:
 * - brand, customer
 *
 * OUTPUT:
 * - string mô tả; ghép bằng dấu phẩy và từ gọi khi đủ hai vế
 *
 * SIDE EFFECT:
 * - Chỉ xử lý chuỗi.
 *
 * EXCEPTION:
 * - Không ném lỗi chủ động.
 * =====================================================================
 */
function joinPronouns(brand, customer) {
  const b = String(brand ?? '').trim()
  const c = String(customer ?? '').trim()
  if (b && c) return `${b}, gọi ${c}`

  return b || c
}

/* Convert rules_json.avoid text -> mảng {text, allowed:false}.
 * Trong mẫu, avoid thường là câu dài liệt kê — tách bằng dấu chấm câu. */
/**
 * =====================================================================
 * CHỨC NĂNG: Tách mô tả điều tránh thành các quy tắc cấm cho form
 * =====================================================================
 *
 * INPUT:
 * - text
 *
 * OUTPUT:
 * - Object[] text/allowed=false, bỏ đoạn ngắn hơn bốn ký tự
 *
 * SIDE EFFECT:
 * - Tạo mảng từ dấu chấm và xuống dòng; không sửa DNA gốc.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function avoidToRules(text) {
  const items = String(text ?? '')
    .split(/[.\n]+/)
    .map(s => s.trim())
    .filter(s => s.length >= 4)

  return items.map(s => ({ text: s, allowed: false }))
}

/**
 * =====================================================================
 * CHỨC NĂNG: Chuyển cụm từ trong ngoặc kép thành quy tắc từ ngữ được khuyến khích
 * =====================================================================
 *
 * INPUT:
 * - text
 *
 * OUTPUT:
 * - Object[] text/allowed=true hoặc mảng rỗng
 *
 * SIDE EFFECT:
 * - Dùng extractQuotedWords, chỉ tạo dữ liệu trong bộ nhớ.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function vocabToRules(text) {
  const words = extractQuotedWords(text)

  return words.length
    ? words.map(w => ({ text: `Nên dùng "${w}"`, allowed: true }))
    : []
}

/* ===================================================================
 * APPLY PROFILE -> FORM
 * =================================================================== */
/**
 * =====================================================================
 * CHỨC NĂNG: Nạp hồ sơ vào form, baseline slider, presets và danh sách sample
 * =====================================================================
 *
 * INPUT:
 * - p
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Thay state trang và initialRuleEdits; khôi phục baseline đã lưu để tránh cộng preset lần hai.
 *
 * EXCEPTION:
 * - Caller truyền resource profile từ API.
 * =====================================================================
 */
function applyProfileToForm(p) {
  profile.value = p

  profileData.name = p.name ?? ''
  profileData.version = `v${p.version ?? 1}`
  profileData.isActive = Boolean(p.is_enabled)
  profileData.status = p.status ?? 'draft'
  profileData.shortDesc = p.description ?? ''
  profileData.slug = p.slug ?? ''
  slugDirty.value = false

  advancedConfig.systemPrompt = p.style_instructions ?? ''
  advancedConfig.voiceTexturePrompt = p.voice_texture_instructions ?? ''
  advancedConfig.tonePrompt = p.tone_instructions ?? ''
  advancedConfig.preferredModel = p.analysis_metadata_json?.ai_model_used ?? 'Chưa rõ (tạo tay)'
  advancedConfig.temperature = typeof p.temperature === 'number' ? p.temperature : 0.7

  const savedVoiceSliders = p.voice_texture_sliders_json && typeof p.voice_texture_sliders_json === 'object'
    ? p.voice_texture_sliders_json
    : {}

  const preferences = p.slider_preferences_json ?? {}

  voiceTextureSliders.forEach(slider => {
    const value = Object.hasOwn(savedVoiceSliders, slider.key) ? savedVoiceSliders[slider.key] : slider.defaultVal

    slider.value = value
    slider.baseline = Object.hasOwn(preferences.voice ?? {}, slider.key) ? preferences.voice[slider.key] : value
  })

  selectedVoiceOptionIds.value = [...(preferences.voice_presets ?? [])]
  selectedToneOptionIds.value = [...(preferences.tone_presets ?? [])]

  // Nạp 8 thanh trượt Sandbox sắc thái cảm xúc từ DB (tone_sliders_json)
  const savedToneSliders = p.tone_sliders_json && typeof p.tone_sliders_json === 'object'
    ? p.tone_sliders_json
    : {}

  toneSliders.forEach(slider => {
    const value = Object.hasOwn(savedToneSliders, slider.key) ? savedToneSliders[slider.key] : 50

    slider.value = value
    slider.baseline = Object.hasOwn(preferences.tone ?? {}, slider.key) ? preferences.tone[slider.key] : value
  })

  const rules = p.rules_json && typeof p.rules_json === 'object' ? p.rules_json : {}

  // traitSummaryList — 4 ô tóm tắt từ rules (icon/color chọn sẵn).
  traitSummaryList.value = [
    { title: 'Giọng điệu', value: rules.tone || '—', icon: 'tabler-microphone', color: 'warning' },
    { title: 'Cảm xúc', value: rules.emotion || '—', icon: 'tabler-heart', color: 'error' },
    { title: 'Xưng hô', value: rules.pronouns || '—', icon: 'tabler-users', color: 'success' },
    { title: 'Vốn từ', value: rules.vocabulary || '—', icon: 'tabler-book', color: 'info' },
    { title: 'Cách mở bài', value: rules.opening || '—', icon: 'tabler-arrow-bar-to-right', color: 'primary' },
  ]

  // Cấu trúc nội dung — 4 phần map 1:1 4 khóa rule_keys (mỗi khóa 1 ô
  // riêng; trước đây gộp structure_patterns+transitions bằng ' • ' rồi
  // tách ngược lúc lưu — hỏng dữ liệu, xem docs/writing-profile-review-findings.md L4).
  const tierVal = p.dominant_tier
  const isMicro = tierVal === 'micro'
  const isShort = tierVal === 'short'

  structureSections.value = [
    {
      title: 'Cách mở bài (Opening)',
      weight: 'rules_json.opening',
      sentences: '',
      guideline: 'Cách AI nhận diện thủ pháp mở đầu trong bài mẫu.',
      sample: rules.opening || '',
      placeholder: isMicro ? 'Không áp dụng cho văn bản cực ngắn (Micro 1–3 câu — ý liền một mạch).' : 'Chưa có dữ liệu',
    },
    {
      title: 'Khung cấu trúc bài (Structure)',
      weight: 'rules_json.structure_patterns',
      sentences: '',
      guideline: 'Mô hình khung bài tổng thể (Hook → Vấn đề → Giải pháp → CTA, listicle, tuyến tính...).',
      sample: rules.structure_patterns || '',
      placeholder: isMicro || isShort ? 'Không áp dụng cho văn bản ngắn (Micro/Short — không chia dàn ý nhiều phần).' : 'Chưa có dữ liệu',
    },
    {
      title: 'Nghệ thuật chuyển đoạn (Transitions)',
      weight: 'rules_json.transitions',
      sentences: '',
      guideline: 'Cách chuyển ý giữa các phần — điểm neo cảm xúc, cách vào ý mới.',
      sample: rules.transitions || '',
      placeholder: isMicro ? 'Không áp dụng cho văn bản cực ngắn (Micro 1–3 câu — ý liền một mạch).' : 'Chưa có dữ liệu',
    },
    {
      title: 'Kết bài & CTA (Ending)',
      weight: 'rules_json.ending',
      sentences: '',
      guideline: 'Cách khép lại bài viết và phong cách kêu gọi hành động.',
      sample: rules.ending || '',
      placeholder: 'Chưa có dữ liệu',
    },
  ]

  // Quy tắc ngôn ngữ
  const pr = splitPronouns(rules.pronouns)

  languageConfig.brandPronoun = pr.brand
  languageConfig.customerPronoun = pr.customer
  languageConfig.editorialRules = [
    ...vocabToRules(rules.vocabulary),
    ...avoidToRules(rules.avoid),
  ]

  // Mẫu nội dung từ evidence_json — lọc bỏ 'uncertainties' (vì đây là điểm thiếu dữ liệu, không phải phong cách viết)
  const evs = (Array.isArray(p.evidence_json) ? p.evidence_json : [])
    .filter(ev => ev.rule_key !== 'uncertainties')

  fullPostSamples.value = evs.map(ev => ({
    channel: ev.rule_key || 'unknown',
    date: '',
    title: ev.note ? ev.note : `Bằng chứng cho ${ruleLabel(ev.rule_key)}`,
    content: ev.excerpt || '',
  }))

  // Từ vựng & CTA
  let vocabDesc = String(rules.vocabulary ?? '').trim()
  const kwMatch = vocabDesc.search(/[;.]\s*Từ ngữ ưu tiên:/i)
  if (kwMatch > 0) {
    vocabDesc = vocabDesc.substring(0, kwMatch).trim()
  }
  vocabConfig.vocabularyDesc = vocabDesc
  vocabConfig.powerWords = extractQuotedWords(rules.vocabulary)
  vocabConfig.negativeWords = avoidToRules(rules.avoid).map(r => r.text)
  vocabConfig.ctaPhilosophy = rules.ending || ''
  initialRuleEdits = readEditableRules()

  // Taxonomy
  categories.value = (p.categories ?? []).map(c => c.id)
  tags.value = (p.tags ?? []).map(t => t.id)
  newTags.value = []

  // Danh sách bản viết thử đã lưu trong Phòng thử DNA (chuẩn hóa cấu trúc step-by-step)
  savedSandboxSamples.value = Array.isArray(p.sandbox_samples_json)
    ? p.sandbox_samples_json.map(item => normalizeSandboxSample({ ...item, saved_to_db: true }))
    : []

  // Lịch sử — mỗi lần giải mã 1 dòng + entry hiện tại trên đầu.
  const analyses = Array.isArray(p.analyses) ? p.analyses : []

  versionHistory.value = [
    {
      version: `v${p.version ?? 1}`,
      summary: 'Phiên bản hiện tại',
      date: p.updated_at ? new Date(p.updated_at).toLocaleString('vi-VN') : '',
      author: '',
      current: true,
    },
    ...analyses.map(a => ({
      version: `analysis`,
      summary: `Giải mã ${a.status === 'succeeded' ? 'thành công' : a.status}`,
      date: a.created_at ? new Date(a.created_at).toLocaleString('vi-VN') : '',
      author: a.ai_run_id ? `ai_run:${a.ai_run_id.slice(0, 8)}` : '',
      current: false,
    })),
  ]
}

/* ===================================================================
 * BUILD PAYLOAD PUT — merge các khóa rules_json user chỉnh trên UI,
 * giữ nguyên 9 khóa còn lại từ profile.rules_json hiện tại.
 * =================================================================== */
/**
 * =====================================================================
 * CHỨC NĂNG: Đọc các ô chỉnh DNA thành một mảng khóa thống nhất để so với bản ban đầu
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - Object chứa cấu trúc, xưng hô, vốn từ, điều tránh và CTA
 *
 * SIDE EFFECT:
 * - Đọc form, không ghi profile.
 *
 * EXCEPTION:
 * - Caller chuẩn bị các danh sách form trước khi gọi.
 * =====================================================================
 */
function readEditableRules() {
  const keys = ['opening', 'structure_patterns', 'transitions', 'ending']
  const sections = Object.fromEntries(keys.map((key, index) => [key, String(structureSections.value[index]?.sample ?? '').trim()]))
  const description = (vocabConfig.vocabularyDesc ?? '').trim()
  const powerWords = vocabConfig.powerWords.map(word => JSON.stringify(word.trim())).join(', ')

  return {
    ...sections,
    pronouns: joinPronouns(languageConfig.brandPronoun, languageConfig.customerPronoun),
    vocabulary: [description, powerWords ? 'Từ ngữ ưu tiên: ' + powerWords : ''].filter(Boolean).join('. ').slice(0, 500),
    avoid: vocabConfig.negativeWords.join('. '),
    cta: vocabConfig.ctaPhilosophy?.trim() || '',
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Tạo rules_json từ các ô đã sửa, giữ nguyên nội dung chưa đổi
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - Object DNA dùng khi lưu profile
 *
 * SIDE EFFECT:
 * - Đọc profile/form; không gọi API.
 *
 * EXCEPTION:
 * - Các lỗi validation độ dài/kiểu dữ liệu được backend trả khi lưu.
 * =====================================================================
 */
function buildRulesJsonPayload() {
  return mergeRuleEdits(profile.value?.rules_json, initialRuleEdits, readEditableRules())
}

/**
 * =====================================================================
 * CHỨC NĂNG: Chuẩn bị bằng chứng trích dẫn cho payload lưu hồ sơ
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - array bằng chứng theo cấu trúc rule_key/excerpt/note
 *
 * SIDE EFFECT:
 * - Trang create đổi tên khóa từ form; trang edit giữ evidence_json đã tải vì chưa có editor bằng chứng.
 *
 * EXCEPTION:
 * - Không xác minh lại trích dẫn tại frontend; validation thuộc backend.
 * =====================================================================
 */
function buildEvidenceJsonPayload() {
  // Giữ nguyên evidence_json hiện tại — tab Mẫu nội dung chỉ hiển thị,
  // không cho chỉnh sửa (chưa thiết kế edit từng excerpt).
  return Array.isArray(profile.value?.evidence_json) ? profile.value.evidence_json : []
}

/**
 * =====================================================================
 * CHỨC NĂNG: Lưu form hồ sơ, DNA và baseline/preset slider qua PUT
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Cập nhật backend rồi nạp response vào form; thay loading/snackbar.
 *
 * EXCEPTION:
 * - Kiểm tra tên trước khi gọi, bắt lỗi API và giải phóng saving trong finally.
 * =====================================================================
 */
async function saveProfile() {
  const name = profileData.name.trim()
  if (!name) {
    snack.text = 'Vui lòng nhập tên Writing Profile'
    snack.color = 'warning'
    snack.show = true

    return
  }

  const voiceSlidersPayload = Object.fromEntries(
    voiceTextureSliders.map(s => [s.key, s.value ?? null]),
  )

  const toneSlidersPayload = Object.fromEntries(
    toneSliders.map(t => [t.key, t.value ?? null]),
  )

  const payload = {
    name,
    description: profileData.shortDesc?.trim() || null,
    dominant_tier: profile.value?.dominant_tier || 'medium',
    rules_json: buildRulesJsonPayload(),
    evidence_json: buildEvidenceJsonPayload(),
    style_instructions: advancedConfig.systemPrompt?.trim() || null,
    voice_texture_instructions: advancedConfig.voiceTexturePrompt?.trim() || null,
    tone_instructions: advancedConfig.tonePrompt?.trim() || null,
    voice_texture_sliders_json: voiceSlidersPayload,
    tone_sliders_json: toneSlidersPayload,
    slider_preferences_json: {
      voice: Object.fromEntries(voiceTextureSliders.map(s => [s.key, s.baseline])),
      tone: Object.fromEntries(toneSliders.map(s => [s.key, s.baseline])),
      voice_presets: [...selectedVoiceOptionIds.value],
      tone_presets: [...selectedToneOptionIds.value],
    },
    temperature: Number(advancedConfig.temperature),
    status: profileData.isActive ? 'active' : 'draft',
    is_enabled: Boolean(profileData.isActive),

    // Taxonomy — TaxonomyField v-model đã chuẩn hóa: categories = id[],
    // tags = (id|name)[]; new_tags = string[] do user gõ mới.
    categories: categories.value,
    tags: tags.value.filter(t => typeof t !== 'string'),
    new_tags: [...tags.value.filter(t => typeof t === 'string'), ...newTags.value],
  }

  // Slug chỉ gửi khi user thực sự đổi (tránh auto-resync thừa).
  if (slugDirty.value && profileData.slug?.trim())
    payload.slug = profileData.slug.trim()

  saving.value = true
  try {
    const saved = await store.updateProfile(profile.value.id, payload)

    applyProfileToForm(saved)

    snack.text = `Đã lưu "${saved?.name ?? name}" — version ${saved?.version ?? ''}`
    snack.color = 'success'
    snack.show = true
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  } finally {
    saving.value = false
  }
}

/* Slug — blur ô name/slug gọi suggest, loại trừ chính id này. */
/**
 * =====================================================================
 * CHỨC NĂNG: Xin slug khả dụng theo nguồn nhập và ID hồ sơ hiện tại
 * =====================================================================
 *
 * INPUT:
 * - source
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi suggestSlug, cập nhật slug/available/takenBy nếu có response.
 *
 * EXCEPTION:
 * - Bắt lỗi và đặt trạng thái kiểm tra về null.
 * =====================================================================
 */
async function runSlugSuggest(source) {
  const src = String(source ?? '').trim()
  if (!src) return

  try {
    const res = await store.suggestSlug({
      source: src,
      sluggable_type: 'writing_profile',
      sluggable_id: profile.value?.id,
    })

    if (!res) return
    if (res?.slug) profileData.slug = res.slug
    slugAvailable.value = res?.available ?? null
    slugTakenBy.value = res?.taken_by ?? null
  } catch {
    slugAvailable.value = null
    slugTakenBy.value = null
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Gợi ý slug từ tên hồ sơ khi người dùng rời ô tên
 * =====================================================================
 *
 * INPUT:
 * - ) { runSlugSuggest(profileData.name
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Gọi bất đồng bộ runSlugSuggest để cập nhật trạng thái slug.
 *
 * EXCEPTION:
 * - Lỗi API do runSlugSuggest xử lý.
 * =====================================================================
 */
function onNameBlur() { runSlugSuggest(profileData.name) }
/**
 * =====================================================================
 * CHỨC NĂNG: Kiểm tra và gợi ý slug từ ô slug khi mất focus
 * =====================================================================
 *
 * INPUT:
 * - ) { runSlugSuggest(profileData.slug
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Gọi bất đồng bộ runSlugSuggest.
 *
 * EXCEPTION:
 * - Lỗi API do runSlugSuggest xử lý.
 * =====================================================================
 */
function onSlugBlur() { runSlugSuggest(profileData.slug) }

/* SlugField update → đánh dấu dirty để chỉ gửi khi đổi. */
/**
 * =====================================================================
 * CHỨC NĂNG: Ghi nhận slug người dùng tự sửa để chỉ gửi trường này khi thay đổi
 * =====================================================================
 *
 * INPUT:
 * - v
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Cập nhật profileData.slug và bật slugDirty.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function onSlugInput(v) {
  profileData.slug = v
  slugDirty.value = true
}

/* ===================================================================
 * TÍNH CHIỀU CAO VCARD BÊN PHẢI Ở TAB 7 ĐỂ VỪA KHÍT VIEWPORT (GIỮ FOOTER, KHÔNG CÓ SCROLL TRÌNH DUYỆT)
 * =================================================================== */
const advancedCardHeight = ref('calc(100vh - 435px)')
const sliderScrollHeight = ref('220px')

/**
 * =====================================================================
 * CHỨC NĂNG: Căn chiều cao panel nâng cao theo viewport và footer
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Bật/tắt class body, đo DOM sau nextTick, cập nhật chiều cao và vị trí cuộn.
 *
 * EXCEPTION:
 * - Bỏ qua khi không có window/document hoặc panel chưa render.
 * =====================================================================
 */
function updateAdvancedCardHeight() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return

  const isAdvanced = activeTab.value === 'advanced' && !loading.value

  document.body.classList.toggle('writing-profile-advanced-no-scroll', isAdvanced)

  if (!isAdvanced) return

  nextTick(() => {
    const el = document.querySelector('.advanced-prompt-window')
    if (!el) return

    if (window.scrollY > 0) {
      window.scrollTo({ top: 0, behavior: 'instant' })
    }

    const rect = el.getBoundingClientRect()
    if (rect.top > 0) {
      const footerEl = document.querySelector('.layout-footer')
      const footerHeight = footerEl ? footerEl.getBoundingClientRect().height : 54
      const pageContentEl = document.querySelector('.layout-page-content')
      const padBottom = pageContentEl ? (parseFloat(window.getComputedStyle(pageContentEl).paddingBottom) || 0) : 16

      // Trừ chiều cao của phần trên (rect.top) + khoảng đệm + chiều cao footer để giữ nguyên dòng "© 2026 Made With By Pixinvent" mà không sinh thanh cuộn trình duyệt
      const available = Math.max(340, Math.floor(window.innerHeight - rect.top - footerHeight - padBottom - 4))

      advancedCardHeight.value = `${available}px`

      // Tính chiều cao cố định cho vùng cuộn thanh trượt (.slider-scroll-box) để khi đóng/mở VExpansionPanel có hiệu ứng trượt mượt và thanh cuộn không bị giật biến mất
      nextTick(() => {
        const activeCard = el.querySelector('.v-window-item--active .advanced-prompt-card') || el.querySelector('.advanced-prompt-card')
        if (!activeCard) return

        const cardItemEl = activeCard.querySelector('.v-card-item')
        const topPanelEl = activeCard.querySelector('.advanced-prompt-right-col > .tonal-panel')
        const titleEl = activeCard.querySelector('.advanced-slider-panels .v-expansion-panel-title')

        const cardItemH = cardItemEl ? cardItemEl.getBoundingClientRect().height : 68
        const topPanelH = topPanelEl ? topPanelEl.getBoundingClientRect().height + 12 : 155
        const titleH = titleEl ? titleEl.getBoundingClientRect().height : 56

        // 15px cách đáy card + 48px (padding wrapper + dòng chú thích phía trên danh sách slider)
        const scrollH = Math.max(120, Math.floor(available - cardItemH - 15 - topPanelH - titleH - 48))

        sliderScrollHeight.value = `${scrollH}px`
      })
    }
  })
}

watch([activeTab, activeAdvancedPromptTab, loading, selectedVoiceOptionIds, selectedToneOptionIds], () => {
  updateAdvancedCardHeight()
}, { deep: true })

/* ===================================================================
 * LOAD
 * =================================================================== */
onMounted(async () => {
  window.addEventListener('resize', updateAdvancedCardHeight)
  loading.value = true
  loadError.value = ''
  try {
    const p = await store.fetchProfile(route.params.id)

    applyProfileToForm(p)

    // options (tiers/ruleKeys) cần cho chuẩn hóa key — nạp song song an toàn.
    store.fetchOptions().catch(() => {})
  } catch (e) {
    loadError.value = firstErrorMessage(e)
  } finally {
    loading.value = false
    updateAdvancedCardHeight()
  }
})

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', updateAdvancedCardHeight)
  }
  if (typeof document !== 'undefined') {
    document.body.classList.remove('writing-profile-advanced-no-scroll')
  }
})
</script>

<template>
  <div>
    <!-- ================= 1. HEADER TRANG ================= -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Chỉnh sửa Writing Profile
        </h4>
        <div class="text-body-1">
          Cập nhật thông tin, đặc điểm và quy tắc văn phong cho thương hiệu của bạn
        </div>
      </div>

      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-arrow-left"
          @click="router.push({ name: 'admin-writing-style-writing-profiles-list' })"
        >
          Quay lại
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-check"
          :loading="saving"
          :disabled="saving || loading || !profile"
          @click="saveProfile"
        >
          Lưu thay đổi
        </VBtn>
      </div>
    </div>

    <!-- Error loading -->
    <VAlert
      v-if="loadError"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ loadError }}
      <template #append>
        <VBtn
          size="small"
          variant="text"
          @click="router.push({ name: 'admin-writing-style-writing-profiles-list' })"
        >
          Về danh sách
        </VBtn>
      </template>
    </VAlert>

    <!-- Loading (skeleton giống trang list: VSkeletonLoader thay spinner) -->
    <template v-else-if="loading">
      <!-- Khung Banner + thanh tabs -->
      <VCard class="mb-6">
        <VCardText>
          <div class="d-flex align-center gap-x-4">
            <VSkeletonLoader
              type="avatar"
              class="bg-transparent flex-shrink-0"
              style="width: 72px; height: 72px;"
            />
            <div class="flex-grow-1">
              <VSkeletonLoader
                type="heading"
                class="bg-transparent mb-2"
                style="max-width: 340px;"
              />
              <VSkeletonLoader
                type="text"
                class="bg-transparent"
                style="max-width: 520px;"
              />
            </div>
            <VSkeletonLoader
              type="chip"
              class="bg-transparent"
              style="width: 90px;"
            />
          </div>
        </VCardText>
        <VDivider />
        <div class="d-flex gap-3 flex-wrap px-4 py-3">
          <VSkeletonLoader
            v-for="n in 6"
            :key="`edit-tab-skel-${n}`"
            type="chip"
            class="bg-transparent"
            style="width: 118px;"
          />
        </div>
      </VCard>

      <!-- Khung nội dung tab: 2 card giống lưới form -->
      <VRow>
        <VCol
          v-for="n in 2"
          :key="`edit-card-skel-${n}`"
          cols="12"
          lg="6"
        >
          <VCard class="mb-6">
            <VCardText>
              <VSkeletonLoader
                type="heading"
                class="bg-transparent mb-4"
                style="max-width: 260px;"
              />
              <VSkeletonLoader
                type="paragraph"
                class="bg-transparent mb-3"
              />
              <VSkeletonLoader
                type="paragraph"
                class="bg-transparent"
              />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <template v-else>
      <!-- ================= 2. BANNER HỒ SƠ + THANH TABS ================= -->
      <VCard class="mb-6">
        <VCardText>
          <VRow class="align-center">
            <VCol
              cols="12"
              class="d-flex align-center gap-x-4"
            >
              <VAvatar
                size="72"
                rounded
                variant="tonal"
                color="primary"
              >
                <VIcon
                  icon="tabler-dna"
                  size="40"
                />
              </VAvatar>

              <div class="flex-grow-1">
                <div class="d-flex align-center flex-wrap gap-x-2 mb-1">
                  <h5 class="text-h5 font-weight-medium mb-0">
                    {{ profileData.name }}
                  </h5>
                  <VChip
                    size="small"
                    color="primary"
                    variant="tonal"
                    label
                  >
                    {{ profileData.version }}
                  </VChip>
                  <VChip
                    size="small"
                    :color="profileData.isActive ? 'success' : 'secondary'"
                    variant="tonal"
                    label
                  >
                    {{ profileData.isActive ? 'Đang sử dụng' : 'Nháp / Lưu trữ' }}
                  </VChip>
                  <VChip
                    size="small"
                    :color="dominantTierMeta.color"
                    variant="tonal"
                    label
                  >
                    <VIcon
                      :icon="dominantTierMeta.icon"
                      size="14"
                      class="me-1"
                    />
                    {{ dominantTierMeta.label }}
                  </VChip>
                  <VSwitch
                    v-model="profileData.isActive"
                    color="primary"
                    density="compact"
                    hide-details
                    inset
                    class="ms-1"
                  />
                </div>
                <div class="text-body-2 text-medium-emphasis">
                  {{ profileData.shortDesc || 'Chưa có mô tả' }}
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VTabs
          v-model="activeTab"
          show-arrows
          class="px-4"
        >
          <VTab
            v-for="tab in profileTabs"
            :key="tab.value"
            :value="tab.value"
          >
            <VIcon
              :icon="tab.icon"
              size="18"
              class="me-2"
            />
            {{ tab.label }}
          </VTab>
        </VTabs>
      </VCard>

      <!-- ================= 3. NỘI DUNG 8 TABS ================= -->
      <VWindow
        v-model="activeTab"
        class="profile-tab-window"
      >
        <!-- TAB 1: TỔNG QUAN -->
        <VWindowItem value="overview">
          <VRow>
            <VCol
              cols="12"
              lg="7"
            >
              <VCard
                title="Thông tin cơ bản của Writing Profile"
                subtitle="Thiết lập tên gọi, slug, phân loại và mô tả"
              >
                <VCardText>
                  <VRow>
                    <VCol
                      cols="12"
                      sm="8"
                    >
                      <AppTextField
                        v-model="profileData.name"
                        label="Tên Writing Profile *"
                        @blur="onNameBlur"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      sm="4"
                    >
                      <AppTextField
                        :model-value="profileData.version"
                        label="Phiên bản"
                        readonly
                        disabled
                      />
                    </VCol>
                    <VCol cols="12">
                      <SlugField
                        v-model="profileData.slug"
                        label="Slug định danh"
                        placeholder="vd: giong-chuyen-gia-ky-thuat"
                        hint="Blur ô tên hoặc ô slug để gợi ý slug khả dụng"
                        persistent-hint
                        :suggesting="store.slugSuggesting"
                        :available="slugAvailable"
                        :taken-by="slugTakenBy"
                        sluggable-type="writing_profile"
                        prefix="localhost:8000/"
                        @update:model-value="onSlugInput"
                        @request-suggest="onSlugBlur"
                      />
                    </VCol>
                    <VCol cols="12">
                      <AppTextarea
                        v-model="profileData.shortDesc"
                        label="Mô tả ngắn gọn"
                        rows="2"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <TaxonomyField
                        v-model="categories"
                        type="category"
                        scope="writing_profile"
                        label="Danh mục (Categories)"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <TaxonomyField
                        v-model="tags"
                        type="tag"
                        scope="writing_profile"
                        label="Thẻ phân loại (Tags)"
                        allow-create
                      />
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              lg="5"
            >
              <VCard
                title="Chân dung Độc giả & Giá trị cốt lõi"
                subtitle="(UI-only — chưa lưu DB, giữ nguyên để dùng sau)"
              >
                <VCardText>
                  <AppTextarea
                    v-model="overviewForm.targetAudience"
                    label="Độc giả mục tiêu (Target Persona)"
                    rows="3"
                    class="mb-4"
                    placeholder="Mô tả độc giả mục tiêu — hiện chưa lưu"
                  />
                  <VLabel class="mb-2 text-body-2">
                    Trụ cột cảm xúc thương hiệu
                  </VLabel>
                  <div
                    v-if="!overviewForm.brandPillars.length"
                    class="text-caption text-medium-emphasis"
                  >
                    Chưa cấu hình — sẽ lưu khi backend hỗ trợ.
                  </div>
                  <div class="d-flex flex-column gap-3">
                    <div
                      v-for="pillar in overviewForm.brandPillars"
                      :key="pillar.title"
                      class="pa-3 rounded border d-flex align-center"
                    >
                      <VAvatar
                        :color="pillar.color"
                        variant="tonal"
                        size="36"
                        rounded
                        class="me-3"
                      >
                        <VIcon
                          :icon="pillar.icon"
                          size="20"
                        />
                      </VAvatar>
                      <div>
                        <div class="text-body-2 font-weight-medium text-high-emphasis">
                          {{ pillar.title }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ pillar.desc }}
                        </div>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- TAB 2: ĐẶC ĐIỂM VĂN PHONG -->
        <VWindowItem value="style-traits">
          <VRow>
            <VCol
              cols="12"
              lg="6"
            >
              <VCard
                title="Tổng quan đặc điểm (từ rules_json)"
                subtitle="Các khóa DNA chính của văn phong"
                class="mb-6"
              >
                <VCardText>
                  <div class="d-flex flex-column gap-3">
                    <div
                      v-for="attr in traitSummaryList"
                      :key="attr.title"
                      class="d-flex align-start"
                    >
                      <VAvatar
                        :color="attr.color"
                        variant="tonal"
                        size="34"
                        rounded
                        class="me-3 flex-shrink-0"
                      >
                        <VIcon
                          :icon="attr.icon"
                          size="18"
                        />
                      </VAvatar>
                      <div>
                        <div class="text-body-2 font-weight-medium text-high-emphasis">
                          {{ attr.title }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ attr.value }}
                        </div>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>

              <!-- Radar UI-only: giữ nguyên không map -->
              <VCard
                title="Biểu đồ DNA (UI-only)"
                subtitle="Chưa có dữ liệu chấm điểm từng trục — giữ layout chờ backend"
                class="mb-6"
              >
                <VCardText class="text-center text-caption text-medium-emphasis">
                  Radar 6 trục sẽ hiển thị khi backend chấm điểm (chưa có trong rules_json).
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              lg="6"
            >
              <VCard
                title="Kênh áp dụng (UI-only)"
                subtitle="Hiển thị mặc định — chưa map DB"
                class="mb-6"
              >
                <VCardText>
                  <VRow>
                    <VCol
                      v-for="ch in displayChannels"
                      :key="ch.name"
                      cols="12"
                      sm="6"
                    >
                      <div class="channel-toggle-box px-3 py-2 rounded border d-flex align-center justify-space-between">
                        <div class="d-flex align-center text-truncate me-2">
                          <VAvatar
                            :color="ch.color"
                            size="24"
                            rounded
                            class="me-2 flex-shrink-0"
                          >
                            <VIcon
                              :icon="ch.icon"
                              size="14"
                              color="white"
                            />
                          </VAvatar>
                          <span class="text-body-2 font-weight-medium text-high-emphasis text-truncate">
                            {{ ch.name }}
                          </span>
                        </div>
                        <VSwitch
                          v-model="ch.enabled"
                          color="primary"
                          density="compact"
                          hide-details
                          inset
                        />
                      </div>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <VCard class="mb-6">
                <VCardItem>
                  <template #title>
                    Ví dụ văn phong (UI-only)
                  </template>
                  <template #subtitle>
                    Chưa lưu DB — giữ để bổ sung sau
                  </template>
                </VCardItem>
                <VCardText>
                  <div class="text-caption text-medium-emphasis">
                    Mẫu trích dẫn văn phong sẽ hiển thị từ evidence_json ở tab "Mẫu nội dung".
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- TAB 3: CẤU TRÚC NỘI DUNG -->
        <VWindowItem value="content-structure">
          <VCard>
            <VCardItem>
              <template #title>
                Cấu trúc bài viết (từ rules_json)
              </template>
              <template #subtitle>
                Đổ từ 4 khóa opening / structure_patterns / transitions / ending — chỉnh text sẽ lưu lại
              </template>
            </VCardItem>
            <VCardText>
              <VRow>
                <VCol
                  v-for="sec in structureSections"
                  :key="sec.title"
                  cols="12"
                  md="6"
                >
                  <div class="border rounded pa-4 h-100 d-flex flex-column">
                    <VChip
                      size="small"
                      color="primary"
                      variant="flat"
                      label
                      class="mb-3"
                    >
                      {{ sec.title }}
                    </VChip>
                    <div class="text-caption text-medium-emphasis mb-3">
                      {{ sec.guideline }}
                    </div>
                    <AppTextarea
                      v-model="sec.sample"
                      :placeholder="sec.placeholder"
                      label="Nội dung rules_json"
                      rows="6"
                      class="flex-grow-1"
                    />
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- TAB 4: QUY TẮC NGÔN NGỮ -->
        <VWindowItem value="language-rules">
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VCard title="Quy chuẩn Đại từ Xưng hô">
                <VCardText class="d-flex flex-column gap-4">
                  <AppTextField
                    v-model="languageConfig.brandPronoun"
                    label="Xưng hô của Thương hiệu"
                    placeholder="vd: nhà em, mình, chúng tôi"
                  />
                  <AppTextField
                    v-model="languageConfig.customerPronoun"
                    label="Cách gọi Khách hàng / Độc giả"
                    placeholder="vd: dâu rể, các bạn, quý khách"
                  />
                  <div class="text-caption text-medium-emphasis">
                    Hai ô này sẽ ghép lại thành "xưng A, gọi B" khi lưu vào rules_json.pronouns.
                  </div>
                  <AppSelect
                    v-model="languageConfig.emojiDensity"
                    :items="['Vừa phải', 'Tối giản', 'Không sử dụng']"
                    label="Mật độ Emoji (UI-only — chưa lưu)"
                  />
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VCard title="Do's & Don'ts (từ vocabulary + avoid)">
                <VCardText class="d-flex flex-column gap-3">
                  <div
                    v-if="!languageConfig.editorialRules.length"
                    class="text-caption text-medium-emphasis"
                  >
                    Chưa có quy tắc từ vocabulary/avoid.
                  </div>
                  <div
                    v-for="(rule, i) in languageConfig.editorialRules"
                    :key="i"
                    class="pa-3 rounded border d-flex align-center justify-space-between"
                  >
                    <div class="d-flex align-center text-body-2 text-high-emphasis">
                      <VIcon
                        :icon="rule.allowed ? 'tabler-circle-check' : 'tabler-circle-x'"
                        :color="rule.allowed ? 'success' : 'error'"
                        size="18"
                        class="me-2"
                      />
                      <span>{{ rule.text }}</span>
                    </div>
                    <VChip
                      size="x-small"
                      :color="rule.allowed ? 'success' : 'error'"
                      variant="tonal"
                      label
                    >
                      {{ rule.allowed ? 'Khuyến khích' : 'Cấm dùng' }}
                    </VChip>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- TAB 5: MẪU NỘI DUNG -->
        <VWindowItem value="content-samples">
          <VCard>
            <VCardItem>
              <template #title>
                Bằng chứng trích dẫn (evidence_json)
              </template>
              <template #subtitle>
                Các câu trích nguyên văn từ bài mẫu — đã kiểm chứng, chỉ đọc
              </template>
            </VCardItem>
            <VCardText>
              <div
                v-if="!fullPostSamples.length"
                class="tonal-panel pa-4 rounded text-caption text-medium-emphasis"
              >
                Chưa có bằng chứng trích dẫn — chạy giải mã để tạo.
              </div>
              <VRow v-else>
                <VCol
                  v-for="post in fullPostSamples"
                  :key="post.title"
                  cols="12"
                  md="4"
                >
                  <div class="border rounded pa-4 h-100 d-flex flex-column">
                    <VChip
                      size="small"
                      color="primary"
                      variant="tonal"
                      label
                      class="mb-3 font-weight-medium"
                    >
                      {{ ruleLabel(post.channel) }}
                    </VChip>
                    <div class="text-body-2 text-medium-emphasis mb-3 flex-grow-1">
                      {{ post.content }}
                    </div>
                    <div class="text-caption text-disabled">
                      {{ post.title }}
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- TAB 6: TỪ VỰNG & CTA -->
        <VWindowItem value="vocab-cta">
          <VRow>
            <!-- Cột 1: Từ điển Từ vựng & Thủ pháp Ngôn ngữ -->
            <VCol
              cols="12"
              md="6"
            >
              <VCard
                title="Từ điển Từ vựng & Thủ pháp Ngôn ngữ"
                subtitle="Đúc kết từ thuộc tính Chất liệu từ vựng"
                class="h-100"
              >
                <VCardText class="d-flex flex-column gap-5">
                  <!-- 1. Mô tả thủ pháp từ vựng -->
                  <div>
                    <div class="text-body-2 font-weight-medium mb-1">
                      Thủ pháp & Chất liệu từ vựng (từ AI giải mã):
                    </div>
                    <AppTextarea
                      v-model="vocabConfig.vocabularyDesc"
                      rows="3"
                      placeholder="Mô tả nguyên lý sử dụng từ vựng, từ tượng thanh, tính từ giác quan..."
                      persistent-placeholder
                    />
                  </div>

                  <!-- 2. Từ khóa thương hiệu ưu tiên dùng (Power Words) -->
                  <div>
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-body-2 font-weight-medium text-success">
                        Từ khóa ưu tiên dùng (Power Words)
                      </span>
                      <span class="text-caption text-medium-emphasis">
                        {{ vocabConfig.powerWords.length }} từ
                      </span>
                    </div>

                    <div class="d-flex gap-2 mb-3">
                      <AppTextField
                        v-model="vocabConfig.newPowerWord"
                        placeholder="Thêm từ khóa thương hiệu..."
                        density="compact"
                        hide-details
                        @keydown.enter.prevent="addPowerWord"
                      />
                      <VBtn
                        size="small"
                        color="success"
                        variant="tonal"
                        prepend-icon="tabler-plus"
                        @click="addPowerWord"
                      >
                        Thêm
                      </VBtn>
                    </div>

                    <div
                      v-if="!vocabConfig.powerWords.length"
                      class="text-caption text-medium-emphasis mb-2"
                    >
                      Chưa có từ khóa riêng lẻ. Bạn có thể thêm các từ/cụm từ thương hiệu độc quyền vào đây.
                    </div>
                    <div
                      v-else
                      class="d-flex flex-wrap gap-2"
                    >
                      <VChip
                        v-for="w in vocabConfig.powerWords"
                        :key="w"
                        size="small"
                        color="success"
                        variant="tonal"
                        closable
                        @click:close="vocabConfig.powerWords = vocabConfig.powerWords.filter(x => x !== w)"
                      >
                        {{ w }}
                      </VChip>
                    </div>
                  </div>

                  <!-- 3. Từ ngữ & thói quen kiêng kỵ (Negative Words / Avoid) -->
                  <div>
                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-body-2 font-weight-medium text-error">
                        Cấm xuất hiện / Kiêng kỵ (từ rules_json.avoid)
                      </span>
                      <span class="text-caption text-medium-emphasis">
                        {{ vocabConfig.negativeWords.length }} quy tắc
                      </span>
                    </div>

                    <div class="d-flex gap-2 mb-3">
                      <AppTextField
                        v-model="vocabConfig.newNegativeWord"
                        placeholder="Thêm điều cấm / từ kiêng kỵ..."
                        density="compact"
                        hide-details
                        @keydown.enter.prevent="addNegativeWord"
                      />
                      <VBtn
                        size="small"
                        color="error"
                        variant="tonal"
                        prepend-icon="tabler-plus"
                        @click="addNegativeWord"
                      >
                        Thêm
                      </VBtn>
                    </div>

                    <div
                      v-if="!vocabConfig.negativeWords.length"
                      class="text-caption text-medium-emphasis mb-2"
                    >
                      Chưa có quy tắc cấm.
                    </div>
                    <div
                      v-else
                      class="d-flex flex-wrap gap-2"
                    >
                      <VChip
                        v-for="(w, i) in vocabConfig.negativeWords"
                        :key="i"
                        size="small"
                        color="error"
                        variant="tonal"
                        closable
                        @click:close="vocabConfig.negativeWords.splice(i, 1)"
                      >
                        {{ w }}
                      </VChip>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Cột 2: Triết lý Kết bài & Kêu gọi Hành động (CTA) -->
            <VCol
              cols="12"
              md="6"
            >
              <VCard
                title="Triết lý Kết bài & Kêu gọi Hành động (CTA)"
                subtitle="Đúc kết từ thuộc tính Kết bài & Kêu gọi Hành động"
                class="h-100"
              >
                <VCardText class="d-flex flex-column gap-5">
                  <div>
                    <div class="text-body-2 font-weight-medium mb-1">
                      Phong cách Kết bài & Chiến lược CTA:
                    </div>
                    <div class="text-caption text-medium-emphasis mb-2">
                      Định hướng cách AI chốt lại bài viết (CTA trực tiếp, lời mời nhẹ nhàng, câu hỏi gợi mở, hay để lại khoảng lặng dư âm).
                    </div>
                    <AppTextarea
                      v-model="vocabConfig.ctaPhilosophy"
                      rows="6"
                      placeholder="Mô tả phong cách kết bài và cách thức kêu gọi hành động..."
                      persistent-placeholder
                    />
                  </div>

                  <div class="border rounded pa-4 bg-var-theme-background">
                    <div class="d-flex align-center gap-2 mb-2">
                      <VIcon
                        icon="tabler-bulb"
                        color="primary"
                        size="20"
                      />
                      <span class="text-body-2 font-weight-bold">
                        Gợi ý phong cách CTA phổ biến
                      </span>
                    </div>
                    <ul class="text-caption text-medium-emphasis ps-4 d-flex flex-column gap-1 mb-0">
                      <li><strong>Status tâm tình / Quiet luxury:</strong> Lời chúc an lành, câu hỏi mở gợi chiêm nghiệm, không ép mua hàng.</li>
                      <li><strong>Bán hàng thực chiến:</strong> CTA trực diện (inbox báo giá, để lại SĐT, ưu đãi có giới hạn).</li>
                      <li><strong>Chia sẻ giá trị / Chuyên gia:</strong> Mời thảo luận, lưu lại bài viết, chia sẻ góc nhìn.</li>
                    </ul>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- TAB 7: CÀI ĐẶT NÂNG CAO (Quản lý 3 mục Prompt bổ sung qua Tab dọc) -->
        <VWindowItem value="advanced">
          <VRow align="start">
            <!-- CỘT TRÁI: TAB DỌC NHỎ GỌN NẰM TRÊN 1 VCARD -->
            <VCol
              cols="12"
              md="auto"
              class="advanced-nav-col"
            >
              <VCard class="pa-3">
                <div class="text-caption text-uppercase text-disabled font-weight-bold mb-2 px-2">
                  Prompt bổ sung
                </div>
                <VTabs
                  v-model="activeAdvancedPromptTab"
                  direction="vertical"
                  density="compact"
                  class="v-tabs-pill disable-tab-transition advanced-vertical-tabs"
                >
                  <VTab
                    value="prompt1"
                    prepend-icon="tabler-user-edit"
                    class="text-caption font-weight-medium"
                  >
                    1. Hành văn & Mô hình
                  </VTab>
                  <VTab
                    value="prompt2"
                    prepend-icon="tabler-signature"
                    class="text-caption font-weight-medium"
                  >
                    2. Chất văn & Nhấn – Lướt
                  </VTab>
                  <VTab
                    value="prompt3"
                    prepend-icon="tabler-mood-heart"
                    class="text-caption font-weight-medium"
                  >
                    3. Sắc thái & Cảm xúc
                  </VTab>
                </VTabs>
              </VCard>
            </VCol>

            <!-- CỘT PHẢI: NỘI DUNG TỪNG MỤC PROMPT BỔ SUNG -->
            <VCol
              cols="12"
              md=""
              class="flex-grow-1"
              style="min-width: 0;"
            >
              <VWindow
                v-model="activeAdvancedPromptTab"
                class="disable-tab-transition advanced-prompt-window"
                :style="{ '--advanced-card-height': advancedCardHeight, '--slider-scroll-height': sliderScrollHeight }"
                :touch="false"
              >
                <!-- MỤC 1: PROMPT BỔ SUNG 1 & THAM SỐ MODEL / ĐỘ BAY BỔNG -->
                <VWindowItem value="prompt1">
                  <VCard
                    title="Prompt bổ sung 1 & Tham số Mô hình"
                    subtitle="Hướng dẫn hành văn tổng thể (style_instructions) và Độ bay bổng (temperature) lưu vào DB"
                    class="advanced-prompt-card"
                  >
            <VCardText>
              <VRow class="advanced-prompt-row">
                <!-- Cột trái: Ô 1 (style_instructions) -->
                <VCol
                  cols="12"
                  md="7"
                  class="advanced-prompt-left-col"
                >
                  <div class="d-flex align-center gap-2 mb-1 flex-shrink-0">
                    <VChip
                      size="x-small"
                      color="primary"
                      variant="flat"
                      label
                    >
                      Prompt bổ sung 1
                    </VChip>
                    <span class="text-body-2 font-weight-bold text-high-emphasis">
                      Hướng dẫn hành văn &amp; Chân dung biên tập (style_instructions)
                    </span>
                  </div>
                  <div class="text-caption text-medium-emphasis mb-2 flex-shrink-0">
                    Định hình chân dung người kể chuyện (Persona), thủ pháp gợi tả giác quan và bố cục tổng thể.
                  </div>
                  <AppTextarea
                    v-model="advancedConfig.systemPrompt"
                    rows="6"
                    counter="10000"
                    placeholder="Nhập hướng dẫn hành văn tổng thể (Ô 1)..."
                    persistent-placeholder
                    class="font-mono advanced-flex-textarea"
                  />
                </VCol>

                <!-- Cột phải: Model & Độ bay bổng (Temperature) -->
                <VCol
                  cols="12"
                  md="5"
                  class="advanced-prompt-right-col justify-start"
                >
                  <AppTextField
                    :model-value="advancedConfig.preferredModel"
                    label="Model đã dùng khi giải mã"
                    readonly
                    disabled
                    class="mb-3 flex-grow-0"
                  />
                  <div class="mb-4 flex-shrink-0">
                    <div class="d-flex justify-space-between text-body-2 font-weight-medium mb-1">
                      <span>Độ bay bổng / Sáng tạo (Temperature)</span>
                      <span class="text-primary">{{ advancedConfig.temperature }}</span>
                    </div>
                    <VSlider
                      v-model="advancedConfig.temperature"
                      min="0"
                      max="1"
                      step="0.05"
                      color="primary"
                      hide-details
                    />
                    <div class="text-caption text-medium-emphasis mt-1">
                      Độ ngẫu hứng của AI: thấp (0.1–0.3: kỷ luật, an toàn) — trung bình (0.7–0.85: tự nhiên, cảm xúc) — cao (0.9–1.0: phá cách, bay bổng).
                    </div>
                  </div>

                  <div class="d-flex align-center justify-space-between pt-3 border-t">
                    <span class="text-body-2 font-weight-medium">
                      Tự động chấm điểm (UI-only)
                    </span>
                    <VSwitch
                      v-model="advancedConfig.autoScoreCheck"
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

                <!-- MỤC 2: PROMPT BỔ SUNG 2 (CHẤT VĂN) — TRÁI: Ô TEXT, PHẢI: GỢI Ý CHECKBOX & 5 THANH TRƯỢT -->
                <VWindowItem value="prompt2">
                  <VCard
                    title="Prompt bổ sung 2 — Chỉ dẫn Chất văn & Nghệ thuật Nhấn – Lướt"
            subtitle="Bóc tách và canh chỉnh 5 thuộc tính Chất văn: Giọng văn, Tính dân dã tự nhiên, Màu sắc cảm xúc, Biến thiên nhịp điệu, Tính nhấn và lướt"
            class="advanced-prompt-card"
          >
            <VCardText>
              <VRow class="advanced-prompt-row">
                <!-- Cột trái: Ô Text Prompt bổ sung 2 + Tóm tắt chỉ số -->
                <VCol
                  cols="12"
                  md="6"
                  class="advanced-prompt-left-col"
                >
                  <div class="d-flex align-center justify-space-between mb-1 flex-shrink-0">
                    <div class="d-flex align-center gap-2">
                      <VChip
                        size="x-small"
                        color="info"
                        variant="flat"
                        label
                      >
                        Prompt bổ sung 2
                      </VChip>
                      <span class="text-body-2 font-weight-bold text-high-emphasis">
                        Chỉ dẫn Chất văn (voice_texture_instructions)
                      </span>
                    </div>
                  </div>
                  <div class="text-caption text-medium-emphasis mb-2 flex-shrink-0">
                    Khi giải mã bài mẫu, AI tự động phân tích Ô 2 này. Bạn có thể tự do chỉnh sửa văn bản hoặc tích chọn các gợi ý bên phải để đồng bộ vào đây.
                  </div>
                  <AppTextarea
                    v-model="advancedConfig.voiceTexturePrompt"
                    rows="6"
                    counter="10000"
                    placeholder="Nhập chỉ dẫn về giọng văn, tính dân dã tự nhiên, màu sắc cảm xúc, biến thiên nhịp điệu, tính nhấn và lướt..."
                    persistent-placeholder
                    class="font-mono mb-3 advanced-flex-textarea"
                  />

                  <!-- Tóm tắt trực quan cấu hình Chất văn hiện tại -->
                  <div class="tonal-panel pa-3 rounded flex-shrink-0">
                    <div class="d-flex align-center gap-2 mb-1">
                      <VIcon
                        icon="tabler-dna"
                        color="primary"
                        size="16"
                      />
                      <span class="text-caption font-weight-bold text-high-emphasis">
                        Tóm tắt chỉ số Chất văn đang áp dụng
                      </span>
                    </div>
                    <div class="summary-grid-2col">
                      <div
                        v-for="slider in voiceTextureSliders"
                        :key="`summary-${slider.key}`"
                        class="d-flex align-center justify-space-between text-caption"
                      >
                        <span class="text-medium-emphasis text-truncate me-1">{{ slider.label }}:</span>
                        <span class="font-weight-bold text-high-emphasis flex-shrink-0">
                          {{ slider.value == null ? 'Chưa xác định' : `${slider.value}%` }}
                        </span>
                      </div>
                    </div>
                  </div>
                </VCol>

                <!-- Cột phải: Toàn bộ Gợi ý Checkbox & 5 Thanh trượt canh chỉnh Chất văn -->
                <VCol
                  cols="12"
                  md="6"
                  class="advanced-prompt-right-col"
                >
                  <!-- 1. Ô Select gợi ý phong cách & nhịp điệu hành văn (thay thế nhóm checkbox cũ) -->
                  <div class="tonal-panel pa-3 rounded mb-3 flex-shrink-0">
                    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                      <div class="d-flex align-center gap-2">
                        <VIcon
                          icon="tabler-select"
                          color="primary"
                          size="20"
                        />
                        <span class="text-body-2 font-weight-bold text-high-emphasis">
                          Gợi ý phong cách &amp; Nhịp điệu hành văn
                        </span>
                        <VChip
                          v-if="selectedVoiceOptionIds.length"
                          size="x-small"
                          color="primary"
                          variant="flat"
                          label
                        >
                          Đã chọn {{ selectedVoiceOptionIds.length }}
                        </VChip>
                      </div>
                      <VBtn
                        v-if="selectedVoiceOptionIds.length"
                        size="x-small"
                        variant="text"
                        color="secondary"
                        prepend-icon="tabler-refresh"
                        @click="resetVoiceTextureSliders"
                      >
                        Khôi phục gốc
                      </VBtn>
                    </div>

                    <div class="text-caption text-medium-emphasis mb-3">
                      Chọn phong cách hoặc nhịp văn để tự động cộng hưởng tính điểm 5 thanh trượt và đồng bộ chỉ đạo sang Ô 2 bên trái.
                    </div>

                    <AppSelect
                      v-model="selectedVoiceOptionIds"
                      :items="voiceStyleSelectItems"
                      item-title="title"
                      item-value="value"
                      label="Chọn phong cách / nhịp văn gợi ý"
                      placeholder="Nhấn để chọn phong cách gợi ý..."
                      multiple
                      chips
                      closable-chips
                      clearable
                      class="mb-1"
                      @update:model-value="recalculateVoiceTextureFromCheckboxes"
                    >
                      <template #item="{ props, item }">
                        <VListItem
                          v-bind="props"
                          :title="undefined"
                        >
                          <template #prepend>
                            <VIcon
                              :icon="item.raw.icon"
                              :color="item.raw.color"
                              size="18"
                              class="me-2"
                            />
                          </template>
                          <VListItemTitle class="text-body-2 font-weight-medium">
                            {{ item.raw.title }}
                          </VListItemTitle>
                          <VListItemSubtitle class="text-caption text-medium-emphasis">
                            {{ item.raw.attributeLabel }} — {{ item.raw.desc }}
                          </VListItemSubtitle>
                        </VListItem>
                      </template>
                    </AppSelect>
                  </div>

                  <!-- 2. Khung 5 Thanh trượt canh chỉnh Chất văn đặt trong Collapse (Expansion Panel) -->
                  <VExpansionPanels
                    v-model="voiceTexturePanel"
                    variant="accordion"
                    class="expansion-panels-width-border advanced-slider-panels"
                    :class="{ 'is-panel-open': voiceTexturePanel === 0 }"
                  >
                    <VExpansionPanel elevation="0">
                      <VExpansionPanelTitle class="py-3 px-4">
                        <div class="d-flex align-center justify-space-between flex-wrap gap-2 w-100 me-2">
                          <div class="d-flex align-center gap-2">
                            <VAvatar
                              color="primary"
                              variant="tonal"
                              size="32"
                              rounded
                            >
                              <VIcon
                                icon="tabler-adjustments-heart"
                                size="18"
                              />
                            </VAvatar>
                            <div class="text-start">
                              <div class="text-body-2 font-weight-bold text-high-emphasis">
                                5 Thanh trượt canh chỉnh Chất văn (0% – 100%)
                              </div>
                              <div class="text-caption text-medium-emphasis">
                                Giọng văn, Dân dã, Cảm xúc, Nhịp điệu, Nhấn – Lướt
                              </div>
                            </div>
                          </div>

                          <div class="d-flex align-center gap-2">
                            <VChip
                              v-if="hasModifiedVoiceSliders"
                              size="x-small"
                              color="warning"
                              variant="tonal"
                              label
                            >
                              Đã tinh chỉnh
                            </VChip>
                            <VBtn
                              size="x-small"
                              variant="text"
                              color="secondary"
                              prepend-icon="tabler-refresh"
                              @click.stop="resetVoiceTextureSliders"
                            >
                              Khôi phục gốc
                            </VBtn>
                          </div>
                        </div>
                      </VExpansionPanelTitle>

                      <VExpansionPanelText>
                        <div class="text-caption text-medium-emphasis mb-3 px-2">
                          Kéo thanh trượt để tinh chỉnh thủ công từng thuộc tính Chất văn (cuộn bên trong khung):
                        </div>

                        <div class="d-flex flex-column gap-3 slider-scroll-box">
                          <div
                            v-for="slider in voiceTextureSliders"
                            :key="slider.key"
                            class="pa-3 rounded border bg-surface"
                          >
                            <div class="d-flex align-center justify-space-between mb-2">
                              <div class="d-flex align-center">
                                <VAvatar
                                  :color="slider.color"
                                  variant="tonal"
                                  size="28"
                                  rounded
                                  class="me-2 flex-shrink-0"
                                >
                                  <VIcon
                                    :icon="slider.icon"
                                    size="15"
                                  />
                                </VAvatar>
                                <span class="text-body-2 font-weight-medium text-high-emphasis">
                                  {{ slider.label }}
                                </span>
                              </div>
                              <div class="d-flex align-center gap-2">
                                <VChip
                                  v-if="slider.value !== slider.baseline"
                                  size="x-small"
                                  :color="slider.value > slider.baseline ? 'success' : 'warning'"
                                  variant="tonal"
                                  label
                                >
                                  {{ slider.value > slider.baseline ? `+${slider.value - slider.baseline}%` : `${slider.value - slider.baseline}%` }} so với gốc
                                </VChip>
                                <span
                                  class="text-body-2 font-weight-bold"
                                  :class="`text-${slider.color}`"
                                >
                                  {{ slider.value == null ? 'Chưa xác định' : `${slider.value}%` }}
                                </span>
                              </div>
                            </div>

                            <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
                              <span>0%: {{ slider.lowPole }}</span>
                              <span>100%: {{ slider.highPole }}</span>
                            </div>

                            <VSlider
                              @update:model-value="slider.value = $event"
                              :model-value="slider.value ?? 50"
                              :min="0"
                              :max="100"
                              :step="1"
                              :color="slider.color"
                              hide-details
                              density="compact"
                            />

                            <div class="text-caption text-medium-emphasis mt-1">
                              {{ slider.hint }}
                            </div>
                          </div>
                        </div>
                      </VExpansionPanelText>
                    </VExpansionPanel>
                  </VExpansionPanels>
                </VCol>
              </VRow>
                    </VCardText>
                  </VCard>
                </VWindowItem>

                <!-- MỤC 3: PROMPT BỔ SUNG 3 (SẮC THÁI CẢM XÚC) — TRÁI: Ô TEXT, PHẢI: GỢI Ý PRESET SELECT & 8 THANH TRƯỢT -->
                <VWindowItem value="prompt3">
                  <VCard
                    title="Prompt bổ sung 3 — Chỉ dẫn Sắc thái & Phối âm cảm xúc"
                    subtitle="Bóc tách và hiệu chỉnh 8 trục sắc thái cảm xúc: Vui vẻ, Đồng cảm, Sắc sảo, Trang trọng, Nhiệt huyết, Chân thực, Trí tuệ, Khẩn thiết"
                    class="advanced-prompt-card"
                  >
            <VCardText>
              <VRow class="advanced-prompt-row">
                <!-- Cột trái: Ô Text Prompt bổ sung 3 + Tóm tắt chỉ số Sắc thái -->
                <VCol
                  cols="12"
                  md="6"
                  class="advanced-prompt-left-col"
                >
                  <div class="d-flex align-center justify-space-between mb-1 flex-shrink-0">
                    <div class="d-flex align-center gap-2">
                      <VChip
                        size="x-small"
                        color="warning"
                        variant="flat"
                        label
                      >
                        Prompt bổ sung 3
                      </VChip>
                      <span class="text-body-2 font-weight-bold text-high-emphasis">
                        Chỉ dẫn Sắc thái &amp; Cảm xúc (tone_instructions)
                      </span>
                    </div>
                  </div>
                  <div class="text-caption text-medium-emphasis mb-2 flex-shrink-0">
                    Khi giải mã bài mẫu, AI tự động phân tích Ô 3 này. Bạn có thể tự do chỉnh sửa văn bản hoặc chọn các gợi ý bên phải để đồng bộ vào đây.
                  </div>
                  <AppTextarea
                    v-model="advancedConfig.tonePrompt"
                    rows="6"
                    counter="10000"
                    placeholder="Nhập chỉ dẫn về sắc thái đối thoại, độ đồng cảm, độ hóm hỉnh, khí chất đĩnh đạc, tính khẩn thiết..."
                    persistent-placeholder
                    class="font-mono mb-3 advanced-flex-textarea"
                  />

                  <!-- Tóm tắt trực quan cấu hình Sắc thái cảm xúc hiện tại -->
                  <div class="tonal-panel pa-3 rounded flex-shrink-0">
                    <div class="d-flex align-center gap-2 mb-1">
                      <VIcon
                        icon="tabler-adjustments-heart"
                        color="warning"
                        size="16"
                      />
                      <span class="text-caption font-weight-bold text-high-emphasis">
                        Tóm tắt 8 trục Sắc thái cảm xúc đang áp dụng
                      </span>
                    </div>
                    <div class="summary-grid-2col">
                      <div
                        v-for="slider in toneSliders"
                        :key="`summary-tone-${slider.key}`"
                        class="d-flex align-center justify-space-between text-caption"
                      >
                        <span class="text-medium-emphasis text-truncate me-1">{{ slider.label }}:</span>
                        <span class="font-weight-bold text-high-emphasis flex-shrink-0">
                          {{ slider.value == null ? 'Chưa xác định' : `${slider.value}%` }}
                        </span>
                      </div>
                    </div>
                  </div>
                </VCol>

                <!-- Cột phải: Ô Select gợi ý sắc thái cảm xúc & Collapse 8 Thanh trượt -->
                <VCol
                  cols="12"
                  md="6"
                  class="advanced-prompt-right-col"
                >
                  <!-- 1. Ô Select gợi ý sắc thái & phối âm cảm xúc -->
                  <div class="tonal-panel pa-3 rounded mb-3 flex-shrink-0">
                    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                      <div class="d-flex align-center gap-2">
                        <VIcon
                          icon="tabler-select"
                          color="warning"
                          size="20"
                        />
                        <span class="text-body-2 font-weight-bold text-high-emphasis">
                          Gợi ý sắc thái &amp; Phối âm cảm xúc
                        </span>
                        <VChip
                          v-if="selectedToneOptionIds.length"
                          size="x-small"
                          color="warning"
                          variant="flat"
                          label
                        >
                          Đã chọn {{ selectedToneOptionIds.length }}
                        </VChip>
                      </div>
                      <VBtn
                        v-if="selectedToneOptionIds.length"
                        size="x-small"
                        variant="text"
                        color="secondary"
                        prepend-icon="tabler-refresh"
                        @click="resetToneSliders"
                      >
                        Khôi phục gốc
                      </VBtn>
                    </div>

                    <div class="text-caption text-medium-emphasis mb-3">
                      Chọn các mẫu phối âm sắc thái để tự động tính điểm 8 thanh trượt và đồng bộ chỉ đạo sang Ô 3 bên trái.
                    </div>

                    <AppSelect
                      v-model="selectedToneOptionIds"
                      :items="toneStyleSelectItems"
                      item-title="title"
                      item-value="value"
                      label="Chọn sắc thái / phối âm gợi ý"
                      placeholder="Nhấn để chọn sắc thái cảm xúc..."
                      multiple
                      chips
                      closable-chips
                      clearable
                      class="mb-1"
                      @update:model-value="recalculateToneFromSelect"
                    >
                      <template #item="{ props, item }">
                        <VListItem
                          v-bind="props"
                          :title="undefined"
                        >
                          <template #prepend>
                            <VIcon
                              :icon="item.raw.icon"
                              :color="item.raw.color"
                              size="18"
                              class="me-2"
                            />
                          </template>
                          <VListItemTitle class="text-body-2 font-weight-medium">
                            {{ item.raw.title }}
                          </VListItemTitle>
                          <VListItemSubtitle class="text-caption text-medium-emphasis">
                            {{ item.raw.attributeLabel }} — {{ item.raw.desc }}
                          </VListItemSubtitle>
                        </VListItem>
                      </template>
                    </AppSelect>
                  </div>

                  <!-- 2. Khung 8 Thanh trượt canh chỉnh Cảm xúc đặt trong Collapse (Expansion Panel) -->
                  <VExpansionPanels
                    v-model="toneSlidersPanel"
                    variant="accordion"
                    class="expansion-panels-width-border advanced-slider-panels"
                    :class="{ 'is-panel-open': toneSlidersPanel === 0 }"
                  >
                    <VExpansionPanel elevation="0">
                      <VExpansionPanelTitle class="py-3 px-4">
                        <div class="d-flex align-center justify-space-between flex-wrap gap-2 w-100 me-2">
                          <div class="d-flex align-center gap-2">
                            <VAvatar
                              color="warning"
                              variant="tonal"
                              size="32"
                              rounded
                            >
                              <VIcon
                                icon="tabler-mood-heart"
                                size="18"
                              />
                            </VAvatar>
                            <div class="text-start">
                              <div class="text-body-2 font-weight-bold text-high-emphasis">
                                8 Thanh trượt Sắc thái cảm xúc (0% – 100%)
                              </div>
                              <div class="text-caption text-medium-emphasis">
                                Vui vẻ, Đồng cảm, Sắc sảo, Trang trọng, Nhiệt huyết, Chân thực, Trí tuệ, Khẩn thiết
                              </div>
                            </div>
                          </div>

                          <div class="d-flex align-center gap-2">
                            <VChip
                              v-if="hasModifiedToneSliders"
                              size="x-small"
                              color="warning"
                              variant="tonal"
                              label
                            >
                              Đã tinh chỉnh
                            </VChip>
                            <VBtn
                              size="x-small"
                              variant="text"
                              color="secondary"
                              prepend-icon="tabler-refresh"
                              @click.stop="resetToneSliders"
                            >
                              Khôi phục gốc
                            </VBtn>
                          </div>
                        </div>
                      </VExpansionPanelTitle>

                      <VExpansionPanelText>
                        <div class="text-caption text-medium-emphasis mb-3 px-2">
                          Kéo thanh trượt để tinh chỉnh thủ công từng trục cảm xúc (cuộn bên trong khung):
                        </div>

                        <div class="d-flex flex-column gap-3 slider-scroll-box slider-scroll-box--tone">
                          <div
                            v-for="slider in toneSliders"
                            :key="`edit-tone-${slider.key}`"
                            class="pa-3 rounded border bg-surface"
                          >
                            <div class="d-flex align-center justify-space-between mb-2">
                              <div class="d-flex align-center">
                                <VAvatar
                                  :color="slider.color"
                                  variant="tonal"
                                  size="28"
                                  rounded
                                  class="me-2 flex-shrink-0"
                                >
                                  <VIcon
                                    :icon="slider.icon"
                                    size="15"
                                  />
                                </VAvatar>
                                <span class="text-body-2 font-weight-medium text-high-emphasis">
                                  {{ slider.label }}
                                </span>
                              </div>
                              <div class="d-flex align-center gap-2">
                                <VChip
                                  v-if="slider.value !== slider.baseline"
                                  size="x-small"
                                  :color="slider.value > slider.baseline ? 'success' : 'warning'"
                                  variant="tonal"
                                  label
                                >
                                  {{ slider.value > slider.baseline ? `+${slider.value - slider.baseline}%` : `${slider.value - slider.baseline}%` }} so với gốc
                                </VChip>
                                <span
                                  class="text-body-2 font-weight-bold"
                                  :class="`text-${slider.color}`"
                                >
                                  {{ slider.value == null ? 'Chưa xác định' : `${slider.value}%` }}
                                </span>
                              </div>
                            </div>

                            <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
                              <span>0%: Tiết chế tối đa</span>
                              <span>100%: Bộc lộ mãnh liệt</span>
                            </div>

                            <VSlider
                              @update:model-value="slider.value = $event"
                              :model-value="slider.value ?? 50"
                              :min="0"
                              :max="100"
                              :step="1"
                              :color="slider.color"
                              hide-details
                              density="compact"
                            />

                            <div class="text-caption text-medium-emphasis mt-1">
                              {{ slider.hint }}
                            </div>
                          </div>
                        </div>
                      </VExpansionPanelText>
                    </VExpansionPanel>
                  </VExpansionPanels>
                </VCol>
              </VRow>
                    </VCardText>
                  </VCard>
                </VWindowItem>
              </VWindow>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- TAB 8: LỊCH SỬ -->
        <VWindowItem value="history">
          <VCard
            title="Lịch sử Phiên bản & Giải mã"
            subtitle="Version hiện tại + các lần giải mã đã chạy trên profile này"
          >
            <VCardText class="d-flex flex-column gap-3">
              <div
                v-for="ver in versionHistory"
                :key="ver.date + ver.summary"
                class="pa-4 rounded border d-flex align-center justify-space-between flex-wrap gap-3"
                :class="{ 'ver-item--current': ver.current }"
              >
                <div class="d-flex align-center">
                  <VAvatar
                    :color="ver.current ? 'primary' : 'secondary'"
                    :variant="ver.current ? 'flat' : 'tonal'"
                    size="40"
                    rounded
                    class="me-3"
                  >
                    <span class="text-body-2 font-weight-medium">{{ ver.version }}</span>
                  </VAvatar>
                  <div>
                    <div class="d-flex align-center gap-2">
                      <span class="text-subtitle-1 font-weight-medium text-high-emphasis">{{ ver.summary }}</span>
                      <VChip
                        v-if="ver.current"
                        size="x-small"
                        color="success"
                        variant="flat"
                        label
                      >
                        Hiện tại
                      </VChip>
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ ver.date }}<template v-if="ver.author">
                        • {{ ver.author }}
                      </template>
                    </div>
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- TAB 9: PHÒNG THỬ DNA — 2 cột: trái Chủ đề thử nghiệm, phải Sandbox sắc thái -->
        <VWindowItem value="sandbox">
          <!-- Cảnh báo chống đạo văn — nằm trên cả 2 card -->
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4 text-caption"
          >
            <strong>Cơ chế Snapshot chống đạo văn:</strong> AI chỉ nhận <code>rules_json</code> + <code>style_instructions</code>
            đã lọc theo cấp độ độ dài — <strong>không bao giờ</strong> thấy câu chữ mẫu (<code>evidence_json</code>)
            nên không thể đạo văn. Các hiệu chỉnh slider/preset được gửi kèm để thấy ngay thay đổi.
          </VAlert>

          <VRow align="start">
            <!-- ===== CỘT TRÁI: TÁCH 2 VCARD RIÊNG BIỆT (1: CHỦ ĐỀ THỬ NGHIỆM MỚI | 2: CÁC BÀI VĂN THỬ NGHIỆM) ===== -->
            <VCol
              cols="12"
              lg="7"
            >
              <!-- VCARD 1: CHỦ ĐỀ THỬ NGHIỆM MỚI -->
              <VCard class="mb-6">
                <VCardItem>
                  <template #title>
                    <div class="d-flex align-center">
                      <VIcon
                        icon="tabler-flask-2"
                        color="primary"
                        size="22"
                        class="me-2"
                      />
                      <span class="text-h6 font-weight-bold text-high-emphasis">Chủ đề thử nghiệm mới</span>
                    </div>
                  </template>
                  <template #subtitle>
                    <div class="text-caption text-medium-emphasis mt-1">
                      Nhập chủ đề và chọn cấp độ độ dài — hệ thống sẽ khởi tạo bản ghi thử nghiệm theo đúng quy trình các bước trong cấu hình
                    </div>
                  </template>
                </VCardItem>

                <VCardText>
                  <AppTextField
                    v-model="sandboxForm.topic"
                    label="Chủ đề viết thử"
                    placeholder="vd: ly cà phê sớm thơm bên bàn làm việc"
                    persistent-placeholder
                    class="mb-4"
                    append-inner-icon="tabler-sparkles"
                    :loading="suggestingBrief || store.suggestingBrief"
                    :disabled="suggestingBrief || store.suggestingBrief"
                    hint="Bấm biểu tượng ✨ để AI suy luận Brief từ chủ đề"
                    persistent-hint
                    @keyup.enter="runSandboxDemo"
                    @click:append-inner="generateBriefWithAI"
                  />

                  <AppSelect
                    v-model="sandboxForm.targetTier"
                    :items="['micro','short','medium','long'].map(t => ({ title: t.toUpperCase(), value: t }))"
                    label="Cấp độ độ dài mục tiêu (Target Tier)"
                    class="mb-4"
                  />

                  <SandboxWritingBrief v-model="briefForm" />

                  <!-- Hiển thị sơ đồ quy trình các bước của Tier đang chọn (đọc từ config) -->
                  <div class="tonal-panel pa-3 rounded mb-4">
                    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                      <div class="d-flex align-center gap-2">
                        <VIcon
                          icon="tabler-git-branch"
                          color="primary"
                          size="16"
                        />
                        <span class="text-caption font-weight-bold text-high-emphasis">
                          Quy trình thực thi của cấp độ "{{ (sandboxForm.targetTier || 'medium').toUpperCase() }}" ({{ currentTierPipelineSteps.length }} bước):
                        </span>
                      </div>
                      <VChip
                        size="x-small"
                        :color="currentTierPipelineSteps.length > 1 ? 'warning' : 'success'"
                        variant="tonal"
                        label
                      >
                        {{ currentTierPipelineSteps.length > 1 ? `Phân tích ${currentTierPipelineSteps.length - 1} bước trước khi viết` : 'Viết trực tiếp 1 bước' }}
                      </VChip>
                    </div>

                    <div class="d-flex align-center flex-wrap gap-2">
                      <template
                        v-for="(st, sIdx) in currentTierPipelineSteps"
                        :key="st.step"
                      >
                        <VChip
                          size="small"
                          :color="sIdx === 0 ? 'primary' : 'secondary'"
                          :variant="sIdx === 0 ? 'flat' : 'tonal'"
                          label
                        >
                          <VIcon
                            :icon="st.icon || (st.is_terminal ? 'tabler-writing' : 'tabler-binary-tree')"
                            size="14"
                            class="me-1"
                          />
                          Bước {{ sIdx + 1 }}: {{ cleanStepLabel(st) }}
                        </VChip>
                        <VIcon
                          v-if="sIdx < currentTierPipelineSteps.length - 1"
                          icon="tabler-arrow-narrow-right"
                          size="16"
                          class="text-disabled"
                        />
                      </template>
                    </div>
                  </div>

                  <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                    <span class="text-caption text-success d-flex align-center">
                      <VIcon
                        icon="tabler-dna"
                        size="15"
                        class="me-1"
                      />
                      Profile: {{ profileData.name }} ({{ profileData.version }})
                    </span>

                    <VBtn
                      variant="flat"
                      color="primary"
                      size="small"
                      prepend-icon="tabler-player-play"
                      :loading="store.sandboxing && !runningSampleStepId"
                      :disabled="store.sandboxing || !profile"
                      @click="runSandboxDemo"
                    >
                      <template v-if="currentTierPipelineSteps.length > 1">
                        Bắt đầu Bước 1/{{ currentTierPipelineSteps.length }}: {{ cleanStepLabel(currentTierPipelineSteps[0]) }}
                      </template>
                      <template v-else>
                        Viết thử với DNA này
                      </template>
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>

              <!-- VCARD 2: CÁC BÀI VĂN THỬ NGHIỆM (QUẢN LÝ TIẾN TRÌNH TỪNG STEP & BÀI ĐÃ HOÀN THÀNH) -->
              <VCard>
                <VCardItem>
                  <template #title>
                    <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                      <div class="d-flex align-center">
                        <VIcon
                          icon="tabler-bookmarks"
                          color="primary"
                          size="22"
                          class="me-2"
                        />
                        <span class="text-h6 font-weight-bold text-high-emphasis">
                          Các bài văn thử nghiệm ({{ savedSandboxSamples.length }})
                        </span>
                      </div>
                      <VChip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        label
                      >
                        Theo dõi tiến trình từng Step &amp; So sánh
                      </VChip>
                    </div>
                  </template>
                  <template #subtitle>
                    <div class="text-caption text-medium-emphasis mt-1">
                      Các cấp độ có nhiều bước sẽ chạy tuần tự từng bước phân tích theo config cho đến giai đoạn viết văn cuối cùng thì hiển thị Hoàn thành
                    </div>
                  </template>
                </VCardItem>

                <VCardText>
                  <div
                    v-if="!savedSandboxSamples.length"
                    class="pa-6 rounded border border-dashed text-center text-caption text-medium-emphasis"
                  >
                    Chưa có bài văn thử nghiệm nào. Hãy nhập chủ đề ở card <strong>"Chủ đề thử nghiệm mới"</strong> phía trên để khởi tạo bản ghi đầu tiên.
                  </div>

                  <div
                    v-else
                    class="d-flex flex-column gap-4"
                  >
                    <div
                      v-for="(sample, idx) in savedSandboxSamples"
                      :key="sample.id || idx"
                      class="pa-4 rounded border bg-surface"
                      :class="sample.status === 'completed' ? 'sample-card--completed' : 'sample-card--in-progress'"
                    >
                      <!-- 1. HEADER BẢN GHI: Số thứ tự, Chủ đề, Tier, Trạng thái Step / Hoàn thành -->
                      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                        <div class="d-flex align-center flex-wrap gap-2">
                          <VChip
                            size="x-small"
                            color="secondary"
                            variant="tonal"
                            label
                          >
                            #{{ savedSandboxSamples.length - idx }}
                          </VChip>
                          <span class="text-body-2 font-weight-bold text-high-emphasis">
                            {{ sample.topic }}
                          </span>
                          <VChip
                            size="x-small"
                            color="primary"
                            variant="tonal"
                            label
                          >
                            {{ (sample.target_tier || 'medium').toUpperCase() }}
                          </VChip>

                          <!-- Badge trạng thái tiến trình bước hoặc Hoàn thành -->
                          <VChip
                            v-if="sample.status === 'completed'"
                            size="x-small"
                            color="success"
                            variant="flat"
                            label
                            prepend-icon="tabler-circle-check"
                          >
                            Hoàn thành ({{ sample.steps?.length || 1 }}/{{ sample.steps?.length || 1 }} bước)
                          </VChip>
                          <VChip
                            v-else
                            size="x-small"
                            color="warning"
                            variant="flat"
                            label
                            prepend-icon="tabler-loader"
                          >
                            Đang ở Bước {{ (sample.current_step_index ?? 0) + 1 }}/{{ sample.steps?.length || 2 }}:
                            {{ cleanStepLabel(sample.steps?.[sample.current_step_index ?? 0]) }}
                          </VChip>

                          <span
                            v-if="sample.created_at"
                            class="text-caption text-disabled"
                          >
                            {{ new Date(sample.created_at).toLocaleString('vi-VN') }}
                          </span>
                        </div>

                        <!-- Cụm nút hành động bên phải header -->
                        <div class="d-flex align-center gap-1">
                          <VBtn
                            variant="tonal"
                            color="primary"
                            size="x-small"
                            prepend-icon="tabler-eye"
                            @click="openSandboxSampleDialog(sample)"
                          >
                            Xem bài viết
                          </VBtn>

                          <VBtn
                            v-if="sample.status === 'completed' && !sample.saved_to_db"
                            variant="tonal"
                            color="success"
                            size="x-small"
                            prepend-icon="tabler-bookmark-plus"
                            :loading="savingSampleId === sample.id"
                            @click="saveCurrentSandboxSample(sample)"
                          >
                            Lưu bản thử
                          </VBtn>

                          <VBtn
                            v-if="sample.text"
                            variant="text"
                            color="primary"
                            size="x-small"
                            prepend-icon="tabler-copy"
                            @click="copyText(sample.text, 'Đã sao chép bản viết thử')"
                          >
                            Sao chép
                          </VBtn>

                          <VBtn
                            variant="text"
                            color="error"
                            size="x-small"
                            icon="tabler-trash"
                            :loading="deletingSandboxSampleId === sample.id"
                            @click="removeSandboxSample(sample.id)"
                          />
                        </div>
                      </div>

                      <!-- 2. THANH TIẾN TRÌNH CÁC BƯỚC (PIPELINE STEPPER THEO CONFIG) -->
                      <div
                        v-if="sample.steps && sample.steps.length > 1"
                        class="d-flex align-center flex-wrap gap-2 mb-3 pa-2 rounded bg-var-theme-background"
                      >
                        <template
                          v-for="(st, sIdx) in sample.steps"
                          :key="`${sample.id}-step-${st.step}`"
                        >
                          <VChip
                            size="x-small"
                            :color="sIdx < sample.current_step_index || sample.status === 'completed'
                              ? 'success'
                              : (sIdx === sample.current_step_index ? 'warning' : 'secondary')"
                            :variant="sIdx <= sample.current_step_index ? 'flat' : 'tonal'"
                            label
                          >
                            <VIcon
                              :icon="sIdx < sample.current_step_index || sample.status === 'completed'
                                ? 'tabler-check'
                                : (sIdx === sample.current_step_index ? 'tabler-sparkles' : 'tabler-clock')"
                              size="12"
                              class="me-1"
                            />
                            Bước {{ sIdx + 1 }}: {{ cleanStepLabel(st) }}
                          </VChip>
                          <VIcon
                            v-if="sIdx < sample.steps.length - 1"
                            icon="tabler-chevron-right"
                            size="14"
                            class="text-disabled"
                          />
                        </template>
                      </div>

                      <!-- 3. NÚT BẤM CHUYỂN SANG BƯỚC PHÂN TÍCH TIẾP THEO / VIẾT VĂN CUỐI CÙNG (KHI ĐANG IN_PROGRESS) -->
                      <div
                        v-if="sample.status !== 'completed' && sample.steps && sample.current_step_index + 1 < sample.steps.length"
                        class="pa-3 rounded border border-dashed d-flex align-center justify-space-between flex-wrap gap-3 bg-var-theme-background"
                      >
                        <div class="d-flex align-center gap-2">
                          <VAvatar
                            color="primary"
                            variant="tonal"
                            size="32"
                            rounded
                          >
                            <VIcon
                              :icon="sample.steps[sample.current_step_index + 1]?.is_terminal ? 'tabler-writing' : 'tabler-arrow-right'"
                              size="18"
                            />
                          </VAvatar>
                          <div>
                            <div class="text-body-2 font-weight-bold text-high-emphasis">
                              Sẵn sàng cho Bước {{ sample.current_step_index + 2 }}/{{ sample.steps.length }}:
                              {{ cleanStepLabel(sample.steps[sample.current_step_index + 1]) }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                              {{ sample.steps[sample.current_step_index + 1]?.description || 'Sử dụng kết quả phân tích ở bước trước kết hợp với DNA văn phong để xử lý bước tiếp theo.' }}
                            </div>
                          </div>
                        </div>

                        <VBtn
                          color="primary"
                          variant="flat"
                          size="small"
                          :prepend-icon="sample.steps[sample.current_step_index + 1]?.is_terminal ? 'tabler-writing' : 'tabler-player-track-next'"
                          :loading="runningSampleStepId === sample.id"
                          :disabled="Boolean(runningSampleStepId)"
                          @click="runNextSandboxStep(sample)"
                        >
                          <template v-if="sample.steps[sample.current_step_index + 1]?.is_terminal">
                            Tiếp tục phân tích lần thứ {{ sample.current_step_index + 2 }} (Viết văn hoàn chỉnh)
                          </template>
                          <template v-else>
                            Tiếp tục phân tích lần thứ {{ sample.current_step_index + 2 }}: {{ cleanStepLabel(sample.steps[sample.current_step_index + 1]) }}
                          </template>
                        </VBtn>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <!-- ===== CỘT PHẢI: HIỆU CHỈNH THỜI GIAN THỰC (SANDBOX SẮC THÁI + CHẤT VĂN) ===== -->
            <VCol
              cols="12"
              lg="5"
            >
              <VExpansionPanels
                v-model="sandboxPanels"
                variant="accordion"
                class="expansion-panels-width-border"
              >
                <!-- ===== PANEL 1: SANDBOX SẮC THÁI (COLLAPSIBLE) ===== -->
                <VExpansionPanel
                  value="tone"
                  elevation="0"
                >
                  <VExpansionPanelTitle class="py-3 px-4">
                    <div class="d-flex align-center justify-space-between flex-wrap gap-2 w-100 me-2">
                      <div class="d-flex align-center gap-2">
                        <VAvatar
                          color="warning"
                          variant="tonal"
                          size="32"
                          rounded
                        >
                          <VIcon
                            icon="tabler-adjustments-heart"
                            size="18"
                          />
                        </VAvatar>
                        <div class="text-start">
                          <div class="text-body-2 font-weight-bold text-high-emphasis">
                            Sandbox sắc thái
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            8 thanh trượt phối âm cảm xúc & preset nhanh
                          </div>
                        </div>
                      </div>

                      <div class="d-flex align-center gap-2">
                        <VChip
                          v-if="hasModifiedToneSliders"
                          size="x-small"
                          color="warning"
                          variant="tonal"
                          label
                        >
                          Đã tinh chỉnh
                        </VChip>
                        <VBtn
                          size="x-small"
                          variant="text"
                          color="secondary"
                          prepend-icon="tabler-refresh"
                          :disabled="!hasModifiedToneSliders && selectedToneOptionIds.length === 0"
                          @click.stop="resetToneSliders"
                        >
                          Khôi phục gốc
                        </VBtn>
                      </div>
                    </div>
                  </VExpansionPanelTitle>

                  <VExpansionPanelText>
                    <div class="text-caption text-medium-emphasis mb-3 px-2">
                      Gạt slider hoặc chọn gợi ý sắc thái — phản ánh <strong>NGAY</strong> vào prompt khi viết thử:
                    </div>

                    <!-- Mẫu sắc thái / phối âm cảm xúc gợi ý (đồng bộ với Tab 7 & presets.json) -->
                    <div class="mb-4 px-2">
                      <AppSelect
                        v-model="selectedToneOptionIds"
                        :items="toneStyleSelectItems"
                        item-title="title"
                        item-value="value"
                        label="Chọn sắc thái / phối âm gợi ý"
                        placeholder="Nhấn để chọn sắc thái cảm xúc..."
                        multiple
                        chips
                        closable-chips
                        clearable
                        @update:model-value="recalculateToneFromSelect"
                      >
                        <template #item="{ props, item }">
                          <VListItem
                            v-bind="props"
                            :title="undefined"
                          >
                            <template #prepend>
                              <VIcon
                                :icon="item.raw.icon"
                                :color="item.raw.color"
                                size="18"
                                class="me-2"
                              />
                            </template>
                            <VListItemTitle class="text-body-2 font-weight-medium">
                              {{ item.raw.title }}
                            </VListItemTitle>
                            <VListItemSubtitle class="text-caption text-medium-emphasis">
                              {{ item.raw.attributeLabel }} — {{ item.raw.desc }}
                            </VListItemSubtitle>
                          </VListItem>
                        </template>
                      </AppSelect>
                    </div>

                    <!-- 8 slider sắc thái -->
                    <div class="d-flex flex-column gap-3 px-2">
                      <div
                        v-for="slider in toneSliders"
                        :key="slider.key"
                        class="pa-3 rounded border bg-surface"
                      >
                        <div class="d-flex align-center justify-space-between mb-2">
                          <div class="d-flex align-center">
                            <VAvatar
                              :color="slider.color"
                              variant="tonal"
                              size="28"
                              rounded
                              class="me-2 flex-shrink-0"
                            >
                              <VIcon
                                :icon="slider.icon"
                                size="15"
                              />
                            </VAvatar>
                            <span class="text-body-2 font-weight-medium">{{ slider.label }}</span>
                          </div>
                          <span
                            class="text-body-2 font-weight-bold"
                            :class="`text-${slider.color}`"
                          >
                            {{ slider.value == null ? 'Chưa xác định' : `${slider.value}%` }}
                          </span>
                        </div>
                        <VSlider
                          @update:model-value="slider.value = $event"
                              :model-value="slider.value ?? 50"
                          :min="0"
                          :max="100"
                          :step="1"
                          :color="slider.color"
                          hide-details
                          density="compact"
                        />
                        <div class="text-caption text-medium-emphasis mt-1">
                          {{ slider.hint }}
                        </div>
                      </div>
                    </div>
                  </VExpansionPanelText>
                </VExpansionPanel>

                <!-- ===== PANEL 2: CHẤT VĂN & NGHỆ THUẬT NHẤN – LƯỚT (COLLAPSIBLE) ===== -->
                <VExpansionPanel
                  value="voice"
                  elevation="0"
                >
                  <VExpansionPanelTitle class="py-3 px-4">
                    <div class="d-flex align-center justify-space-between flex-wrap gap-2 w-100 me-2">
                      <div class="d-flex align-center gap-2">
                        <VAvatar
                          color="primary"
                          variant="tonal"
                          size="32"
                          rounded
                        >
                          <VIcon
                            icon="tabler-signature"
                            size="18"
                          />
                        </VAvatar>
                        <div class="text-start">
                          <div class="text-body-2 font-weight-bold text-high-emphasis">
                            Chất văn & Nghệ thuật Nhấn – Lướt
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            5 thanh trượt chất văn & mẫu nhịp văn gợi ý
                          </div>
                        </div>
                      </div>

                      <div class="d-flex align-center gap-2">
                        <VChip
                          v-if="hasModifiedVoiceSliders"
                          size="x-small"
                          color="warning"
                          variant="tonal"
                          label
                        >
                          Đã tinh chỉnh
                        </VChip>
                        <VBtn
                          size="x-small"
                          variant="text"
                          color="secondary"
                          prepend-icon="tabler-refresh"
                          :disabled="!hasModifiedVoiceSliders && selectedVoiceOptionIds.length === 0"
                          @click.stop="resetVoiceTextureSliders"
                        >
                          Khôi phục gốc
                        </VBtn>
                      </div>
                    </div>
                  </VExpansionPanelTitle>

                  <VExpansionPanelText>
                    <div class="text-caption text-medium-emphasis mb-3 px-2">
                      Hiệu chỉnh khẩu ngữ, nhịp buông bắt và độ tự nhiên để kiểm chứng khẩu khí DNA:
                    </div>

                    <!-- Mẫu phong cách / nhịp văn gợi ý -->
                    <div class="mb-4 px-2">
                      <AppSelect
                        v-model="selectedVoiceOptionIds"
                        :items="VOICE_STYLE_OPTIONS"
                        item-title="title"
                        item-value="id"
                        label="Chọn phong cách / nhịp văn gợi ý"
                        placeholder="vd: Giọng thủ thỉ tâm tình, Nhấn lướt gắt..."
                        multiple
                        chips
                        closable-chips
                        clearable
                        @update:model-value="recalculateVoiceTextureFromCheckboxes"
                      >
                        <template #item="{ props, item }">
                          <VListItem
                            v-bind="props"
                            :title="undefined"
                          >
                            <template #prepend>
                              <VIcon
                                :icon="item.raw.icon"
                                :color="item.raw.color"
                                size="18"
                                class="me-2"
                              />
                            </template>
                            <VListItemTitle class="text-body-2 font-weight-medium">
                              {{ item.raw.title }}
                            </VListItemTitle>
                            <VListItemSubtitle class="text-caption text-medium-emphasis">
                              {{ item.raw.attributeLabel }} — {{ item.raw.desc }}
                            </VListItemSubtitle>
                          </VListItem>
                        </template>
                      </AppSelect>
                    </div>

                    <!-- 5 slider chất văn -->
                    <div class="d-flex flex-column gap-3 px-2">
                      <div
                        v-for="slider in voiceTextureSliders"
                        :key="slider.key"
                        class="pa-3 rounded border bg-surface"
                      >
                        <div class="d-flex align-center justify-space-between mb-2">
                          <div class="d-flex align-center">
                            <VAvatar
                              :color="slider.color"
                              variant="tonal"
                              size="28"
                              rounded
                              class="me-2 flex-shrink-0"
                            >
                              <VIcon
                                :icon="slider.icon"
                                size="15"
                              />
                            </VAvatar>
                            <span class="text-body-2 font-weight-medium">{{ slider.label }}</span>
                          </div>
                          <span
                            class="text-body-2 font-weight-bold"
                            :class="`text-${slider.color}`"
                          >
                            {{ slider.value == null ? 'Chưa xác định' : `${slider.value}%` }}
                          </span>
                        </div>
                        <VSlider
                          @update:model-value="slider.value = $event"
                              :model-value="slider.value ?? 50"
                          :min="0"
                          :max="100"
                          :step="1"
                          :color="slider.color"
                          hide-details
                          density="compact"
                        />
                        <div class="text-caption text-medium-emphasis mt-1">
                          {{ slider.hint }}
                        </div>
                      </div>
                    </div>
                  </VExpansionPanelText>
                </VExpansionPanel>
              </VExpansionPanels>
            </VCol>
          </VRow>
        </VWindowItem>
      </VWindow>
    </template>

    <!-- Dialog xem chi tiết bài văn thử nghiệm (Dữ liệu động theo chuẩn §4) -->
    <SandboxSampleDetailDialog
      v-model="sandboxDetailDialogOpen"
      :sample="activeDialogSample"
      :profile="profile"
      :running-step="Boolean(runningSampleStepId)"
      @run-next-step="runNextSandboxStep"
      @refresh-outline="refreshSandboxOutline"
      @copy-text="txt => copyText(txt, 'Đã sao chép nội dung')"
    />

    <!-- Snackbar -->
    <VSnackbar
      v-model="snack.show"
      :color="snack.color"
      :timeout="3500"
      location="top right"
    >
      {{ snack.text }}
    </VSnackbar>
  </div>
</template>

<style scoped lang="scss">
/* Mở rộng vùng padding-box của VWindow thêm 16px (bù bằng margin -16px)
 * để vừa giữ nguyên hiệu ứng trượt ngang khi chuyển tab, vừa không cắt
 * mất box-shadow (elevation 6) ở 4 cạnh của các VCard bên trong tab. */
.profile-tab-window {
  padding: 16px;
  margin: -16px;
  overflow-anchor: none;
}

@media (min-width: 960px) {
  .advanced-nav-col {
    width: 285px;
    flex: 0 0 285px;
  }
}

.advanced-vertical-tabs {
  :deep(.v-btn) {
    min-height: 40px !important;
    padding-inline: 12px !important;
    justify-content: flex-start;
    white-space: nowrap;
  }
}

/* Khi đang ở Tab 7 (Cài đặt nâng cao), ẩn thanh cuộn dọc của trình duyệt nhưng vẫn giữ nguyên Footer hiển thị bên dưới */
:global(html:has(body.writing-profile-advanced-no-scroll)),
:global(body.writing-profile-advanced-no-scroll) {
  overflow-y: hidden !important;
}

:global(body.writing-profile-advanced-no-scroll .layout-page-content) {
  padding-bottom: 8px !important;
  padding-block-end: 8px !important;
}

/* Card Prompt ở Tab 7: chiều cao tự khớp phần màn hình phía trên Footer (không xuất hiện scroll trình duyệt) */
.advanced-prompt-card {
  height: var(--advanced-card-height, calc(100vh - 435px)) !important;
  max-height: var(--advanced-card-height, calc(100vh - 435px)) !important;
  display: flex !important;
  flex-direction: column !important;
  overflow: hidden !important;

  :deep(.v-card-item) {
    flex-shrink: 0;
    padding-top: 14px !important;
    padding-bottom: 10px !important;
  }

  :deep(.v-card-text) {
    flex: 1 1 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding-top: 0 !important;
    padding-bottom: 15px !important; /* Các thanh trượt cách đáy card đúng 15px */
    overflow: hidden !important;
  }
}

.advanced-prompt-row {
  flex: 1 1 0;
  min-height: 0;
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  height: 100%;
}

@media (min-width: 960px) {
  .advanced-prompt-row {
    flex-wrap: nowrap !important;
  }
}

.advanced-prompt-left-col {
  display: flex;
  flex-direction: column;
  height: 100%;
  max-height: 100%;
  min-height: 0;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  overflow: hidden;
}

.advanced-prompt-right-col {
  display: flex;
  flex-direction: column;
  height: 100%;
  max-height: 100%;
  min-height: 0;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  overflow: hidden;

  :deep(.app-text-field) {
    flex-grow: 0 !important;
  }
}

/* Textarea co giãn linh hoạt theo chiều cao còn lại của cột trái, cuộn nội bộ bên trong ô */
.advanced-flex-textarea {
  flex: 1 1 0;
  min-height: 110px;
  display: flex;
  flex-direction: column;

  :deep(.v-input),
  :deep(.v-input__control),
  :deep(.v-field),
  :deep(.v-field__field) {
    flex: 1 1 0;
    min-height: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
  }

  :deep(textarea.v-field__input) {
    flex: 1 1 0;
    min-height: 0 !important;
    height: 100% !important;
    overflow-y: auto !important;
    resize: none;
  }
}

.summary-grid-2col {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 16px;
}

/* Khung expansion panel thanh trượt: giữ nguyên hiệu ứng trượt v-expand-transition mượt mà và không làm giật/mất thanh cuộn khi thu gọn */
.advanced-slider-panels {
  width: 100%;
  flex: 0 0 auto;

  :deep(.v-expansion-panel) {
    overflow: hidden;
  }

  :deep(.v-expansion-panel-text) {
    overflow: hidden !important;

    .v-expansion-panel-text__wrapper {
      padding: 8px 12px 12px 12px !important;
    }
  }

  :deep(.slider-scroll-box) {
    height: var(--slider-scroll-height, 220px);
    max-height: var(--slider-scroll-height, 220px);
    overflow-y: auto !important;
    scrollbar-gutter: stable;
    overscroll-behavior: contain;
    padding-right: 6px;

    > div {
      flex-shrink: 0 !important;
    }
  }
}

.slider-scroll-box {
  overflow-y: auto;
  padding-right: 6px;
  overscroll-behavior: contain;
}

/* Chống giật (jitter) khi đóng/mở VExpansionPanel và khi cuộn trang lên/xuống */
:deep(.v-expansion-panels) {
  overflow-anchor: none;
}

:deep(.v-expansion-panel-title),
:deep(.v-expansion-panel-title--active) {
  min-height: 54px !important;
  transition: background-color 0.15s ease !important;
}

/* Triệt tiêu hiện tượng bóng ma (ghosting) khi kéo chuột trực tiếp (.v-slider--pressed), nhưng giữ hiệu ứng trượt mượt khi click hoặc chọn preset */
:deep(.v-slider) {
  contain: layout style;

  &.v-slider--pressed {
    .v-slider-track__fill,
    .v-slider-thumb {
      transition: none !important;
    }
  }

  /* Vô hiệu hóa ripple và halo ::before gây vệt mờ/dư ảnh subpixel khi di chuột nhanh */
  .v-slider-thumb__ripple,
  .v-slider-thumb__surface::before {
    display: none !important;
  }

  .v-slider-thumb__surface {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
  }
}

/* Đảm bảo placeholder hiển thị liên tục, không cần click/focus mới hiện */
:deep(.v-field--persistent-placeholder) input::placeholder,
:deep(.v-field--persistent-placeholder) textarea::placeholder,
:deep(.persistent-placeholder) textarea::placeholder {
  opacity: 0.6 !important;
}

/* Ô bật/tắt kênh hiển thị. */
.channel-toggle-box {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  transition: border-color 0.15s ease;
}
.channel-toggle-box:hover {
  border-color: rgb(var(--v-theme-primary));
}

/* Item lịch sử đang là bản hiện tại. */
.ver-item--current {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.04);
}

/* Viền nổi bật cho bản ghi đang chạy từng step (in_progress) trong Phòng thử DNA */
.sample-card--in-progress {
  border-color: rgba(var(--v-theme-warning), 0.55) !important;
  background: rgba(var(--v-theme-warning), 0.02);
}

.sample-card--completed {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
