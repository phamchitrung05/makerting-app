<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang AI Phân tích bài viết & Tạo DNA Văn phong.
  =====================================================================

  Màn phân tích bài viết mẫu và đóng gói thành hồ sơ văn phong (WritingProfile):
  - Card 0 (Thông tin văn phong): field Tên văn phong và Slug định danh.
  - Card 1 (Nguồn bài viết): 3 tab nguồn đầu vào (Từ URL / Dán nội dung /
    Từ file), tích hợp TiptapEditor, tự động đếm từ, đếm câu và nhận diện
    cấp độ độ dài LengthTier (micro | short | medium | long) realtime.
  - Card 2 (Giải mã DNA & Phân tích chi tiết): 3 tab dữ liệu thật từ AI:
    1. Văn phong (4 chỉ số DNA, danh sách các quy tắc DNA thực tế trích xuất
       được theo cấp độ độ dài — có quy tắc nào hiển thị quy tắc đó).
    2. Cấu trúc (các quy tắc cấu trúc trích xuất được từ bài mẫu).
    3. Bằng chứng trích dẫn (các câu trích nguyên văn từ bài mẫu đã kiểm chứng).
  - Cột phải: System Prompt DNA (style_instructions) và Card Đóng gói & Lưu
    thành Writing Profile.
  =====================================================================
-->
<script setup>
/* eslint-disable camelcase */
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import TiptapEditor from '@core/components/TiptapEditor.vue'
import SlugField from '@modules/Core/resources/js/components/SlugField.vue'
import { firstErrorMessage } from '@/utils/api'
import { useWritingStyleStore } from '../../../stores/writingStyle'

definePage({ meta: { layout: 'default' } })

const router = useRouter()
const store = useWritingStyleStore()

/* Tab đang chọn của 2 khối tabs (nguồn bài viết & phân tích chi tiết). */
const sourceTab = ref('paste')
const detailTab = ref('style')

const snack = reactive({ show: false, text: '', color: 'success' })

const SOURCE_TABS = [
  { value: 'url', label: 'Từ URL', icon: 'tabler-link', disabled: true, isExperiment: true },
  { value: 'paste', label: 'Dán nội dung', icon: 'tabler-clipboard-text' },
  { value: 'file', label: 'Từ file', icon: 'tabler-file-upload' },
]

const DETAIL_TABS = [
  { value: 'style', label: 'Văn phong', icon: 'tabler-feather' },
  { value: 'structure', label: 'Cấu trúc', icon: 'tabler-layout-distribute-horizontal' },
  { value: 'evidence', label: 'Bằng chứng trích dẫn', icon: 'tabler-quote' },
]

/* Bản đồ nhãn tiếng Việt cho 16 khóa DNA văn phong */
const RULE_LABELS = {
  tone: 'Giọng điệu (Tone)',
  pronouns: 'Đại từ xưng hô',
  emotion: 'Cảm xúc chủ đạo',
  opening: 'Cách mở bài',
  sentence_rhythm: 'Nhịp điệu câu',
  paragraph_rhythm: 'Nhịp điệu đoạn',
  transitions: 'Nghệ thuật chuyển ý',
  vocabulary: 'Vốn từ & Biểu đạt',
  technical_terms: 'Thuật ngữ chuyên môn',
  structure_patterns: 'Mô hình cấu trúc',
  headings: 'Tiêu đề con (Headings)',
  bullets: 'Danh sách gạch đầu dòng',
  examples: 'Dẫn chứng & Ví dụ',
  ending: 'Kết bài & Kêu gọi hành động',
  avoid: 'Điều kiêng kỵ (Avoid)',
  uncertainties: 'Điểm chưa xác định',
}

function ruleLabel(key) {
  return RULE_LABELS[key] || key || 'Quy tắc'
}

/* Các khóa thuộc nhóm Cấu trúc bài viết */
const STRUCTURE_RULE_KEYS = [
  'opening',
  'structure_patterns',
  'paragraph_rhythm',
  'transitions',
  'headings',
  'bullets',
  'ending',
]

/* Quy tắc lược bỏ theo từng cấp độ độ dài (khớp config/writingstyle.php) */
const TIER_DROP_RULES = {
  micro: ['headings', 'bullets', 'paragraph_rhythm', 'structure_patterns', 'opening', 'transitions', 'technical_terms', 'examples'],
  short: ['headings', 'bullets', 'structure_patterns', 'technical_terms', 'examples'],
  medium: ['headings'],
  long: [],
}

/* ---------------------------------------------------------------------
 * CẤP ĐỘ ĐỘ DÀI (LengthTier) — nạp từ GET /writing-profiles/options.
 * ------------------------------------------------------------------- */
const TIER_OPTIONS_FALLBACK = [
  { title: 'Micro — Cực ngắn (1–3 câu, < 60 từ)', value: 'micro', hint: '2–3 câu', color: 'error' },
  { title: 'Short — Ngắn (4–10 câu, 60–180 từ)', value: 'short', hint: '3–4 câu', color: 'warning' },
  { title: 'Medium — Trung bình (11–30 câu, 180–600 từ)', value: 'medium', hint: '20–30 câu', color: 'info' },
  { title: 'Long — Bài dài (> 30 câu, > 600 từ)', value: 'long', hint: 'bài dài (phân đoạn rõ ràng)', color: 'primary' },
]

const TIER_COLOR = Object.fromEntries(
  TIER_OPTIONS_FALLBACK.map(t => [t.value, t.color]),
)

const tierOptions = computed(() => {
  const apiTiers = store.tiers

  if (!apiTiers?.length) return TIER_OPTIONS_FALLBACK

  return apiTiers.map(t => ({
    value: t.value,
    title: t.label ?? t.value,
    hint: t.sentence_hint ?? '',
    color: TIER_COLOR[t.value] ?? 'secondary',
  }))
})

/* ---------------------------------------------------------------------
 * DỮ LIỆU NGUỒN BÀI VIẾT (3 TAB: URL / PASTE / FILE)
 * ------------------------------------------------------------------- */
const sourceUrl = ref('')
const urlExtractMode = ref('article')

const URL_EXTRACT_MODES = [
  { title: 'Chỉ trích xuất nội dung bài viết chính (Article Body)', value: 'article' },
  { title: 'Trích xuất toàn trang kèm cấu trúc Heading & Meta SEO', value: 'full' },
]

const URL_PREVIEW_META = reactive({
  domain: '',
  title: '',
  wordCount: '—',
  readingTime: '—',
  fetchedAt: '',
})

const articleContent = ref('')
const fileInputRef = ref(null)

const selectedFile = reactive({
  name: '',
  sizeLabel: '',
  typeLabel: '',
  words: '',
  status: '',
})

