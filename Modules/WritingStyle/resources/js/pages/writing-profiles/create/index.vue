<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang AI Phân tích bài viết (DEMO TĨNH, chưa nối API).
  =====================================================================

  Màn phân tích một bài viết mẫu: nhập nguồn (URL / dán nội dung / file),
  rồi hiển thị kết quả phân tích gồm tóm tắt, chủ đề, điểm mạnh — điểm cần
  cải thiện, bộ chỉ số tổng quan, điểm đánh giá, phân tích văn phong và
  prompt gợi ý viết lại.

  TRẠNG THÁI: toàn bộ số liệu là DỮ LIỆU TĨNH khai báo trong <script setup>
  (các hằng số viết HOA). Chưa gọi $api / Pinia store — khi nối API sau,
  thay các hằng số này bằng dữ liệu từ services/writingStyleApi.js qua
  store, giữ nguyên cấu trúc template.

  CHUẨN UI (docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §2–3):
  - Component PascalCase (VCard/VBtn/VTabs), field dùng AppSelect wrapper.
  - Icon chỉ tabler-*, truyền qua prop icon="..." (không text node).
  - Màu theme Vuetify / rgba(var(--v-theme-*)) — không hex cứng, không
  text-grey-darken-*, bg-white; Dark Mode tự đúng.
  - Typography theo thang §2: text-h4 header, text-subtitle-1 tiêu đề card,
  text-body-2 nội dung, text-caption meta — không inline font-size.
  - Spacing logical me-*/ms-*/pe-*/ps-* (ESLint vue/no-restricted-class).

  CÁC HÀM/COMPUTED TRONG FILE:
  - copyPrompt()  : sao chép prompt gợi ý vào clipboard + hiện snackbar.
  - scoreColor(n) : màu theo ngưỡng điểm (>=80 success, >=60 warning, còn
  lại error) — dùng cho gauge và các thước đo con.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : không có props / route params; dữ liệu tĩnh trong file.
  - OUTPUT: render giao diện phân tích; side effect duy nhất là ghi
  clipboard khi bấm "Sao chép".
  =====================================================================
-->
<script setup>
import { reactive, ref } from 'vue'

definePage({ meta: { layout: 'default' } })

/* Tab đang chọn của 2 khối tabs (nguồn bài viết & phân tích chi tiết). */
const sourceTab = ref('paste')
const detailTab = ref('content')
const editorFormat = ref('Đoạn văn')

const snack = reactive({ show: false, text: '', color: 'success' })

/* Khối định dạng của toolbar soạn thảo (demo — chưa gắn lệnh editor). */
const EDITOR_FORMATS = ['Đoạn văn', 'Tiêu đề 1', 'Tiêu đề 2', 'Tiêu đề 3']

const SOURCE_TABS = [
  { value: 'url', label: 'Từ URL' },
  { value: 'paste', label: 'Dán nội dung' },
  { value: 'file', label: 'Từ file' },
]

const DETAIL_TABS = [
  { value: 'content', label: 'Nội dung' },
  { value: 'style', label: 'Văn phong' },
  { value: 'structure', label: 'Cấu trúc' },
  { value: 'seo', label: 'SEO & Từ khóa' },
  { value: 'images', label: 'Hình ảnh' },
  { value: 'uniqueness', label: 'Tính độc đáo' },
]

/* Nhóm nút định dạng của toolbar — gom thành 3 cụm, ngăn bằng VDivider. */
const EDITOR_TOOL_GROUPS = [
  ['tabler-bold', 'tabler-italic', 'tabler-underline'],
  ['tabler-list', 'tabler-list-numbers', 'tabler-align-left'],
  ['tabler-link', 'tabler-quote', 'tabler-photo', 'tabler-code', 'tabler-braces'],
]

/* ---------------------------------------------------------------------
 * DỮ LIỆU TĨNH — bài viết mẫu đang hiển thị trong vùng soạn thảo.
 * ------------------------------------------------------------------- */
const SAMPLE_ARTICLE = {
  title: 'Laravel 11 mang đến những tính năng mới gì?',
  wordCount: 1245,
  blocks: [
    {
      type: 'paragraph',
      text: 'Laravel 11 là phiên bản mới nhất của framework PHP phổ biến, mang đến nhiều cải tiến đáng chú ý giúp lập trình viên xây dựng ứng dụng nhanh hơn, hiệu quả hơn và bảo mật hơn. Trong bài viết này, chúng ta sẽ cùng tìm hiểu những tính năng nổi bật nhất của Laravel 11.',
    },
    { type: 'heading', text: '1. Hiệu suất được cải thiện' },
    {
      type: 'paragraph',
      text: 'Laravel 11 tập trung vào việc tối ưu hiệu suất, bao gồm thời gian khởi động nhanh hơn và cải thiện khả năng tự động tải class. Những thay đổi này giúp ứng dụng của bạn chạy mượt mà hơn, đặc biệt là ở quy mô lớn.',
    },
  ],
}

