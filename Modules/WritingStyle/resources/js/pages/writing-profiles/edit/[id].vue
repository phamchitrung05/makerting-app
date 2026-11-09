<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang Chỉnh sửa Writing Profile (route edit).
  =====================================================================
  Route: /admin/writing-style/writing-profiles/edit/:id
  Tự nạp qua file-based routing — tên 'admin-writing-style-writing-profiles-edit-id'.

  Nối API thật:
  - onMounted: store.fetchProfile(id) -> GET /writing-profiles/{id}
  (kèm categories/tags/analyses eager-load từ controller show()).
  - Map vào 8 tab theo rule_key:
  Banner+Tổng quan: name/version/description/is_enabled/slug/status
  Đặc điểm văn phong: rules_json.tone|emotion|pronouns|vocabulary + style_instructions
  Cấu trúc nội dung: rules_json.opening|structure_patterns|transitions|ending
  Quy tắc ngôn ngữ: rules_json.pronouns + editorialRules(avoid+vocabulary)
  Mẫu nội dung: evidence_json
  Từ vựng & CTA: rules_json.vocabulary/avoid
  Cài đặt nâng cao: style_instructions + ai_model_used (hiển thị)
  Lịch sử: analyses (đã load) + version hiện tại
  - saveProfile() -> PUT /writing-profiles/{id}: chỉ merge các khóa rules_json
  user được chỉnh trên UI (không đè 9 khóa còn lại) + categories/tags +
  status/is_enabled + slug + description + style_instructions.

  UI-only giữ nguyên (KHÔNG map, không lưu — doc §10.3):
  radar 6 trục, expressionLevel, sampleQuotes, displayChannels,
  overviewForm.industry/primaryGoal/targetAudience/brandPillars, avatar,
  emojiDensity, ctaList, temperature, autoScoreCheck, nút "Khôi phục" lịch sử.

  CHUẨN UI (docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §3.5):
  PascalCase V*/App*, icon tabler-*, màu theme, spacing logical me/ms,
  không inline font-size, không mồi dữ liệu giả cho phần đã có API.
  =====================================================================
-->
<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SlugField from '@modules/Core/resources/js/components/SlugField.vue'
import TaxonomyField from '@modules/Taxonomy/resources/js/components/TaxonomyField.vue'
import { firstErrorMessage } from '@/utils/api'
import { useWritingStyleStore } from '../../../stores/writingStyle'

definePage({ meta: { layout: 'default' } })

const route = useRoute()
const router = useRouter()
const store = useWritingStyleStore()

const profile = ref(null)
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

const prevQuote = () => {
  currentQuoteIdx.value = (currentQuoteIdx.value - 1 + sampleQuotes.value.length) % sampleQuotes.value.length
}

const nextQuote = () => {
  currentQuoteIdx.value = (currentQuoteIdx.value + 1) % sampleQuotes.value.length
}

const addSampleQuote = () => {
  sampleQuotes.value.push({ text: 'Nội dung mẫu mới…', image: '', tags: [] })
  currentQuoteIdx.value = sampleQuotes.value.length - 1
}

/* ---------------------------------------------------------------------
 * TAB 3: CẤU TRÚC NỘI DUNG — render từ rules_json
 * ------------------------------------------------------------------- */
const structureSections = ref([]) // [{title, weight, sentences, guideline, sample}]

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

