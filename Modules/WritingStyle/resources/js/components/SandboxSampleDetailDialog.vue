<!--
  =====================================================================
  CHỨC NĂNG FILE: Dialog "Chi tiết bài văn thử nghiệm" trong Phòng thử DNA.
  =====================================================================
  Component dialog riêng dùng khung 3 vùng bắt buộc AppDialogLayout
  (header cố định -> body cuộn -> footer cố định), đúng chuẩn
  docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §3.5 & §4.

  DỮ LIỆU ĐỘNG (nhận từ trang chỉnh sửa Writing Profile):
  - Cột bên trái: Danh sách Tab các Step theo đúng quy trình `sample.steps`
  (Nếu bài viết thuộc cấp độ 1 bước thì chỉ hiện 1 tab duy nhất;
  nếu có nhiều bước phân tích thì hiện đầy đủ các tab theo thứ tự Step).
  - Cột bên phải: Kết quả AI trả về tương ứng với từng Step trong `sample.artifacts`
  và nội dung bài viết hoàn chỉnh trong `sample.text`.

  CÁC HÀM/METHOD TRONG FILE:
  - closeDialog(), resetAfterLeave(), cleanStepLabel(), isStepCompleted()
  - addOutlineSection(), removeOutlineSection()
  =====================================================================
-->
<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import TiptapEditor from '@core/components/TiptapEditor.vue'
import AppDialogLayout from '@/components/dialogs/AppDialogLayout.vue'