function htmlToText(html) {
  const BLOCK_TAGS = 'p|li|h[1-6]|div|blockquote|pre|tr|table|ul|ol'

  return String(html ?? '')
    .replace(new RegExp(`</(?:${BLOCK_TAGS})>`, 'gi'), '\n\n')
    .replace(/<(?:br|hr)[^>]*>/gi, '\n')
    .replace(new RegExp(`<(?:${BLOCK_TAGS})[^>]*>`, 'gi'), '\n\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .trim()
}

const wordCount = computed(() => {
  const t = htmlToText(articleContent.value)
  if (!t) return 0

  return t.split(/\s+/).filter(Boolean).length
})

const sentenceCount = computed(() => {
  const plain = htmlToText(articleContent.value)
  if (!plain) return 0
  const parts = plain.split(/[.!?…]+|\n{2,}|—\s*/u).map(s => s.trim()).filter(Boolean)

  return Math.max(1, parts.length)
})

const detectedTier = computed(() => {
  const s = sentenceCount.value
  const w = wordCount.value
  if (s <= 1 && w >= 60) {
    if (w <= 180) return 'short'
    if (w <= 600) return 'medium'

    return 'long'
  }
  if (s <= 3) return 'micro'
  if (s <= 10) return 'short'
  if (s <= 30) return 'medium'

  return 'long'
})

const detectedTierMeta = computed(() => {
  const list = tierOptions.value

  return list.find(t => t.value === detectedTier.value)
    ?? list.find(t => t.value === 'medium')
    ?? list[0]
})

/* ---------------------------------------------------------------------
 * GIẢI MÃ DNA VĂN PHONG & BẰNG CHỨNG
 * ------------------------------------------------------------------- */
const selectedDnaTierFilter = ref('long')

/* savedProfileId: id profile ĐÃ LƯU qua nút Lưu (sống sót qua các lần
 * re-analyze — phân tích lại KHÔNG reset id này; Lưu lần sau = PUT cùng
 * profile, không tạo thêm). Phân tích chỉ ghi result_json vào analysis,
 * không tự tạo profile nào. */
const savedProfileId = ref(null)
const lastSubmittedAnalysisId = ref(null)
/* Phiên phân tích thành công gần nhất — gửi kèm payload Lưu để backend gắn
 * profile vào run log (analysis.draft_profile_id) + suy origin=reference. */
const lastSucceededAnalysisId = ref(null)
const rawRulesJson = ref(null)
const isAnalyzing = computed(() => store.analyzing || store.analysisPolling)

const hasAnalysisResult = computed(() => !!rawRulesJson.value)

/* Các khóa bị lược bỏ theo tier đang chọn */
const droppedRuleKeys = computed(() => {
  return TIER_DROP_RULES[selectedDnaTierFilter.value] ?? []
})

/* Câu hỏi giải mã từ API: key -> question */
const ruleQuestionByKey = computed(() =>
  Object.fromEntries((store.ruleKeys ?? []).map(r => [r.key, r.question])),
)

/* Danh sách quy tắc thực tế đã được AI trích xuất (chỉ chứa các khóa có dữ liệu) */
const dnaRuleExtractions = ref([])

/* Chỉ hiển thị những quy tắc thực sự có trong kết quả phân tích và thuộc tier đang chọn */
const filteredDnaRules = computed(() => {
  if (!hasAnalysisResult.value) return []

  return dnaRuleExtractions.value
    .filter(rule => !droppedRuleKeys.value.includes(rule.key))
    .map(rule => ({
      ...rule,
      title: ruleLabel(rule.key),
      question: ruleQuestionByKey.value[rule.key] ?? '',
    }))
})

/* Các quy tắc thuộc nhóm Cấu trúc trong số các quy tắc đã trích xuất */
const extractedStructureRules = computed(() => {
  return filteredDnaRules.value.filter(rule => STRUCTURE_RULE_KEYS.includes(rule.key))
})

/* Bằng chứng trích dẫn nguyên văn (đã lọc bỏ uncertainties) */
const dnaEvidences = ref([])

/* 4 thẻ chỉ số DNA — chỉ phản ánh dữ liệu thực tế */
const styleDnaMetrics = computed(() => [
  {
    label: 'Cấp độ độ dài (Tier)',
    value: (wordCount.value > 0 || hasAnalysisResult.value)
      ? (detectedTierMeta.value?.title ?? '—')
      : 'Chưa có bài mẫu',
    sub: (wordCount.value > 0 || hasAnalysisResult.value)
      ? 'Phát hiện theo độ dài bài mẫu'
      : 'Nhập bài viết để tự nhận diện',
    icon: 'tabler-ruler-measure',
    color: 'primary',
  },
  {
    label: 'Kích thước bài mẫu',
    value: `${wordCount.value} từ • ${sentenceCount.value} câu`,
    sub: 'Đếm realtime từ nội dung đang soạn',
    icon: 'tabler-file-text',
    color: 'info',
  },
  {
    label: 'Quy tắc DNA trích xuất',
    value: hasAnalysisResult.value ? `${filteredDnaRules.value.length} quy tắc` : '—',
    sub: hasAnalysisResult.value
      ? `Áp dụng cho cấp độ ${selectedDnaTierFilter.value.toUpperCase()}`
      : 'Chưa chạy phân tích',
    icon: 'tabler-dna',
    color: 'success',
  },
  {
    label: 'Trích dẫn xác thực',
    value: hasAnalysisResult.value ? `${dnaEvidences.value.length} câu` : '—',
    sub: 'Bằng chứng nguyên văn khớp bài mẫu',
    icon: 'tabler-discount-check',
    color: 'warning',
  },
])

/* System prompt cơ sở — nạp từ analysis.style_instructions (Ô 1) & voice_texture_instructions (Ô 2) */
const styleInstructionsBase = ref('')
const voiceTextureInstructionsBase = ref('')
const voiceTextureSliders = reactive({
  voice_timbre: 70,
  vernacular_flow: 65,
  emotional_nuance: 75,
  rhythm_variation: 70,
  emphasis_glide: 80,
})
const toneSliders = reactive({
  humor: 50,
  empathy: 50,
  assertiveness: 50,
  formality: 50,
  inspiration: 50,
  candor: 50,
  depth: 50,
  urgency: 50,
})
const VOICE_TEXTURE_LABELS = {
  voice_timbre: 'Giọng văn',
  vernacular_flow: 'Tính dân dã tự nhiên',
  emotional_nuance: 'Màu sắc cảm xúc',
  rhythm_variation: 'Biến thiên nhịp điệu',
  emphasis_glide: 'Tính nhấn và lướt',
}
const displayedDnaPrompt = computed(() => styleInstructionsBase.value)

/* Form đóng gói kết quả phân tích thành bản ghi WritingProfile mới. */
const profileForm = reactive({
  name: '',
  slug: '',
  dominantTier: 'long',
  status: 'active',
  isEnabled: true,
  description: '',
  taxonomyTags: [],
})

const slugAvailable = ref(null)
const slugTakenBy = ref(null)

async function runSlugSuggest(source) {
  const src = source.trim()
  if (!src) return

  try {
    const res = await store.suggestSlug({
      source: src,
      sluggable_type: 'writing_profile',
      sluggable_id: savedProfileId.value,
    })

    if (!res) return

    if (res?.slug) profileForm.slug = res.slug
    slugAvailable.value = res?.available ?? null
    slugTakenBy.value = res?.taken_by ?? null
  } catch {
    slugAvailable.value = null
    slugTakenBy.value = null
  }
}

function onNameBlur() {
  runSlugSuggest(profileForm.name)
}

function onSlugBlur() {
  runSlugSuggest(profileForm.slug)
}

function clearContent() {
  articleContent.value = ''
  snack.text = 'Đã xóa nội dung bài viết đã dán'
  snack.color = 'info'
  snack.show = true
}

function hasRuleValue(rawValue) {
  if (Array.isArray(rawValue)) {
    return rawValue.map(v => String(v ?? '').trim()).filter(Boolean).length > 0
  }

  return String(rawValue ?? '').trim().length > 0
}

function formatRuleDesc(rawValue) {
  if (Array.isArray(rawValue))
    return rawValue.map(v => String(v ?? '').trim()).filter(Boolean).join(' • ')

  return String(rawValue ?? '').trim()
}

/**
 * Đổ kết quả giải mã (WritingAnalysisResource) vào state UI.
 * Chỉ lấy các quy tắc thực sự có dữ liệu trong rules_json (loại bỏ khóa trống & uncertainties).
 */
function applyAnalysisResult(analysis) {
  const result = analysis?.result_json ?? {}

  const rulesObj = result.rules_json && typeof result.rules_json === 'object'
    ? result.rules_json
    : {}

  rawRulesJson.value = rulesObj

  const backendTier = analysis?.detected_tier || result.detected_tier || detectedTier.value
  if (backendTier) {
    selectedDnaTierFilter.value = backendTier
    profileForm.dominantTier = backendTier
  }

  const keysOrder = store.ruleKeys?.length
    ? store.ruleKeys.map(r => r.key)
    : Object.keys(rulesObj)

  // Chỉ giữ những quy tắc thực sự có nội dung từ kết quả phân tích (loại bỏ uncertainties)
  dnaRuleExtractions.value = keysOrder
    .filter(key => key !== 'uncertainties' && hasRuleValue(rulesObj[key]))
    .map(key => ({
      key,
      title: ruleLabel(key),
      desc: formatRuleDesc(rulesObj[key]),
    }))

  const evidencesRaw = Array.isArray(result.evidence_json) ? result.evidence_json : []

  dnaEvidences.value = evidencesRaw
    .filter(ev => (ev.rule_key ?? ev.ruleKey) !== 'uncertainties')
    .map(ev => ({
      ruleKey: ev.rule_key ?? ev.ruleKey ?? 'unknown',
      excerpt: String(ev.excerpt ?? '').trim(),
      note: String(ev.note ?? '').trim(),
    }))
    .filter(ev => ev.excerpt !== '')

  styleInstructionsBase.value = typeof result.style_instructions === 'string'
    ? result.style_instructions.trim()
    : ''

  voiceTextureInstructionsBase.value = typeof result.voice_texture_instructions === 'string'
    ? result.voice_texture_instructions.trim()
    : ''

  if (result.voice_texture_sliders_json && typeof result.voice_texture_sliders_json === 'object') {
    for (const k of Object.keys(voiceTextureSliders)) {
      if (typeof result.voice_texture_sliders_json[k] === 'number') {
        voiceTextureSliders[k] = result.voice_texture_sliders_json[k]
      }
    }
  }

  if (result.tone_sliders_json && typeof result.tone_sliders_json === 'object') {
    for (const k of Object.keys(toneSliders)) {
      if (typeof result.tone_sliders_json[k] === 'number') {
        toneSliders[k] = result.tone_sliders_json[k]
      }
    }
  }

  /* Phân tích KHÔNG tạo profile — chỉ ghi nhận phiên phân tích để gửi kèm
   * payload Lưu (backend gắn analysis.draft_profile_id khi user Lưu).
   * savedProfileId giữ nguyên qua các lần re-analyze. */
  lastSucceededAnalysisId.value = analysis?.id ?? null
}

function buildRulesJsonPayload() {
  if (rawRulesJson.value && typeof rawRulesJson.value === 'object')
    return { ...rawRulesJson.value }

  const out = {}
  for (const item of dnaRuleExtractions.value) {
    out[item.key] = item.desc ?? ''
  }

  return out
}

function buildEvidenceJsonPayload() {
  return dnaEvidences.value.map(ev => ({
    rule_key: ev.ruleKey,
    excerpt: ev.excerpt,
    note: ev.note ?? '',
  }))
}

async function awaitAndApply(dispatched, okMessage) {
  const analysisId = dispatched?.analysis_id
  if (!analysisId) {
    snack.text = 'Không nhận được mã phiên phân tích từ máy chủ'
    snack.color = 'error'
    snack.show = true

    return false
  }

  if (analysisId === lastSubmittedAnalysisId.value) {
    snack.text = 'Đang giải mã nội dung này rồi — tiếp tục chờ kết quả...'
    snack.color = 'info'
    snack.show = true
  } else {
    lastSubmittedAnalysisId.value = analysisId
    snack.text = 'Đã tiếp nhận — AI đang giải mã các quy tắc DNA văn phong trong nền...'
    snack.color = 'info'
    snack.show = true
  }

  const finalAnalysis = await store.waitForAnalysis(analysisId)
  if (!finalAnalysis) return false

  if (finalAnalysis.status === 'failed') {
    snack.text = finalAnalysis.error_message || 'Giải mã văn phong thất bại'
    snack.color = 'error'
    snack.show = true

    return false
  }

  applyAnalysisResult(finalAnalysis)
  detailTab.value = 'style'

  const tierLabel = (finalAnalysis.detected_tier || detectedTier.value).toUpperCase()

  snack.text = okMessage ?? `Đã giải mã DNA văn phong thành công (cấp độ ${tierLabel})`
  snack.color = 'success'
  snack.show = true

  return true
}

async function runAnalysis() {
  if (isAnalyzing.value) return

  const plainText = articleContent.value
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim()

  if (plainText.length < 50) {
    snack.text = 'Nội dung bài viết mẫu phải có tối thiểu 50 ký tự để giải mã DNA'
    snack.color = 'warning'
    snack.show = true

    return
  }

  try {
    const dispatched = await store.analyze({
      source_type: 'paste',
      reference_text: plainText,
      name: profileForm.name.trim() || undefined,
    })

    await awaitAndApply(
      dispatched,
      `Đã giải mã DNA văn phong thành công (${wordCount.value} từ)`,
    )
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  }
}

async function fetchFromUrl() {
  if (isAnalyzing.value) return

  const raw = sourceUrl.value.trim()
  if (!raw) {
    snack.text = 'Vui lòng nhập đường dẫn URL hợp lệ trước khi trích xuất'
    snack.color = 'warning'
    snack.show = true

    return
  }

  let parsed = null
  try {
    parsed = new URL(raw)
  } catch {
    snack.text = 'Đường dẫn URL không hợp lệ — cần dạng http:// hoặc https://'
    snack.color = 'warning'
    snack.show = true

    return
  }

  if (!['http:', 'https:'].includes(parsed.protocol)) {
    snack.text = 'Chỉ hỗ trợ URL http:// hoặc https://'
    snack.color = 'warning'
    snack.show = true

    return
  }

  try {
    const dispatched = await store.analyze({
      source_type: 'url',
      source_url: raw,
      name: profileForm.name.trim() || undefined,
    })

    const meta = dispatched?.meta
    if (meta) {
      URL_PREVIEW_META.domain = meta.domain || parsed.hostname
      URL_PREVIEW_META.title = meta.title || parsed.hostname
      URL_PREVIEW_META.wordCount = meta.word_count != null ? `${meta.word_count} từ` : '—'
      URL_PREVIEW_META.readingTime = meta.word_count != null
        ? `~ ${Math.max(1, Math.ceil(meta.word_count / 200))} phút đọc`
        : '—'
    } else {
      URL_PREVIEW_META.domain = parsed.hostname
      URL_PREVIEW_META.title = parsed.hostname
    }
    URL_PREVIEW_META.fetchedAt = 'Vừa trích xuất xong'

    if (dispatched?.reference_text) {
      articleContent.value = `<p>${String(dispatched.reference_text).replace(/\n+/g, '</p><p>')}</p>`
    }

    await awaitAndApply(dispatched, 'Đã trích xuất & giải mã DNA từ URL thành công')
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  }
}

function triggerFileInput() {
  fileInputRef.value?.click()
}

function handleFileChange(event) {
  const file = event.target?.files?.[0]
  if (!file) return

  const sizeKb = Math.max(1, Math.round((file.size / 1024) * 10) / 10)

  selectedFile.name = file.name
  selectedFile.sizeLabel = `${sizeKb} KB`
  selectedFile.typeLabel = file.type || 'Tài liệu văn bản'
  selectedFile.status = 'Đã tải lên từ máy tính'

  const reader = new FileReader()

  reader.onload = () => {
    const rawText = String(reader.result || '').trim()
    if (rawText) {
      const paragraphs = rawText
        .split(/\r?\n\r?\n/)
        .map(p => `<p>${p.replace(/\r?\n/g, '<br>')}</p>`)
        .join('')

      articleContent.value = paragraphs
      selectedFile.words = `${wordCount.value} từ`
    }
    snack.text = `Đã nạp nội dung từ file "${file.name}"`
    snack.color = 'success'
    snack.show = true
  }
  reader.readAsText(file)
}

function removeSelectedFile() {
  selectedFile.name = ''
  selectedFile.sizeLabel = ''
  selectedFile.typeLabel = ''
  selectedFile.words = ''
  selectedFile.status = ''
  if (fileInputRef.value) fileInputRef.value.value = ''
  snack.text = 'Đã gỡ tệp nguồn'
  snack.color = 'info'
  snack.show = true
}

async function saveWritingProfile(openEditor = false) {
  const name = profileForm.name.trim()
  if (!name) {
    snack.text = 'Vui lòng nhập tên Writing Profile trước khi lưu'
    snack.color = 'warning'
    snack.show = true

    return
  }

  if (!hasAnalysisResult.value) {
    snack.text = 'Hãy phân tích bài mẫu trước để tạo hồ sơ DNA thật rồi mới lưu.'
    snack.color = 'warning'
    snack.show = true

    return
  }

  const payload = {
    name,
    ...(profileForm.slug?.trim() ? { slug: profileForm.slug.trim() } : {}),
    description: profileForm.description?.trim() || null,
    dominant_tier: profileForm.dominantTier,
    rules_json: buildRulesJsonPayload(),
    evidence_json: buildEvidenceJsonPayload(),
    style_instructions: styleInstructionsBase.value,
    voice_texture_instructions: voiceTextureInstructionsBase.value || null,
    voice_texture_sliders_json: { ...voiceTextureSliders },
    tone_sliders_json: { ...toneSliders },
    status: profileForm.status,
    is_enabled: profileForm.isEnabled,
    // Phiên phân tích thành công — backend gắn profile vào run log
    // (analysis.draft_profile_id, source_hash, metadata) + origin=reference.
    ...(lastSucceededAnalysisId.value ? { analysis_id: lastSucceededAnalysisId.value } : {}),
  }

  try {
    // Đã Lưu >= 1 lần -> PUT cập nhật cùng profile; chưa -> POST tạo mới.
    const saved = savedProfileId.value
      ? await store.updateProfile(savedProfileId.value, payload)
      : await store.createProfile(payload)

    if (saved?.id)
      savedProfileId.value = saved.id

    if (saved?.slug)
      profileForm.slug = saved.slug

    snack.text = `Đã lưu hồ sơ "${saved?.name ?? name}" thành công`
    snack.color = 'success'
    snack.show = true

    if (openEditor && saved?.id)
      router.push({ name: 'admin-writing-style-writing-profiles-edit-id', params: { id: saved.id } })
    else
      router.push({ name: 'admin-writing-style-writing-profiles-list' })
  } catch (e) {
    snack.text = firstErrorMessage(e)
    snack.color = 'error'
    snack.show = true
  }
}

async function copyText(text, successMessage = 'Đã sao chép vào bộ nhớ tạm') {
  try {
    await navigator.clipboard.writeText(text)
    snack.text = successMessage
    snack.color = 'success'
  } catch {
    snack.text = 'Không sao chép được — vui lòng chọn và copy thủ công'
    snack.color = 'error'
  }
  snack.show = true
}

async function copyPrompt() {
  const targetText = displayedDnaPrompt.value

  if (!targetText.trim()) {
    snack.text = 'Chưa có System Prompt DNA — hãy phân tích bài mẫu trước'
    snack.color = 'warning'
    snack.show = true

    return
  }

  await copyText(targetText, 'Đã sao chép System Prompt DNA văn phong')
}

onMounted(async () => {
  try {
    await store.fetchOptions()
  } catch (e) {
    snack.text = `Không nạp được danh mục cấp độ/khóa DNA: ${firstErrorMessage(e)}`
    snack.color = 'error'
    snack.show = true
  }
})

onBeforeUnmount(() => {
  store.stopPolling()
})
</script>

<template>
  <div>
    <!-- ================= HEADER: Tiêu đề & Cụm nút ================= -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex align-center gap-3">
        <div class="d-flex flex-column justify-center">
          <h4 class="text-h4 font-weight-bold text-high-emphasis mb-1">
            AI Phân tích bài viết
          </h4>
          <div class="text-body-2 text-medium-emphasis">
            Giải mã các quy tắc DNA văn phong theo cấp độ bài viết và đóng gói thành Writing Profile.
          </div>
        </div>
      </div>

      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-arrow-left"
          @click="router.push({ name: 'admin-writing-style-writing-profiles-list' })"
        >
          Quay lại danh sách
        </VBtn>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-sparkles"
          :loading="isAnalyzing"
          :disabled="isAnalyzing || store.saving"
          @click="runAnalysis"
        >
          {{ isAnalyzing ? 'Đang giải mã AI...' : 'Phân tích với AI' }}
        </VBtn>
        <VBtn
          variant="flat"
          color="primary"
          prepend-icon="tabler-device-floppy"
          :loading="store.saving"
          :disabled="store.saving || isAnalyzing || !hasAnalysisResult"
          @click="saveWritingProfile(false)"
        >
          Lưu Writing Profile
        </VBtn>
      </div>
    </div>

    <VRow>
      <!-- ===== CỘT TRÁI: Nhập bài viết & Kết quả phân tích chi tiết ===== -->
      <VCol
        cols="12"
        lg="7"
      >
        <!-- 0. Card Thông tin văn phong (Tên & Slug) -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-signature"
                  color="primary"
                  size="22"
                  class="me-2"
                />
                <span class="text-h6 font-weight-bold text-high-emphasis">Thông tin văn phong</span>
              </div>
            </template>
            <template #subtitle>
              <div class="text-caption text-medium-emphasis mt-1">
                Đặt tên và đường dẫn định danh (slug) đại diện cho hồ sơ DNA văn phong
              </div>
            </template>
          </VCardItem>

          <VCardText>
            <VRow dense>
              <VCol cols="12">
                <AppTextField
                  v-model="profileForm.name"
                  label="Tên văn phong *"
                  placeholder="vd: Giọng Chuyên gia Kỹ thuật – PHP / Laravel"
                  :rules="[v => !!v?.trim() || 'Tên văn phong bắt buộc nhập']"
                  required
                  class="mb-3"
                  @blur="onNameBlur"
                />
              </VCol>

              <VCol cols="12">
                <SlugField
                  v-model="profileForm.slug"
                  label="Slug định danh *"
                  placeholder="giong-chuyen-gia-ky-thuat-php-laravel"
                  hint="Blur ô tên hoặc ô slug — hệ thống gợi ý slug khả dụng và cập nhật lại ô."
                  persistent-hint
                  :suggesting="store.slugSuggesting"
                  :available="slugAvailable"
                  :taken-by="slugTakenBy"
                  sluggable-type="writing_profile"
                  prefix="localhost:8000/"
                  @request-suggest="onSlugBlur"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- 1. Card Nguồn bài viết & Trình soạn thảo -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-link"
                  color="primary"
                  size="22"
                  class="me-2"
                />
                <span class="text-h6 font-weight-bold text-high-emphasis">Nguồn bài viết mẫu</span>
              </div>
            </template>
            <template #subtitle>
              <div class="text-caption text-medium-emphasis mt-1">
                Nạp bài viết từ URL, dán văn bản trực tiếp hoặc tải lên tệp tài liệu để AI trích xuất DNA
              </div>
            </template>
            <template #append>
              <VChip
                v-if="wordCount > 0 || hasAnalysisResult"
                size="x-small"
                :color="detectedTierMeta.color"
                variant="tonal"
                label
              >
                Cấp độ tự nhận diện: {{ detectedTier.toUpperCase() }} ({{ detectedTierMeta.hint }})
              </VChip>
            </template>
          </VCardItem>

          <VCardText>
            <!-- Tabs chọn nguồn -->
            <VTabs
              v-model="sourceTab"
              color="primary"
              class="mb-4"
            >
              <VTab
                v-for="tab in SOURCE_TABS"
                :key="tab.value"
                :value="tab.value"
                :disabled="tab.disabled"
              >
                <VIcon
                  :icon="tab.icon"
                  size="18"
                  class="me-2"
                />
                <span>{{ tab.label }}</span>
                <VChip
                  v-if="tab.isExperiment"
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  class="ms-2"
                >
                  Thử nghiệm
                </VChip>
              </VTab>
            </VTabs>

            <VWindow v-model="sourceTab">
              <!-- TAB NGUỒN 1: TỪ URL -->
              <VWindowItem value="url">
                <VRow class="mb-3">
                  <VCol
                    cols="12"
                    md="7"
                  >
                    <AppTextField
                      v-model="sourceUrl"
                      label="Đường dẫn bài viết (URL)"
                      placeholder="https://example.com/bai-viet-mau"
                      prepend-inner-icon="tabler-world"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="5"
                  >
                    <AppSelect
                      v-model="urlExtractMode"
                      :items="URL_EXTRACT_MODES"
                      label="Chế độ thu thập"
                      disabled
                      hint="Luôn trích phần thân bài viết"
                      persistent-hint
                    />
                  </VCol>
                </VRow>

                <!-- Chỉ hiển thị hộp thông tin khi đã trích xuất URL thật -->
                <div
                  v-if="URL_PREVIEW_META.domain"
                  class="tonal-panel pa-4 rounded mb-4"
                >
                  <div class="d-flex justify-space-between align-start flex-wrap gap-2 mb-2">
                    <div class="d-flex align-center">
                      <VAvatar
                        color="primary"
                        variant="tonal"
                        size="36"
                        rounded
                        class="me-3 flex-shrink-0"
                      >
                        <VIcon
                          icon="tabler-article"
                          size="20"
                        />
                      </VAvatar>
                      <div>
                        <div class="text-body-2 font-weight-medium text-high-emphasis">
                          {{ URL_PREVIEW_META.title }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          Nguồn: {{ URL_PREVIEW_META.domain }} • {{ URL_PREVIEW_META.wordCount }} • {{ URL_PREVIEW_META.readingTime }}
                        </div>
                      </div>
                    </div>
                    <VChip
                      size="x-small"
                      color="success"
                      variant="tonal"
                      label
                    >
                      {{ URL_PREVIEW_META.fetchedAt }}
                    </VChip>
                  </div>
                </div>

                <div class="d-flex justify-space-between justify-sm-end flex-wrap gap-2">
                  <VBtn
                    v-if="URL_PREVIEW_META.domain"
                    variant="tonal"
                    color="secondary"
                    size="small"
                    prepend-icon="tabler-eye"
                    @click="sourceTab = 'paste'"
                  >
                    Xem văn bản đã trích xuất
                  </VBtn>
                  <VBtn
                    variant="flat"
                    color="primary"
                    size="small"
                    prepend-icon="tabler-cloud-download"
                    :loading="isAnalyzing"
                    :disabled="isAnalyzing"
                    @click="fetchFromUrl"
                  >
                    Trích xuất &amp; Phân tích
                  </VBtn>
                </div>
              </VWindowItem>

              <!-- TAB NGUỒN 2: DÁN NỘI DUNG -->
              <VWindowItem value="paste">
                <div class="editor-shell rounded overflow-hidden">
                  <TiptapEditor
                    v-model="articleContent"
                    placeholder="Dán hoặc soạn nội dung bài viết cần phân tích..."
                  />

                  <VDivider />

                  <!-- Thanh trạng thái và nút phân tích -->
                  <div class="editor-footer d-flex justify-space-between align-center flex-wrap gap-2 px-4 py-2">
                    <div class="d-flex align-center flex-wrap gap-3">
                      <span class="text-caption text-medium-emphasis">
                        Số từ: <strong>{{ wordCount }}</strong>
                      </span>
                      <span class="text-caption text-medium-emphasis">
                        Số câu: <strong>{{ sentenceCount }}</strong>
                      </span>
                      <span
                        v-if="wordCount > 0"
                        class="text-caption text-primary font-weight-medium"
                      >
                        LengthTier: {{ detectedTier }}
                      </span>
                    </div>
                    <div class="d-flex align-center gap-2">
                      <IconBtn
                        size="small"
                        color="secondary"
                        @click="clearContent"
                      >
                        <VIcon icon="tabler-refresh" />
                      </IconBtn>
                      <IconBtn
                        size="small"
                        color="secondary"
                        @click="articleContent = ''"
                      >
                        <VIcon icon="tabler-trash" />
                      </IconBtn>
                      <VBtn
                        variant="flat"
                        color="primary"
                        size="small"
                        :loading="isAnalyzing"
                        :disabled="isAnalyzing"
                        @click="runAnalysis"
                      >
                        Phân tích bài viết
                      </VBtn>
                    </div>
                  </div>
                </div>
              </VWindowItem>

              <!-- TAB NGUỒN 3: TỪ FILE -->
              <VWindowItem value="file">
                <input
                  ref="fileInputRef"
                  type="file"
                  accept=".txt,.md,.html,.doc,.docx"
                  class="d-none"
                  @change="handleFileChange"
                >

                <!-- Vùng kéo thả / chọn tệp -->
                <div
                  class="file-dropzone pa-6 rounded text-center cursor-pointer mb-4"
                  @click="triggerFileInput"
                >
                  <VAvatar
                    color="primary"
                    variant="tonal"
                    size="48"
                    rounded="circle"
                    class="mb-3"
                  >
                    <VIcon
                      icon="tabler-cloud-upload"
                      size="24"
                    />
                  </VAvatar>
                  <div class="text-body-1 font-weight-medium text-high-emphasis mb-1">
                    Bấm để chọn tệp bài viết từ máy tính
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Hỗ trợ định dạng .txt, .md, .html, .docx (Tối đa 5MB)
                  </div>
                </div>

                <!-- Thông tin tệp đang nạp (chỉ hiển thị khi đã chọn file thật) -->
                <div
                  v-if="selectedFile.name"
                  class="tonal-panel pa-3 rounded d-flex align-center justify-space-between flex-wrap gap-3"
                >
                  <div class="d-flex align-center">
                    <VAvatar
                      color="info"
                      variant="tonal"
                      size="38"
                      rounded
                      class="me-3 flex-shrink-0"
                    >
                      <VIcon
                        icon="tabler-file-description"
                        size="20"
                      />
                    </VAvatar>
                    <div>
                      <div class="d-flex align-center flex-wrap gap-2">
                        <span class="text-body-2 font-weight-medium text-high-emphasis">
                          {{ selectedFile.name }}
                        </span>
                        <VChip
                          size="x-small"
                          color="primary"
                          variant="tonal"
                          label
                        >
                          {{ selectedFile.typeLabel }}
                        </VChip>
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        Dung lượng: {{ selectedFile.sizeLabel }} • {{ selectedFile.words }} • {{ selectedFile.status }}
                      </div>
                    </div>
                  </div>

                  <div class="d-flex align-center gap-2">
                    <IconBtn
                      size="small"
                      color="secondary"
                      @click="removeSelectedFile"
                    >
                      <VIcon icon="tabler-refresh" />
                    </IconBtn>
                    <VBtn
                      variant="flat"
                      color="primary"
                      size="small"
                      prepend-icon="tabler-sparkles"
                      :loading="isAnalyzing"
                      :disabled="isAnalyzing"
                      @click="runAnalysis"
                    >
                      Phân tích tài liệu
                    </VBtn>
                  </div>
                </div>
              </VWindowItem>
            </VWindow>
          </VCardText>
        </VCard>

        <!-- 2. Card Giải mã DNA & Phân tích chi tiết -->
        <VCard>
          <VProgressLinear
            v-if="isAnalyzing"
            indeterminate
            color="primary"
          />
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-file-search"
                  color="primary"
                  size="22"
                  class="me-2"
                />
                <span class="text-h6 font-weight-bold text-high-emphasis">Giải mã DNA &amp; Phân tích chi tiết</span>
              </div>
            </template>
            <template #subtitle>
              <div class="text-caption text-medium-emphasis mt-1">
                Hiển thị các quy tắc hành văn thực tế được AI trích xuất theo cấp độ độ dài bài viết
              </div>
            </template>
          </VCardItem>

          <VCardText>
            <VAlert
              v-if="isAnalyzing"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4 text-caption"
            >
              AI đang giải mã các quy tắc DNA văn phong và kiểm chứng trích dẫn nguyên văn từ bài mẫu (có thể mất 10–60 giây)...
            </VAlert>

            <!-- Tabs danh mục phân tích -->
            <VTabs
              v-model="detailTab"
              color="primary"
              show-arrows
              class="mb-6"
            >
              <VTab
                v-for="tab in DETAIL_TABS"
                :key="tab.value"
                :value="tab.value"
              >
                <VIcon
                  :icon="tab.icon"
                  size="16"
                  class="me-1"
                />
                <span>{{ tab.label }}</span>
              </VTab>
            </VTabs>

            <VWindow v-model="detailTab">
              <!-- ================= TAB 1: VĂN PHONG ================= -->
              <VWindowItem value="style">
                <!-- 4 Chỉ số nhận diện DNA Văn phong -->
                <VRow
                  dense
                  class="mb-6"
                >
                  <VCol
                    v-for="metric in styleDnaMetrics"
                    :key="metric.label"
                    cols="12"
                    sm="6"
                  >
                    <div class="tonal-panel pa-3 rounded h-100 d-flex align-center">
                      <VAvatar
                        :color="metric.color"
                        variant="tonal"
                        size="38"
                        rounded
                        class="me-3 flex-shrink-0"
                      >
                        <VIcon
                          :icon="metric.icon"
                          size="20"
                        />
                      </VAvatar>
                      <div>
                        <div class="text-caption text-medium-emphasis">
                          {{ metric.label }}
                        </div>
                        <div class="text-body-2 font-weight-medium text-high-emphasis">
                          {{ metric.value }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ metric.sub }}
                        </div>
                      </div>
                    </div>
                  </VCol>
                </VRow>

                <!-- Khi đang chạy phân tích: Skeleton loading -->
                <div v-if="isAnalyzing">
                  <VRow dense>
                    <VCol
                      v-for="n in 6"
                      :key="n"
                      cols="12"
                      sm="6"
                    >
                      <div class="tonal-panel pa-3 rounded">
                        <VSkeletonLoader type="heading, text" />
                      </div>
                    </VCol>
                  </VRow>
                </div>

                <!-- Khi chưa có kết quả phân tích: Empty state sạch sẽ, không hiện 16 thẻ tĩnh -->
                <div
                  v-else-if="!hasAnalysisResult"
                  class="tonal-panel pa-6 rounded text-center"
                >
                  <VIcon
                    icon="tabler-dna"
                    size="36"
                    color="secondary"
                    class="mb-2"
                  />
                  <div class="text-body-2 font-weight-medium text-high-emphasis mb-1">
                    Chưa có quy tắc DNA nào được trích xuất
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Hãy dán nội dung bài viết mẫu ở trên và bấm <strong>Phân tích bài viết</strong> để AI bóc tách các quy tắc văn phong tương ứng với độ dài bài viết.
                  </div>
                </div>

                <!-- Khi đã phân tích: Có quy tắc nào thì chỉ hiển thị đúng quy tắc đó -->
                <div v-else>
                  <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                    <div class="d-flex align-center text-body-2 font-weight-medium">
                      <VIcon
                        icon="tabler-dna"
                        size="16"
                        color="primary"
                        class="me-1"
                      />
                      Quy tắc DNA Văn phong đã trích xuất ({{ filteredDnaRules.length }} quy tắc)
                    </div>

                    <!-- Bộ lọc xem theo cấp độ LengthTier -->
                    <div class="d-flex align-center flex-wrap gap-1">
                      <VBtn
                        v-for="tierOpt in tierOptions"
                        :key="tierOpt.value"
                        size="x-small"
                        :variant="selectedDnaTierFilter === tierOpt.value ? 'flat' : 'tonal'"
                        :color="selectedDnaTierFilter === tierOpt.value ? 'primary' : 'secondary'"
                        @click="selectedDnaTierFilter = tierOpt.value"
                      >
                        {{ tierOpt.value }}
                      </VBtn>
                    </div>
                  </div>

                  <VRow dense>
                    <VCol
                      v-for="rule in filteredDnaRules"
                      :key="rule.key"
                      cols="12"
                      sm="6"
                    >
                      <div class="tonal-panel pa-3 rounded h-100">
                        <div class="d-flex align-center justify-space-between gap-2 mb-1">
                          <span class="text-body-2 font-weight-medium text-high-emphasis">
                            {{ rule.title }}
                          </span>
                          <VChip
                            size="x-small"
                            color="primary"
                            variant="tonal"
                            label
                          >
                            {{ rule.key }}
                          </VChip>
                        </div>
                        <div
                          v-if="rule.question"
                          class="text-caption text-disabled mb-1"
                        >
                          {{ rule.question }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          {{ rule.desc }}
                        </div>
                      </div>
                    </VCol>
                  </VRow>
                </div>
              </VWindowItem>

              <!-- ================= TAB 2: CẤU TRÚC ================= -->
              <VWindowItem value="structure">
                <div
                  v-if="!hasAnalysisResult"
                  class="tonal-panel pa-6 rounded text-center text-caption text-medium-emphasis"
                >
                  Chưa có dữ liệu cấu trúc — hãy chạy phân tích bài viết mẫu trước.
                </div>

                <div
                  v-else-if="!extractedStructureRules.length"
                  class="tonal-panel pa-4 rounded text-caption text-medium-emphasis"
                >
                  Ở cấp độ <strong>{{ selectedDnaTierFilter.toUpperCase() }}</strong>, bài viết quá ngắn nên không áp dụng các quy tắc cấu trúc dàn ý dài.
                </div>

                <VRow
                  v-else
                  dense
                >
                  <VCol
                    v-for="rule in extractedStructureRules"
                    :key="rule.key"
                    cols="12"
                    sm="6"
                  >
                    <div class="tonal-panel pa-3 rounded h-100">
                      <div class="d-flex align-center justify-space-between gap-2 mb-1">
                        <span class="text-body-2 font-weight-medium text-high-emphasis">
                          {{ rule.title }}
                        </span>
                        <VChip
                          size="x-small"
                          color="info"
                          variant="tonal"
                          label
                        >
                          {{ rule.key }}
                        </VChip>
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ rule.desc }}
                      </div>
                    </div>
                  </VCol>
                </VRow>
              </VWindowItem>

              <!-- ================= TAB 3: BẰNG CHỨNG TRÍCH DẪN ================= -->
              <VWindowItem value="evidence">
                <div
                  v-if="!dnaEvidences.length"
                  class="tonal-panel pa-6 rounded text-center text-caption text-medium-emphasis"
                >
                  Chưa có trích dẫn nguyên văn nào được xác thực từ bài viết mẫu.
                </div>

                <div
                  v-else
                  class="d-flex flex-column gap-3"
                >
                  <div
                    v-for="(ev, idx) in dnaEvidences"
                    :key="`${ev.ruleKey}-${idx}`"
                    class="prompt-box pa-3 rounded"
                  >
                    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-1">
                      <VChip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        label
                      >
                        {{ ruleLabel(ev.ruleKey) }}
                      </VChip>
                      <span class="d-flex align-center text-caption text-success">
                        <VIcon
                          icon="tabler-discount-check"
                          size="14"
                          class="me-1"
                        />
                        Khớp nguyên văn 100%
                      </span>
                    </div>
                    <div class="text-body-2 font-italic text-high-emphasis mb-1">
                      “{{ ev.excerpt }}”
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ ev.note }}
                    </div>
                  </div>
                </div>
              </VWindowItem>
            </VWindow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- ===== CỘT PHẢI: System Prompt DNA & Lưu Writing Profile ===== -->
      <VCol
        cols="12"
        lg="5"
      >
        <!-- 1. Gợi ý prompt & System Prompt DNA -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-sparkles"
                  color="primary"
                  size="22"
                  class="me-2"
                />
                <span class="text-h6 font-weight-bold text-high-emphasis">
                  System Prompt DNA (style_instructions)
                </span>
              </div>
            </template>
            <template #subtitle>
              <div class="text-caption text-medium-emphasis mt-1">
                Bản chỉ đạo phong cách hành văn tự do được AI đúc kết từ bài mẫu
              </div>
            </template>
            <template #append>
              <VBtn
                variant="text"
                color="primary"
                size="small"
                prepend-icon="tabler-copy"
                :disabled="!displayedDnaPrompt.trim()"
                @click="copyPrompt"
              >
                Sao chép
              </VBtn>
            </template>
          </VCardItem>

          <VCardText>
            <div class="text-caption font-weight-bold text-primary mb-1">
              Ô 1 — Hướng dẫn hành văn & Chân dung (style_instructions)
            </div>
            <div
              class="prompt-box pa-3 rounded text-body-2 text-high-emphasis sandbox-output mb-4"
              style="max-height: 280px; overflow-y: auto;"
            >
              <div
                v-if="!displayedDnaPrompt.trim()"
                class="text-caption text-medium-emphasis"
              >
                Chưa có System Prompt DNA. Hãy phân tích bài mẫu để AI tổng hợp hướng dẫn hành văn.
              </div>
              <template v-else>
                {{ displayedDnaPrompt }}
              </template>
            </div>

            <div class="text-caption font-weight-bold text-info mb-1">
              Ô 2 — Chất văn & Nghệ thuật Nhấn – Lướt (voice_texture_instructions)
            </div>
            <div
              class="prompt-box pa-3 rounded text-body-2 text-high-emphasis sandbox-output"
              style="max-height: 260px; overflow-y: auto;"
            >
              <div
                v-if="!voiceTextureInstructionsBase.trim()"
                class="text-caption text-medium-emphasis"
              >
                Chưa có chỉ dẫn Chất văn (Ô 2). Khi phân tích bài mẫu, AI sẽ tự động bóc tách 5 thuộc tính Chất văn tại đây.
              </div>
              <template v-else>
                <div class="mb-2">
                  {{ voiceTextureInstructionsBase }}
                </div>
                <div class="d-flex flex-wrap gap-1 pt-2 border-t">
                  <VChip
                    v-for="(label, key) in VOICE_TEXTURE_LABELS"
                    :key="key"
                    size="x-small"
                    color="info"
                    variant="tonal"
                    label
                  >
                    {{ label }}: {{ voiceTextureSliders[key] }}%
                  </VChip>
                </div>
              </template>
            </div>
          </VCardText>
        </VCard>

        <!-- 2. Đóng gói & Lưu thành Writing Profile -->
        <VCard>
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-dna-2"
                  color="primary"
                  size="22"
                  class="me-2"
                />
                <span class="text-h6 font-weight-bold text-high-emphasis">
                  Đóng gói thành Writing Profile
                </span>
              </div>
            </template>
            <template #subtitle>
              <div class="text-caption text-medium-emphasis mt-1">
                Lưu trữ hồ sơ DNA để sẵn sàng cấp quyền cho AI Writer sinh bài
              </div>
            </template>
            <template #append>
              <VChip
                size="x-small"
                :color="(hasAnalysisResult || savedProfileId) ? 'info' : 'secondary'"
                variant="tonal"
                label
              >
                origin: {{ (hasAnalysisResult || savedProfileId) ? 'reference' : 'manual' }}
              </VChip>
            </template>
          </VCardItem>

          <VCardText>
            <VRow dense>
              <VCol
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="profileForm.dominantTier"
                  :items="tierOptions"
                  label="Cấp độ chủ đạo (Tier)"
                  class="mb-3"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="profileForm.status"
                  :items="[
                    { title: 'Đang sử dụng (Active)', value: 'active' },
                    { title: 'Bản nháp (Draft)', value: 'draft' },
                  ]"
                  label="Trạng thái"
                  class="mb-3"
                />
              </VCol>

              <VCol cols="12">
                <AppTextarea
                  v-model="profileForm.description"
                  label="Mô tả ngắn hồ sơ văn phong"
                  rows="2"
                  class="mb-3"
                />
              </VCol>

              <VCol cols="12">
                <div class="d-flex align-center justify-space-between mb-4">
                  <span class="text-body-2">Kích hoạt sử dụng ngay cho AI Writer</span>
                  <VSwitch
                    v-model="profileForm.isEnabled"
                    color="primary"
                    hide-details
                    inset
                  />
                </div>
              </VCol>
            </VRow>

            <div class="d-flex justify-end flex-wrap gap-2">
              <VBtn
                variant="tonal"
                color="secondary"
                size="small"
                prepend-icon="tabler-adjustments-horizontal"
                :loading="store.saving"
                :disabled="store.saving || isAnalyzing || !hasAnalysisResult"
                @click="saveWritingProfile(true)"
              >
                Lưu &amp; Sửa chi tiết
              </VBtn>
              <VBtn
                variant="flat"
                color="primary"
                size="small"
                prepend-icon="tabler-check"
                :loading="store.saving"
                :disabled="store.saving || isAnalyzing || !hasAnalysisResult"
                @click="saveWritingProfile(false)"
              >
                Tạo Writing Profile
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VSnackbar
      v-model="snack.show"
      :color="snack.color"
      location="top end"
    >
      {{ snack.text }}
    </VSnackbar>
  </div>
