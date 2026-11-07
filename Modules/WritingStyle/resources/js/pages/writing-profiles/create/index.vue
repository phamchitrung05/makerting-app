<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang AI Phân tích bài viết & Tạo DNA Văn phong (DEMO TĨNH).
  =====================================================================

  Màn phân tích bài viết mẫu và đóng gói thành hồ sơ văn phong (WritingProfile):
  - Card 0 (Thông tin văn phong): field Tên văn phong và Slug định danh.
  - Card 1 (Nguồn bài viết): 3 tab nguồn đầu vào (Từ URL / Dán nội dung /
  Từ file), tích hợp TiptapEditor, tự động đếm từ, đếm câu và nhận diện
  cấp độ độ dài LengthTier (micro | short | medium | long) realtime.
  - Card 2 (Phân tích chi tiết): 6 tab chuyên sâu:
  1. Nội dung (tóm tắt, chủ đề, điểm mạnh & điểm cần cải thiện).
  2. Văn phong (4 chỉ số DNA, trọn bộ 16/16 RULE_KEYS lọc theo LengthTier,
  bằng chứng nguyên văn evidence_json, kiểm tra 16 quy tắc viết cốt lõi).
  3. Cấu trúc (chỉ số cấu trúc, phân bổ 3 phần, cây dàn ý Heading H1–H3).
  4. Hình ảnh (thống kê ảnh, kiểm tra Alt text, đề xuất vị trí & AI Prompt).
  5. Tính độc đáo (3 thước đo độc đáo, đối chiếu bản gốc & gợi ý viết lại).
  6. Phòng thử DNA - Sandbox (viết thử chủ đề mới theo cấp độ độ dài +
  xem Snapshot System Prompt đã loại bỏ evidence_json chống đạo văn).
  - Cột phải: Thông tin tổng quan (6 chỉ số), Đánh giá tổng thể (gauge +
  checklist + 3 thước đo con), Phân tích văn phong (5 trục), Gợi ý
  prompt (chuyển đổi giữa Prompt viết lại bài & System Prompt DNA), và
  Card Đóng gói & Lưu thành Writing Profile.

  TRẠNG THÁI: Giai đoạn 1–4 đã nối API:
  - GĐ1: tiers + 16 câu hỏi giải mã nạp từ GET /writing-profiles/options.
  - GĐ2–3: runAnalysis() gọi POST /writing-profiles/analyze (paste), poll
  GET /writing-profiles/analyses/{id} qua store.waitForAnalysis() và đổ
  rules_json (16 khóa), evidence_json, style_instructions, detected_tier thật.
  - GĐ4: saveWritingProfile() phân nhánh PUT (khi đã có draft_profile_id) /
  POST (khi tạo thủ công) và điều hướng theo id thật; runSandboxDemo() gọi
  POST /writing-profiles/{id}/sandbox + hiển thị snapshot.system_prompt.
  Có kết nối điều hướng vue-router sang trang danh sách
  (list) và trang chỉnh sửa chi tiết (edit/[id]).

  CHUẨN UI (docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §2–3):
  - Component PascalCase (VCard/VBtn/VTabs/VWindow), field dùng App* wrapper.
  - Icon chỉ tabler-*, truyền qua prop icon="..." (không text node).
  - Màu theme Vuetify / rgba(var(--v-theme-*)) — không hex cứng, không
  text-grey-darken-*, bg-white; Dark Mode tự đúng.
  - Typography theo thang §2: text-h4 header, text-subtitle-1 tiêu đề card,
  text-body-2 nội dung, text-caption meta — không inline font-size.
  - Spacing logical me-*/ms-*/pe-*/ps-* (ESLint vue/no-restricted-class).

  CÁC HÀM/COMPUTED TRONG FILE:
  - wordCount               : computed đếm số từ realtime từ HTML của TiptapEditor.
  - sentenceCount           : computed đếm số câu realtime theo dấu kết câu.
  - detectedTier            : computed phân tầng micro|short|medium|long (PLAN.md §304).
  - detectedTierMeta        : computed thông tin hiển thị của tier phát hiện được.
  - filteredDnaRules        : computed lọc 16 RULE_KEYS theo tier đang xem kèm question.
  - droppedRuleKeys         : computed danh sách khóa đẩy vào uncertainties ở tier ngắn.
  - styleDnaMetrics         : computed 4 chỉ số DNA dẫn xuất từ tier/wordCount/sentenceCount/evidences thật.
  - activeSandboxSample     : computed văn bản viết thử sinh từ Sandbox API (không còn mẫu cứng).
  - displayedDnaPrompt      : computed System Prompt DNA ưu tiên snapshot đã strip evidence.
  - hasAnalysisResult       : computed đã có rules_json thật hay chưa (chốt điều kiện Lưu Profile).
  - scoreColor(n)           : màu theo ngưỡng điểm (>=80 success, >=60 warning, <60 error).
  - severityMeta(level)     : nhãn và màu theme cho mức độ vi phạm quy tắc viết.
  - clearContent()          : xóa sạch nội dung bài viết đã dán trong editor.
  - formatRuleDesc()        : chuẩn hóa giá trị chuỗi hoặc mảng uncertainties trong rules_json.
  - applyAnalysisResult()   : đổ kết quả giải mã từ API vào 16 rule card, evidence và tier.
  - buildRulesJsonPayload() : đóng gói mảng quy tắc đúng schema trước khi gửi lưu.
  - buildEvidenceJsonPayload(): đóng gói mảng bằng chứng {rule_key,excerpt,note} khi lưu.
  - runAnalysis()           : gửi bài viết lên API giải mã và poll kết quả trong nền (GĐ3).
  - fetchFromUrl()          : trích xuất nội dung bài viết từ URL đầu vào.
  - triggerFileInput()      : mở hộp thoại chọn file từ máy tính.
  - handleFileChange(e)     : đọc nội dung file văn bản (.txt/.md/.html) vào editor.
  - removeSelectedFile()    : xóa file đang chọn và khôi phục trạng thái mặc định.
  - runSandboxDemo()        : gọi API viết thử nghiệm chủ đề mới theo DNA (GĐ4).
  - saveWritingProfile()    : lưu hồ sơ mới qua PUT (nếu đã analyze) hoặc POST (GĐ4).
  - runSlugSuggest(src)     : gọi API gợi ý slug từ chuỗi nguồn, ghi lại ô slug.
  - onNameBlur()            : blur ô Tên  → gợi ý từ name, ghi vào ô slug.
  - onSlugBlur()            : blur ô Slug → gợi ý từ slug đang gõ, ghi lại ô slug.
  - copyText(text, msg)     : sao chép văn bản bất kỳ vào clipboard + hiện snackbar.
  - copyPrompt()            : sao chép prompt đang hiển thị ở Card Prompt.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : route params (không có), dữ liệu bài viết qua articleContent (v-model),
  options từ writingStyleApi.options() và kết quả giải mã từ writingStyleApi.analysis().
  - OUTPUT: render giao diện phân tích đầy đủ các tab, đồng bộ state với store
  và lưu bản ghi vào database bảng writing_profiles.
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
const promptMode = ref('dna')

const snack = reactive({ show: false, text: '', color: 'success' })

const SOURCE_TABS = [
  { value: 'url', label: 'Từ URL', icon: 'tabler-link', disabled: true, isExperiment: true },
  { value: 'paste', label: 'Dán nội dung', icon: 'tabler-clipboard-text' },
  { value: 'file', label: 'Từ file', icon: 'tabler-file-upload' },
]

const DETAIL_TABS = [
  { value: 'style', label: 'Văn phong', icon: 'tabler-feather' },
  { value: 'structure', label: 'Cấu trúc', icon: 'tabler-layout-distribute-horizontal' },
  { value: 'sandbox', label: 'Phòng thử DNA', icon: 'tabler-flask' },
  { value: 'content', label: 'Nội dung', icon: 'tabler-file-text', isExperiment: true },
  { value: 'images', label: 'Hình ảnh', icon: 'tabler-photo', isExperiment: true },
  { value: 'uniqueness', label: 'Tính độc đáo', icon: 'tabler-fingerprint', isExperiment: true },
]

/* ---------------------------------------------------------------------
 * CẤP ĐỘ ĐỘ DÀI (LengthTier) — nạp từ GET /writing-profiles/options.
 * Hằng số dưới là FALLBACK: giữ UI không trắng khi API chưa trả / lỗi,
 * đồng thời cấp `title` + `color` mà endpoint options không có.
 * ------------------------------------------------------------------- */