const props = defineProps({
  sample: {
    type: Object,
    default: () => null,
  },
  profile: {
    type: Object,
    default: () => null,
  },
  runningStep: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['runNextStep', 'refreshOutline', 'copyText'])

const isOpen = defineModel({ type: Boolean, default: false })

const activeStep = ref('')
const showSystemPrompt = ref(false)
const articleEditorHtml = ref('')

/* Chuyển văn bản thuần / markdown nhẹ từ AI sang HTML chuẩn cho TiptapEditor */
function plainTextToEditorHtml(rawText) {
  const txt = String(rawText ?? '').trim()
  if (!txt) return ''

  // Nếu đã là HTML từ TiptapEditor thì giữ nguyên
  if (/^\s*<(?:p|h[1-6]|div|ul|ol|blockquote)\b/i.test(txt)) {
    return txt
  }

  const escapeHtml = str =>
    str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')

  return txt
    .split(/\n{2,}/)
    .map(block => {
      const trimmed = block.trim()
      if (!trimmed) return ''
      if (trimmed.startsWith('### ')) {
        return `<h3>${escapeHtml(trimmed.slice(4))}</h3>`
      }
      if (trimmed.startsWith('## ')) {
        return `<h2>${escapeHtml(trimmed.slice(3))}</h2>`
      }
      if (trimmed.startsWith('# ')) {
        return `<h1>${escapeHtml(trimmed.slice(2))}</h1>`
      }

      const lines = trimmed.split('\n').map(l => escapeHtml(l)).join('<br>')

      return `<p>${lines}</p>`
    })
    .filter(Boolean)
    .join('')
}

/* Tham chiếu dữ liệu Bước 1 (topic_outline) phục vụ hiển thị và chỉnh sửa trước khi qua bước sau */
const topicOutlineArtifact = computed(() => props.sample?.artifacts?.topic_outline ?? null)
const isV3Outline = computed(() => topicOutlineArtifact.value?.schema_version === 3)
const qualityWarnings = computed(() => props.sample?.artifacts?.article_writer?.quality_report?.warnings ?? [])

/* Danh sách các bước thực thi của bản ghi hiện tại (tối thiểu 1 bước) */
const sampleSteps = computed(() => {
  const rawSteps = props.sample?.steps
  if (Array.isArray(rawSteps) && rawSteps.length > 0) {
    return rawSteps
  }

  return [
    {
      step: 'article_writer',
      label: 'Bước 1: Áp dụng DNA & Viết toàn bài',
      description: 'Áp dụng toàn bộ DNA văn phong để sinh bài viết hoàn chỉnh.',
      icon: 'tabler-writing',
      stage: 100,
      is_terminal: true,
      depends_on: [],
    },
  ]
})

/* Bước kế tiếp cần chạy nếu bản ghi đang ở trạng thái in_progress */
const nextPendingStep = computed(() => {
  if (!props.sample || props.sample.status === 'completed') return null
  const nextIdx = (props.sample.current_step_index ?? 0) + 1
  if (nextIdx >= sampleSteps.value.length) return null

  return {
    index: nextIdx,
    stepObj: sampleSteps.value[nextIdx],
  }
})

/* Nội dung bài viết hoàn chỉnh ở bước cuối */
const finalArticleText = computed(() => {
  if (!props.sample) return ''
  if (props.sample.text) return String(props.sample.text)

  const writerArtifact = props.sample.artifacts?.article_writer
  if (writerArtifact?.text) return String(writerArtifact.text)

  return ''
})

/* System Prompt đã áp dụng ở bước viết bài */
const finalSystemPrompt = computed(() => {
  if (!props.sample) return ''
  if (props.sample.system_prompt) return String(props.sample.system_prompt)

  const writerArtifact = props.sample.artifacts?.article_writer
  if (writerArtifact?.snapshot?.system_prompt) {
    return String(writerArtifact.snapshot.system_prompt)
  }

  return ''
})

/* Thống kê số từ của bài viết */
const articleWordCount = computed(() => {
  const txt = finalArticleText.value.trim()
  if (!txt) return 0

  return txt.split(/\s+/).length
})

/* Rút gọn nhãn bước (bỏ tiền tố "Bước X:" để đánh số đồng nhất) */
function cleanStepLabel(stepObj) {
  if (!stepObj) return ''

  return String(stepObj.label || stepObj.step || '')
    .replace(/^Bước\s+\d+\s*:\s*/i, '')
    .trim()
}

/* Kiểm tra bước thứ `idx` đã hoàn thành dữ liệu hay chưa */
function isStepCompleted(st, idx) {
  if (!props.sample) return false
  if (props.sample.status === 'completed') {
    if (st.is_terminal) return Boolean(finalArticleText.value)

    return Boolean(props.sample.artifacts?.[st.step])
  }

  const currentIdx = props.sample.current_step_index ?? 0

  return idx <= currentIdx && Boolean(props.sample.artifacts?.[st.step])
}

/* Đồng bộ tab đang chọn khi mở dialog hoặc khi chạy xong bước mới */
watch(
  () => [isOpen.value, props.sample?.id, props.sample?.current_step_index, props.sample?.status],
  ([open]) => {
    if (!open || !props.sample) return

    const steps = sampleSteps.value
    if (steps.length === 1) {
      activeStep.value = steps[0].step

      return
    }

    // Nếu bản ghi đang chạy dở (in_progress), chọn ngay bước hiện tại vừa chạy xong
    if (props.sample.status !== 'completed') {
      const idx = Math.min(props.sample.current_step_index ?? 0, steps.length - 1)

      activeStep.value = steps[idx]?.step || steps[0].step

      return
    }

    // Nếu vừa chuyển sang completed hoặc mở bản ghi đã hoàn thành:
    // ưu tiên hiển thị bước cuối (bài viết) hoặc giữ nguyên tab người dùng đang xem
    const validCurrent = steps.some(s => s.step === activeStep.value)
    if (!validCurrent) {
      activeStep.value = steps[steps.length - 1].step
    }
  },
  { immediate: true },
)

/* Khi chạy xong bước cuối cùng từ trong dialog -> tự chuyển sang tab bài viết hoàn chỉnh */
watch(
  () => props.sample?.status,
  newStatus => {
    if (newStatus === 'completed' && sampleSteps.value.length > 1) {
      activeStep.value = sampleSteps.value[sampleSteps.value.length - 1].step
    }
  },
)

/* Đồng bộ nội dung bài viết vào TiptapEditor */
watch(
  () => [props.sample?.id, finalArticleText.value],
  () => {
    articleEditorHtml.value = plainTextToEditorHtml(finalArticleText.value)
  },
  { immediate: true },
)

/* Thêm 1 mục H2/H3 mới vào dàn ý của Bước 1 (`topic_outline`) */
function addOutlineSection() {
  const outlineArtifact = props.sample?.artifacts?.topic_outline
  if (!outlineArtifact) return
  if (!Array.isArray(outlineArtifact.outline)) {
    outlineArtifact.outline = []
  }
  outlineArtifact.outline.push({
    tag: 'H2',
    title: 'Tiêu đề luận điểm mới',
    key_points: ['Ý chính triển khai'],
    suggested_evidence: '',
  })
}

/* Xóa 1 mục khỏi dàn ý của Bước 1 (`topic_outline`) */
function removeOutlineSection(idx) {
  const outline = props.sample?.artifacts?.topic_outline?.outline
  if (!Array.isArray(outline) || outline.length <= 1) return
  outline.splice(idx, 1)
}

/* Đóng dialog nếu không đang chạy step AI */
function closeDialog() {
  if (!props.runningStep) {
    isOpen.value = false
  }
}

/* Reset state phụ sau khi animation đóng hoàn tất (chuẩn §4.2) */
function resetAfterLeave() {
  if (isOpen.value) return
  showSystemPrompt.value = false
  activeStep.value = ''
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    width="92vw"
    max-width="1480"
    scrollable
    :persistent="runningStep"
    @after-leave="resetAfterLeave"
  >
    <AppDialogLayout
      close-label="Đóng chi tiết bài văn thử nghiệm"
      :close-disabled="runningStep"
      @close="closeDialog"
    >
      <!-- ==================== VÙNG 1: HEADER CỐ ĐỊNH ==================== -->
      <template #header>
        <VCardItem v-if="sample">
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
              size="40"
              rounded
              class="me-3"
            >
              <VIcon
                icon="tabler-flask-2"
                size="22"
              />
            </VAvatar>
          </template>

          <VCardTitle class="d-flex align-center flex-wrap gap-2">
            <span class="text-h6 font-weight-medium text-high-emphasis">
              {{ sample.topic || 'Chi tiết bài văn thử nghiệm' }}
            </span>

            <VChip
              size="small"
              color="primary"
              variant="tonal"
              label
            >
              {{ (sample.target_tier || 'medium').toUpperCase() }}
            </VChip>

            <VChip
              v-if="sample.status === 'completed'"
              size="small"
              color="success"
              variant="flat"
              label
              prepend-icon="tabler-circle-check"
            >
              Hoàn thành ({{ sampleSteps.length }}/{{ sampleSteps.length }} bước)
            </VChip>
            <VChip
              v-else
              size="small"
              color="warning"
              variant="flat"
              label
              prepend-icon="tabler-loader"
            >
              Đang ở Bước {{ (sample.current_step_index ?? 0) + 1 }}/{{ sampleSteps.length }}
            </VChip>
          </VCardTitle>

          <VCardSubtitle class="mt-1">
            Hồ sơ DNA: {{ profile?.name || 'Writing Profile' }}
            <template v-if="sample.created_at">
              • Khởi tạo lúc {{ new Date(sample.created_at).toLocaleString('vi-VN') }}
            </template>
          </VCardSubtitle>
        </VCardItem>
      </template>

      <!-- ==================== VÙNG 2: BODY CUỘN (BỐ CỤC 2 CỘT) ==================== -->
      <VCardText
        v-if="sample"
        class="pa-6"
      >
        <VRow>
          <!-- ========== CỘT TRÁI: DANH SÁCH TAB CÁC STEP PHÂN TÍCH ========== -->
          <VCol
            cols="12"
            md="3"
          >
            <div class="pa-3 rounded border bg-var-theme-background h-100">
              <div class="text-caption text-uppercase text-disabled font-weight-medium mb-3 px-2">
                Quy trình thực thi ({{ sampleSteps.length }} bước)
              </div>

              <div class="d-flex flex-column gap-2">
                <button
                  v-for="(st, sIdx) in sampleSteps"
                  :key="st.step"
                  type="button"
                  class="step-nav-item pa-3 rounded text-start d-flex align-start gap-3 w-100"
                  :class="{ 'step-nav-item--active': activeStep === st.step }"
                  @click="activeStep = st.step"
                >
                  <VAvatar
                    :color="activeStep === st.step
                      ? 'primary'
                      : (isStepCompleted(st, sIdx) ? 'success' : 'secondary')"
                    :variant="activeStep === st.step ? 'flat' : 'tonal'"
                    size="32"
                    rounded
                    class="flex-shrink-0 mt-1"
                  >
                    <VIcon
                      :icon="isStepCompleted(st, sIdx)
                        ? 'tabler-check'
                        : (st.icon || (st.is_terminal ? 'tabler-writing' : 'tabler-binary-tree'))"
                      size="18"
                    />
                  </VAvatar>

                  <div class="step-nav-item__content">
                    <div class="text-body-2 font-weight-medium text-high-emphasis">
                      Bước {{ sIdx + 1 }}: {{ cleanStepLabel(st) }}
                    </div>
                    <div
                      v-if="st.description"
                      class="text-caption text-medium-emphasis mt-1"
                    >
                      {{ st.description }}
                    </div>
                  </div>
                </button>
              </div>
            </div>
          </VCol>

          <!-- ========== CỘT PHẢI: KẾT QUẢ CỦA TỪNG STEP ========== -->
          <VCol
            cols="12"
            md="9"
          >
            <VWindow
              v-model="activeStep"
              class="disable-tab-transition"
              :touch="false"
            >
              <VWindowItem
                v-for="(st, sIdx) in sampleSteps"
                :key="`step-pane-${st.step}`"
                :value="st.step"
              >
                <!-- Header của Step đang xem -->
                <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-4 pb-3 border-b">
                  <div>
                    <div class="d-flex align-center gap-2">
                      <VChip
                        size="small"
                        :color="isStepCompleted(st, sIdx) ? 'success' : 'secondary'"
                        variant="tonal"
                        label
                      >
                        Bước {{ sIdx + 1 }}/{{ sampleSteps.length }}
                      </VChip>
                      <span class="text-subtitle-1 font-weight-medium text-high-emphasis">
                        {{ cleanStepLabel(st) }}
                      </span>
                    </div>
                    <div
                      v-if="st.description"
                      class="text-caption text-medium-emphasis mt-1"
                    >
                      {{ st.description }}
                    </div>
                  </div>

                  <!-- Cụm nút phụ trợ cho bước viết bài cuối cùng -->
                  <div
                    v-if="st.is_terminal && finalArticleText"
                    class="d-flex align-center flex-wrap gap-2"
                  >
                    <VChip
                      size="small"
                      color="info"
                      variant="tonal"
                      label
                    >
                      {{ articleWordCount }} từ • {{ finalArticleText.length }} ký tự
                    </VChip>

                    <VBtn
                      v-if="finalSystemPrompt"
                      size="small"
                      variant="tonal"
                      color="secondary"
                      :prepend-icon="showSystemPrompt ? 'tabler-eye-off' : 'tabler-terminal-2'"
                      @click="showSystemPrompt = !showSystemPrompt"
                    >
                      {{ showSystemPrompt ? 'Ẩn Prompt' : 'Xem Prompt' }}
                    </VBtn>

                    <VBtn
                      size="small"
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-copy"
                      @click="emit('copyText', finalArticleText)"
                    >
                      Sao chép bài viết
                    </VBtn>
                  </div>
                </div>

                <!-- ==================== TRƯỜNG HỢP 1: STEP PHÂN TÍCH TRUNG GIAN (!st.is_terminal) ==================== -->
                <template v-if="!st.is_terminal">
                  <div v-if="sample.artifacts && sample.artifacts[st.step]">
                    <!-- Giao diện cho Step: Phân tích Chủ đề & Lập Dàn ý (topic_outline) -->
                    <template v-if="st.step === 'topic_outline' && isV3Outline">
                      <AppTextarea
                        v-model="topicOutlineArtifact.central_message"
                        label="Thông điệp chính"
                        :readonly="sample.status === 'completed'"
                        rows="2"
                        class="mb-3"
                      />
                      <AppTextarea
                        v-model="topicOutlineArtifact.target_angle"
                        label="Góc tiếp cận độc giả"
                        :readonly="sample.status === 'completed'"
                        rows="2"
                        class="mb-3"
                      />
                      <div
                        v-for="section in topicOutlineArtifact.sections"
                        :key="section.id"
                        class="pa-3 mb-3 rounded border"
                      >
                        <div class="text-caption mb-2">
                          {{ section.heading_level ? `H${section.heading_level}` : section.role }}
                          · {{ section.length_budget }} {{ sample.brief?.length?.unit === 'sentences' ? 'câu' : 'từ' }}
                        </div>
                        <AppTextField
                          v-model="section.title"
                          label="Nội dung mục"
                          :readonly="sample.status === 'completed'"
                        />
                        <ul class="ps-5 mt-2">
                          <li v-for="(point, index) in section.key_points" :key="index">
                            {{ point }}
                          </li>
                        </ul>
                      </div>
                      <VAlert
                        v-if="topicOutlineArtifact.evidence_requests.length || topicOutlineArtifact.missing_fields.length || topicOutlineArtifact.warnings.length"
                        type="info"
                        variant="tonal"
                        title="Thông tin cần bổ sung hoặc kiểm tra"
                      >
                        <div v-for="(item, index) in [...topicOutlineArtifact.evidence_requests, ...topicOutlineArtifact.missing_fields, ...topicOutlineArtifact.warnings]" :key="index">
                          {{ item }}
                        </div>
                      </VAlert>
                    </template>
                    <template v-else-if="st.step === 'topic_outline' && topicOutlineArtifact">
                      <VRow class="mb-4">
                        <VCol
                          cols="12"
                          md="6"
                        >
                          <AppTextarea
                            v-model="topicOutlineArtifact.thesis"
                            label="Luận điểm cốt lõi (Thesis)"
                            placeholder="Nhập luận điểm xuyên suốt bài viết..."
                            :readonly="sample.status === 'completed'"
                            rows="3"
                          />
                        </VCol>

                        <VCol
                          cols="12"
                          md="6"
                        >
                          <AppTextarea
                            v-model="topicOutlineArtifact.target_angle"
                            label="Góc tiếp cận độc giả (Target Angle)"
                            placeholder="Nhập góc nhìn triển khai chủ đề..."
                            :readonly="sample.status === 'completed'"
                            rows="3"
                          />
                        </VCol>
                      </VRow>

                      <!-- Danh sách Cây dàn ý H2 / H3 AI trả về -->
                      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                        <div class="d-flex align-center gap-2">
                          <span class="text-subtitle-1 font-weight-medium text-high-emphasis">
                            Nội dung Dàn ý chi tiết (H2 / H3)
                          </span>
                          <VChip
                            size="small"
                            color="info"
                            variant="tonal"
                            label
                          >
                            {{ topicOutlineArtifact.outline?.length || 0 }} mục
                          </VChip>
                        </div>

                        <VBtn
                          v-if="sample.status !== 'completed'"
                          size="small"
                          variant="tonal"
                          color="primary"
                          prepend-icon="tabler-plus"
                          @click="addOutlineSection"
                        >
                          Thêm mục dàn ý
                        </VBtn>
                      </div>

                      <div class="d-flex flex-column gap-3">
                        <div
                          v-for="(sec, oIdx) in (topicOutlineArtifact.outline || [])"
                          :key="`outline-sec-${oIdx}`"
                          class="pa-4 rounded border bg-var-theme-background"
                        >
                          <div class="d-flex align-center justify-space-between gap-2 mb-2">
                            <div class="d-flex align-center gap-2 flex-grow-1">
                              <VChip
                                size="small"
                                :color="sec.tag === 'H3' ? 'secondary' : 'primary'"
                                variant="flat"
                                label
                                class="flex-shrink-0"
                              >
                                {{ sec.tag || 'H2' }}
                              </VChip>

                              <span
                                v-if="sample.status === 'completed'"
                                class="text-body-1 font-weight-medium text-high-emphasis"
                              >
                                {{ sec.title }}
                              </span>
                              <div
                                v-else
                                class="flex-grow-1"
                              >
                                <AppTextField
                                  v-model="sec.title"
                                  placeholder="Nhập tiêu đề mục dàn ý..."
                                />
                              </div>
                            </div>

                            <VBtn
                              v-if="sample.status !== 'completed' && (topicOutlineArtifact.outline?.length || 0) > 1"
                              icon="tabler-trash"
                              size="small"
                              variant="text"
                              color="error"
                              @click="removeOutlineSection(oIdx)"
                            />
                          </div>

                          <!-- Các ý triển khai chính (key_points) -->
                          <div
                            v-if="Array.isArray(sec.key_points) && sec.key_points.length"
                            class="ps-2 mt-2"
                          >
                            <div class="text-caption text-medium-emphasis mb-1">
                              Các ý triển khai chính:
                            </div>
                            <div
                              v-for="(point, pIdx) in sec.key_points"
                              :key="pIdx"
                              class="text-body-2 text-high-emphasis mb-1"
                            >
                              • {{ point }}
                            </div>
                          </div>

                          <!-- Dẫn chứng gợi ý (suggested_evidence) -->
                          <div
                            v-if="sec.suggested_evidence"
                            class="text-caption text-info ps-2 mt-2 pt-2 border-t"
                          >
                            Dẫn chứng / Chi tiết gợi ý: {{ sec.suggested_evidence }}
                          </div>
                        </div>
                      </div>
                    </template>

                    <!-- Fallback cho các Step phân tích khác được cấu hình thêm trong config -->
                    <template v-else>
                      <div class="pa-4 rounded border bg-var-theme-background">
                        <pre class="text-caption font-mono prompt-pre">{{ JSON.stringify(sample.artifacts[st.step], null, 2) }}</pre>
                      </div>
                    </template>
                  </div>

                  <!-- Khi step phân tích này chưa có dữ liệu -->
                  <div
                    v-else
                    class="pa-8 rounded border border-dashed text-center"
                  >
                    <VIcon
                      icon="tabler-clock-pause"
                      size="36"
                      color="secondary"
                      class="mb-2"
                    />
                    <div class="text-body-1 font-weight-medium text-high-emphasis mb-1">
                      Bước phân tích này chưa có dữ liệu
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ sample.status === 'completed'
                        ? 'Bản ghi này được lưu trước khi có dữ liệu phân tích trung gian.'
                        : 'Hãy bấm nút tiếp tục phân tích ở bên dưới để AI thực thi bước này.' }}
                    </div>
                  </div>
                </template>

                <!-- ==================== TRƯỜNG HỢP 2: STEP VIẾT BÀI HOÀN CHỈNH (st.is_terminal) ==================== -->
                <template v-else>
                  <div v-if="finalArticleText">
                    <VAlert v-if="qualityWarnings.length" type="warning" variant="tonal" class="mb-4" title="Các điểm cần kiểm tra">
                      <div v-for="warning in qualityWarnings" :key="warning">{{ warning }}</div>
                    </VAlert>
                    <!-- Nội dung bài viết đặt trong TiptapEditor -->
                    <TiptapEditor
                      v-model="articleEditorHtml"
                      class="border rounded bg-surface sandbox-article-editor"
                      placeholder="Nội dung bài viết..."
                    />

                    <!-- Khung xem System Prompt -->
                    <div
                      v-if="showSystemPrompt && finalSystemPrompt"
                      class="mt-4 pa-4 rounded border bg-surface"
                    >
                      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                        <span class="text-body-2 font-weight-medium text-high-emphasis">
                          System Prompt áp dụng cho bản viết thử:
                        </span>
                        <VBtn
                          size="small"
                          variant="text"
                          color="primary"
                          prepend-icon="tabler-copy"
                          @click="emit('copyText', finalSystemPrompt)"
                        >
                          Sao chép Prompt
                        </VBtn>
                      </div>
                      <pre class="text-caption font-mono prompt-pre">{{ finalSystemPrompt }}</pre>
                    </div>
                  </div>

                  <!-- Khi bản ghi chưa chạy tới bước viết văn cuối cùng -->
                  <div
                    v-else
                    class="pa-8 rounded border border-dashed text-center"
                  >
                    <VIcon
                      icon="tabler-writing"
                      size="36"
                      color="warning"
                      class="mb-2"
                    />
                    <div class="text-body-1 font-weight-medium text-high-emphasis mb-1">
                      Chưa đến giai đoạn viết bài hoàn chỉnh
                    </div>
                    <div class="text-caption text-medium-emphasis mb-4">
                      Bản ghi hiện đang ở Bước {{ (sample.current_step_index ?? 0) + 1 }}/{{ sampleSteps.length }}.
                      Hãy bấm tiếp tục phân tích để AI tổng hợp thành bài văn hoàn chỉnh.
                    </div>
                    <VBtn
                      v-if="nextPendingStep"
                      color="primary"
                      variant="flat"
                      :prepend-icon="nextPendingStep.stepObj?.is_terminal ? 'tabler-writing' : 'tabler-player-track-next'"
                      :loading="runningStep"
                      :disabled="runningStep"
                      @click="emit('runNextStep', sample)"
                    >
                      <template v-if="nextPendingStep.stepObj?.is_terminal">
                        {{ topicOutlineArtifact ? 'Duyệt dàn ý và viết bài' : 'Viết bài hoàn chỉnh' }}
                      </template>
                      <template v-else>
                        Tiếp tục phân tích lần thứ {{ nextPendingStep.index + 1 }}: {{ cleanStepLabel(nextPendingStep.stepObj) }}
                      </template>
                    </VBtn>
                  </div>
                </template>
              </VWindowItem>
            </VWindow>
          </VCol>
        </VRow>
      </VCardText>

      <!-- ==================== VÙNG 3: FOOTER CỐ ĐỊNH ==================== -->
      <template #footer>
        <VCardActions class="justify-space-between flex-wrap">
          <span class="text-caption text-medium-emphasis">
            <template v-if="sampleSteps.length > 1">
              Chọn từng bước ở cột bên trái để xem kết quả phân tích tương ứng.
            </template>
            <template v-else>
              Cấp độ {{ (sample?.target_tier || 'medium').toUpperCase() }} thực thi trực tiếp 1 bước viết bài.
            </template>
          </span>

          <div class="d-flex align-center gap-3">
            <VBtn
              v-if="nextPendingStep && topicOutlineArtifact"
              variant="tonal"
              :disabled="runningStep"
              @click="emit('refreshOutline', sample)"
            >
              Lập lại dàn ý
            </VBtn>
            <VBtn
              v-if="nextPendingStep"
              variant="flat"
              color="primary"
              :prepend-icon="nextPendingStep.stepObj?.is_terminal ? 'tabler-writing' : 'tabler-player-track-next'"
              :loading="runningStep"
              :disabled="runningStep"
              @click="emit('runNextStep', sample)"
            >
              <template v-if="nextPendingStep.stepObj?.is_terminal">
                {{ topicOutlineArtifact ? 'Duyệt dàn ý và viết bài' : 'Viết bài hoàn chỉnh' }}
              </template>
              <template v-else>
                Tiếp tục phân tích lần thứ {{ nextPendingStep.index + 1 }}: {{ cleanStepLabel(nextPendingStep.stepObj) }}
              </template>
            </VBtn>

            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="runningStep"
              @click="closeDialog"
            >
              Đóng
            </VBtn>
          </div>
        </VCardActions>
      </template>
    </AppDialogLayout>
  </VDialog>
</template>

<style scoped lang="scss">
/* Item điều hướng bước ở cột trái — tuân thủ chuẩn §4.2 chống tràn chữ */
.step-nav-item {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.step-nav-item:hover {
  border-color: rgb(var(--v-theme-primary));
}

.step-nav-item--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

.step-nav-item__content {
  flex: 1 1 auto;
  min-inline-size: 0;
  overflow: hidden;
  overflow-wrap: anywhere;
}

.sandbox-article-editor {
  :deep(.ProseMirror) {
    min-block-size: 380px;
    max-block-size: 56vh;
    overflow-y: auto;
    padding: 16px 20px;
    line-height: 1.75;
    outline: none;

    p {
      margin-block-end: 12px;
    }

    h1,
    h2,
    h3 {
      margin-block: 14px 8px;
      line-height: 1.4;
    }
  }
}

.prompt-pre {
  white-space: pre-wrap;
  margin: 0;
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