</template>

<style scoped>
/* Khung hộp soạn thảo — viền và nền theo biến theme để Dark Mode tự đúng. */
.editor-shell {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

/* Thanh toolbar của TiptapEditor + footer trạng thái: nền tonal nhẹ. */
.editor-shell :deep(.editor),
.editor-footer {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

/* Vùng soạn thảo: chiều cao tối thiểu + padding theo khung card. */
.editor-shell :deep(.ProseMirror) {
  padding: 16px;
  min-block-size: 220px;
}

/* Vùng kéo thả / chọn tệp nguồn — viền nét đứt theo biến theme. */
.file-dropzone {
  border: 1px dashed rgba(var(--v-theme-primary), 0.45);
  background: rgba(var(--v-theme-primary), 0.04);
  transition: border-color 0.2s ease, background-color 0.2s ease;
}

.file-dropzone:hover {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

/* Khối panel phụ trong các tab — dùng màu viền/nền theme, không hex cứng. */
.tonal-panel {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-on-surface), 0.02);
}

/* Hiển thị văn bản nhiều dòng cho System Prompt. */
.sandbox-output {
  white-space: pre-line;
}

/* Hộp prompt gợi ý — nền tonal primary nhạt. */
.prompt-box {
  border: 1px solid rgba(var(--v-theme-primary), 0.16);
  background: rgba(var(--v-theme-primary), 0.05);
}
</style>