const TIER_OPTIONS_FALLBACK = [
  { title: 'Micro — Cực ngắn (1–3 câu, < 60 từ)', value: 'micro', hint: '2–3 câu', color: 'error' },
  { title: 'Short — Ngắn (4–10 câu, 60–180 từ)', value: 'short', hint: '3–4 câu', color: 'warning' },
  { title: 'Medium — Trung bình (11–30 câu, 180–600 từ)', value: 'medium', hint: '20–30 câu', color: 'info' },
  { title: 'Long — Bài dài (> 30 câu, > 600 từ)', value: 'long', hint: 'bài dài (phân đoạn rõ ràng)', color: 'primary' },
]

/* Màu theo tier — chỉ là phần trang trí UI, backend không quản. Giữ hằng
 * nhỏ này; title + hint giờ lấy từ API (config label/sentence_hint). */
const TIER_COLOR = Object.fromEntries(
  TIER_OPTIONS_FALLBACK.map(t => [t.value, t.color]),
)

/* Danh sách tier dùng cho mọi AppSelect/VBtn: ưu tiên store.tiers (API),
 * fallback hằng số. `title` = `label` tiếng Việt backend trả; `hint` =
 * `sentence_hint` backend làm chuẩn — một nguồn metadata duy nhất. */
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

/* State cho tab "Từ URL" — KHÔNG mồi sẵn dữ liệu giả: ô URL để trống,
 * meta preview chỉ nạp sau khi backend trích xuất thật qua ReferenceSourceFetcher. */
const sourceUrl = ref('')
const urlExtractMode = ref('article')

const URL_EXTRACT_MODES = [
  { title: 'Chỉ trích xuất nội dung bài viết chính (Article Body)', value: 'article' },
  { title: 'Trích xuất toàn trang kèm cấu trúc Heading & Meta SEO', value: 'full' },
]

const URL_PREVIEW_META = reactive({
  domain: '',
  title: 'Nhập URL bài viết để trích xuất nội dung',
  wordCount: '—',
  readingTime: '—',
  fetchedAt: 'Chưa trích xuất',
})

/* State cho tab "Dán nội dung" — khởi tạo rỗng (không mồi bài mẫu giả). */
const articleContent = ref('')

/* State cho tab "Từ file" — chỉ hiển thị khi user đã chọn file thật. */
const fileInputRef = ref(null)

const selectedFile = reactive({
  name: '',
  sizeLabel: '',
  typeLabel: '',
  words: '',
  status: '',
})

/* Chuẩn hóa HTML Tiptap -> text: giữ \n giữa các block để đếm câu đúng
 * (replace tag thô làm </p><p> dính thành 1 cục, \n{2,} không bao giờ match). */
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

/* Đếm từ realtime từ HTML của editor (bỏ tag, tách khoảng trắng). */
const wordCount = computed(() => {
  const t = htmlToText(articleContent.value)
  if (!t) return 0

  return t.split(/\s+/).filter(Boolean).length
})

/* Đếm số câu realtime theo quy tắc WritingProfileDefinition::countSentences. */
const sentenceCount = computed(() => {
  const plain = htmlToText(articleContent.value)
  if (!plain) return 0
  const parts = plain.split(/[.!?…]+|\n{2,}|—\s*/u).map(s => s.trim()).filter(Boolean)

  return Math.max(1, parts.length)
})

/* Tự động phân loại cấp độ độ dài LengthTier giống WritingProfileDefinition::detectTier. */
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

/* Meta của tier đang phát hiện. Fallback tra theo value 'medium' (KHÔNG
 * dùng chỉ số [2] — thứ tự mảng từ API không đảm bảo). */
const detectedTierMeta = computed(() => {
  const list = tierOptions.value

  return list.find(t => t.value === detectedTier.value)
    ?? list.find(t => t.value === 'medium')
    ?? list[0]
})

/* ---------------------------------------------------------------------
 * TAB 1 (PHÂN TÍCH CHI TIẾT): NỘI DUNG
 * -------------------------------------------------------------------
 * Backend hiện CHƯA cung cấp summary/topics/strengths/improvements trong
 * result_json (lộ trình giai đoạn sau) — các khối này hiển thị empty state
 * cho tới khi có dữ liệu thật. KHÔNG mồi dữ liệu giả (doc §10.3). */

/* ---------------------------------------------------------------------
 * TAB 2 (PHÂN TÍCH CHI TIẾT): VĂN PHONG (ĐỦ 16 RULE_KEYS & DNA EVIDENCE)
 * ------------------------------------------------------------------- */
const selectedDnaTierFilter = ref('long')

/* ID hồ sơ nháp do AnalyzeWritingProfileJob tự tạo khi giải mã thành công.
 * Có giá trị -> saveWritingProfile gọi PUT cập nhật (không tạo trùng) và
 * mở khóa nút viết thử trong Phòng thử DNA (Sandbox). */
const draftProfileId = ref(null)
const lastSubmittedAnalysisId = ref(null)
const rawRulesJson = ref(null)
const isAnalyzing = computed(() => store.analyzing || store.analysisPolling)

/* Đã có kết quả giải mã thật chưa — chốt điều kiện hiển thị dữ liệu Tab 2
 * và điều kiện LƯU HỒ SƠ (không cho lưu rules trống vào DB — doc §10.3). */
const hasAnalysisResult = computed(() => !!rawRulesJson.value)

/* 4 thẻ chỉ số DNA — tính từ dữ liệu THẬT: tier phát hiện, đếm từ/câu của
 * bài mẫu, số khóa kích hoạt theo tier và số trích dẫn đã xác thực. */
const styleDnaMetrics = computed(() => [
  {
    label: 'Cấp độ độ dài (Tier)',
    value: detectedTierMeta.value?.title ?? '—',
    sub: 'Phát hiện theo độ dài bài mẫu',
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
    label: 'Khóa DNA kích hoạt',
    value: `${16 - droppedRuleKeys.value.length}/16`,
    sub: droppedRuleKeys.value.length
      ? `${droppedRuleKeys.value.length} khóa đẩy vào uncertainties`
      : 'Đủ 16/16 khóa áp dụng',
    icon: 'tabler-dna',
    color: 'success',
  },
  {
    label: 'Trích dẫn xác thực',
    value: String(dnaEvidences.value.length),
    sub: 'Bằng chứng nguyên văn khớp bài mẫu',
    icon: 'tabler-discount-check',
    color: 'warning',
  },
])

/* Các khóa bị đẩy vào uncertainties khi chọn tier ngắn (theo WritingProfile::applicableRules). */
const droppedRuleKeys = computed(() => {
  if (selectedDnaTierFilter.value === 'micro') {
    return ['headings', 'bullets', 'paragraph_rhythm', 'structure_patterns']
  }
  if (selectedDnaTierFilter.value === 'short') {
    return ['headings', 'bullets', 'structure_patterns']
  }

  return []
})

/* Câu hỏi giải mã của 16 khóa, nạp từ options API: key -> question.
 * Rỗng khi API chưa trả -> rule.title dùng key tự chuẩn hóa làm nhãn. */
const ruleQuestionByKey = computed(() =>
  Object.fromEntries((store.ruleKeys ?? []).map(r => [r.key, r.question])),
)

/* 16 khóa DNA — CHỈ đổ dữ liệu THẬT từ applyAnalysisResult(); khởi tạo rỗng,
 * không mồi demo (doc §10.3). Khi chưa phân tích, khối render empty state. */
const dnaRuleExtractions = ref([])

/* Thứ tự 16 khóa: ưu tiên thứ tự chuẩn từ API (config rule_keys). title =
 * câu hỏi giải mã backend trả (doc GĐ1); chỉ khi backend chưa trả mới rơi
 * về title/key tự chuẩn hóa. */
const filteredDnaRules = computed(() => {
  const byKey = Object.fromEntries(dnaRuleExtractions.value.map(r => [r.key, r]))

  const ordered = store.ruleKeys?.length
    ? store.ruleKeys.map(r => byKey[r.key] ?? {
      key: r.key,
      title: r.key,
      desc: '',
    })
    : dnaRuleExtractions.value

  return ordered.map(rule => {
    const question = ruleQuestionByKey.value[rule.key] ?? ''

    return {
      ...rule,

      // Ưu tiên question từ backend làm title (đúng spec GĐ1); giữ title cũ
      // chỉ khi backend chưa trả. Khi question === title thì caption tự ẩn.
      title: question || rule.title || rule.key,
      question,
      isDroppedInTier: droppedRuleKeys.value.includes(rule.key),
    }
  })
})