/* Tóm tắt & chủ đề chính do AI trích xuất. */
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

/* Lưới 6 chỉ số tổng quan (3 ô mỗi hàng). */
const OVERVIEW_STATS = [
  { value: '1,245', label: 'Số từ' },
  { value: '8', label: 'Số đoạn văn' },
  { value: '6', label: 'Số hình ảnh (ước tính)' },
  { value: '4', label: 'Số liên kết' },
  { value: '~ 5 phút', label: 'Thời gian đọc' },
  { value: 'Công nghệ', label: 'Chủ đề chính' },
]

/* Điểm tổng thể + checklist đánh giá nhanh. */
const OVERALL_SCORE = { value: 78, label: 'Tốt' }

const SCORE_CHECKLIST = [
  { text: 'Nội dung đầy đủ, rõ ràng', passed: true },
  { text: 'Cấu trúc hợp lý (H2, H3)', passed: true },
  { text: 'Văn phong dễ hiểu, tự nhiên', passed: true },
  { text: 'Có ví dụ minh họa', passed: true },
  { text: 'Tối ưu SEO tương đối tốt', passed: false },
  { text: 'Có thể cải thiện tính độc đáo', passed: false },
  { text: 'Nên thêm hình ảnh và liên kết tham khảo', passed: false },
]

/* 3 thước đo con dưới gauge điểm tổng. */
const SUB_SCORES = [
  { label: 'Chất lượng nội dung', value: 82, icon: 'tabler-file-text' },
  { label: 'Văn phong', value: 76, icon: 'tabler-feather' },
  { label: 'Tối ưu SEO', value: 70, icon: 'tabler-search' },
]

/* Phân tích văn phong — 5 trục, màu theo mức độ (không hex cứng). */
const STYLE_ANALYSIS = [
  { name: 'Tính chuyên môn', percent: 70, color: 'primary', desc: 'Cân bằng giữa kỹ thuật và dễ hiểu' },
  { name: 'Tính tự nhiên', percent: 85, color: 'primary', desc: 'Văn phong tự nhiên, mạch lạc' },
  { name: 'Tính thuyết phục', percent: 75, color: 'info', desc: 'Có lập luận, ví dụ cụ thể' },
  { name: 'Độ phức tạp', percent: 40, color: 'warning', desc: 'Dễ đọc, phù hợp nhiều đối tượng' },
  { name: 'Cảm xúc', percent: 60, color: 'primary', desc: 'Trung tính, tập trung vào thông tin' },
]

const PROMPT_TEXT = 'Hãy viết lại bài viết này về các tính năng mới của Laravel 11 bằng văn phong tự nhiên, hấp dẫn, chuẩn SEO, có cấu trúc rõ ràng với các tiêu đề H2, H3. Bổ sung thêm ví dụ thực tế, số liệu (nếu có), hình ảnh minh họa và liên kết tham khảo chính thức. Đối tượng là lập trình viên PHP/Laravel, nội dung khoảng 1200 - 1500 từ, viết bằng tiếng Việt.'

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
 * CHỨC NĂNG: Sao chép prompt gợi ý vào clipboard của trình duyệt.
 * =====================================================================
 * INPUT : không — đọc hằng số PROMPT_TEXT.
 * OUTPUT: Promise<void>.
 * SIDE EFFECT: ghi clipboard hệ thống; hiện snackbar kết quả.
 * EXCEPTION: trình duyệt chặn quyền clipboard (hoặc không chạy HTTPS)
 *            -> hiện snackbar lỗi thay vì ném ra ngoài.
 * =====================================================================
 */
async function copyPrompt() {
  try {
    await navigator.clipboard.writeText(PROMPT_TEXT)
    snack.text = 'Đã sao chép prompt'
    snack.color = 'success'
  } catch {
    snack.text = 'Không sao chép được — vui lòng chọn và copy thủ công'
    snack.color = 'error'
  }
  snack.show = true
}
</script>

