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
  - activeSandboxSample     : computed văn bản viết thử sinh từ Sandbox API hoặc mẫu.
  - displayedDnaPrompt      : computed System Prompt DNA ưu tiên snapshot đã strip evidence.
  - scoreColor(n)           : màu theo ngưỡng điểm (>=80 success, >=60 warning, <60 error).
  - severityMeta(level)     : nhãn và màu theme cho mức độ vi phạm quy tắc viết.
  - resetContent()          : khôi phục nội dung mẫu trong editor.
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
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import TiptapEditor from '@core/components/TiptapEditor.vue'
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

/* Màu + nhãn hiển thị theo tier (API chỉ trả value + sentence_hint). */
const TIER_META = Object.fromEntries(
  TIER_OPTIONS_FALLBACK.map(t => [t.value, { title: t.title, color: t.color }]),
)

/* Danh sách tier dùng cho mọi AppSelect/VBtn: ưu tiên store.tiers (API),
 * fallback hằng số. `hint` lấy `sentence_hint` của backend làm chuẩn. */
const tierOptions = computed(() => {
  const apiTiers = store.tiers

  if (!apiTiers?.length) return TIER_OPTIONS_FALLBACK

  return apiTiers.map(t => ({
    value: t.value,
    title: TIER_META[t.value]?.title ?? t.label ?? t.value,
    hint: t.sentence_hint ?? TIER_META[t.value]?.hint ?? '',
    color: TIER_META[t.value]?.color ?? 'secondary',
  }))
})

/* ---------------------------------------------------------------------
 * DỮ LIỆU NGUỒN BÀI VIẾT (3 TAB: URL / PASTE / FILE)
 * ------------------------------------------------------------------- */
const SAMPLE_ARTICLE_HTML = `
  <h4>Laravel 11 mang đến những tính năng mới gì?</h4>
  <p>Laravel 11 là phiên bản mới nhất của framework PHP phổ biến, mang đến nhiều cải tiến đáng chú ý giúp lập trình viên xây dựng ứng dụng nhanh hơn, hiệu quả hơn và bảo mật hơn. Trong bài viết này, chúng ta sẽ cùng tìm hiểu những tính năng nổi bật nhất của Laravel 11.</p>
  <p><strong>1. Hiệu suất được cải thiện</strong></p>
  <p>Laravel 11 tập trung vào việc tối ưu hiệu suất, bao gồm thời gian khởi động nhanh hơn và cải thiện khả năng tự động tải class. Những thay đổi này giúp ứng dụng của bạn chạy mượt mà hơn, đặc biệt là ở quy mô lớn.</p>
`

/* State cho tab "Từ URL". */
const sourceUrl = ref('https://laravel-news.com/laravel-11-new-features-guide')
const urlExtractMode = ref('article')

const URL_EXTRACT_MODES = [
  { title: 'Chỉ trích xuất nội dung bài viết chính (Article Body)', value: 'article' },
  { title: 'Trích xuất toàn trang kèm cấu trúc Heading & Meta SEO', value: 'full' },
]

const URL_PREVIEW_META = reactive({
  domain: 'laravel-news.com',
  title: 'Laravel 11 mang đến những tính năng mới gì? — Hướng dẫn chi tiết',
  wordCount: '1,245 từ',
  readingTime: '~ 5 phút đọc',
  fetchedAt: 'Đã trích xuất sẵn dữ liệu mẫu',
})

/* State cho tab "Dán nội dung". */
const articleContent = ref(SAMPLE_ARTICLE_HTML)

/* State cho tab "Từ file". */
const fileInputRef = ref(null)

const selectedFile = reactive({
  name: 'laravel-11-release-analysis.md',
  sizeLabel: '18.4 KB',
  typeLabel: 'Markdown (.md)',
  words: '1,245 từ',
  status: 'Đã nạp nội dung mẫu',
})

/* Đếm từ realtime từ HTML của editor (bỏ tag, tách khoảng trắng). */
const wordCount = computed(() =>
  articleContent.value
    .replace(/<[^>]+>/g, ' ')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .length,
)