/* Bằng chứng trích dẫn — CHỈ đổ dữ liệu THẬT từ applyAnalysisResult();
 * khởi tạo rỗng, không mồi demo (doc §10.3). */
const dnaEvidences = ref([])

/* Bộ 16 Quy tắc Viết văn Cốt lõi — hiện CHƯA có đầu vào thật từ backend
 * (severity/điểm từng quy tắc chưa được tính), nên hiển thị empty state
 * thay vì danh sách chấm demo (doc §10.3). */

/* ---------------------------------------------------------------------
 * TAB 3 (PHÂN TÍCH CHI TIẾT): CẤU TRÚC — CHƯA có dữ liệu thật từ backend
 * (thống kê heading/đoạn/câu, dàn ý H1-H3 không được tính trong result_json),
 * nên hiển thị empty state thay vì số liệu demo (doc §10.3).
 * ------------------------------------------------------------------- */

/* ---------------------------------------------------------------------
 * TAB 4 (PHÂN TÍCH CHI TIẾT): HÌNH ẢNH — CHƯA có dữ liệu thật từ backend
 * (chưa có bộ quét ảnh/gợi ý minh họa trong result_json), nên hiển thị
 * empty state thay vì số liệu + ảnh demo (doc §10.3).
 * ------------------------------------------------------------------- */

/* ---------------------------------------------------------------------
 * TAB 6 (PHÂN TÍCH CHI TIẾT): TÍNH ĐỘC ĐÁO — CHƯA có dữ liệu thật từ backend
 * (chưa chấm độ độc đáo/information gain/human-like, chưa so sánh đoạn
 * khuôn mẫu), nên hiển thị empty state thay vì số liệu demo (doc §10.3).
 * ------------------------------------------------------------------- */

/* ---------------------------------------------------------------------
 * TAB 7 (PHÂN TÍCH CHI TIẾT): PHÒNG THỬ NGHIỆM DNA (SANDBOX - PLAN.md §310-311)
 * ------------------------------------------------------------------- */
const sandboxForm = reactive({
  topic: '',
  targetTier: 'short',
})

const sandboxGeneratedText = ref('')
const sandboxSnapshotPrompt = ref('')

/* Chỉ render văn bản THẬT sandbox sinh — KHÔNG còn fallback mẫu cứng.
 * Khác biệt "chưa chạy" vs "đã chạy mà rỗng" được phân tách bằng cờ
 * sandboxHasRan bên dưới (doc §10.6). */
const sandboxHasRan = ref(false)

/* Chỉ render văn bản THẬT sandbox sinh — KHÔNG còn fallback mẫu cứng.
 * sandboxHasRan phân biệt "chưa chạy" vs "đã chạy mà rỗng" (doc §10.6). */
const activeSandboxSample = computed(() => sandboxGeneratedText.value)

/* ---------------------------------------------------------------------
 * CỘT PHẢI: HIỆU CHỈNH SẮC THÁI CẢM XÚC (TONE & EMOTION CALIBRATION)
 * ------------------------------------------------------------------- */
const TONE_PRESETS = [
  { title: 'DNA Gốc (Từ bài mẫu)', value: 'default' },
  { title: 'Hóm hỉnh & Gần gũi', value: 'humorous' },
  { title: 'Đồng cảm & Trân trọng', value: 'empathy' },
  { title: 'Sắc sảo & Quyết đoán', value: 'assertive' },
  { title: 'Truyền lửa & Thôi thúc', value: 'inspiring' },
]

const activeTonePreset = ref('default')

const toneSliders = reactive([
  {
    key: 'humor',
    category: 'emotion',
    label: 'Vui vẻ & Hóm hỉnh (Humor & Wit)',
    baseline: 20,
    value: 20,
    color: 'warning',
    icon: 'tabler-mood-smile',
    hint: 'Chêm xen ví dụ dí dỏm, chơi chữ tinh tế, tạo không khí nhẹ nhàng',
  },
  {
    key: 'empathy',
    category: 'emotion',
    label: 'Trân trọng & Đồng cảm (Empathy & Gratitude)',
    baseline: 40,
    value: 40,
    color: 'error',
    icon: 'tabler-heart',
    hint: 'Ghi nhận nỗ lực độc giả, thấu hiểu khó khăn/pain point, giọng điệu ấm áp',
  },
  {
    key: 'assertiveness',
    category: 'tone',
    label: 'Sắc sảo & Quyết đoán (Assertiveness & Edge)',
    baseline: 75,
    value: 75,
    color: 'primary',
    icon: 'tabler-bolt',
    hint: 'Luận điểm đanh thép, lập trường dứt khoát, giảm bớt từ ngập ngừng né tránh',
  },
  {
    key: 'formality',
    category: 'tone',
    label: 'Trang trọng vs Thân mật (Formality Spectrum)',
    baseline: 65,
    value: 65,
    color: 'info',
    icon: 'tabler-scale',
    hint: '0%: Thân tình như bạn bè | 100%: Chuẩn mực học thuật / ngoại giao',
  },
  {
    key: 'inspiration',
    category: 'emotion',
    label: 'Nhiệt huyết & Truyền lửa (Energy & Inspiration)',
    baseline: 45,
    value: 45,
    color: 'warning',
    icon: 'tabler-flame',
    hint: 'Tiết tấu hào sảng, thắp sáng động lực, khơi gợi niềm tin vào giải pháp',
  },
  {
    key: 'candor',
    category: 'emotion',
    label: 'Chân thực & Bộc trực (Candor & Authenticity)',
    baseline: 70,
    value: 70,
    color: 'success',
    icon: 'tabler-shield-check',
    hint: 'Kể chuyện thực tế (Storytelling), dám nói sự thật gai góc, không né tránh thất bại',
  },
  {
    key: 'depth',
    category: 'tone',
    label: 'Trí tuệ & Chuyên sâu (Intellect & Depth)',
    baseline: 85,
    value: 85,
    color: 'primary',
    icon: 'tabler-brain',
    hint: 'Tập trung phân tích bản chất, cơ chế hoạt động ngầm, số liệu và kiến trúc',
  },
  {
    key: 'urgency',
    category: 'tone',
    label: 'Khẩn thiết & Kêu gọi (Urgency & Call-to-Action)',
    baseline: 35,
    value: 35,
    color: 'error',
    icon: 'tabler-alarm',
    hint: 'Nhấn mạnh tính cấp thiết của thời điểm, thôi thúc độc giả hành động ngay',
  },
])