function addPowerWord() {
  const w = vocabConfig.newPowerWord.trim()
  if (w && !vocabConfig.powerWords.includes(w)) {
    vocabConfig.powerWords.push(w)
    vocabConfig.newPowerWord = ''
  }
}

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
const VOICE_STYLE_OPTIONS = [
  {
    id: 'intimate_whisper',
    title: 'Thủ thỉ tâm tình & Kể chuyện gần gũi',
    attributeLabel: 'Giọng văn',
    color: 'primary',
    icon: 'tabler-microphone-2',
    desc: 'Hạ thấp khoảng cách với người đọc, trò chuyện ấm áp như tri kỷ bên tách trà.',
    deltas: { voice_timbre: 25, emotional_nuance: 15, vernacular_flow: 10, rhythm_variation: 0, emphasis_glide: 5 },
    directive: 'Giọng văn trầm ấm, thủ thỉ như một người bạn tri kỷ đang kể chuyện 1-1, không lên gân thuyết giáo.',
  },
  {
    id: 'sharp_poised',
    title: 'Đĩnh đạc, sắc sảo & Chuẩn mực chuyên gia',
    attributeLabel: 'Giọng văn',
    color: 'primary',
    icon: 'tabler-award',
    desc: 'Khẩu khí chững chạc, lập luận gãy gọn, tạo độ tin cậy cao và tiết chế cảm tính.',
    deltas: { voice_timbre: -20, vernacular_flow: -15, emotional_nuance: -15, rhythm_variation: 5, emphasis_glide: 15 },
    directive: 'Giọng văn đĩnh đạc, điềm tĩnh và sắc sảo; ưu tiên nhận định gãy gọn, xác đáng của người có trải nghiệm sâu.',
  },
  {
    id: 'vernacular_natural',
    title: 'Dân dã đời thường & Thuận tai người Việt',
    attributeLabel: 'Tính dân dã tự nhiên',
    color: 'success',
    icon: 'tabler-message-circle',
    desc: 'Từ thuần Việt dung dị, mở câu bằng từ nối cảm xúc tự nhiên, thoát mùi văn dịch AI.',
    deltas: { voice_timbre: 15, vernacular_flow: 30, emotional_nuance: 10, rhythm_variation: 10, emphasis_glide: 0 },
    directive: 'Ưu tiên từ thuần Việt dung dị, thuận tai; cho phép mở câu bằng từ nối cảm xúc đời thường (Mà thật ra, Ấy vậy mà, Đôi khi...).',
  },
  {
    id: 'quiet_luxury_polish',
    title: 'Trau chuốt tinh tế & Tiết chế (Quiet Luxury)',
    attributeLabel: 'Tính dân dã tự nhiên',
    color: 'success',
    icon: 'tabler-diamond',
    desc: 'Từ vựng chọn lọc kỹ lưỡng, sang trọng kín đáo, tuyệt đối tránh khẩu ngữ suồng sã.',
    deltas: { voice_timbre: 5, vernacular_flow: -20, emotional_nuance: 15, rhythm_variation: 0, emphasis_glide: 20 },
    directive: 'Câu chữ gọt giũa tinh tế theo tinh thần Quiet Luxury; chọn lọc từ ngữ giàu sức gợi, tránh khẩu ngữ suồng sã.',
  },
  {
    id: 'deep_resonance',
    title: 'Sâu lắng, giàu dư âm & Gợi tả giác quan',
    attributeLabel: 'Màu sắc cảm xúc',
    color: 'error',
    icon: 'tabler-heart-spark',
    desc: 'Không dùng tính từ sáo rỗng, để chi tiết ánh sáng/âm thanh/xúc giác tự gợi nỗi niềm.',
    deltas: { voice_timbre: 15, vernacular_flow: 5, emotional_nuance: 30, rhythm_variation: 10, emphasis_glide: 15 },
    directive: 'Màu sắc cảm xúc lắng đọng, giàu dư âm; truyền tải qua chi tiết giác quan và khoảng lặng thay vì tính từ cảm thán sáo rỗng.',
  },
  {
    id: 'dynamic_cadence',
    title: 'Co giãn nhịp thở (Câu dài xen câu cực ngắn)',
    attributeLabel: 'Biến thiên nhịp điệu',
    color: 'warning',
    icon: 'tabler-wave-sine',
    desc: 'Sau 2–3 câu ghép dài miêu tả liên hoàn sẽ đặt 1 câu đơn 3–6 chữ để chốt nhịp.',
    deltas: { voice_timbre: 0, vernacular_flow: 10, emotional_nuance: 10, rhythm_variation: 30, emphasis_glide: 15 },
    directive: 'Biến thiên nhịp câu rõ rệt: đan xen 2–3 câu ghép dài nhiều vế với 1 câu đơn cực ngắn (3–6 chữ) đứng riêng làm điểm rơi.',
  },
  {
    id: 'steady_calm_flow',
    title: 'Nhịp văn đều đặn, êm ả & Thư thái',
    attributeLabel: 'Biến thiên nhịp điệu',
    color: 'warning',
    icon: 'tabler-ripple',
    desc: 'Duy trì nhịp thở chậm rãi, liền mạch, dẫn dắt người đọc tuần tự không giật cục.',
    deltas: { voice_timbre: 10, vernacular_flow: 0, emotional_nuance: 5, rhythm_variation: -25, emphasis_glide: -10 },
    directive: 'Duy trì nhịp điệu hành văn êm ả, khoan thai; các câu nối tiếp nhau mượt mà, không ngắt nhịp đột ngột.',
  },
  {
    id: 'zoom_in_glide',
    title: 'Lướt nhanh ý phụ – Zoom-in nhấn sâu chi tiết đắt',
    attributeLabel: 'Tính nhấn và lướt',
    color: 'info',
    icon: 'tabler-focus-2',
    desc: 'Gói gọn thông tin nền trong 1 câu lướt nhanh, dừng lại khắc họa cận cảnh điểm sáng.',
    deltas: { voice_timbre: 0, vernacular_flow: 0, emotional_nuance: 15, rhythm_variation: 15, emphasis_glide: 30 },
    directive: 'Áp dụng triệt để nghệ thuật Nhấn – Lướt: lướt nhanh thông tin nền phụ trong 1 câu ngắn, dừng lại zoom-in khắc họa sâu 1 chi tiết đắt giá nhất.',
  },
]

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
  VOICE_STYLE_OPTIONS.map(opt => ({
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

function recalculateVoiceTextureFromCheckboxes() {
  const checkedItems = VOICE_STYLE_OPTIONS.filter(opt =>
    selectedVoiceOptionIds.value.includes(opt.id),
  )

  // 1. Tính lại điểm 5 thanh trượt từ baseline + tổng delta các ô đã chọn
  voiceTextureSliders.forEach(slider => {
    const base = typeof slider.baseline === 'number' ? slider.baseline : 50
    if (!checkedItems.length) {
      slider.value = base
      
      return
    }

    const totalDelta = checkedItems.reduce(
      (sum, item) => sum + (Number(item.deltas?.[slider.key]) || 0),
      0,
    )

    slider.value = Math.max(5, Math.min(100, base + totalDelta))
  })

  // 2. Đồng bộ các dòng chỉ đạo đã tích chọn vào Ô 2 (giữ nguyên phần văn bản gốc của AI)
  const allDirectives = new Set(VOICE_STYLE_OPTIONS.map(o => `- ${o.directive}`))

  const currentLines = String(advancedConfig.voiceTexturePrompt || '')
    .split('\n')
    .map(l => l.trim())
    .filter(l => l !== '' && !allDirectives.has(l))

  const selectedLines = checkedItems.map(o => `- ${o.directive}`)

  advancedConfig.voiceTexturePrompt = [...currentLines, ...selectedLines].join('\n')
}

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
const TONE_STYLE_OPTIONS = [
  {
    id: 'humorous',
    title: 'Hóm hỉnh & Gần gũi',
    attributeLabel: 'Vui vẻ & Hóm hỉnh',
    color: 'warning',
    icon: 'tabler-mood-smile',
    desc: 'Chêm xen ví dụ dí dỏm, chơi chữ tinh tế, tạo không khí nhẹ nhàng.',
    deltas: { humor: 30, formality: -15, empathy: 20 },
    directive: 'Giữ sắc thái vui vẻ, dí dỏm; đan cài ví dụ hóm hỉnh và chơi chữ tinh tế để kéo gần khoảng cách với người đọc.',
  },
  {
    id: 'empathy',
    title: 'Đồng cảm & Trân trọng',
    attributeLabel: 'Trân trọng & Đồng cảm',
    color: 'error',
    icon: 'tabler-heart',
    desc: 'Ghi nhận nỗ lực độc giả, thấu hiểu khó khăn, giọng điệu ấm áp.',
    deltas: { empathy: 40, candor: 30, formality: -5 },
    directive: 'Sắc thái trân trọng, giàu lòng trắc ẩn; luôn ghi nhận trăn trở và khó khăn của độc giả trước khi đưa ra gợi ý.',
  },
  {
    id: 'assertive',
    title: 'Sắc sảo & Quyết đoán',
    attributeLabel: 'Sắc sảo & Quyết đoán',
    color: 'primary',
    icon: 'tabler-bolt',
    desc: 'Luận điểm đanh thép, lập trường dứt khoát, giảm từ ngập ngừng.',
    deltas: { assertiveness: 40, depth: 40, urgency: 20 },
    directive: 'Khẩu khí dứt khoát, sắc bén; khẳng định quan điểm mạnh mẽ bằng lý lẽ thuyết phục, không dùng từ ngữ do dự.',
  },
  {
    id: 'formality_academic',
    title: 'Trang trọng & Chuẩn mực học thuật',
    attributeLabel: 'Trang trọng',
    color: 'info',
    icon: 'tabler-scale',
    desc: 'Văn phong đĩnh đạc, chuẩn mực báo chí/nghiên cứu, khách quan.',
    deltas: { formality: 35, depth: 25, humor: -20 },
    directive: 'Duy trì sắc thái trang trọng, điềm tĩnh; phân tích có tính hệ thống và dẫn chứng minh bạch chuẩn mực.',
  },
  {
    id: 'inspiring',
    title: 'Truyền lửa & Thôi thúc',
    attributeLabel: 'Nhiệt huyết & Truyền lửa',
    color: 'warning',
    icon: 'tabler-flame',
    desc: 'Tiết tấu hào sảng, thắp sáng động lực, khơi gợi niềm tin bứt phá.',
    deltas: { inspiration: 40, urgency: 25, humor: 10 },
    directive: 'Giọng văn tràn đầy năng lượng, thôi thúc hành động; khơi gợi khát vọng và tinh thần dám nghĩ dám làm.',
  },
  {
    id: 'candor_authentic',
    title: 'Chân thực, Gai góc & Bộc trực',
    attributeLabel: 'Chân thực & Bộc trực',
    color: 'success',
    icon: 'tabler-shield-check',
    desc: 'Kể chuyện thực tế, dám nói sự thật góc khuất, không né tránh.',
    deltas: { candor: 40, empathy: 20, formality: -15 },
    directive: 'Thẳng thắn bộc trực, dám nhìn thẳng vào thực tế gai góc và mặt trái của vấn đề, không tô hồng hay sáo rỗng.',
  },
  {
    id: 'intellectual_depth',
    title: 'Trí tuệ, Chuyên sâu & Bản chất',
    attributeLabel: 'Trí tuệ & Chuyên sâu',
    color: 'primary',
    icon: 'tabler-brain',
    desc: 'Phân tích bản chất, cơ chế hoạt động ngầm, kiến trúc, số liệu.',
    deltas: { depth: 45, assertiveness: 20, formality: 10 },
    directive: 'Tư duy sắc sảo, giải phẫu vấn đề tới tận tầng bản chất cốt lõi; ưu tiên số liệu và mô hình hóa tư duy.',
  },
  {
    id: 'urgent_call',
    title: 'Khẩn thiết & Kêu gọi hành động ngay',
    attributeLabel: 'Khẩn thiết & Kêu gọi',
    color: 'error',
    icon: 'tabler-alarm',
    desc: 'Nhấn mạnh tính cấp thiết của thời điểm, thôi thúc quyết định.',
    deltas: { urgency: 45, assertiveness: 25, inspiration: 15 },
    directive: 'Tạo cảm giác cấp bách, nhấn mạnh cơ hội và rủi ro nếu chần chừ; hướng độc giả tới hành động cụ thể ngay lập tức.',
  },
]

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
  TONE_STYLE_OPTIONS.map(opt => ({
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
 * CHỨC NĂNG HÀM: Tính lại điểm 8 thanh trượt và đồng bộ chỉ đạo vào Ô 3
 * =====================================================================
 * Được gọi khi người dùng chọn / bỏ chọn sắc thái trong AppSelect gợi ý.
 *
 * INPUT/OUTPUT:
 * - INPUT : selectedToneOptionIds (ref mảng ID đã chọn)
 * - OUTPUT: Cập nhật trực tiếp toneSliders (8 sliders) và advancedConfig.tonePrompt
 * =====================================================================
 */
function recalculateToneFromSelect() {
  const checkedItems = TONE_STYLE_OPTIONS.filter(opt =>
    selectedToneOptionIds.value.includes(opt.id),
  )

  // 1. Tính lại điểm 8 thanh trượt từ baseline + tổng delta các ô đã chọn
  toneSliders.forEach(slider => {
    const base = typeof slider.baseline === 'number' ? slider.baseline : 50
    if (!checkedItems.length) {
      slider.value = base
      return
    }

    const totalDelta = checkedItems.reduce(
      (sum, item) => sum + (Number(item.deltas?.[slider.key]) || 0),
      0,
    )

    slider.value = Math.max(0, Math.min(100, base + totalDelta))
  })

  // 2. Đồng bộ các dòng chỉ đạo đã tích chọn vào Ô 3 (giữ nguyên phần văn bản gốc)
  const allDirectives = new Set(TONE_STYLE_OPTIONS.map(o => `- ${o.directive}`))

  const currentLines = String(advancedConfig.tonePrompt || '')
    .split('\n')
    .map(l => l.trim())
    .filter(l => l !== '' && !allDirectives.has(l))

  const selectedLines = checkedItems.map(o => `- ${o.directive}`)

  advancedConfig.tonePrompt = [...currentLines, ...selectedLines].join('\n')
}

/**
 * =====================================================================
 * CHỨC NĂNG HÀM: Reset 8 thanh trượt Cảm xúc về baseline và xóa gợi ý đã chọn
 * =====================================================================
 */
function resetToneSliders() {
  selectedToneOptionIds.value = []
  toneSliders.forEach(t => {
    t.value = t.baseline
  })

  const allDirectives = new Set(TONE_STYLE_OPTIONS.map(o => `- ${o.directive}`))

  advancedConfig.tonePrompt = String(advancedConfig.tonePrompt || '')
    .split('\n')
    .map(l => l.trim())
    .filter(l => l !== '' && !allDirectives.has(l))
    .join('\n')

  snack.text = 'Đã khôi phục 8 thanh trượt Sắc thái cảm xúc về cân bằng'
  snack.color = 'info'
  snack.show = true
}

function resetVoiceTextureSliders() {
  selectedVoiceOptionIds.value = []
  voiceTextureSliders.forEach(s => {
    s.value = s.baseline
  })

  const allDirectives = new Set(VOICE_STYLE_OPTIONS.map(o => `- ${o.directive}`))

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
 * TAB 9 (MỚI): PHÒNG THỬ DNA — sandbox + tone calibration
 * ------------------------------------------------------------------- */
const sandboxForm = reactive({
  topic: '',
  targetTier: 'medium',
})

const sandboxGeneratedText = ref('')
const sandboxSnapshotPrompt = ref('')
const sandboxHasRan = ref(false)

/** Danh sách các bản viết thử đã lưu trong DB của hồ sơ này (sandbox_samples_json) */
const savedSandboxSamples = ref([])
const savingSandboxSample = ref(false)
const deletingSandboxSampleId = ref(null)
const expandedSamplePrompts = reactive({})

/** Quản lý trạng thái mở/đóng các panel trong Tab 9 Phòng thử DNA (chế độ accordion đơn, mặc định đóng cả 2):
 * null: Đóng cả 2 panel mặc định
 * 'tone': Mở Sandbox sắc thái (tự đóng Chất văn)
 * 'voice': Mở Chất văn & Nghệ thuật Nhấn – Lướt (tự đóng Sandbox sắc thái)
 */
const sandboxPanels = ref(null)

/* Chỉ render văn bản THẬT sandbox sinh — KHÔNG có fallback mẫu. */
const activeSandboxSample = computed(() => sandboxGeneratedText.value)

/* Preset hiệu chỉnh tone — giống trang create; khi chọn preset đẩy các
 * slider tương ứng lên cao rồi gửi tone_overrides xuống Sandbox. */
const TONE_PRESETS = [
  { title: 'DNA Gốc', value: 'default' },
  { title: 'Hóm hỉnh & Gần gũi', value: 'humorous' },
  { title: 'Đồng cảm & Trân trọng', value: 'empathy' },
  { title: 'Sắc sảo & Quyết đoán', value: 'assertive' },
  { title: 'Truyền lửa & Thôi thúc', value: 'inspiring' },
]

const activeTonePreset = ref('default')

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

function applyTonePreset(presetKey) {
  activeTonePreset.value = presetKey
  selectedToneOptionIds.value = presetKey === 'default' ? [] : [presetKey]
  recalculateToneFromSelect()
  if (presetKey === 'default') {
    toneSliders.forEach(t => { t.value = t.baseline })
    snack.text = 'Đã khôi phục phổ cảm xúc về mặc định'
  } else if (presetKey === 'humorous') {
    toneSliders.forEach(t => {
      if (t.key === 'humor') t.value = 80
      else if (t.key === 'formality') t.value = 35
      else if (t.key === 'empathy') t.value = 70
      else t.value = t.baseline
    })
    snack.text = 'Đã áp dụng preset: Hóm hỉnh & Gần gũi'
  } else if (presetKey === 'empathy') {
    toneSliders.forEach(t => {
      if (t.key === 'empathy') t.value = 90
      else if (t.key === 'candor') t.value = 80
      else if (t.key === 'formality') t.value = 45
      else t.value = t.baseline
    })
    snack.text = 'Đã áp dụng preset: Đồng cảm & Trân trọng'
  } else if (presetKey === 'assertive') {
    toneSliders.forEach(t => {
      if (t.key === 'assertiveness') t.value = 90
      else if (t.key === 'depth') t.value = 90
      else if (t.key === 'urgency') t.value = 70
      else t.value = t.baseline
    })
    snack.text = 'Đã áp dụng preset: Sắc sảo & Quyết đoán'
  } else if (presetKey === 'inspiring') {
    toneSliders.forEach(t => {
      if (t.key === 'inspiration') t.value = 90
      else if (t.key === 'urgency') t.value = 75
      else if (t.key === 'humor') t.value = 60
      else t.value = t.baseline
    })
    snack.text = 'Đã áp dụng preset: Truyền lửa & Thôi thúc'
  }
  snack.color = 'primary'
  snack.show = true
}

function resetToneToBaseline() { applyTonePreset('default') }

/* style_instructions gửi lên backend = style_instructions của profile hiện
 * tại (KHÔNG ghép TONE CALIBRATION phía client — backend tự render section
 * đó từ `tone_overrides` qua SnapshotService::renderToneSection, ghép thêm
 * ở đây sẽ sinh 2 khối calibration trùng lặp trong system prompt). */
const sandboxStyleInstructions = computed(() => {
  return advancedConfig.systemPrompt || profile.value?.style_instructions || ''
})

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

  // tone_overrides: key => percent hiện tại trên các slider (8 trục).
  const toneOverrides = Object.fromEntries(
    toneSliders.map(t => [t.key, Number(t.value) || 0]),
  )

  // voice_texture_sliders_json: 5 thanh trượt Chất văn hiện tại ở Tab 7.
  const voiceSlidersPayload = Object.fromEntries(
    voiceTextureSliders.map(s => [s.key, Number(s.value) || 0]),
  )

  try {
    const res = await store.sandbox(profile.value.id, {
      topic,
      target_tier: sandboxForm.targetTier || profile.value.dominant_tier,

      // style_instructions (Ô 1) + voice_texture_instructions (Ô 2) + 5 sliders Chất văn
      style_instructions: sandboxStyleInstructions.value || undefined,
      voice_texture_instructions: advancedConfig.voiceTexturePrompt?.trim() || undefined,
      tone_instructions: advancedConfig.tonePrompt?.trim() || undefined,
      voice_texture_sliders_json: voiceSlidersPayload,
      tone_overrides: toneOverrides,
      temperature: Number(advancedConfig.temperature),
    })

    sandboxGeneratedText.value = res?.text ?? ''
    sandboxHasRan.value = true

    if (res?.snapshot?.system_prompt) {
      sandboxSnapshotPrompt.value = res.snapshot.system_prompt
    }

    const tierLabel = (res?.target_tier || sandboxForm.targetTier).toUpperCase()

    snack.text = `Đã sinh văn bản thử nghiệm ở cấp độ "${tierLabel}"`
    snack.color = 'success'
    snack.show = true
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Lưu (Ghim) bản viết thử hiện tại vào Database của hồ sơ
 * =====================================================================
 */
async function saveCurrentSandboxSample() {
  if (!profile.value?.id || !activeSandboxSample.value) return

  savingSandboxSample.value = true
  try {
    const res = await store.saveSandboxSample(profile.value.id, {
      topic: sandboxForm.topic.trim() || 'Chủ đề thử nghiệm',
      target_tier: sandboxForm.targetTier || profile.value.dominant_tier || 'medium',
      text: activeSandboxSample.value,
      system_prompt: sandboxSnapshotPrompt.value || '',
    })

    if (Array.isArray(res?.samples)) {
      savedSandboxSamples.value = res.samples
    }

    snack.text = 'Đã lưu bản viết thử vào hồ sơ để so sánh'
    snack.color = 'success'
    snack.show = true
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  } finally {
    savingSandboxSample.value = false
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Xóa 1 bản viết thử đã lưu khỏi Database
 * =====================================================================
 */
async function removeSandboxSample(sampleId) {
  if (!profile.value?.id || !sampleId) return

  deletingSandboxSampleId.value = sampleId
  try {
    const res = await store.deleteSandboxSample(profile.value.id, sampleId)
    if (Array.isArray(res?.samples)) {
      savedSandboxSamples.value = res.samples
    }
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
function joinPronouns(brand, customer) {
  const b = String(brand ?? '').trim()
  const c = String(customer ?? '').trim()
  if (b && c) return `${b}, gọi ${c}`

  return b || c
}

/* Convert rules_json.avoid text -> mảng {text, allowed:false}.
 * Trong mẫu, avoid thường là câu dài liệt kê — tách bằng dấu chấm câu. */
function avoidToRules(text) {
  const items = String(text ?? '')
    .split(/[.\n]+/)
    .map(s => s.trim())
    .filter(s => s.length >= 4)

  return items.map(s => ({ text: s, allowed: false }))
}

function vocabToRules(text) {
  const words = extractQuotedWords(text)

  return words.length
    ? words.map(w => ({ text: `Nên dùng "${w}"`, allowed: true }))
    : []
}

/* ===================================================================
 * APPLY PROFILE -> FORM
 * =================================================================== */
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

  voiceTextureSliders.forEach(s => {
    const val = typeof savedVoiceSliders[s.key] === 'number'
      ? savedVoiceSliders[s.key]
      : s.defaultVal

    s.baseline = val
    s.value = val
  })

  // Đồng bộ trạng thái checkbox nếu trong Ô 2 đã có các dòng gợi ý tương ứng
  const currentVoiceText = String(p.voice_texture_instructions ?? '')

  selectedVoiceOptionIds.value = VOICE_STYLE_OPTIONS
    .filter(opt => currentVoiceText.includes(opt.directive))
    .map(opt => opt.id)

  // Nạp 8 thanh trượt Sandbox sắc thái cảm xúc từ DB (tone_sliders_json)
  const savedToneSliders = p.tone_sliders_json && typeof p.tone_sliders_json === 'object'
    ? p.tone_sliders_json
    : {}

  toneSliders.forEach(t => {
    const val = typeof savedToneSliders[t.key] === 'number'
      ? savedToneSliders[t.key]
      : 50

    t.baseline = val
    t.value = val
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

  // Cấu trúc nội dung — 3 phần lấy từ rule_keys (thân thiện với tier ngắn).
  const tierVal = p.dominant_tier
  const isMicro = tierVal === 'micro'
  const isShort = tierVal === 'short'

  structureSections.value = [
    {
      title: 'Cách mở bài (Opening)',
      weight: 'rules_json.opening',
      sentences: '',
      guideline: 'Cách AI nhận diện thủ pháp mở đầu trong bài mẫu.',
      sample: rules.opening || (isMicro ? 'Không áp dụng cho văn bản cực ngắn (Micro 1–3 câu — ý liền một mạch).' : 'Chưa có dữ liệu.'),
    },
    {
      title: 'Cấu trúc bài & Chuyển đoạn (Structure & Transitions)',
      weight: 'rules_json.structure_patterns',
      sentences: '',
      guideline: 'Mô hình khung bài tổng thể + nghệ thuật chuyển đoạn giữa các ý.',
      sample: [rules.structure_patterns, rules.transitions].filter(Boolean).join(' • ') || (isMicro || isShort ? 'Không áp dụng cho văn bản ngắn (Micro/Short — không chia dàn ý nhiều phần).' : 'Chưa có dữ liệu.'),
    },
    {
      title: 'Kết bài & CTA (Ending)',
      weight: 'rules_json.ending',
      sentences: '',
      guideline: 'Cách khép lại bài viết và phong cách kêu gọi hành động.',
      sample: rules.ending || 'Chưa có dữ liệu.',
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

  // Taxonomy
  categories.value = (p.categories ?? []).map(c => c.id)
  tags.value = (p.tags ?? []).map(t => t.id)
  newTags.value = []

  // Danh sách bản viết thử đã lưu trong Phòng thử DNA
  savedSandboxSamples.value = Array.isArray(p.sandbox_samples_json) ? p.sandbox_samples_json : []

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
function buildRulesJsonPayload() {
  const current = (profile.value?.rules_json && typeof profile.value.rules_json === 'object')
    ? { ...profile.value.rules_json }
    : {}

  // Các khóa có UI chỉnh: pronouns (từ 2 ô), vocabulary (powerWords),
  // avoid (negativeWords), opening/structure_patterns/transitions/ending
  // (3 structureSections), tone/emotion (từ traitSummaryList — chỉ đọc
  // trên UI nên không đè; nếu muốn chỉnh được thì thêm input).
  const pronounsJoined = joinPronouns(languageConfig.brandPronoun, languageConfig.customerPronoun)
  if (pronounsJoined) current.pronouns = pronounsJoined

  if (structureSections.value.length >= 3) {
    current.opening = structureSections.value[0]?.sample ?? current.opening


    // structure_patterns & transitions gộp trong section 2 — giữ chuỗi gộp,
    // không tách lại (vì UI chỉ hiển thị 1 sample gộp).
    const s2 = structureSections.value[1]?.sample ?? ''

    if (s2 && s2 !== 'Chưa có dữ liệu.') {
      // Tách lại theo ' • ' nếu có (rules gốc đã join bằng •).
      const parts = s2.split(' • ').map(s => s.trim()).filter(Boolean)

      if (parts.length >= 2) {
        current.structure_patterns = parts[0]
        current.transitions = parts.slice(1).join(' • ')
      } else {
        current.structure_patterns = s2
      }
    }
    current.ending = structureSections.value[2]?.sample ?? current.ending
  }

  // 1. Vocabulary + Power Words (ghép vào rules_json.vocabulary)
  let finalVocab = (vocabConfig.vocabularyDesc ?? '').trim()
  const kwIdx = finalVocab.search(/[;.]\s*Từ ngữ ưu tiên:/i)
  if (kwIdx > 0) {
    finalVocab = finalVocab.substring(0, kwIdx).trim()
  }

  if (Array.isArray(vocabConfig.powerWords) && vocabConfig.powerWords.length > 0) {
    const quotedList = vocabConfig.powerWords.map(w => `"${w.trim()}"`).join(', ')

    finalVocab = finalVocab
      ? `${finalVocab}. Từ ngữ ưu tiên: ${quotedList}.`
      : `Từ ngữ ưu tiên: ${quotedList}.`
  }

  if (finalVocab) {
    current.vocabulary = finalVocab.slice(0, 495)
  }

  // 2. CTA / Ending
  if (vocabConfig.ctaPhilosophy?.trim()) {
    current.ending = vocabConfig.ctaPhilosophy.trim()
  }

  // avoid từ negativeWords (các dòng đã bỏ allowed=false ra).
  if (Array.isArray(vocabConfig.negativeWords) && vocabConfig.negativeWords.length) {
    current.avoid = vocabConfig.negativeWords.join('. ')
  }

  return current
}

function buildEvidenceJsonPayload() {
  // Giữ nguyên evidence_json hiện tại — tab Mẫu nội dung chỉ hiển thị,
  // không cho chỉnh sửa (chưa thiết kế edit từng excerpt).
  return Array.isArray(profile.value?.evidence_json) ? profile.value.evidence_json : []
}

async function saveProfile() {
  const name = profileData.name.trim()
  if (!name) {
    snack.text = 'Vui lòng nhập tên Writing Profile'
    snack.color = 'warning'
    snack.show = true

    return
  }

  const voiceSlidersPayload = Object.fromEntries(
    voiceTextureSliders.map(s => [s.key, Number(s.value) || 0]),
  )

  const toneSlidersPayload = Object.fromEntries(
    toneSliders.map(t => [t.key, Number(t.value) || 0]),
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

function onNameBlur() { runSlugSuggest(profileData.name) }
function onSlugBlur() { runSlugSuggest(profileData.slug) }

/* SlugField update → đánh dấu dirty để chỉ gửi khi đổi. */
function onSlugInput(v) {
  profileData.slug = v
  slugDirty.value = true
}

/* ===================================================================
 * TÍNH CHIỀU CAO VCARD BÊN PHẢI Ở TAB 7 ĐỂ VỪA KHÍT VIEWPORT (GIỮ FOOTER, KHÔNG CÓ SCROLL TRÌNH DUYỆT)
 * =================================================================== */
const advancedCardHeight = ref('calc(100vh - 435px)')
const sliderScrollHeight = ref('220px')

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
                Đổ từ 3 khóa opening / structure_patterns+transitions / ending — chỉnh text sẽ lưu lại
              </template>
            </VCardItem>
            <VCardText>
              <VRow>
                <VCol
                  v-for="sec in structureSections"
                  :key="sec.title"
                  cols="12"
                  md="4"
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
                          {{ slider.value }}%
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
                                  {{ slider.value }}%
                                </span>
                              </div>
                            </div>

                            <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
                              <span>0%: {{ slider.lowPole }}</span>
                              <span>100%: {{ slider.highPole }}</span>
                            </div>

                            <VSlider
                              v-model="slider.value"
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
                          {{ slider.value }}%
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
                                  {{ slider.value }}%
                                </span>
                              </div>
                            </div>

                            <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
                              <span>0%: Tiết chế tối đa</span>
                              <span>100%: Bộc lộ mãnh liệt</span>
                            </div>

                            <VSlider
                              v-model="slider.value"
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
            <!-- ===== VCARD TRÁI: CHỦ ĐỀ THỬ NGHIỆM MỚI ===== -->
            <VCol
              cols="12"
              lg="7"
            >
              <VCard>
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
                      Nhập chủ đề bất kỳ để kiểm chứng khẩu khí DNA của hồ sơ này
                    </div>
                  </template>
                </VCardItem>

                <VCardText>
                  <AppTextField
                    v-model="sandboxForm.topic"
                    label="Chủ đề viết thử"
                    placeholder="vd: lợi ích của cà phê đen buổi sáng"
                    persistent-placeholder
                    class="mb-4"
                    @keyup.enter="runSandboxDemo"
                  />

                  <AppSelect
                    v-model="sandboxForm.targetTier"
                    :items="['micro','short','medium','long'].map(t => ({ title: t, value: t }))"
                    label="Cấp độ độ dài mục tiêu (Target Tier)"
                    class="mb-4"
                  />

                  <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-4">
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
                      :loading="store.sandboxing"
                      :disabled="store.sandboxing || !profile"
                      @click="runSandboxDemo"
                    >
                      Viết thử với DNA này
                    </VBtn>
                  </div>

                  <!-- Kết quả sinh văn bản -->
                  <div class="prompt-box pa-4 rounded">
                    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                      <div class="d-flex align-center gap-2">
                        <span class="text-body-2 font-weight-medium text-high-emphasis">
                          Văn bản sinh thử nghiệm
                        </span>
                        <VChip
                          size="x-small"
                          color="primary"
                          variant="tonal"
                          label
                        >
                          Tier: {{ (sandboxForm.targetTier || 'medium').toUpperCase() }}
                        </VChip>
                      </div>

                      <div class="d-flex align-center gap-2">
                        <VBtn
                          variant="tonal"
                          color="success"
                          size="x-small"
                          prepend-icon="tabler-bookmark-plus"
                          :loading="savingSandboxSample"
                          :disabled="!activeSandboxSample || savingSandboxSample"
                          @click="saveCurrentSandboxSample"
                        >
                          Lưu bản thử
                        </VBtn>

                        <VBtn
                          variant="text"
                          color="primary"
                          size="x-small"
                          prepend-icon="tabler-copy"
                          :disabled="!activeSandboxSample"
                          @click="copyText(activeSandboxSample, 'Đã sao chép văn bản viết thử')"
                        >
                          Sao chép
                        </VBtn>
                      </div>
                    </div>

                    <div class="text-body-2 text-high-emphasis sandbox-output">
                      <template v-if="!sandboxHasRan && !activeSandboxSample">
                        <span class="text-caption text-medium-emphasis">
                          Chưa có văn bản. Nhập chủ đề và bấm "Viết thử với DNA này" để sinh.
                        </span>
                      </template>
                      <template v-else-if="sandboxHasRan && !activeSandboxSample">
                        <span class="text-caption text-warning">
                          AI chưa sinh được nội dung — thử chủ đề khác hoặc đợi vài giây rồi gọi lại.
                        </span>
                      </template>
                      <template v-else>
                        {{ activeSandboxSample }}
                      </template>
                    </div>
                  </div>

                  <!-- Snapshot System Prompt đã gửi AI (xem để kiểm chứng) -->
                  <div
                    v-if="sandboxSnapshotPrompt"
                    class="mt-4"
                  >
                    <div class="text-caption text-medium-emphasis mb-2 d-flex align-center">
                      <VIcon
                        icon="tabler-terminal-2"
                        size="14"
                        class="me-1"
                      />
                      System Prompt đã gửi cho AI (snapshot đã strip evidence):
                    </div>
                    <div class="prompt-box pa-3 rounded font-mono text-caption">
                      <pre style="white-space: pre-wrap; margin: 0;">{{ sandboxSnapshotPrompt }}</pre>
                    </div>
                  </div>

                  <!-- Danh sách các bản viết thử đã lưu trong DB để đọc và so sánh -->
                  <div class="mt-6 pt-4 border-t">
                    <div class="d-flex align-center justify-space-between mb-3">
                      <div class="d-flex align-center gap-2">
                        <VIcon
                          icon="tabler-bookmarks"
                          color="primary"
                          size="18"
                        />
                        <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                          Các bản viết thử đã lưu ({{ savedSandboxSamples.length }})
                        </span>
                      </div>
                      <span class="text-caption text-medium-emphasis">
                        Lưu cố định vào Database của hồ sơ
                      </span>
                    </div>

                    <div
                      v-if="!savedSandboxSamples.length"
                      class="pa-4 rounded border border-dashed text-center text-caption text-medium-emphasis"
                    >
                      Chưa có bản viết thử nào được lưu. Sau khi chạy viết thử, bấm nút <strong>"Lưu bản thử"</strong> ở trên để ghim lại và đọc đối chiếu.
                    </div>

                    <div
                      v-else
                      class="d-flex flex-column gap-3"
                    >
                      <div
                        v-for="(sample, idx) in savedSandboxSamples"
                        :key="sample.id || idx"
                        class="pa-4 rounded border bg-surface"
                      >
                        <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
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
                            <span
                              v-if="sample.created_at"
                              class="text-caption text-disabled"
                            >
                              {{ new Date(sample.created_at).toLocaleString('vi-VN') }}
                            </span>
                          </div>

                          <div class="d-flex align-center gap-1">
                            <VBtn
                              v-if="sample.system_prompt"
                              variant="text"
                              color="secondary"
                              size="x-small"
                              :prepend-icon="expandedSamplePrompts[sample.id] ? 'tabler-eye-off' : 'tabler-terminal-2'"
                              @click="expandedSamplePrompts[sample.id] = !expandedSamplePrompts[sample.id]"
                            >
                              {{ expandedSamplePrompts[sample.id] ? 'Ẩn Prompt' : 'Xem Prompt' }}
                            </VBtn>

                            <VBtn
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

                        <div
                          class="text-body-2 text-high-emphasis pa-3 rounded prompt-box"
                          style="white-space: pre-wrap;"
                        >{{ sample.text }}</div>

                        <div
                          v-if="sample.system_prompt && expandedSamplePrompts[sample.id]"
                          class="mt-2 pa-3 rounded prompt-box font-mono text-caption"
                        >
                          <div class="text-caption text-medium-emphasis mb-1 font-weight-bold">
                            System Prompt lúc chạy bản này:
                          </div>
                          <pre style="white-space: pre-wrap; margin: 0;">{{ sample.system_prompt }}</pre>
                        </div>
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
                          :disabled="activeTonePreset === 'default' && !hasModifiedToneSliders"
                          @click.stop="resetToneToBaseline"
                        >
                          Khôi phục
                        </VBtn>
                      </div>
                    </div>
                  </VExpansionPanelTitle>

                  <VExpansionPanelText>
                    <div class="text-caption text-medium-emphasis mb-3 px-2">
                      Gạt slider hoặc chọn preset — phản ánh <strong>NGAY</strong> vào prompt khi viết thử:
                    </div>

                    <!-- Preset nhanh -->
                    <div class="mb-4 px-2">
                      <div class="text-caption text-medium-emphasis mb-2 font-weight-medium">
                        Mẫu phối âm nhanh (Preset)
                      </div>
                      <div class="d-flex flex-wrap gap-1">
                        <VBtn
                          v-for="preset in TONE_PRESETS"
                          :key="preset.value"
                          size="x-small"
                          :variant="activeTonePreset === preset.value ? 'flat' : 'tonal'"
                          :color="activeTonePreset === preset.value ? 'primary' : 'secondary'"
                          @click="applyTonePreset(preset.value)"
                        >
                          {{ preset.title }}
                        </VBtn>
                      </div>
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
                            {{ slider.value }}%
                          </span>
                        </div>
                        <VSlider
                          v-model="slider.value"
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
                            {{ slider.value }}%
                          </span>
                        </div>
                        <VSlider
                          v-model="slider.value"
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

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