/* Đếm số câu realtime theo quy tắc WritingProfileDefinition::countSentences. */
const sentenceCount = computed(() => {
  const plain = articleContent.value.replace(/<[^>]+>/g, ' ').trim()
  if (!plain) return 0
  const parts = plain.split(/[.!?…]+|\n{2,}/u).map(s => s.trim()).filter(Boolean)

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
 * ------------------------------------------------------------------- */
const ANALYSIS_SUMMARY = 'Bài viết giới thiệu các tính năng mới trong Laravel 11, tập trung vào hiệu suất, công cụ phát triển, bảo mật và trải nghiệm lập trình viên. Nội dung phù hợp với đối tượng lập trình viên PHP/Laravel, cung cấp thông tin hữu ích và thực tế.'

const TOPICS = [
  'Laravel 11',
  'Hiệu suất',
  'Tính năng mới',
  'Bảo mật',
  'Công cụ phát triển',
  'Lập trình PHP',
]

const STRENGTHS = [
  'Nội dung rõ ràng, mạch lạc',
  'Cung cấp ví dụ cụ thể',
  'Phù hợp với đối tượng mục tiêu',
  'Cập nhật các tính năng mới',
  'Có cấu trúc tốt (H2, H3)',
]

const IMPROVEMENTS = [
  'Có thể thêm số liệu thực tế để tăng độ tin cậy',
  'Nên bổ sung hình ảnh minh họa',
  'Thêm liên kết tham khảo chính thức',
  'Tăng tính độc đáo, tránh trùng lặp nội dung',
  'Có thể mở rộng phần so sánh với phiên bản cũ',
]

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

const STYLE_DNA_METRICS = [
  { label: 'Cấp độ độ dài (Tier)', value: 'Long (> 30 câu)', sub: 'Đủ 16/16 khóa DNA', icon: 'tabler-ruler-measure', color: 'primary' },
  { label: 'Hình mẫu (Archetype)', value: 'Thought Leader', sub: 'Chuyên gia chia sẻ thực chiến', icon: 'tabler-award', color: 'info' },
  { label: 'Ngôi xưng (Pronouns)', value: 'Chúng ta – Bạn', sub: 'Nhất quán 100% toàn bài', icon: 'tabler-users', color: 'success' },
  { label: 'Thể chủ động (Active)', value: '88%', sub: 'Đạt chuẩn >= 85% câu chủ động', icon: 'tabler-bolt', color: 'success' },
]

/* Giá trị khởi tạo 16 khóa thuộc tính DNA văn phong (giữ UI không trống trước khi phân tích). */
const DNA_RULE_EXTRACTIONS_DEFAULT = [
  {
    key: 'tone',
    title: '1. Giọng điệu chung (Tone)',
    desc: 'Chuyên nghiệp, điềm tĩnh và mang tính hướng dẫn kỹ thuật rõ ràng, không dùng từ ngữ phóng đại.',
  },
  {
    key: 'pronouns',
    title: '2. Cách xưng hô (Pronouns)',
    desc: 'Xưng "chúng ta" khi dẫn dắt vấn đề và gọi độc giả là "bạn", tạo cảm giác đồng hành cùng lập trình viên.',
  },
  {
    key: 'emotion',
    title: '3. Cung bậc cảm xúc (Emotion)',
    desc: 'Tự tin, khách quan và hào hứng vừa phải trước các cải tiến công nghệ mới.',
  },
  {
    key: 'opening',
    title: '4. Cách mở bài (Opening)',
    desc: 'Mở đầu bằng câu hỏi trực diện vào nhu cầu tìm hiểu phiên bản mới, sau đó tóm tắt 3 lợi ích cốt lõi.',
  },
  {
    key: 'sentence_rhythm',
    title: '5. Nhịp câu (Sentence Rhythm)',
    desc: 'Độ dài trung bình 16.4 từ/câu; xen kẽ câu định nghĩa ngắn với câu giải thích cơ chế hoạt động.',
  },
  {
    key: 'paragraph_rhythm',
    title: '6. Nhịp đoạn văn (Paragraph Rhythm)',
    desc: 'Mỗi đoạn gồm 2–4 câu (khoảng 45–70 từ), dãn cách thoáng mắt trên cả màn hình máy tính và điện thoại.',
  },
  {
    key: 'transitions',
    title: '7. Cách chuyển đoạn (Transitions)',
    desc: 'Chuyển mục bằng đánh số thứ tự rõ ràng; liên kết các đoạn bằng quan hệ nguyên nhân – hệ quả tự nhiên.',
  },
  {
    key: 'vocabulary',
    title: '8. Vốn từ đặc trưng (Vocabulary)',
    desc: 'Sử dụng từ vựng công nghệ chuẩn mực ("tối ưu hiệu suất", "quy mô lớn", "khởi động"), không dùng tiếng lóng.',
  },
  {
    key: 'technical_terms',
    title: '9. Thuật ngữ kỹ thuật (Technical Terms)',
    desc: 'Giữ nguyên thuật ngữ tiếng Anh quen thuộc (framework, class, middleware, WebSocket) kèm giải thích ngữ cảnh.',
  },
  {
    key: 'structure_patterns',
    title: '10. Cấu trúc tổng thể (Structure Patterns)',
    desc: 'Mô hình Listicle kỹ thuật: Câu hỏi mở đầu → Tổng quan → Danh sách tính năng đánh số → Khuyến nghị nâng cấp.',
  },
  {
    key: 'headings',
    title: '11. Tiêu đề con (Headings H2/H3)',
    desc: 'Tiêu đề H2 đánh số thứ tự kèm lợi ích trực tiếp; H3 đi sâu vào từng thành phần cấu hình bên trong.',
  },
  {
    key: 'bullets',
    title: '12. Gạch đầu dòng (Bullets)',
    desc: 'Dùng danh sách liệt kê cho các thông số thay đổi và các bước kiểm tra tương thích trước khi nâng cấp.',
  },
  {
    key: 'examples',
    title: '13. Dẫn chứng & Ví dụ (Examples)',
    desc: 'Minh họa bằng tình huống thực tế khi ứng dụng chạy ở quy mô lớn và so sánh trực tiếp với Laravel 10.',
  },
  {
    key: 'ending',
    title: '14. Cách kết bài (Ending)',
    desc: 'Khép lại câu hỏi ở mở bài bằng nhận định tổng kết và lời khuyên nâng cấp có lộ trình.',
  },
  {
    key: 'avoid',
    title: '15. Yếu tố tuyệt đối tránh (Avoid)',
    desc: 'Không dùng văn phong quảng cáo giật gân, không dịch máy từng từ, không lạm dụng câu bị động dài dòng.',
  },
  {
    key: 'uncertainties',
    title: '16. Yếu tố chưa đủ dữ liệu (Uncertainties)',
    desc: 'Mẫu hiện tại chưa có đoạn hội thoại trực tiếp hoặc kịch bản xử lý phản biện gay gắt.',
  },
]

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
 * Rỗng khi API chưa trả -> UI dùng `title` hằng số làm fallback. */
const ruleQuestionByKey = computed(() =>
  Object.fromEntries((store.ruleKeys ?? []).map(r => [r.key, r.question])),
)

const RULE_TITLE_BY_KEY = Object.fromEntries(
  DNA_RULE_EXTRACTIONS_DEFAULT.map(item => [item.key, item.title]),
)

const dnaRuleExtractions = ref(
  DNA_RULE_EXTRACTIONS_DEFAULT.map(item => ({ ...item })),
)

/* Thứ tự 16 khóa: ưu tiên thứ tự chuẩn từ API (config rule_keys), fallback
 * thứ tự hằng số. Mỗi card: title hiển thị + question (tooltip giải mã). */
const filteredDnaRules = computed(() => {
  const byKey = Object.fromEntries(dnaRuleExtractions.value.map(r => [r.key, r]))

  const ordered = store.ruleKeys?.length
    ? store.ruleKeys.map(r => byKey[r.key] ?? {
      key: r.key,
      title: RULE_TITLE_BY_KEY[r.key] ?? r.key,
      desc: '',
    })
    : dnaRuleExtractions.value

  return ordered.map(rule => ({
    ...rule,
    question: ruleQuestionByKey.value[rule.key] ?? '',
    isDroppedInTier: droppedRuleKeys.value.includes(rule.key),
  }))
})

const DNA_EVIDENCES_DEFAULT = [
  {
    ruleKey: 'opening',
    excerpt: 'Laravel 11 mang đến những tính năng mới gì? Trong bài viết này, chúng ta sẽ cùng tìm hiểu những tính năng nổi bật nhất của Laravel 11.',
    note: 'Mở bài bằng câu hỏi trực tiếp kèm lời dẫn xác lập phạm vi bài viết cùng độc giả.',
  },
  {
    ruleKey: 'technical_terms',
    excerpt: 'Laravel 11 tập trung vào việc tối ưu hiệu suất, bao gồm thời gian khởi động nhanh hơn và cải thiện khả năng tự động tải class.',
    note: 'Giữ nguyên thuật ngữ chuyên ngành "class" tự nhiên trong ngữ cảnh câu tiếng Việt.',
  },
]

const dnaEvidences = ref(DNA_EVIDENCES_DEFAULT.map(item => ({ ...item })))

const STYLE_RULE_CHECKS = [
  {
    code: 'active_voice_dominance',
    name: 'Ưu tiên thể chủ động (>= 85%)',
    detail: '88% câu văn sử dụng chủ ngữ hành động trực tiếp, giúp nhịp đọc dứt khoát và dễ hiểu.',
    severity: 'passed',
  },
  {
    code: 'consistent_persona_pronouns',
    name: 'Xưng hô nhất quán tuyệt đối',
    detail: 'Sử dụng cặp đại từ "chúng ta – bạn" xuyên suốt từ phần giới thiệu đến phần kết luận.',
    severity: 'passed',
  },
  {
    code: 'concrete_proof_points',
    name: 'Dẫn chứng bằng con số cụ thể',
    detail: 'Các cụm "nhanh hơn", "mượt mà hơn" còn định tính; nên bổ sung số liệu benchmark (vd: giảm 25% thời gian boot).',
    severity: 'warning',
  },
  {
    code: 'no_corporate_cliche',
    name: 'Tránh cụm từ sáo rỗng chung chung',
    detail: 'Xuất hiện 2 cụm mang tính khuôn mẫu ("nhiều cải tiến đáng chú ý", "phổ biến"); có thể thay bằng chi tiết kỹ thuật.',
    severity: 'hint',
  },
]

/* ---------------------------------------------------------------------
 * TAB 3 (PHÂN TÍCH CHI TIẾT): CẤU TRÚC
 * ------------------------------------------------------------------- */
const STRUCTURE_METRICS = [
  { label: 'Hệ thống Heading', value: '1 H1 • 4 H2 • 2 H3', note: 'Phân cấp đúng chuẩn' },
  { label: 'Độ dài đoạn TB', value: '3.2 câu / đoạn', note: 'Tối ưu đọc trên mobile' },
  { label: 'Độ dài câu TB', value: '16.4 từ / câu', note: 'Nhịp đọc vừa phải' },
  { label: 'Định dạng đọc lướt', value: '82 / 100', note: 'Có in đậm & đánh số mục' },
]

const STRUCTURE_FLOW = [
  {
    part: 'Mở bài (Hook & Đặt vấn đề)',
    ratio: 15,
    words: '185 từ • 2 đoạn',
    status: 'Tốt',
    color: 'success',
    desc: 'Nêu bật bối cảnh ra mắt Laravel 11 và tóm tắt giá trị cốt lõi cho lập trình viên PHP.',
  },
  {
    part: 'Thân bài (Triển khai 4 tính năng trọng tâm)',
    ratio: 70,
    words: '875 từ • 5 đoạn',
    status: 'Đầy đủ',
    color: 'primary',
    desc: 'Chia thành các mục H2 đánh số rõ ràng: Hiệu suất, Cấu trúc thư mục tối giản, Laravel Reverb và Bảo mật.',
  },
  {
    part: 'Kết bài (Tổng kết & Lời khuyên nâng cấp)',
    ratio: 15,
    words: '185 từ • 1 đoạn',
    status: 'Cần thêm CTA',
    color: 'warning',
    desc: 'Đã tóm lược được ý chính nhưng thiếu câu hỏi tương tác hoặc liên kết dẫn sang hướng dẫn cài đặt chi tiết.',
  },
]

const HEADING_OUTLINE = [
  { tag: 'H1', title: 'Laravel 11 mang đến những tính năng mới gì?', words: '120 từ', status: 'Chuẩn' },
  { tag: 'H2', title: '1. Hiệu suất được cải thiện', words: '240 từ', status: 'Chuẩn' },
  { tag: 'H2', title: '2. Cấu trúc thư mục ứng dụng tối giản hơn', words: '310 từ', status: 'Chuẩn' },
  { tag: 'H3', title: '2.1. Tinh gọn ServiceProvider và Middleware mặc định', words: '165 từ', status: 'Chuẩn' },
  { tag: 'H3', title: '2.2. Tệp cấu hình tập trung trong bootstrap/app.php', words: '145 từ', status: 'Chuẩn' },
  { tag: 'H2', title: '3. Laravel Reverb – WebSocket Server tốc độ cao', words: '215 từ', status: 'Thiếu ví dụ code' },
  { tag: 'H2', title: '4. Cải tiến Rate Limiting và Bảo mật', words: '195 từ', status: 'Chuẩn' },
]

/* ---------------------------------------------------------------------
 * TAB 4 (PHÂN TÍCH CHI TIẾT): HÌNH ẢNH
 * ------------------------------------------------------------------- */
const IMAGE_STATS = [
  { label: 'Ảnh hiện có', value: '2 ảnh', sub: 'Trong nội dung bài', color: 'primary' },
  { label: 'Khuyến nghị', value: '5 – 6 ảnh', sub: '1 ảnh mỗi ~250 từ', color: 'info' },
  { label: 'Thẻ Alt hợp lệ', value: '1 / 2 ảnh', sub: '1 ảnh đang thiếu Alt', color: 'warning' },
  { label: 'Định dạng chuẩn', value: 'WebP', sub: 'Tối ưu tốc độ tải trang', color: 'success' },
]

const DETECTED_IMAGES = [
  {
    name: 'laravel-11-banner-overview.webp',
    section: 'Ngay dưới tiêu đề mở bài (H1)',
    alt: 'Tổng quan các tính năng mới nổi bật trên Laravel 11',
    hasAlt: true,
    note: 'Đã có Alt chứa từ khóa chính, kích thước chuẩn 1200x630px.',
  },
  {
    name: 'screenshot-folder-structure.png',
    section: 'Mục 2: Cấu trúc thư mục ứng dụng tối giản hơn',
    alt: '(Trống — chưa khai báo thuộc tính alt)',
    hasAlt: false,
    note: 'Nên đổi sang định dạng WebP và bổ sung Alt: "So sánh cấu trúc thư mục Laravel 10 và Laravel 11".',
  },
]

const SUGGESTED_IMAGE_SLOTS = [
  {
    position: 'Sau mục H2: 1. Hiệu suất được cải thiện',
    type: 'Biểu đồ Benchmark (Infographic)',
    suggestedAlt: 'Biểu đồ so sánh thời gian phản hồi request giữa Laravel 10 và Laravel 11',
    aiPrompt: 'Clean minimalist tech infographic chart comparing Laravel 10 vs Laravel 11 boot time and requests per second, modern flat UI style, red and slate palette, high resolution.',
  },
  {
    position: 'Sau mục H2: 3. Laravel Reverb – WebSocket Server',
    type: 'Sơ đồ kiến trúc (Architecture Diagram)',
    suggestedAlt: 'Sơ đồ luồng kết nối thời gian thực giữa Client và Laravel Reverb WebSocket Server',
    aiPrompt: 'Modern software architecture diagram showing browser clients connecting to Laravel Reverb WebSocket server and broadcasting events in real time, clean isometric vector style.',
  },
]

/* ---------------------------------------------------------------------
 * TAB 6 (PHÂN TÍCH CHI TIẾT): TÍNH ĐỘC ĐÁO
 * ------------------------------------------------------------------- */
const UNIQUENESS_METRICS = [
  { label: 'Độ độc đáo ngôn từ', value: 84, desc: 'Diễn đạt tự nhiên, ít trùng lặp nguyên văn với nguồn khác' },
  { label: 'Giá trị thông tin mới (Information Gain)', value: 66, desc: 'Cần thêm trải nghiệm thực tế và số liệu thử nghiệm riêng' },
  { label: 'Độ tự nhiên (Human-like Score)', value: 88, desc: 'Nhịp văn mạch lạc, ít dấu hiệu văn mẫu máy móc' },
]

const REWRITE_COMPARISONS = [
  {
    location: 'Đoạn mở bài — Giới thiệu chung',
    issue: 'Diễn đạt mang tính khuôn mẫu, xuất hiện ở nhiều bài dịch Release Notes',
    before: 'Laravel 11 là phiên bản mới nhất của framework PHP phổ biến, mang đến nhiều cải tiến đáng chú ý giúp lập trình viên xây dựng ứng dụng nhanh hơn, hiệu quả hơn và bảo mật hơn.',
    after: 'Sau khi trực tiếp nâng cấp 3 dự án production lên Laravel 11, thay đổi dễ nhận thấy nhất không chỉ nằm ở tốc độ khởi tạo ứng dụng mà còn ở cách bộ khung thư mục mới cắt giảm tới 60% file cấu hình thừa.',
  },
  {
    location: 'Mục 1 — Hiệu suất được cải thiện',
    issue: 'Nhận định chung chung, thiếu con số đo lường cụ thể',
    before: 'Những thay đổi này giúp ứng dụng của bạn chạy mượt mà hơn, đặc biệt là ở quy mô lớn.',
    after: 'Trong bài test thực tế với 500 route và 40 middleware, thời gian bootstrap request giảm từ 18ms xuống còn 11ms — tạo khác biệt rõ rệt khi hệ thống chịu tải hàng nghìn kết nối đồng thời.',
  },
]

/* ---------------------------------------------------------------------
 * TAB 7 (PHÂN TÍCH CHI TIẾT): PHÒNG THỬ NGHIỆM DNA (SANDBOX - PLAN.md §310-311)
 * ------------------------------------------------------------------- */
const sandboxForm = reactive({
  topic: 'Cách tối ưu truy vấn Eloquent ORM để tránh lỗi N+1 Query trong dự án lớn',
  targetTier: 'short',
})

const SANDBOX_SAMPLES_BY_TIER = {
  micro: 'Bạn đã bao giờ thấy trang danh sách đơn hàng chậm đi 5 lần chỉ vì thiếu một hàm with() trong Eloquent chưa? Với các dự án Laravel quy mô lớn, kích hoạt preventLazyLoading() ngay trên môi trường local là chốt chặn đơn giản nhất để loại bỏ triệt để bài toán N+1 Query.',
  short: 'Khi ứng dụng Laravel của bạn tăng trưởng từ vài trăm lên hàng chục nghìn bản ghi, N+1 Query thường là thủ phạm thầm lặng làm nghẽn kết nối cơ sở dữ liệu. Thay vì đợi đến khi hệ thống chậm mới rà soát từng vòng lặp foreach, chúng ta có thể chủ động kiểm soát ngay từ giai đoạn phát triển. Hãy bật Model::preventLazyLoading() trong AppServiceProvider và kết hợp Eager Loading có chọn lọc cột để giữ thời gian phản hồi luôn dưới 50ms.',
  medium: 'Tại sao một truy vấn Eloquent chạy rất mượt ở môi trường phát triển nhưng lại làm treo máy chủ khi đưa lên production? Nguyên nhân phổ biến nhất mà chúng ta thường gặp chính là lỗi N+1 Query khi truy cập quan hệ lồng nhau.\n\n1. Nhận diện sớm bằng công cụ có sẵn của Laravel\nThay vì cài đặt thêm nhiều gói phụ thuộc nặng nề, bạn chỉ cần kích hoạt chế độ nghiêm ngặt Model::shouldBeStrict() trong hàm boot. Ngay khi có một thuộc tính quan hệ bị gọi thụ động trong vòng lặp, Laravel sẽ lập tức cảnh báo chính xác dòng code cần tối ưu.\n\n2. Kết hợp Eager Loading và giới hạn cột dữ liệu\nKhi nạp dữ liệu kèm quan hệ bằng with(), hãy chỉ định rõ các cột cần thiết như with(\'author:id,name\') để giảm tới 40% bộ nhớ RAM tiêu thụ trên mỗi request.',
  long: 'Làm thế nào để giữ cho tầng truy vấn Eloquent ORM luôn phản hồi tức thì ngay cả khi bảng dữ liệu vượt mốc hàng triệu dòng? Trong bài viết này, chúng ta sẽ cùng đi qua 3 kỹ thuật thực chiến giúp kiểm soát triệt để hiệu suất cơ sở dữ liệu.\n\n1. Chặn đứng N+1 Query ngay từ môi trường phát triển\nVới cơ chế mặc định, Eloquent cho phép nạp lười (Lazy Loading) các mối quan hệ. Điều này tiện lợi khi viết code nhanh nhưng lại sinh ra hàng trăm câu lệnh SQL lặp lại. Khi kích hoạt Model::preventLazyLoading(! app()->isProduction()), bạn sẽ phát hiện ngay mọi truy vấn thừa trước khi code được đẩy lên máy chủ.\n\n2. Tối ưu bộ nhớ bằng Eager Loading chọn lọc và Chunking\nKhông phải lúc nào nạp sẵn toàn bộ quan hệ cũng là tối ưu. Hãy chỉ lấy đúng các trường khóa ngoại và trường hiển thị cần thiết, đồng thời kết hợp cursor() hoặc chunkById() cho các tác vụ xử lý hàng loạt để giữ mức sử dụng RAM ổn định.',
}

const sandboxGeneratedText = ref('')
const sandboxSnapshotPrompt = ref('')

const activeSandboxSample = computed(() =>
  sandboxGeneratedText.value
    || SANDBOX_SAMPLES_BY_TIER[sandboxForm.targetTier]
    || SANDBOX_SAMPLES_BY_TIER.short,
)

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

const STYLE_INSTRUCTIONS_BASE_DEFAULT = `=== HỆ THỐNG QUY TẮC DNA VĂN PHONG (SYSTEM PROMPT DNA SNAPSHOT) ===
[HỒ SƠ ĐỊNH DANH: CHUYÊN GIA KỸ THUẬT – PHP / LARAVEL]
[PHÂN TẦNG ĐỘ DÀI: TIER LONG (1.000 – 2.500 TỪ)]

1. ĐỊNH DANH VAI TRÒ & KHẨU KHÍ (PERSONA & TONE):
- Vai trò tác giả: Kỹ sư trưởng / Chuyên gia kiến trúc hệ thống (Thought Leader) với tư duy thực chiến sâu sắc.
- Phong thái: Điềm tĩnh, tự tin, tôn trọng thời gian và trí tuệ của độc giả; giải thích cặn kẽ bản chất thay vì liệt kê tính năng bề nổi.
- Cự ly xưng hô: Nhất quán sử dụng ngôi xưng "chúng ta" (khi cùng thảo luận giải pháp) và "bạn" (khi hướng dẫn thao tác cụ thể). Tuyệt đối không dùng "tôi" kiêu ngạo hoặc "chúng tôi" mang tính công ty quảng cáo.

2. QUY CHUẨN KẾT CẤU & BỐ CỤC (STRUCTURAL RULES):
- Mở bài (Hook & Context): Đi thẳng vào bài toán thực tế và giá trị bài viết mang lại ngay trong 2 đoạn đầu (dưới 200 từ). Không chào hỏi rườm rà.
- Thân bài (Feature Breakdown): Chia tách rõ ràng bằng tiêu đề H2 dạng hành động/lợi ích. Mỗi mục H2 triển khai theo chuỗi logic 3 bước:
  [Bản chất kỹ thuật ngầm] -> [Ví dụ minh họa code thực tế] -> [Cảnh báo lỗi thường gặp & Tối ưu hiệu năng (Gotchas)].
- Kết bài (Actionable Wrap-up): Tóm lược 3 điểm then chốt, kèm lời khuyên lộ trình nâng cấp và câu hỏi mở kích thích tư duy phản biện.

3. NHỊP ĐIỆU CÂU & HÌNH THÁI VĂN BẢN (SYNTAX & PACING):
- Tỷ lệ câu chủ động: Bắt buộc >= 85%. Đưa chủ thể và hành động lên đầu câu để câu văn dứt khoát.
- Nhịp điệu câu: Đan xen nhịp nhàng giữa câu định nghĩa ngắn (10–15 từ) và câu phân tích kiến trúc (20–28 từ).
- Độ dài đoạn văn: Mỗi đoạn chỉ chứa từ 2 đến 4 câu; xuống dòng thường xuyên để tối ưu khả năng đọc lướt trên màn hình thiết bị di động.

4. BỘ TỪ ĐIỂN & RÀO CHẮN NGÔN TỪ (LEXICON & CONSTRAINTS):
- Thuật ngữ chuyên ngành: Giữ nguyên từ gốc tiếng Anh chuẩn (Framework, Dependency Injection, Middleware, WebSocket Server, Artisan Console, Service Provider); tuyệt đối không dịch gượng gạo sang tiếng Việt.
- Rào chắn cấm kỵ (Negative Constraints):
  * CẤM dùng từ ngữ thổi phồng sáo rỗng: "thần thánh", "siêu phẩm", "đột phá vũ bão", "toàn diện nhất lịch sử".
  * CẤM mở đầu đoạn bằng các sáo ngữ dịch máy: "Như chúng ta đã biết", "Trong thế giới ngày nay", "Có thể nói rằng".

5. NGUYÊN TẮC BẢO MẬT & CHỐNG ĐẠO VĂN (ANTI-PLAGIARISM PROTOCOL):
- Tuyệt đối KHÔNG sao chép nguyên văn bất kỳ câu chữ nào từ các bài viết mẫu. Chỉ kế thừa quy luật tư duy, cấu trúc luận điểm và nhịp điệu hành văn của hồ sơ DNA này để sáng tạo nội dung mới 100%.`

const styleInstructionsBase = ref(STYLE_INSTRUCTIONS_BASE_DEFAULT)

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

const STYLE_ANALYSIS = [
  {
    name: 'Tính chuyên môn',
    percent: 70,
    color: 'primary',
    icon: 'tabler-school',
    desc: 'Cân bằng giữa chiều sâu kỹ thuật và tính dễ tiếp cận',
  },
  {
    name: 'Tính tự nhiên',
    percent: 85,
    color: 'success',
    icon: 'tabler-leaf',
    desc: 'Văn phong mạch lạc, nhịp câu nhịp nhàng, ít gượng gạo',
  },
  {
    name: 'Tính thuyết phục',
    percent: 75,
    color: 'info',
    icon: 'tabler-bulb',
    desc: 'Có số liệu, dẫn chứng thực tế và lập luận chặt chẽ',
  },
  {
    name: 'Độ phức tạp',
    percent: 40,
    color: 'warning',
    icon: 'tabler-puzzle',
    desc: 'Dễ đọc, phù hợp từ Junior đến Senior Developer',
  },
  {
    name: 'Cảm xúc & Khẩu khí',
    percent: 60,
    color: 'secondary',
    icon: 'tabler-heart',
    desc: 'Khách quan, điềm tĩnh, tập trung vào giá trị thực tế',
  },
]

const PROMPT_TEXT = 'Hãy viết lại bài viết này về các tính năng mới của Laravel 11 bằng văn phong tự nhiên, hấp dẫn, có cấu trúc rõ ràng với các tiêu đề H2, H3. Bổ sung thêm ví dụ thực tế, số liệu (nếu có), hình ảnh minh họa và liên kết tham khảo chính thức. Đối tượng là lập trình viên PHP/Laravel, nội dung khoảng 1200 - 1500 từ, viết bằng tiếng Việt.'

/* Form đóng gói kết quả phân tích thành bản ghi WritingProfile mới. */
const profileForm = reactive({
  name: 'Giọng Chuyên gia Kỹ thuật – PHP / Laravel',
  slug: 'giong-chuyen-gia-ky-thuat-php-laravel',
  dominantTier: 'long',
  status: 'active',
  isEnabled: true,
  description: 'Văn phong hướng dẫn kỹ thuật mạch lạc, cân bằng giữa chiều sâu kiến trúc hệ thống và khả năng đọc lướt dễ hiểu.',
  taxonomyTags: ['Công nghệ', 'Lập trình PHP', 'Laravel', 'Technical Blog'],
})

/**
 * =====================================================================
 * CHỨC NĂNG: Chuẩn hóa chuỗi tiếng Việt thành slug URL an toàn.
 * =====================================================================
 * INPUT : str (string).
 * OUTPUT: string slug dạng kebab-case.
 * =====================================================================
 */
function slugify(text) {
  return String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/đ/g, 'd')
    .replace(/[^a-z0-9 -]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
}

watch(
  () => profileForm.name,
  val => {
    profileForm.slug = slugify(val)
  },
)

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
 * CHỨC NĂNG: Khôi phục nội dung mẫu trong trình soạn thảo.
 * =====================================================================
 * INPUT : không — gán lại articleContent = SAMPLE_ARTICLE_HTML.
 * OUTPUT: void; UI editor render lại nội dung mẫu.
 * =====================================================================
 */
function resetContent() {
  articleContent.value = SAMPLE_ARTICLE_HTML
  snack.text = 'Đã khôi phục nội dung bài viết mẫu'
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
function formatRuleDesc(key, rawValue) {
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

  return text || 'Không áp dụng ở cấp độ độ dài này (đã chuyển vào uncertainties).'
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
    : DNA_RULE_EXTRACTIONS_DEFAULT.map(r => r.key)

  dnaRuleExtractions.value = keysOrder.map((key, idx) => ({
    key,
    title: RULE_TITLE_BY_KEY[key] ?? `${idx + 1}. ${key}`,
    desc: formatRuleDesc(key, rulesObj[key]),
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

    const analysisId = dispatched?.analysis_id
    if (!analysisId) {
      snack.text = 'Không nhận được mã phiên phân tích từ máy chủ'
      snack.color = 'error'
      snack.show = true

      return
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
    if (!finalAnalysis) return

    if (finalAnalysis.status === 'failed') {
      snack.text = finalAnalysis.error_message || 'Giải mã văn phong thất bại'
      snack.color = 'error'
      snack.show = true

      return
    }

    applyAnalysisResult(finalAnalysis)
    detailTab.value = 'style'

    const tierLabel = (finalAnalysis.detected_tier || detectedTier.value).toUpperCase()

    snack.text = `Đã giải mã DNA văn phong thành công (${wordCount.value} từ • cấp độ ${tierLabel})`
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
 * CHỨC NĂNG: Mô phỏng trích xuất nội dung bài viết từ đường dẫn URL.
 * =====================================================================
 * INPUT : đọc từ ref sourceUrl.
 * OUTPUT: void.
 * SIDE EFFECT: cập nhật thông tin preview URL, nạp nội dung vào editor và
 *              hiển thị snackbar thông báo.
 * =====================================================================
 */
function fetchFromUrl() {
  const raw = sourceUrl.value.trim()
  if (!raw) {
    snack.text = 'Vui lòng nhập đường dẫn URL hợp lệ trước khi trích xuất'
    snack.color = 'warning'
    snack.show = true

    return
  }

  try {
    const parsed = new URL(raw)

    URL_PREVIEW_META.domain = parsed.hostname
  } catch {
    URL_PREVIEW_META.domain = raw
  }

  URL_PREVIEW_META.fetchedAt = 'Vừa trích xuất xong'
  articleContent.value = SAMPLE_ARTICLE_HTML
  snack.text = 'Đã trích xuất nội dung bài viết từ URL thành công'
  snack.color = 'success'
  snack.show = true
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
 * CHỨC NĂNG: Xóa file đang chọn và khôi phục dữ liệu mẫu ban đầu
 * =====================================================================
 *
 * INPUT:
 * - Không có
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Đặt lại selectedFile về file mẫu, xóa giá trị file input và bật snackbar
 *
 * EXCEPTION:
 * - Không có
 * =====================================================================
 */
function removeSelectedFile() {
  selectedFile.name = 'laravel-11-release-analysis.md'
  selectedFile.sizeLabel = '18.4 KB'
  selectedFile.typeLabel = 'Markdown (.md)'
  selectedFile.words = '1,245 từ'
  selectedFile.status = 'Đã khôi phục file mẫu'
  if (fileInputRef.value) fileInputRef.value.value = ''
  snack.text = 'Đã đặt lại tệp nguồn về mặc định'
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

  const payload = {
    name,
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
  const targetText = promptMode.value === 'rewrite' ? PROMPT_TEXT : displayedDnaPrompt.value

  const label = promptMode.value === 'rewrite'
    ? 'Đã sao chép prompt gợi ý viết lại'
    : 'Đã sao chép System Prompt DNA văn phong'

  await copyText(targetText, label)
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
          :disabled="store.saving || isAnalyzing"
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
                  class="mb-3"
                />
              </VCol>

              <VCol cols="12">
                <AppTextField
                  v-model="profileForm.slug"
                  label="Slug định danh *"
                  placeholder="giong-chuyen-gia-ky-thuat-php-laravel"
                  prefix="localhost:8000/"
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
                    <AppSelect
                      v-model="urlExtractMode"
                      :items="URL_EXTRACT_MODES"
                      label="Chế độ thu thập"
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
                        @click="resetContent"
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

                <!-- Thông tin tệp đang nạp -->
                <div class="tonal-panel pa-3 rounded d-flex align-center justify-space-between flex-wrap gap-3">
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
                    v-for="metric in STYLE_DNA_METRICS"
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

                <!-- Kiểm tra 16 Quy tắc viết văn cốt lõi -->
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

                  <div class="d-flex flex-column gap-2">
                    <div
                      v-for="item in STYLE_RULE_CHECKS"
                      :key="item.code"
                      class="tonal-panel pa-3 rounded d-flex align-start justify-space-between gap-3"
                    >
                      <div class="d-flex align-start">
                        <VIcon
                          :icon="severityMeta(item.severity).icon"
                          :color="severityMeta(item.severity).color"
                          size="18"
                          class="me-2 mt-1 flex-shrink-0"
                        />
                        <div>
                          <div class="text-body-2 font-weight-medium text-high-emphasis">
                            {{ item.name }}
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ item.detail }}
                          </div>
                        </div>
                      </div>
                      <VChip
                        size="x-small"
                        :color="severityMeta(item.severity).color"
                        variant="tonal"
                        label
                        class="flex-shrink-0"
                      >
                        {{ severityMeta(item.severity).label }}
                      </VChip>
                    </div>
                  </div>
                </div>
              </VWindowItem>

              <!-- ================= TAB 3: CẤU TRÚC ================= -->
              <VWindowItem value="structure">
                <!-- 4 ô chỉ số cấu trúc -->
                <VRow
                  dense
                  class="mb-6"
                >
                  <VCol
                    v-for="stat in STRUCTURE_METRICS"
                    :key="stat.label"
                    cols="6"
                    sm="3"
                  >
                    <div class="tonal-panel pa-3 rounded text-center h-100">
                      <div class="text-caption text-medium-emphasis mb-1">
                        {{ stat.label }}
                      </div>
                      <div class="text-body-1 font-weight-medium text-high-emphasis">
                        {{ stat.value }}
                      </div>
                      <div class="text-caption text-primary">
                        {{ stat.note }}
                      </div>
                    </div>
                  </VCol>
                </VRow>

                <!-- Tỷ lệ bố cục 3 phần -->
                <div class="mb-6">
                  <div class="d-flex align-center text-body-2 font-weight-medium mb-3">
                    <VIcon
                      icon="tabler-chart-bar"
                      size="16"
                      color="primary"
                      class="me-1"
                    />
                    Phân bổ Bố cục Bài viết (Mở bài – Thân bài – Kết bài)
                  </div>

                  <div class="d-flex flex-column gap-3">
                    <div
                      v-for="sec in STRUCTURE_FLOW"
                      :key="sec.part"
                      class="tonal-panel pa-3 rounded"
                    >
                      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-1">
                        <span class="text-body-2 font-weight-medium text-high-emphasis">
                          {{ sec.part }}
                        </span>
                        <div class="d-flex align-center gap-2">
                          <span class="text-caption text-medium-emphasis">{{ sec.words }}</span>
                          <VChip
                            size="x-small"
                            :color="sec.color"
                            variant="tonal"
                            label
                          >
                            {{ sec.ratio }}% • {{ sec.status }}
                          </VChip>
                        </div>
                      </div>

                      <VProgressLinear
                        :model-value="sec.ratio"
                        :color="sec.color"
                        height="5"
                        rounded
                        class="mb-2"
                      />

                      <div class="text-caption text-medium-emphasis">
                        {{ sec.desc }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Cây dàn ý Heading (H1 - H3) -->
                <div>
                  <div class="d-flex align-center text-body-2 font-weight-medium mb-2">
                    <VIcon
                      icon="tabler-hierarchy-2"
                      size="16"
                      color="primary"
                      class="me-1"
                    />
                    Cây Dàn ý Tiêu đề (Heading Outline)
                  </div>

                  <div class="d-flex flex-column gap-2">
                    <div
                      v-for="(h, idx) in HEADING_OUTLINE"
                      :key="idx"
                      class="tonal-panel px-3 py-2 rounded d-flex align-center justify-space-between flex-wrap gap-2"
                      :class="{
                        'ms-4': h.tag === 'H2',
                        'ms-8': h.tag === 'H3',
                      }"
                    >
                      <div class="d-flex align-center">
                        <VChip
                          size="x-small"
                          :color="h.tag === 'H1' ? 'primary' : h.tag === 'H2' ? 'info' : 'secondary'"
                          variant="tonal"
                          label
                          class="me-2 flex-shrink-0"
                        >
                          {{ h.tag }}
                        </VChip>
                        <span class="text-body-2 text-high-emphasis">
                          {{ h.title }}
                        </span>
                      </div>

                      <div class="d-flex align-center gap-2">
                        <span class="text-caption text-medium-emphasis">{{ h.words }}</span>
                        <VChip
                          size="x-small"
                          :color="h.status === 'Chuẩn' ? 'success' : 'warning'"
                          variant="tonal"
                          label
                        >
                          {{ h.status }}
                        </VChip>
                      </div>
                    </div>
                  </div>
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
                      @click="copyText(activeSandboxSample, 'Đã sao chép văn bản viết thử')"
                    >
                      Sao chép
                    </VBtn>
                  </div>

                  <div class="text-body-2 text-high-emphasis sandbox-output">
                    {{ activeSandboxSample }}
                  </div>
                </div>
              </VWindowItem>

              <!-- ================= TAB 4: NỘI DUNG (THỬ NGHIỆM) ================= -->
              <VWindowItem value="content">
                <!-- Tóm tắt nội dung chính -->
                <div class="mb-6">
                  <div class="d-flex align-center text-body-2 font-weight-medium mb-1">
                    <VIcon
                      icon="tabler-list-details"
                      size="16"
                      color="primary"
                      class="me-1"
                    />
                    Tóm tắt nội dung chính
                  </div>
                  <p class="text-body-2 text-medium-emphasis mb-0">
                    {{ ANALYSIS_SUMMARY }}
                  </p>
                </div>

                <!-- Các chủ đề chính -->
                <div class="mb-6">
                  <div class="d-flex align-center text-body-2 font-weight-medium mb-2">
                    <VIcon
                      icon="tabler-tag"
                      size="16"
                      color="primary"
                      class="me-1"
                    />
                    Các chủ đề chính
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <VChip
                      v-for="topic in TOPICS"
                      :key="topic"
                      size="small"
                      variant="tonal"
                      color="primary"
                      label
                    >
                      {{ topic }}
                    </VChip>
                  </div>
                </div>

                <!-- 2 Cột: Điểm mạnh & Điểm cần cải thiện -->
                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <div class="d-flex align-center text-body-2 font-weight-medium mb-2">
                      <VIcon
                        icon="tabler-circle-check"
                        size="16"
                        color="success"
                        class="me-1"
                      />
                      Điểm mạnh
                    </div>
                    <div class="d-flex flex-column gap-2">
                      <div
                        v-for="pro in STRENGTHS"
                        :key="pro"
                        class="d-flex align-start text-body-2 text-medium-emphasis"
                      >
                        <VIcon
                          icon="tabler-circle-check"
                          color="success"
                          size="16"
                          class="me-1 mt-1 flex-shrink-0"
                        />
                        <span>{{ pro }}</span>
                      </div>
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <div class="d-flex align-center text-body-2 font-weight-medium mb-2">
                      <VIcon
                        icon="tabler-alert-circle"
                        size="16"
                        color="warning"
                        class="me-1"
                      />
                      Điểm cần cải thiện
                    </div>
                    <div class="d-flex flex-column gap-2">
                      <div
                        v-for="con in IMPROVEMENTS"
                        :key="con"
                        class="d-flex align-start text-body-2 text-medium-emphasis"
                      >
                        <VIcon
                          icon="tabler-alert-circle"
                          color="warning"
                          size="16"
                          class="me-1 mt-1 flex-shrink-0"
                        />
                        <span>{{ con }}</span>
                      </div>
                    </div>
                  </VCol>
                </VRow>
              </VWindowItem>

              <!-- ================= TAB 5: HÌNH ẢNH (THỬ NGHIỆM) ================= -->
              <VWindowItem value="images">
                <!-- 4 Thẻ thống kê hình ảnh -->
                <VRow
                  dense
                  class="mb-6"
                >
                  <VCol
                    v-for="stat in IMAGE_STATS"
                    :key="stat.label"
                    cols="6"
                    sm="3"
                  >
                    <div class="tonal-panel pa-3 rounded text-center h-100">
                      <div class="text-caption text-medium-emphasis mb-1">
                        {{ stat.label }}
                      </div>
                      <div
                        class="text-body-1 font-weight-medium"
                        :class="`text-${stat.color}`"
                      >
                        {{ stat.value }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ stat.sub }}
                      </div>
                    </div>
                  </VCol>
                </VRow>

                <!-- Kiểm tra ảnh hiện có trong bài -->
                <div class="mb-6">
                  <div class="d-flex align-center text-body-2 font-weight-medium mb-2">
                    <VIcon
                      icon="tabler-photo-check"
                      size="16"
                      color="primary"
                      class="me-1"
                    />
                    Đánh giá Hình ảnh Hiện có trong Bài viết
                  </div>

                  <div class="d-flex flex-column gap-3">
                    <div
                      v-for="img in DETECTED_IMAGES"
                      :key="img.name"
                      class="tonal-panel pa-3 rounded"
                    >
                      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-1">
                        <div class="d-flex align-center">
                          <VIcon
                            icon="tabler-file-info"
                            size="16"
                            color="primary"
                            class="me-2"
                          />
                          <span class="text-body-2 font-weight-medium text-high-emphasis">
                            {{ img.name }}
                          </span>
                        </div>
                        <VChip
                          size="x-small"
                          :color="img.hasAlt ? 'success' : 'warning'"
                          variant="tonal"
                          label
                        >
                          {{ img.hasAlt ? 'Đạt chuẩn Alt SEO' : 'Thiếu thẻ Alt' }}
                        </VChip>
                      </div>
                      <div class="text-caption text-medium-emphasis mb-1">
                        Vị trí: {{ img.section }} • Alt: <span class="text-high-emphasis">{{ img.alt }}</span>
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ img.note }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Gợi ý vị trí bổ sung ảnh & AI Image Prompt -->
                <div>
                  <div class="d-flex align-center text-body-2 font-weight-medium mb-2">
                    <VIcon
                      icon="tabler-wand"
                      size="16"
                      color="primary"
                      class="me-1"
                    />
                    Gợi ý Bổ sung Minh họa &amp; Prompt Tạo ảnh AI
                  </div>

                  <div class="d-flex flex-column gap-3">
                    <div
                      v-for="slot in SUGGESTED_IMAGE_SLOTS"
                      :key="slot.position"
                      class="prompt-box pa-3 rounded"
                    >
                      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-1">
                        <span class="text-body-2 font-weight-medium text-high-emphasis">
                          {{ slot.position }}
                        </span>
                        <div class="d-flex align-center gap-2">
                          <VChip
                            size="x-small"
                            color="primary"
                            variant="tonal"
                            label
                          >
                            {{ slot.type }}
                          </VChip>
                          <VBtn
                            variant="text"
                            color="primary"
                            size="x-small"
                            prepend-icon="tabler-copy"
                            @click="copyText(slot.aiPrompt, 'Đã sao chép Prompt tạo ảnh minh họa')"
                          >
                            Copy Prompt
                          </VBtn>
                        </div>
                      </div>
                      <div class="text-caption text-medium-emphasis mb-1">
                        Gợi ý thẻ Alt: <span class="text-high-emphasis font-weight-medium">“{{ slot.suggestedAlt }}”</span>
                      </div>
                      <div class="text-caption text-medium-emphasis font-italic">
                        Prompt AI: “{{ slot.aiPrompt }}”
                      </div>
                    </div>
                  </div>
                </div>
              </VWindowItem>

              <!-- ================= TAB 6: TÍNH ĐỘC ĐÁO (THỬ NGHIỆM) ================= -->
              <VWindowItem value="uniqueness">
                <!-- 3 Thước đo Tính độc đáo & Giá trị thông tin -->
                <div class="d-flex flex-column gap-3 mb-6">
                  <div
                    v-for="metric in UNIQUENESS_METRICS"
                    :key="metric.label"
                    class="tonal-panel pa-3 rounded"
                  >
                    <div class="d-flex justify-space-between align-center mb-1">
                      <span class="text-body-2 font-weight-medium text-high-emphasis">
                        {{ metric.label }}
                      </span>
                      <span
                        class="text-body-2 font-weight-medium"
                        :class="`text-${scoreColor(metric.value)}`"
                      >
                        {{ metric.value }}%
                      </span>
                    </div>
                    <VProgressLinear
                      :model-value="metric.value"
                      :color="scoreColor(metric.value)"
                      height="6"
                      rounded
                      class="mb-1"
                    />
                    <div class="text-caption text-medium-emphasis">
                      {{ metric.desc }}
                    </div>
                  </div>
                </div>

                <!-- Phát hiện đoạn văn khuôn mẫu & Gợi ý viết lại tạo khác biệt -->
                <div>
                  <div class="d-flex align-center text-body-2 font-weight-medium mb-2">
                    <VIcon
                      icon="tabler-sparkles"
                      size="16"
                      color="primary"
                      class="me-1"
                    />
                    So sánh Đoạn văn Khuôn mẫu &amp; Gợi ý Viết lại Độc đáo hơn
                  </div>

                  <div class="d-flex flex-column gap-4">
                    <div
                      v-for="item in REWRITE_COMPARISONS"
                      :key="item.location"
                      class="tonal-panel pa-4 rounded"
                    >
                      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                        <div>
                          <div class="text-body-2 font-weight-medium text-high-emphasis">
                            {{ item.location }}
                          </div>
                          <div class="text-caption text-warning">
                            Phát hiện: {{ item.issue }}
                          </div>
                        </div>
                        <VBtn
                          variant="tonal"
                          color="primary"
                          size="x-small"
                          prepend-icon="tabler-copy"
                          @click="copyText(item.after, 'Đã sao chép đoạn văn gợi ý viết lại')"
                        >
                          Sao chép bản cải thiện
                        </VBtn>
                      </div>

                      <VRow dense>
                        <VCol
                          cols="12"
                          md="6"
                        >
                          <div class="rewrite-box rewrite-box--before pa-3 rounded h-100">
                            <div class="text-caption font-weight-medium text-warning mb-1">
                              Bản gốc trong bài (Khá phổ biến)
                            </div>
                            <div class="text-body-2 text-medium-emphasis">
                              “{{ item.before }}”
                            </div>
                          </div>
                        </VCol>
                        <VCol
                          cols="12"
                          md="6"
                        >
                          <div class="rewrite-box rewrite-box--after pa-3 rounded h-100">
                            <div class="text-caption font-weight-medium text-success mb-1">
                              Gợi ý tạo bản sắc &amp; chiều sâu thực chiến
                            </div>
                            <div class="text-body-2 text-high-emphasis">
                              “{{ item.after }}”
                            </div>
                          </div>
                        </VCol>
                      </VRow>
                    </div>
                  </div>
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

            <!-- Danh sách 5 trục phong cách thiết kế dạng Cardlet -->
            <div class="d-flex flex-column gap-2">
              <div
                v-for="item in STYLE_ANALYSIS"
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
            <!-- Chuyển đổi giữa Prompt viết lại bài & System Prompt DNA -->
            <div class="d-flex gap-2 mb-3">
              <VBtn
                size="x-small"
                :variant="promptMode === 'rewrite' ? 'flat' : 'tonal'"
                :color="promptMode === 'rewrite' ? 'primary' : 'secondary'"
                @click="promptMode = 'rewrite'"
              >
                Prompt viết lại bài
              </VBtn>
              <VBtn
                size="x-small"
                :variant="promptMode === 'dna' ? 'flat' : 'tonal'"
                :color="promptMode === 'dna' ? 'primary' : 'secondary'"
                @click="promptMode = 'dna'"
              >
                System Prompt DNA (Snapshot)
              </VBtn>
            </div>

            <div
              v-if="promptMode === 'rewrite'"
              class="prompt-box pa-3 rounded text-body-2 text-medium-emphasis font-italic"
            >
              “{{ PROMPT_TEXT }}”
            </div>
            <div
              v-else
              class="prompt-box pa-3 rounded text-body-2 text-high-emphasis sandbox-output"
              style="max-height: 480px; overflow-y: auto;"
            >
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
                <div class="text-caption text-medium-emphasis mb-1">
                  Gợi ý phân loại tự động (taxonomy_suggestion):
                </div>
                <div class="d-flex flex-wrap gap-1 mb-3">
                  <VChip
                    v-for="tag in profileForm.taxonomyTags"
                    :key="tag"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    label
                  >
                    {{ tag }}
                  </VChip>
                </div>
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
                :disabled="store.saving || isAnalyzing"
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
                :disabled="store.saving || isAnalyzing"
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