function applyTonePreset(presetKey) {
  activeTonePreset.value = presetKey
  if (presetKey === 'default') {
    toneSliders.forEach(t => {
      t.value = t.baseline
    })
    snack.text = 'Đã khôi phục phổ cảm xúc về DNA gốc của bài mẫu'
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

function resetToneToBaseline() {
  applyTonePreset('default')
}

/* System prompt cơ sở — CHỈ nạp từ analysis.style_instructions (applyAnalysisResult);
 * khởi tạo rỗng, không mồi snapshot demo (doc §10.3). Phần calibration do
 * computedStyleInstructions bên dưới ghép thêm, chỉ khi đã có dữ liệu thật. */
const styleInstructionsBase = ref('')

const computedStyleInstructions = computed(() => {
  const toneStatusLines = toneSliders.map(t => {
    const diff = t.value - t.baseline
    const diffLabel = diff === 0 ? 'Chuẩn DNA gốc' : diff > 0 ? `+${diff}%` : `${diff}%`

    return `  * ${t.label.split('(')[0].trim()}: ${t.value}% (${diffLabel}) – ${t.hint}`
  })

  const toneSection = `\n\n6. HIỆU CHỈNH SẮC THÁI CẢM XÚC THỜI GIAN THỰC (TONE CALIBRATION):\n${toneStatusLines.join('\n')}`

  return `${styleInstructionsBase.value}${toneSection}`
})

/* Nội dung hiển thị ở Card Gợi ý Prompt chế độ "dna":
 * Khi đã chạy Sandbox và nhận snapshot.system_prompt từ backend (bản đã strip
 * hoàn toàn evidence_json) thì ưu tiên hiển thị để chứng minh chống đạo văn. */
const displayedDnaPrompt = computed(() =>
  sandboxSnapshotPrompt.value || computedStyleInstructions.value,
)

/* 5 trục phong cách gốc — CHƯA có điểm thật từ backend (chưa chấm expertise/
 * naturalness/persuasiveness/complexity/emotion), nên hiển thị empty state
 * thay vì cột điểm demo (doc §10.3). */
const styleAnalysisTraits = ref([])

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

/* Trạng thái gợi ý slug từ backend — đổ vào SlugField (dumb component).
 * available=null: chưa gọi; true: base trống; false: base đã có record khác. */
const slugAvailable = ref(null)
const slugTakenBy = ref(null)

/* Gọi API gợi ý slug từ 1 chuỗi nguồn bất kỳ — parent chịu trách fetch,
 * component SlugField chỉ nhận props trạng thái. Mọi gợi ý thành công đều
 * GHI LẠI vào ô slug (kể cả slug tay — backend chuẩn hóa + suffix nếu trùng). */
async function runSlugSuggest(source) {
  const src = source.trim()
  if (!src) return

  try {
    const res = await store.suggestSlug({
      source: src,
      sluggable_type: 'writing_profile',
      sluggable_id: draftProfileId.value,
    })

    // null = response cũ đã bị request mới thay thế (latest-wins ở store) — bỏ qua.
    if (!res) return

    if (res?.slug) profileForm.slug = res.slug
    slugAvailable.value = res?.available ?? null
    slugTakenBy.value = res?.taken_by ?? null
  } catch {
    // Lỗi mạng — giữ nguyên slug user đang gõ, không chặn nhập.
    slugAvailable.value = null
    slugTakenBy.value = null
  }
}

/* Blur ô Tên  → source = name hiện tại  → gợi ý + ghi vào ô slug. */
function onNameBlur() {
  runSlugSuggest(profileForm.name)
}

/* Blur ô Slug → source = slug đang gõ  → gợi ý + ghi lại ô slug (chuẩn hóa). */
function onSlugBlur() {
  runSlugSuggest(profileForm.slug)
}

/**
 * =====================================================================
 * CHỨC NĂNG: Màu theme theo ngưỡng điểm đánh giá.
 * =====================================================================
 * Ngưỡng theo spec module (docs/modules/09-writing-style.md §7):
 * >= 80 xanh (success), 60–79 vàng (warning), < 60 đỏ (error).
 *
 * INPUT : score (number 0–100).
 * OUTPUT: string tên màu theme Vuetify.
 * =====================================================================
 */
function scoreColor(score) {
  if (score >= 80) return 'success'
  if (score >= 60) return 'warning'

  return 'error'
}

/**
 * =====================================================================
 * CHỨC NĂNG: Trả về cấu hình hiển thị (màu, icon, nhãn) cho mức kiểm tra quy tắc.
 * =====================================================================
 * INPUT : severity ('passed' | 'warning' | 'hint' | 'blocking').
 * OUTPUT: { color: string, icon: string, label: string }.
 * =====================================================================
 */
function severityMeta(severity) {
  if (severity === 'passed') {
    return { color: 'success', icon: 'tabler-circle-check', label: 'Đạt chuẩn' }
  }
  if (severity === 'blocking') {
    return { color: 'error', icon: 'tabler-alert-octagon', label: 'Vi phạm nghiêm trọng' }
  }
  if (severity === 'warning') {
    return { color: 'warning', icon: 'tabler-alert-triangle', label: 'Cần lưu ý' }
  }

  return { color: 'info', icon: 'tabler-bulb', label: 'Gợi ý tinh chỉnh' }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Xóa sạch nội dung bài viết đã dán trong trình soạn thảo.
 * =====================================================================
 * INPUT : không.
 * OUTPUT: void; UI editor trở về trạng thái trống.
 * =====================================================================
 */
function clearContent() {
  articleContent.value = ''
  snack.text = 'Đã xóa nội dung bài viết đã dán'
  snack.color = 'info'
  snack.show = true
}

/**
 * =====================================================================
 * CHỨC NĂNG: Chuẩn hóa giá trị 1 khóa trong rules_json thành chuỗi hiển thị
 * =====================================================================
 *
 * INPUT:
 * - key (string): Tên khóa thuộc tính DNA (tone, pronouns, uncertainties...)
 * - rawValue (string | string[] | null): Giá trị trả về từ AI
 *
 * OUTPUT:
 * - string: Chuỗi mô tả đã chuẩn hóa, an toàn để hiển thị trên thẻ UI
 *
 * SIDE EFFECT:
 * - Nối mảng chuỗi bằng dấu ' • ' đối với khóa uncertainties
 *
 * EXCEPTION:
 * - Không có (xử lý null-safe cho mọi kiểu dữ liệu)
 * =====================================================================
 */
function formatRuleDesc(key, rawValue, isDroppedInTier = false) {
  if (key === 'uncertainties') {
    if (Array.isArray(rawValue)) {
      const items = rawValue.map(v => String(v ?? '').trim()).filter(Boolean)

      return items.length
        ? items.join(' • ')
        : 'Mẫu đầy đủ dữ liệu — không có yếu tố bất định.'
    }
    if (typeof rawValue === 'string' && rawValue.trim())
      return rawValue.trim()

    return 'Mẫu đầy đủ dữ liệu — không có yếu tố bất định.'
  }

  if (Array.isArray(rawValue))
    return rawValue.map(v => String(v ?? '').trim()).filter(Boolean).join(' • ')

  const text = String(rawValue ?? '').trim()
  if (text)
    return text

  // Chỉ khóa bị đẩy vào uncertainties theo tier mới nói "đã chuyển" — còn
  // lại là AI trả rỗng (khác hẳn "không áp dụng").
  if (isDroppedInTier)
    return 'Không áp dụng ở cấp độ độ dài này (đã chuyển vào uncertainties).'

  return 'Chưa có dữ liệu cho khóa này.'
}

/**
 * =====================================================================
 * CHỨC NĂNG: Đổ kết quả giải mã (WritingAnalysisResource) vào state UI
 * =====================================================================
 *
 * INPUT:
 * - analysis (Object): Resource trả về từ GET /writing-profiles/analyses/{id}
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Cập nhật dnaRuleExtractions, dnaEvidences, styleInstructionsBase,
 *   selectedDnaTierFilter, profileForm.dominantTier và draftProfileId
 *
 * EXCEPTION:
 * - Không ném exception (bảo vệ bằng optional chaining)
 * =====================================================================
 */
function applyAnalysisResult(analysis) {
  const result = analysis?.result_json ?? {}

  const rulesObj = result.rules_json && typeof result.rules_json === 'object'
    ? result.rules_json
    : {}

  rawRulesJson.value = rulesObj

  const keysOrder = store.ruleKeys?.length
    ? store.ruleKeys.map(r => r.key)
    : []

  dnaRuleExtractions.value = keysOrder.map((key, idx) => ({
    key,
    title: key,
    desc: formatRuleDesc(key, rulesObj[key], droppedRuleKeys.value.includes(key)),
  }))

  const evidencesRaw = Array.isArray(result.evidence_json) ? result.evidence_json : []

  dnaEvidences.value = evidencesRaw.map(ev => ({
    ruleKey: ev.rule_key ?? ev.ruleKey ?? 'unknown',
    excerpt: String(ev.excerpt ?? '').trim(),
    note: String(ev.note ?? '').trim(),
  })).filter(ev => ev.excerpt !== '')

  if (typeof result.style_instructions === 'string' && result.style_instructions.trim())
    styleInstructionsBase.value = result.style_instructions.trim()

  const backendTier = analysis?.detected_tier || result.detected_tier || detectedTier.value
  if (backendTier) {
    selectedDnaTierFilter.value = backendTier
    profileForm.dominantTier = backendTier
    sandboxForm.targetTier = backendTier
  }

  draftProfileId.value = analysis?.draft_profile_id ?? analysis?.draft_profile?.id ?? null
  sandboxGeneratedText.value = ''
  sandboxSnapshotPrompt.value = ''
  sandboxHasRan.value = false
}

/**
 * =====================================================================
 * CHỨC NĂNG: Đóng gói rules_json đúng schema backend khi lưu profile
 * =====================================================================
 *
 * INPUT:
 * - Không có (đọc từ rawRulesJson hoặc dnaRuleExtractions.value)
 *
 * OUTPUT:
 * - Object: 15 khóa chuỗi và khóa uncertainties dạng mảng chuỗi string[]
 *
 * SIDE EFFECT:
 * - Không có (chỉ tạo object payload)
 *
 * EXCEPTION:
 * - Không có
 * =====================================================================
 */
function buildRulesJsonPayload() {
  if (rawRulesJson.value && typeof rawRulesJson.value === 'object')
    return { ...rawRulesJson.value }

  const out = {}
  for (const item of dnaRuleExtractions.value) {
    if (item.key === 'uncertainties')
      out.uncertainties = item.desc ? [item.desc] : []
    else
      out[item.key] = item.desc ?? ''
  }

  return out
}

/**
 * =====================================================================
 * CHỨC NĂNG: Đóng gói mảng evidence_json đúng schema backend ({rule_key, excerpt, note})
 * =====================================================================
 *
 * INPUT:
 * - Không có (đọc từ dnaEvidences.value)
 *
 * OUTPUT:
 * - Array<Object>: Danh sách trích dẫn bằng chứng hợp lệ
 *
 * SIDE EFFECT:
 * - Chuẩn hóa trường ruleKey thành rule_key chuẩn snake_case
 *
 * EXCEPTION:
 * - Không có
 * =====================================================================
 */
function buildEvidenceJsonPayload() {
  return dnaEvidences.value.map(ev => ({
    rule_key: ev.ruleKey,
    excerpt: ev.excerpt,
    note: ev.note ?? '',
  }))
}

/**
 * =====================================================================
 * CHỨC NĂNG: Gửi bài viết mẫu lên AI giải mã DNA và poll kết quả (GĐ3)
 * =====================================================================
 *
 * INPUT:
 * - Không có (đọc từ articleContent.value và profileForm.name)
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi POST /analyze, hiển thị snackbar tiến trình, bật cờ isAnalyzing,
 *   gọi store.waitForAnalysis() và cập nhật toàn bộ thẻ DNA khi hoàn tất
 *
 * EXCEPTION:
 * - ApiError và timeout error được bắt trong try/catch và hiển thị qua snackbar
 * =====================================================================
 */
/*
 * Phần chung sau khi dispatch: nhận analysis_id, xử lý dedupe, chờ job xong,
 * đổ kết quả vào DNA + chuyển tab Văn phong. Trả true khi thành công.
 * Dùng chung cho cả 3 nguồn (paste/url/file) để không lặp logic poll.
 */
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
    snack.text = 'Đã tiếp nhận — AI đang giải mã 16 khóa DNA văn phong trong nền...'
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

/**
 * =====================================================================
 * CHỨC NĂNG: Trích xuất nội dung bài viết thật từ URL (GĐ5).
 * =====================================================================
 * Gọi POST /analyze với source_type='url' — backend tải + bóc nội dung
 * thật qua ReferenceSourceFetcher (chống SSRF). Meta preview nạp từ
 * response.meta {domain,title,word_count}; không còn nạp HTML mẫu.
 *
 * INPUT : đọc từ ref sourceUrl.
 * OUTPUT: void.
 * SIDE EFFECT: fetch URL thật (backend), cập nhật URL_PREVIEW_META +
 * articleContent (nếu backend trả preview text), dispatch job giải mã.
 * =====================================================================
 */
async function fetchFromUrl() {
  if (isAnalyzing.value) return

  const raw = sourceUrl.value.trim()
  if (!raw) {
    snack.text = 'Vui lòng nhập đường dẫn URL hợp lệ trước khi trích xuất'
    snack.color = 'warning'
    snack.show = true

    return
  }

  // Validate cơ bản phía client — backend vẫn kiểm đầy đủ (url:http,https
  // + chặn IP nội bộ). Chặn sớm URL không phải http(s) để UX rõ.
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

    // Nạp meta preview thật từ backend (ReferenceSourceFetcher).
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

    // Preview bóc nội dung thật vào editor để người dùng soát trước khi
    // phân tích xong — đồng bộ với luồng paste.
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

/**
 * =====================================================================
 * CHỨC NĂNG: Kích hoạt hộp thoại chọn file từ máy tính
 * =====================================================================
 *
 * INPUT:
 * - Không có
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Kích hoạt sự kiện click() trên thẻ input[type=file] ẩn
 *
 * EXCEPTION:
 * - Không có
 * =====================================================================
 */
function triggerFileInput() {
  fileInputRef.value?.click()
}

/**
 * =====================================================================
 * CHỨC NĂNG: Đọc nội dung file văn bản người dùng chọn và nạp vào editor
 * =====================================================================
 *
 * INPUT:
 * - event (Event): Sự kiện change từ thẻ input[type=file]
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Cập nhật thông tin selectedFile, dùng FileReader đọc text vào articleContent
 *   và kích hoạt snackbar thông báo thành công
 *
 * EXCEPTION:
 * - Không có
 * =====================================================================
 */
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

/**
 * =====================================================================
 * CHỨC NĂNG: Gỡ file đang chọn, xóa metadata và giá trị input
 * =====================================================================
 *
 * INPUT:
 * - Không có
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Đặt lại selectedFile về rỗng, xóa giá trị file input và bật snackbar
 *
 * EXCEPTION:
 * - Không có
 * =====================================================================
 */
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

/**
 * =====================================================================
 * CHỨC NĂNG: Chạy Phòng thử nghiệm DNA (Sandbox) qua API thật (GĐ4)
 * =====================================================================
 *
 * INPUT:
 * - Không có (đọc draftProfileId.value, sandboxForm.topic, sandboxForm.targetTier)
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi POST /writing-profiles/{id}/sandbox, cập nhật sandboxGeneratedText,
 *   nạp snapshot.system_prompt vào sandboxSnapshotPrompt và báo snackbar
 *
 * EXCEPTION:
 * - ApiError khi AI gateway gặp sự cố được bắt và hiển thị qua snackbar
 * =====================================================================
 */
async function runSandboxDemo() {
  if (!draftProfileId.value) {
    snack.text = 'Hãy phân tích bài mẫu trước để tạo hồ sơ DNA thử nghiệm'
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
    const res = await store.sandbox(draftProfileId.value, {
      topic,
      target_tier: sandboxForm.targetTier,
    })

    sandboxGeneratedText.value = res?.text ?? ''
    sandboxHasRan.value = true

    if (res?.snapshot?.system_prompt) {
      sandboxSnapshotPrompt.value = res.snapshot.system_prompt
      promptMode.value = 'dna'
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
 * CHỨC NĂNG: Lưu kết quả giải mã thành Writing Profile thật (GĐ4)
 * =====================================================================
 *
 * INPUT:
 * - openEditor (boolean): true nếu muốn điều hướng sang trang chỉnh sửa chi tiết
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi PUT /writing-profiles/{id} (nếu đã có draftProfileId) hoặc
 *   POST /writing-profiles (nếu tạo tay), điều hướng bằng router.push
 *
 * EXCEPTION:
 * - ApiError 422 hoặc lỗi mạng được bắt và hiển thị qua snackbar
 * =====================================================================
 */
async function saveWritingProfile(openEditor = false) {
  const name = profileForm.name.trim()
  if (!name) {
    snack.text = 'Vui lòng nhập tên Writing Profile trước khi lưu'
    snack.color = 'warning'
    snack.show = true

    return
  }

  // Chặn lưu khi chưa phân tích — rules_json chỉ từ rawRulesJson thật
  // (tránh ghi dữ liệu giả/trống vào DB theo quyết định doc §10.3).
  if (!hasAnalysisResult.value) {
    snack.text = 'Hãy phân tích bài mẫu trước để tạo hồ sơ DNA thật rồi mới lưu.'
    snack.color = 'warning'
    snack.show = true

    return
  }

  const payload = {
    name,

    // Slug: ô cho người dùng tự đặt (đã slugify trên input); để trống thì
    // backend tự sinh từ name — KHÔNG gửi slug rỗng lên khi user không nhập.
    ...(profileForm.slug?.trim() ? { slug: profileForm.slug.trim() } : {}),
    description: profileForm.description?.trim() || null,
    dominant_tier: profileForm.dominantTier,
    rules_json: buildRulesJsonPayload(),
    evidence_json: buildEvidenceJsonPayload(),
    style_instructions: computedStyleInstructions.value,
    status: profileForm.status,
    is_enabled: profileForm.isEnabled,
  }

  try {
    const saved = draftProfileId.value
      ? await store.updateProfile(draftProfileId.value, payload)
      : await store.createProfile(payload)

    if (saved?.id)
      draftProfileId.value = saved.id

    // Đồng bộ slug thật backend trả (có thể tự sinh/auto-suffix khi trùng).
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

/**
 * =====================================================================
 * CHỨC NĂNG: Sao chép chuỗi văn bản bất kỳ vào clipboard của trình duyệt
 * =====================================================================
 *
 * INPUT:
 * - text (string): Nội dung cần sao chép
 * - successMessage (string): Thông báo hiển thị khi sao chép thành công
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Ghi clipboard qua navigator.clipboard.writeText và bật snackbar
 *
 * EXCEPTION:
 * - Bắt lỗi khi trình duyệt chặn quyền clipboard và thông báo cho người dùng
 * =====================================================================
 */
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

/**
 * =====================================================================
 * CHỨC NĂNG: Sao chép prompt đang chọn ở Card Gợi ý Prompt
 * =====================================================================
 *
 * INPUT:
 * - Không có (đọc theo promptMode: 'rewrite' hoặc 'dna')
 *
 * OUTPUT:
 * - Promise<void>
 *
 * SIDE EFFECT:
 * - Gọi hàm copyText với nội dung tương ứng
 *
 * EXCEPTION:
 * - Không có
 * =====================================================================
 */
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

/**
 * =====================================================================
 * GIAI ĐOẠN 1 — nạp options (tiers + 16 câu hỏi giải mã) 1 lần từ API.
 * =====================================================================
 * Lỗi (403/500/mất mạng) KHÔNG làm trắng UI: tierOptions tự quay về
 * TIER_OPTIONS_FALLBACK và rule card dùng `title` hằng số. Vẫn báo
 * snackbar để người dùng biết đang xem nhãn mặc định, không phải dữ
 * liệu từ server. Mọi phần giải mã (analyze/save/sandbox) còn demo tĩnh.
 * =====================================================================
 */
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
    <!-- ================= HEADER: Breadcrumbs, Tiêu đề & Cụm nút ================= -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex align-center gap-3">
        <div class="d-flex flex-column justify-center">
          <h4 class="text-h4 font-weight-bold text-high-emphasis mb-1">
            AI Phân tích bài viết
          </h4>
          <div class="text-body-2 text-medium-emphasis">
            Phân tích chi tiết nội dung, giải mã 16 khóa DNA văn phong và đóng gói thành Writing Profile.
          </div>
        </div>
      </div>

      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-history"
        >
          Lịch sử phân tích
        </VBtn>
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-download"
        >
          Tải báo cáo
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
                    <!--
                      Chế độ thu thập chưa có backend (ReferenceSourceFetcher
                      hiện luôn bóc article body). Vô hiệu để không làm control chết. 
                    -->
                    <AppSelect
                      v-model="urlExtractMode"
                      :items="URL_EXTRACT_MODES"
                      label="Chế độ thu thập"
                      disabled
                      hint="Luôn trích phần thân bài viết (chế độ toàn trang sắp có)"
                      persistent-hint
                    />
                  </VCol>
                </VRow>

                <!-- Hộp thông tin bài viết trích xuất từ URL -->
                <div class="tonal-panel pa-4 rounded mb-4">
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

                  <div class="text-caption text-medium-emphasis">
                    Hệ thống tự động loại bỏ quảng cáo, menu điều hướng và chân trang để giữ lại phần văn bản thuần phục vụ giải mã văn phong &amp; SEO.
                  </div>
                </div>

                <div class="d-flex justify-space-between justify-sm-end flex-wrap gap-2">
                  <VBtn
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
                      <span class="text-caption text-primary font-weight-medium">
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
                    Hỗ trợ định dạng .txt, .md, .html, .docx (Tối đa 5MB — tự động bóc tách tiêu đề H1–H3 và đoạn văn)
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

        <!-- 2. Card Giải mã DNA & Phân tích chi tiết (6 Tabs) -->
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
                Trích xuất 16 quy tắc hành văn (rules_json), cấu trúc dàn ý và phòng thử nghiệm DNA
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
              AI đang giải mã 16 khóa DNA văn phong và kiểm chứng trích dẫn nguyên văn từ bài mẫu (có thể mất 10–60 giây)...
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
                <VChip
                  v-if="tab.isExperiment"
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  class="ms-1"
                >
                  Thử nghiệm
                </VChip>
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

                <!-- Giải mã trọn bộ 16 Khóa DNA (rules_json) theo LengthTier -->
                <div class="mb-6">
                  <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                    <div class="d-flex align-center text-body-2 font-weight-medium">
                      <VIcon
                        icon="tabler-dna"
                        size="16"
                        color="primary"
                        class="me-1"
                      />
                      Bộ 16 Khóa DNA Văn phong trích xuất ({{ 16 - droppedRuleKeys.length }}/16 khóa kích hoạt)
                    </div>

                    <!-- Bộ chọn thử nghiệm phân tầng LengthTier -->
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

                  <VAlert
                    v-if="droppedRuleKeys.length"
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mb-3 text-caption"
                  >
                    Ở cấp độ <strong>{{ selectedDnaTierFilter.toUpperCase() }}</strong>, {{ droppedRuleKeys.length }} khóa cấu trúc bài dài ({{ droppedRuleKeys.join(', ') }}) được tự động đẩy vào <strong>uncertainties</strong> để AI không bịa đặt quy tắc.
                  </VAlert>

                  <VRow dense>
                    <VCol
                      v-for="rule in filteredDnaRules"
                      :key="rule.key"
                      cols="12"
                      sm="6"
                    >
                      <div
                        class="tonal-panel pa-3 rounded h-100"
                        :class="{ 'rule-card--dropped': rule.isDroppedInTier }"
                      >
                        <div class="d-flex align-center justify-space-between gap-2 mb-1">
                          <span class="text-body-2 font-weight-medium text-high-emphasis">
                            {{ rule.title }}
                          </span>
                          <VChip
                            size="x-small"
                            :color="rule.isDroppedInTier ? 'warning' : 'secondary'"
                            variant="tonal"
                            label
                          >
                            {{ rule.isDroppedInTier ? '-> uncertainties' : rule.key }}
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

                <!-- Bằng chứng trích dẫn nguyên văn (evidence_json) -->
                <div class="mb-6">
                  <div class="d-flex align-center text-body-2 font-weight-medium mb-2">
                    <VIcon
                      icon="tabler-quote"
                      size="16"
                      color="primary"
                      class="me-1"
                    />
                    Bằng chứng trích dẫn nguyên văn (Verified Evidence)
                  </div>

                  <div
                    v-if="!dnaEvidences.length"
                    class="tonal-panel pa-3 rounded text-caption text-medium-emphasis"
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
                          Khóa: {{ ev.ruleKey }}
                        </VChip>
                        <span class="d-flex align-center text-caption text-success">
                          <VIcon
                            icon="tabler-discount-check"
                            size="14"
                            class="me-1"
                          />
                          Khớp nguyên văn 100% (str_contains)
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
                </div>

                <!--
                  Kiểm tra 16 Quy tắc viết văn cốt lõi — CHƯA có dữ liệu chấm
                  thật từ backend (chỉ có 16 key + evidence, chưa chấm severity
                  từng quy tắc), nên hiển thị empty state thay vì danh sách demo. 
                -->
                <div>
                  <div class="d-flex align-center text-body-2 font-weight-medium mb-2">
                    <VIcon
                      icon="tabler-shield-check"
                      size="16"
                      color="primary"
                      class="me-1"
                    />
                    Đánh giá theo Bộ 16 Quy tắc Viết văn Cốt lõi
                  </div>

                  <div class="tonal-panel pa-3 rounded text-caption text-medium-emphasis">
                    Phần chấm điểm theo Bộ 16 Quy tắc Viết văn Cốt lõi sẽ hiển thị ở giai đoạn phát triển sau.
                  </div>
                </div>
              </VWindowItem>

              <!-- ================= TAB 3: CẤU TRÚC ================= -->
              <VWindowItem value="structure">
                <!--
                  Chưa có dữ liệu thật từ backend (chỉ số heading/đoạn/câu,
                  dàn ý H1-H3) — hiển thị empty state thay vì số liệu demo. 
                -->
                <div class="tonal-panel pa-4 rounded text-caption text-medium-emphasis">
                  Phần phân tích cấu trúc (hệ thống Heading, phân bổ Mở–Thân–Kết, dàn ý H1–H3) sẽ được phân tích và hiển thị ở giai đoạn phát triển sau.
                </div>
              </VWindowItem>

              <!-- ================= TAB 3: PHÒNG THỬ NGHIỆM DNA (SANDBOX) ================= -->
              <VWindowItem value="sandbox">
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mb-4 text-caption"
                >
                  <strong>Cơ chế Snapshot chống đạo văn (PLAN.md §310):</strong> Khi cấp DNA cho AI viết bài mới, hệ thống chỉ truyền 16 quy tắc hành văn (<code>rules_json</code> + <code>style_instructions</code>) và tự động loại bỏ hoàn toàn trích dẫn mẫu (<code>evidence_json</code>).
                </VAlert>

                <VRow class="mb-4">
                  <VCol
                    cols="12"
                    md="7"
                  >
                    <AppTextField
                      v-model="sandboxForm.topic"
                      label="Chủ đề thử nghiệm mới"
                      placeholder="Nhập chủ đề bất kỳ để kiểm chứng khẩu khí DNA..."
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="5"
                  >
                    <AppSelect
                      v-model="sandboxForm.targetTier"
                      :items="tierOptions"
                      label="Cấp độ độ dài mục tiêu (Target Tier)"
                    />
                  </VCol>
                </VRow>

                <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-4">
                  <span
                    v-if="!draftProfileId"
                    class="text-caption text-warning d-flex align-center"
                  >
                    <VIcon
                      icon="tabler-info-circle"
                      size="15"
                      class="me-1"
                    />
                    Hãy phân tích bài mẫu trước để mở khóa Phòng thử DNA
                  </span>
                  <span
                    v-else
                    class="text-caption text-success d-flex align-center"
                  >
                    <VIcon
                      icon="tabler-circle-check"
                      size="15"
                      class="me-1"
                    />
                    Đang kết nối với hồ sơ nháp #{{ draftProfileId }}
                  </span>

                  <VTooltip
                    :disabled="Boolean(draftProfileId)"
                    location="top"
                  >
                    <template #activator="{ props: tooltipProps }">
                      <span v-bind="tooltipProps">
                        <VBtn
                          variant="flat"
                          color="primary"
                          size="small"
                          prepend-icon="tabler-player-play"
                          :loading="store.sandboxing"
                          :disabled="!draftProfileId || store.sandboxing || isAnalyzing"
                          @click="runSandboxDemo"
                        >
                          Viết thử với DNA vừa phân tích
                        </VBtn>
                      </span>
                    </template>
                    <span>Hãy phân tích bài mẫu trước</span>
                  </VTooltip>
                </div>

                <!-- Kết quả sinh văn bản thử nghiệm -->
                <div class="prompt-box pa-4 rounded">
                  <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                    <div class="d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-flask-2"
                        size="18"
                        color="primary"
                      />
                      <span class="text-body-2 font-weight-medium text-high-emphasis">
                        Văn bản sinh thử nghiệm theo DNA
                      </span>
                      <VChip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        label
                      >
                        Tier: {{ sandboxForm.targetTier.toUpperCase() }}
                      </VChip>
                    </div>

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

                  <div class="text-body-2 text-high-emphasis sandbox-output">
                    <template v-if="!sandboxHasRan && !activeSandboxSample">
                      <span class="text-caption text-medium-emphasis">
                        Chưa có văn bản. Bấm "Viết thử với DNA vừa phân tích" để sinh.
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
              </VWindowItem>

              <!-- ================= TAB 4: NỘI DUNG (THỬ NGHIỆM) ================= -->
              <VWindowItem value="content">
                <!--
                  Tóm tắt nội dung chính — CHƯA có dữ liệu thật từ backend
                  (chưa chấm tóm tắt/chủ đề/điểm mạnh-điểm cần cải thiện),
                  nên hiển thị empty state thay vì nội dung demo. 
                -->
                <div class="tonal-panel pa-4 rounded text-caption text-medium-emphasis">
                  Phần tóm tắt nội dung, các chủ đề chính, điểm mạnh và điểm cần cải thiện sẽ được phân tích và hiển thị ở giai đoạn phát triển sau.
                </div>
              </VWindowItem>

              <!-- ================= TAB 5: HÌNH ẢNH (THỬ NGHIỆM) ================= -->
              <VWindowItem value="images">
                <!--
                  Chưa có dữ liệu quét ảnh / gợi ý minh họa thật từ backend
                  (chưa có bộ quét ảnh trong result_json), nên hiển thị
                  empty state thay vì số liệu + ảnh demo. 
                -->
                <div class="tonal-panel pa-4 rounded text-caption text-medium-emphasis">
                  Phần phân tích hình ảnh, thẻ Alt SEO và gợi ý Prompt tạo ảnh AI sẽ được phân tích và hiển thị ở giai đoạn phát triển sau.
                </div>
              </VWindowItem>

              <!-- ================= TAB 6: TÍNH ĐỘC ĐÁO (THỬ NGHIỆM) ================= -->
              <VWindowItem value="uniqueness">
                <!--
                  Chưa có điểm độ độc đáo / information gain / so sánh đoạn
                  khuôn mẫu thật từ backend — hiển thị empty state. 
                -->
                <div class="tonal-panel pa-4 rounded text-caption text-medium-emphasis">
                  Phần chấm độ độc đáo, giá trị thông tin và so sánh đoạn khuôn mẫu sẽ được phân tích và hiển thị ở giai đoạn phát triển sau.
                </div>
              </VWindowItem>
            </VWindow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- ===== CỘT PHẢI: Chỉ số, Đánh giá, Prompt & Lưu Writing Profile ===== -->
      <VCol
        cols="12"
        lg="5"
      >
        <!-- 1. Phân tích văn phong (Baseline từ bài mẫu) -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-feather"
                  color="primary"
                  size="22"
                  class="me-2"
                />
                <span class="text-h6 font-weight-bold text-high-emphasis">Phân tích văn phong gốc</span>
              </div>
            </template>
            <template #subtitle>
              <div class="text-caption text-medium-emphasis mt-1">
                5 đặc trưng phong cách cốt lõi đo lường trực tiếp từ bài viết mẫu
              </div>
            </template>
            <template #append>
              <VChip
                size="x-small"
                variant="tonal"
                color="primary"
                label
                class="font-weight-medium"
              >
                Baseline DNA
              </VChip>
            </template>
          </VCardItem>

          <VCardText>
            <!-- Khối tóm tắt nhanh khẩu khí & độ dễ đọc -->
            <div class="tonal-panel pa-3 rounded-lg d-flex align-center justify-space-between flex-wrap gap-2 mb-4">
              <div class="d-flex align-center gap-2">
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="34"
                  rounded
                >
                  <VIcon
                    icon="tabler-signature"
                    size="18"
                  />
                </VAvatar>
                <div>
                  <div
                    class="text-caption text-medium-emphasis"
                    style="font-size: 11px;"
                  >
                    Khẩu khí chủ đạo
                  </div>
                  <div class="text-body-2 font-weight-bold text-high-emphasis">
                    Chuyên gia Kỹ thuật
                  </div>
                </div>
              </div>

              <div class="d-flex align-center gap-2">
                <VAvatar
                  color="info"
                  variant="tonal"
                  size="34"
                  rounded
                >
                  <VIcon
                    icon="tabler-book"
                    size="18"
                  />
                </VAvatar>
                <div>
                  <div
                    class="text-caption text-medium-emphasis"
                    style="font-size: 11px;"
                  >
                    Mức độ đọc hiểu
                  </div>
                  <div class="text-body-2 font-weight-bold text-info">
                    Dễ tiếp cận (Flesch 68)
                  </div>
                </div>
              </div>
            </div>

            <!--
              Danh sách 5 trục phong cách thiết kế dạng Cardlet — CHƯA có
              điểm thật từ backend (chưa chấm expertise/naturalness/...). 
            -->
            <div class="d-flex flex-column gap-2">
              <div
                v-if="!styleAnalysisTraits.length"
                class="tonal-panel pa-3 rounded-lg text-caption text-medium-emphasis"
              >
                Phần chấm 5 trục phong cách sẽ hiển thị ở giai đoạn sau.
              </div>
              <div
                v-for="item in styleAnalysisTraits"
                :key="item.name"
                class="trait-cardlet pa-3 rounded-lg"
              >
                <div class="d-flex justify-space-between align-center mb-1">
                  <div class="d-flex align-center gap-2">
                    <VAvatar
                      :color="item.color"
                      variant="tonal"
                      size="26"
                      rounded
                    >
                      <VIcon
                        :icon="item.icon"
                        size="14"
                      />
                    </VAvatar>
                    <span class="text-body-2 font-weight-bold text-high-emphasis">
                      {{ item.name }}
                    </span>
                  </div>

                  <VChip
                    size="x-small"
                    :color="item.color"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    {{ item.percent }}%
                  </VChip>
                </div>

                <VProgressLinear
                  :model-value="item.percent"
                  :color="item.color"
                  height="6"
                  rounded
                  class="my-2"
                />

                <div class="text-caption text-medium-emphasis">
                  {{ item.desc }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- 2. BỘ PHỐI ÂM CẢM XÚC (Tone & Emotion Tuning) -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-adjustments-horizontal"
                  color="primary"
                  size="22"
                  class="me-2"
                />
                <span class="text-h6 font-weight-bold text-high-emphasis">Hiệu chỉnh sắc thái cảm xúc</span>
              </div>
            </template>
            <template #subtitle>
              <div class="text-caption text-medium-emphasis mt-1">
                Phối âm và điều chỉnh dải cảm xúc để định hình khẩu khí chặt chẽ theo thương hiệu
              </div>
            </template>
            <template #append>
              <VBtn
                variant="tonal"
                color="secondary"
                size="x-small"
                prepend-icon="tabler-rotate-clockwise"
                @click="resetToneToBaseline"
              >
                Khôi phục gốc
              </VBtn>
            </template>
          </VCardItem>

          <VCardText>
            <!-- Ô chọn Mẫu phối âm (Tone Preset) -->
            <AppSelect
              v-model="activeTonePreset"
              :items="TONE_PRESETS"
              label="Mẫu phối âm (Preset)"
              placeholder="Chọn mẫu phối âm nhanh..."
              class="mb-4"
              @update:model-value="applyTonePreset"
            />

            <!-- Danh sách 8 thanh trượt cảm xúc dạng Tone Cardlet độc lập -->
            <div class="d-flex flex-column gap-3">
              <div
                v-for="tone in toneSliders"
                :key="tone.key"
                class="tone-cardlet pa-3 rounded-lg"
              >
                <!-- Tầng 1: Tiêu đề & Thông số -->
                <div class="d-flex justify-space-between align-center mb-2">
                  <div class="d-flex align-center">
                    <VAvatar
                      :color="tone.color"
                      variant="tonal"
                      size="26"
                      rounded
                      class="me-2 flex-shrink-0"
                    >
                      <VIcon
                        :icon="tone.icon"
                        size="14"
                      />
                    </VAvatar>
                    <span class="text-body-2 font-weight-bold text-high-emphasis">
                      {{ tone.label }}
                    </span>
                  </div>

                  <div class="d-flex align-center gap-1">
                    <span class="text-caption text-medium-emphasis me-1">Gốc: {{ tone.baseline }}%</span>
                    <VChip
                      size="x-small"
                      variant="flat"
                      :color="tone.color"
                      class="font-weight-bold"
                    >
                      {{ tone.value }}%
                    </VChip>
                    <VChip
                      v-if="tone.value !== tone.baseline"
                      size="x-small"
                      :color="tone.value > tone.baseline ? 'success' : 'warning'"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ tone.value > tone.baseline ? '+' : '' }}{{ tone.value - tone.baseline }}%
                    </VChip>
                  </div>
                </div>

                <!-- Tầng 2: Thanh trượt VSlider khoảng cách thông thoáng -->
                <div class="px-1 py-1">
                  <VSlider
                    v-model="tone.value"
                    :color="tone.color"
                    track-color="secondary"
                    density="compact"
                    hide-details
                    :min="0"
                    :max="100"
                    :step="5"
                    thumb-size="14"
                  />
                </div>

                <!-- Tầng 3: Hộp chú thích tách biệt -->
                <div class="tone-hint-box pa-2 rounded mt-2 d-flex align-center gap-1">
                  <VIcon
                    icon="tabler-info-circle"
                    size="14"
                    color="primary"
                    class="flex-shrink-0"
                  />
                  <span class="text-caption text-medium-emphasis">
                    {{ tone.hint }}
                  </span>
                </div>
              </div>
            </div>

            <VAlert
              type="info"
              variant="tonal"
              density="compact"
              class="mt-4 text-caption"
            >
              Các điều chỉnh cảm xúc được tự động đồng bộ tức thì vào <strong>System Prompt DNA</strong> và phản ánh trực tiếp trong <strong>Phòng thử DNA</strong>.
            </VAlert>
          </VCardText>
        </VCard>

        <!-- 3. Gợi ý prompt để viết lại / cải thiện & System Prompt DNA -->
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
                  Gợi ý prompt &amp; System Prompt DNA
                </span>
              </div>
            </template>
            <template #subtitle>
              <div class="text-caption text-medium-emphasis mt-1">
                Xem trước câu lệnh hệ thống đã được nhúng các quy tắc cảm xúc vừa tinh chỉnh
              </div>
            </template>
            <template #append>
              <VBtn
                variant="text"
                color="primary"
                size="small"
                prepend-icon="tabler-copy"
                @click="copyPrompt"
              >
                Sao chép
              </VBtn>
            </template>
          </VCardItem>

          <VCardText>
            <div
              class="prompt-box pa-3 rounded text-body-2 text-high-emphasis sandbox-output"
              style="max-height: 480px; overflow-y: auto;"
            >
              <div
                v-if="!displayedDnaPrompt.trim()"
                class="text-caption text-medium-emphasis"
              >
                Chưa có System Prompt DNA. Phân tích bài mẫu để xem snapshot.
              </div>
              <template v-else>
                <div
                  v-if="sandboxSnapshotPrompt"
                  class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2"
                >
                  <VChip
                    size="x-small"
                    color="success"
                    variant="tonal"
                    label
                  >
                    Snapshot thực tế từ Backend (đã loại bỏ evidence_json)
                  </VChip>
                </div>
                {{ displayedDnaPrompt }}
              </template>
            </div>
          </VCardText>
        </VCard>

        <!-- 4. Đóng gói & Lưu thành Writing Profile (khớp DB writing_profiles) -->
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
                :color="draftProfileId ? 'info' : 'secondary'"
                variant="tonal"
                label
              >
                origin: {{ draftProfileId ? 'reference' : 'manual' }}
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
                Lưu &amp; Sửa chi tiết 8 Tab
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

/* Khóa DNA bị đẩy vào uncertainties ở tier ngắn. */
.rule-card--dropped {
  border-style: dashed;
  opacity: 0.72;
}

/* Hộp so sánh trước / sau ở tab Tính độc đáo. */
.rewrite-box--before {
  border: 1px solid rgba(var(--v-theme-warning), 0.28);
  background: rgba(var(--v-theme-warning), 0.05);
}

.rewrite-box--after {
  border: 1px solid rgba(var(--v-theme-success), 0.28);
  background: rgba(var(--v-theme-success), 0.05);
}

/* Hiển thị văn bản nhiều dòng cho Sandbox & System Prompt. */
.sandbox-output {
  white-space: pre-line;
}

/* Thẻ đặc trưng văn phong gốc (Baseline Trait Cardlet) */
.trait-cardlet {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-on-surface), 0.015);
  transition: all 0.2s ease;
}

.trait-cardlet:hover {
  background: rgba(var(--v-theme-on-surface), 0.035);
}

/* Thẻ thanh trượt phối âm cảm xúc (Tone Cardlet) */
.tone-cardlet {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-on-surface), 0.015);
  transition: all 0.2s ease;
}

.tone-cardlet:hover {
  border-color: rgba(var(--v-theme-primary), 0.35);
  background: rgba(var(--v-theme-on-surface), 0.025);
}

/* Hộp ghi chú chú thích khẩu khí */
.tone-hint-box {
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-on-surface), 0.025);
}

/* Hộp prompt gợi ý — nền tonal primary nhạt. */
.prompt-box {
  border: 1px solid rgba(var(--v-theme-primary), 0.16);
  background: rgba(var(--v-theme-primary), 0.05);
}
</style>