<template>
  <div>
    <!-- ================= HEADER: Breadcrumbs, Tiêu đề & Cụm nút ================= -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <div class="text-caption text-medium-emphasis mb-1">
          AI Tools &gt; <span class="text-high-emphasis">Analyze Article</span>
        </div>
        <h4 class="text-h4 font-weight-medium">
          AI Phân tích bài viết
        </h4>
        <div class="text-body-1">
          Phân tích chi tiết nội dung, văn phong và gợi ý prompt để tạo bài viết chất lượng hơn.
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
          variant="flat"
          color="primary"
          prepend-icon="tabler-sparkles"
        >
          Phân tích với AI
        </VBtn>
      </div>
    </div>

    <VRow>
      <!-- ===== CỘT TRÁI: Nhập bài viết & Kết quả phân tích chi tiết ===== -->
      <VCol
        cols="12"
        lg="7"
      >
        <!-- 1. Card Nguồn bài viết & Trình soạn thảo -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-link"
                  color="primary"
                  size="20"
                  class="me-2"
                />
                <span class="text-subtitle-1 font-weight-medium">Nguồn bài viết</span>
              </div>
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
              >
                {{ tab.label }}
              </VTab>
            </VTabs>

            <!-- Hộp soạn thảo nội dung -->
            <div class="editor-shell rounded overflow-hidden">
              <!-- Toolbar -->
              <div class="editor-toolbar d-flex align-center flex-wrap px-2 py-1">
                <AppSelect
                  v-model="editorFormat"
                  :items="EDITOR_FORMATS"
                  density="compact"
                  variant="plain"
                  hide-details
                  class="editor-format-select me-2"
                />

                <template
                  v-for="(group, gIdx) in EDITOR_TOOL_GROUPS"
                  :key="gIdx"
                >
                  <VDivider
                    vertical
                    class="mx-1 my-1"
                  />
                  <IconBtn
                    v-for="toolIcon in group"
                    :key="toolIcon"
                    size="x-small"
                    color="secondary"
                  >
                    <VIcon :icon="toolIcon" />
                  </IconBtn>
                </template>
              </div>

              <VDivider />

              <!-- Vùng hiển thị văn bản soạn thảo -->
              <div class="editor-body pa-4">
                <h5 class="text-subtitle-1 font-weight-medium mb-2">
                  {{ SAMPLE_ARTICLE.title }}
                </h5>
                <template
                  v-for="(block, bIdx) in SAMPLE_ARTICLE.blocks"
                  :key="bIdx"
                >
                  <div
                    v-if="block.type === 'heading'"
                    class="text-body-1 font-weight-medium mb-1"
                  >
                    {{ block.text }}
                  </div>
                  <p
                    v-else
                    class="text-body-2 text-medium-emphasis mb-3"
                  >
                    {{ block.text }}
                  </p>
                </template>
              </div>

              <VDivider />

              <!-- Thanh trạng thái và nút phân tích -->
              <div class="editor-footer d-flex justify-space-between align-center flex-wrap gap-2 px-4 py-2">
                <span class="text-caption text-medium-emphasis">
                  Số từ: {{ SAMPLE_ARTICLE.wordCount }}
                </span>
                <div class="d-flex align-center gap-2">
                  <IconBtn
                    size="small"
                    color="secondary"
                  >
                    <VIcon icon="tabler-refresh" />
                  </IconBtn>
                  <IconBtn
                    size="small"
                    color="secondary"
                  >
                    <VIcon icon="tabler-trash" />
                  </IconBtn>
                  <VBtn
                    variant="flat"
                    color="primary"
                    size="small"
                  >
                    Phân tích bài viết
                  </VBtn>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- 2. Card Phân tích chi tiết -->
        <VCard>
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-file-search"
                  color="primary"
                  size="20"
                  class="me-2"
                />
                <span class="text-subtitle-1 font-weight-medium">Phân tích chi tiết</span>
              </div>
            </template>
          </VCardItem>

          <VCardText>
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
                {{ tab.label }}
              </VTab>
            </VTabs>

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
          </VCardText>
        </VCard>
      </VCol>

      <!-- ===== CỘT PHẢI: Chỉ số, Đánh giá tổng thể & Gợi ý Prompt ===== -->
      <VCol
        cols="12"
        lg="5"
      >
        <!-- 1. Thông tin tổng quan (Lưới 6 chỉ số) -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-info-circle"
                  color="primary"
                  size="18"
                  class="me-2"
                />
                <span class="text-subtitle-1 font-weight-medium">Thông tin tổng quan</span>
              </div>
            </template>
          </VCardItem>

          <VCardText>
            <VRow
              dense
              class="overview-grid text-center"
            >
              <VCol
                v-for="(stat, sIdx) in OVERVIEW_STATS"
                :key="stat.label"
                cols="4"
                class="overview-cell pa-2"
                :class="{ 'overview-cell--row-first': sIdx < 3 }"
              >
                <div class="text-h6 font-weight-medium">
                  {{ stat.value }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ stat.label }}
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- 2. Đánh giá tổng thể -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-gauge"
                  color="primary"
                  size="18"
                  class="me-2"
                />
                <span class="text-subtitle-1 font-weight-medium">Đánh giá tổng thể</span>
              </div>
            </template>
          </VCardItem>

          <VCardText>
            <!-- Gauge Score & Checklist -->
            <div class="d-flex align-center flex-wrap gap-4 mb-4">
              <VProgressCircular
                :model-value="OVERALL_SCORE.value"
                :size="92"
                :width="9"
                :color="scoreColor(OVERALL_SCORE.value)"
              >
                <div class="text-center">
                  <div class="text-h6 font-weight-medium">
                    {{ OVERALL_SCORE.value }}<span class="text-caption text-medium-emphasis">/100</span>
                  </div>
                  <div
                    class="text-caption font-weight-medium"
                    :class="`text-${scoreColor(OVERALL_SCORE.value)}`"
                  >
                    {{ OVERALL_SCORE.label }}
                  </div>
                </div>
              </VProgressCircular>

              <div class="d-flex flex-column gap-1 flex-grow-1">
                <div
                  v-for="check in SCORE_CHECKLIST"
                  :key="check.text"
                  class="d-flex align-center text-caption text-medium-emphasis"
                >
                  <VIcon
                    :icon="check.passed ? 'tabler-circle-check' : 'tabler-circle-dot'"
                    :color="check.passed ? 'success' : 'warning'"
                    size="14"
                    class="me-1 flex-shrink-0"
                  />
                  {{ check.text }}
                </div>
              </div>
            </div>

            <VDivider class="mb-4" />

            <!-- 3 Thước đo con bên dưới -->
            <VRow dense>
              <VCol
                v-for="sub in SUB_SCORES"
                :key="sub.label"
                cols="4"
              >
                <div class="d-flex align-center text-caption text-medium-emphasis mb-1">
                  <VIcon
                    :icon="sub.icon"
                    size="13"
                    :color="scoreColor(sub.value)"
                    class="me-1 flex-shrink-0"
                  />
                  <span class="text-truncate">{{ sub.label }}</span>
                </div>
                <div class="text-body-1 font-weight-medium mb-1">
                  {{ sub.value }}<span class="text-caption text-medium-emphasis">/100</span>
                </div>
                <VProgressLinear
                  :model-value="sub.value"
                  :color="scoreColor(sub.value)"
                  height="5"
                  rounded
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- 3. Phân tích văn phong -->
        <VCard class="mb-6">
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-feather"
                  color="primary"
                  size="18"
                  class="me-2"
                />
                <span class="text-subtitle-1 font-weight-medium">Phân tích văn phong</span>
              </div>
            </template>
            <template #append>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  label
                >
                  Văn phong: Trung tính
                </VChip>
                <VChip
                  size="x-small"
                  variant="tonal"
                  color="info"
                  label
                >
                  Mức độ: Dễ đọc
                </VChip>
              </div>
            </template>
          </VCardItem>

          <VCardText>
            <div class="d-flex flex-column gap-3">
              <div
                v-for="item in STYLE_ANALYSIS"
                :key="item.name"
              >
                <div class="d-flex justify-space-between align-center gap-2 mb-1">
                  <span class="text-body-2 font-weight-medium style-trait-name">
                    {{ item.name }}
                  </span>
                  <VProgressLinear
                    :model-value="item.percent"
                    :color="item.color"
                    height="6"
                    rounded
                    class="flex-grow-1"
                  />
                  <span class="text-body-2 font-weight-medium text-end style-trait-value">
                    {{ item.percent }}%
                  </span>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ item.desc }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- 4. Gợi ý prompt để viết lại / cải thiện -->
        <VCard>
          <VCardItem>
            <template #title>
              <div class="d-flex align-center">
                <VIcon
                  icon="tabler-sparkles"
                  color="primary"
                  size="18"
                  class="me-2"
                />
                <span class="text-subtitle-1 font-weight-medium">
                  Gợi ý prompt để viết lại / cải thiện
                </span>
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
            <div class="prompt-box pa-3 rounded text-body-2 text-medium-emphasis font-italic">
              “{{ PROMPT_TEXT }}”
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

.editor-toolbar,
.editor-footer {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.editor-body {
  min-block-size: 220px;
}

/* Select định dạng trong toolbar — thu gọn chiều ngang, bỏ padding mặc định. */
.editor-format-select {
  max-inline-size: 115px;
}

.editor-format-select :deep(.v-field__input) {
  padding: 0;
  min-block-size: auto;
}

/* Lưới 6 chỉ số — kẻ ô bằng viền theme thay vì hex cứng. */
.overview-grid {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  overflow: hidden;
}

.overview-cell:not(:nth-child(3n)) {
  border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.overview-cell--row-first {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Hàng phân tích văn phong — cố định bề rộng nhãn và số % để thanh đo thẳng cột. */
.style-trait-name {
  flex: 0 0 100px;
}

.style-trait-value {
  flex: 0 0 38px;
}

/* Hộp prompt gợi ý — nền tonal primary nhạt. */
.prompt-box {
  border: 1px solid rgba(var(--v-theme-primary), 0.16);
  background: rgba(var(--v-theme-primary), 0.05);
}
</style>
