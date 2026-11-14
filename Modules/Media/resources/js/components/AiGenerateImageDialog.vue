<!--
  =====================================================================
  CHỨC NĂNG FILE: Hộp thoại "Tạo ảnh từ văn bản" bằng AI (nối API Media + CoreAi).
  =====================================================================
  Dùng khung 3 vùng AppDialogLayout (header cố định -> body cuộn -> footer cố định),
  component App* và biến CSS theme Vuetify (hỗ trợ Dark Mode).

  LUỒNG TÍCH HỢP API:
  - Mở dialog: nạp danh sách model khả dụng (`mediaApi.aiImageModels()`).
  - Bấm "Tạo ảnh" / "Tạo lại": gọi `mediaApi.generateAiImages()` nhận HTTP 202 `job_id` rồi polling `mediaApi.aiImageJobStatus(job_id)` mỗi 1.5s đến khi `ready` hoặc `failed`.
  - Bấm "Lưu vào thư viện": gọi `mediaApi.saveAiImage()`, phát emit('saved', asset).
  - Bấm "Tải về": tải trực tiếp từ `download_url` (signed URL + `?download=1`).
  - Các mục chưa nối backend (`Mô tả hình ảnh`, `Gợi ý prompt`, `Ảnh tham chiếu`, `Phong cách hình ảnh`, `Yêu thích`) vẫn giữ nguyên hoạt động UI cục bộ.

  CÁC HÀM/METHOD TRONG FILE: loadAiModels(), stopPolling(), pickDefaultModelId(),
  generateImages(), pollJobStatus(), applyJobSnapshot(), saveToLibrary(),
  downloadSelected(), downloadItem(), selectResult(), toggleLike(),
  clearReferenceImage(), setReferenceImage(), onPickReferenceImage(),
  onReferenceFileChange(), onReferenceDragOver(), onReferenceDragLeave(),
  onReferenceDrop(), applySamplePrompt(), clearActionAlert(), closeDialog(),
  resetAfterLeave().
  INPUT: v-model (isOpen: boolean), props.folder (string, mặc định 'root').
  OUTPUT: emit('update:modelValue', bool), emit('saved', MediaAssetResource).
  SIDE EFFECT: Gọi API Media AI Image và dọn polling timer khi đóng/unmount.
  =====================================================================
-->
<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import AppDialogLayout from '@/components/dialogs/AppDialogLayout.vue'
import { firstErrorMessage } from '@/utils/api'
import AppSelect from '@core/components/app-form-elements/AppSelect.vue'
import AppTextarea from '@core/components/app-form-elements/AppTextarea.vue'
import { mediaApi } from '../services/mediaApi'

const props = defineProps({
  folder: {
    type: String,
    default: 'root',
  },
})

const emit = defineEmits(['saved'])

const isOpen = defineModel({ type: Boolean, default: false })

const DEFAULT_DESCRIPTION = 'Tem dán đồ uống "Olong Sen Kem Nhãn", phong cách mực nước vẽ tay, màu tươi sáng, nền trắng, có hoa sen, hạt sen và nhãn, chữ nâu mềm mại, bố cục hài hòa.'
const DEFAULT_AI_PROMPT = 'Hand-drawn watercolor beverage sticker label for "Olong Sen Kem Nhãn", lotus flower, lotus seeds and longan illustration, soft brown hand-lettered typography, bright fresh colors on clean white background, balanced composition.'
const MAX_REFERENCE_IMAGE_BYTES = 10 * 1024 * 1024
const ALLOWED_REFERENCE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'bmp']
const POLL_INTERVAL_MS = 1500
const MAX_POLL_ATTEMPTS = 120

const samplePrompts = [
  'Trà sữa Oolong nướng kem cheese, phong cách minh họa 3D hiện đại, tông màu ấm áp, ánh sáng studio chuyên nghiệp.',
  'Cốc cà phê muối Hội An vẽ tay màu nước, kèm hạt cà phê và hoa giấy, nền kem nhẹ nhàng, phong cách vintage.',
  'Poster quảng cáo nước ép dứa chanh leo mùa hè, màu sắc rực rỡ, hiệu ứng giọt nước mát lạnh, hiện đại.',
]

const aspectRatios = [
  { value: '1:1', label: 'Vuông', res: '1024 x 1024 px', boxWidth: 16, boxHeight: 16 },
  { value: '4:3', label: 'Ngang', res: '1152 x 864 px', boxWidth: 18, boxHeight: 14 },
  { value: '3:4', label: 'Dọc', res: '864 x 1152 px', boxWidth: 14, boxHeight: 18 },
  { value: '16:9', label: 'Ngang rộng', res: '1344 x 768 px', boxWidth: 20, boxHeight: 12 },
  { value: '9:16', label: 'Dọc dài', res: '768 x 1344 px', boxWidth: 12, boxHeight: 20 },
]

const visualStyles = [
  {
    value: 'watercolor',
    title: 'Mực nước / Vẽ tay',
    image: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=200',
  },
  {
    value: 'cute',
    title: 'Dễ thương / Cute',
    image: 'https://images.unsplash.com/photo-1563089145-599997674d42?w=200',
  },
  {
    value: 'minimal',
    title: 'Tối giản / Minimal',
    image: 'https://images.unsplash.com/photo-1513519245088-0e12902e5a38?w=200',
  },
  {
    value: 'realistic',
    title: 'Thực tế / Realistic',
    image: 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=200',
  },
  {
    value: 'poster',
    title: 'Poster / Quảng bá',
    image: 'https://images.unsplash.com/photo-1558655146-d09347e92766?w=200',
  },
]

const batchCountOptions = [1, 2, 4]

const descriptionInputId = useId()
const referenceInputId = useId()
const ratioGroupId = useId()
const styleGroupId = useId()

const promptInputRef = ref(null)
const promptText = ref(DEFAULT_DESCRIPTION)
const aiPromptText = ref(DEFAULT_AI_PROMPT)
const promptError = ref('')

const referenceFileInput = ref(null)
const referenceImage = ref(null)
const isReferenceDragging = ref(false)
const referenceError = ref('')

const selectedRatio = ref('1:1')
const selectedStyle = ref('watercolor')
const batchCount = ref(4)

const aiModels = ref([])
const defaultModelId = ref(null)
const selectedModel = ref(null)
const modelsLoading = ref(false)
const modelsLoaded = ref(false)
const modelsLoadError = ref('')

const resultViewMode = ref('grid')
const selectedResultId = ref(null)
const isGenerating = ref(false)
const isSaving = ref(false)
const jobTotal = ref(0)
const jobCompleted = ref(0)
const actionAlert = ref(null)
const samplePromptIndex = ref(0)
const generatedResults = ref([])

let pollTimer = null
let generationSeq = 0

const modelSelectOptions = computed(() => aiModels.value.map(model => ({
  title: model.provider_name
    ? `${model.label || model.name} (${model.provider_name})`
    : (model.label || model.name),
  value: model.id,
})))

const hasNoAvailableModel = computed(() => (
  modelsLoaded.value
  && !modelsLoading.value
  && !modelsLoadError.value
  && aiModels.value.length === 0
))

const currentResolutionText = computed(() => {
  const found = aspectRatios.find(item => item.value === selectedRatio.value)

  return found ? found.res : '1024 x 1024 px'
})

const selectedModelTitle = computed(() => {
  const found = aiModels.value.find(item => item.id === selectedModel.value)

  return found ? (found.label || found.name) : 'Mặc định hệ thống'
})

const selectedResult = computed(() => (
  generatedResults.value.find(item => item.preview_id === selectedResultId.value) || null
))

const pendingSlotsCount = computed(() => {
  if (!isGenerating.value)
    return 0

  return Math.max(0, jobTotal.value - generatedResults.value.length)
})

const resultHeaderSubtitle = computed(() => {
  if (isGenerating.value)
    return `Đang tạo ${jobCompleted.value}/${jobTotal.value || batchCount.value} ảnh • ${currentResolutionText.value}`
  if (generatedResults.value.length > 0)
    return `${generatedResults.value.length} ảnh được tạo • ${currentResolutionText.value}`

  return `Chưa tạo ảnh • ${currentResolutionText.value}`
})

const isBusy = computed(() => isGenerating.value || isSaving.value)

const referenceImageMetaText = computed(() => {
  if (!referenceImage.value)
    return ''

  const parts = []
  if (referenceImage.value.width && referenceImage.value.height)
    parts.push(`${referenceImage.value.width} × ${referenceImage.value.height} px`)
  if (referenceImage.value.size)
    parts.push(formatFileSize(referenceImage.value.size))

  return parts.join(' • ')
})

/**
 * CHỨC NĂNG: Chọn ID model mặc định hợp lệ từ danh sách model khả dụng.
 * INPUT: models (array), preferredId (?number).
 * OUTPUT: ?number.
 */
function pickDefaultModelId(models, preferredId) {
  if (!Array.isArray(models) || models.length === 0)
    return null

  if (preferredId !== null && preferredId !== undefined && models.some(m => m.id === preferredId))
    return preferredId

  return models[0].id
}

/**
 * =====================================================================
 * CHỨC NĂNG: Nạp danh sách model tạo ảnh thật từ CoreAi qua API Media.
 * =====================================================================
 * Cache trong phiên component để tránh gọi lặp lại khi đóng/mở nhanh; hỗ trợ
 * force=true khi người dùng bấm tải lại sau lỗi.
 */
async function loadAiModels({ force = false } = {}) {
  if (modelsLoading.value)
    return
  if (modelsLoaded.value && !force && !modelsLoadError.value)
    return

  modelsLoading.value = true
  modelsLoadError.value = ''

  try {
    const data = await mediaApi.aiImageModels()
    const list = Array.isArray(data?.models) ? data.models : []

    aiModels.value = list
    defaultModelId.value = data?.default_model_id ?? null
    selectedModel.value = pickDefaultModelId(
      list,
      selectedModel.value ?? defaultModelId.value,
    )
    modelsLoaded.value = true
  } catch (err) {
    modelsLoadError.value = firstErrorMessage(err) || 'Không tải được danh sách model AI tạo ảnh.'
  } finally {
    modelsLoading.value = false
  }
}

/**
 * Giải phóng Object URL của ảnh tham chiếu hiện tại để tránh rò rỉ bộ nhớ.
 */
function revokeReferencePreviewUrl() {
  const url = referenceImage.value?.previewUrl
  if (url && typeof URL !== 'undefined')
    URL.revokeObjectURL(url)
}

/**
 * Định dạng dung lượng tệp ảnh tham chiếu sang KB/MB dễ đọc.
 */
function formatFileSize(bytes) {
  if (!bytes || bytes <= 0)
    return '0 KB'
  if (bytes < 1024 * 1024)
    return `${Math.max(1, Math.round(bytes / 1024))} KB`

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/**
 * Xóa ảnh tham chiếu đang chọn và thông báo lỗi liên quan.
 */
function clearReferenceImage() {
  revokeReferencePreviewUrl()
  referenceImage.value = null
  referenceError.value = ''
  if (referenceFileInput.value)
    referenceFileInput.value.value = ''
}

/**
 * Kiểm tra định dạng/dung lượng và nạp 1 tệp ảnh tham chiếu cục bộ để xem trước.
 */
function setReferenceImage(file) {
  if (!file)
    return

  const dotIdx = file.name.lastIndexOf('.')
  const ext = dotIdx >= 0 ? file.name.slice(dotIdx + 1).toLowerCase() : ''

  if (!ALLOWED_REFERENCE_EXTENSIONS.includes(ext)) {
    referenceError.value = 'Định dạng chưa hỗ trợ. Vui lòng chọn ảnh JPG, PNG, WEBP, AVIF hoặc GIF.'

    return
  }

  if (file.size <= 0 || file.size > MAX_REFERENCE_IMAGE_BYTES) {
    referenceError.value = 'Ảnh tham chiếu phải lớn hơn 0 byte và không vượt quá 10 MB.'

    return
  }

  revokeReferencePreviewUrl()
  referenceError.value = ''

  const previewUrl = URL.createObjectURL(file)

  referenceImage.value = {
    name: file.name,
    size: file.size,
    type: file.type,
    width: null,
    height: null,
    previewUrl,
    file,
  }

  const probe = new Image()

  probe.onload = () => {
    if (referenceImage.value?.previewUrl === previewUrl) {
      referenceImage.value = {
        ...referenceImage.value,
        width: probe.naturalWidth || null,
        height: probe.naturalHeight || null,
      }
    }
  }
  probe.src = previewUrl
}

function onPickReferenceImage() {
  if (!isBusy.value)
    referenceFileInput.value?.click()
}

function onReferenceFileChange(event) {
  const file = event.target?.files?.[0]
  if (file)
    setReferenceImage(file)

  if (event.target)
    event.target.value = ''
}

function onReferenceDragOver() {
  if (!isBusy.value)
    isReferenceDragging.value = true
}

function onReferenceDragLeave(event) {
  if (!event.currentTarget?.contains(event.relatedTarget))
    isReferenceDragging.value = false
}

function onReferenceDrop(event) {
  isReferenceDragging.value = false
  if (isBusy.value)
    return

  const file = event.dataTransfer?.files?.[0]
  if (file)
    setReferenceImage(file)
}

/**
 * Dừng timer polling job tạo ảnh AI nếu đang chạy.
 */
function stopPolling() {
  if (pollTimer !== null) {
    clearTimeout(pollTimer)
    pollTimer = null
  }
}

/**
 * Chuyển sang câu mô tả mẫu tiếp theo trong danh sách gợi ý tĩnh.
 */
function applySamplePrompt() {
  promptText.value = samplePrompts[samplePromptIndex.value % samplePrompts.length]
  samplePromptIndex.value += 1
}

function onPromptUpdate(val) {
  if (promptError.value && String(val ?? '').trim())
    promptError.value = ''
}

function selectRatio(value) {
  selectedRatio.value = value
}

function selectStyle(value) {
  selectedStyle.value = value
}

function selectResult(previewId) {
  selectedResultId.value = previewId
}

function setResultViewMode(mode) {
  resultViewMode.value = mode
}

function toggleLike(item) {
  item.liked = !item.liked
}

function clearActionAlert() {
  actionAlert.value = null
}

/**
 * =====================================================================
 * CHỨC NĂNG: Đồng bộ snapshot kết quả từ job tracker (giữ liked/saved local).
 * =====================================================================
 */
function applyJobSnapshot(job, modelTitleSnapshot, resolutionSnapshot) {
  const previousMap = new Map(generatedResults.value.map(item => [item.preview_id, item]))
  const incoming = Array.isArray(job?.results) ? job.results : []

  jobTotal.value = Number(job?.total) || batchCount.value
  jobCompleted.value = Number(job?.completed) || incoming.length

  generatedResults.value = incoming.map(item => {
    const prev = previousMap.get(item.preview_id)

    return {
      ...item,
      liked: prev?.liked ?? false,
      savedAssetId: item.saved_asset_id ?? prev?.savedAssetId ?? null,
      modelName: prev?.modelName || modelTitleSnapshot,
      resolutionText: (item.width && item.height)
        ? `${item.width} × ${item.height} px`
        : resolutionSnapshot,
    }
  })

  if (generatedResults.value.length > 0) {
    const stillExists = generatedResults.value.some(item => item.preview_id === selectedResultId.value)
    if (!stillExists)
      selectedResultId.value = generatedResults.value[0].preview_id
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Polling trạng thái job tạo ảnh AI mỗi 1.5s đến khi ready/failed.
 * =====================================================================
 */
async function pollJobStatus(jobId, runSeq, modelTitleSnapshot, resolutionSnapshot, attempt = 0) {
  if (runSeq !== generationSeq)
    return

  try {
    const job = await mediaApi.aiImageJobStatus(jobId)
    if (runSeq !== generationSeq)
      return

    applyJobSnapshot(job, modelTitleSnapshot, resolutionSnapshot)

    if (job.status === 'ready') {
      isGenerating.value = false
      stopPolling()
      actionAlert.value = {
        type: 'success',
        text: `Đã tạo xong ${generatedResults.value.length} ảnh preview.`,
      }

      return
    }

    if (job.status === 'failed') {
      isGenerating.value = false
      stopPolling()
      actionAlert.value = {
        type: 'error',
        text: job.error || 'Quá trình tạo ảnh AI thất bại. Vui lòng thử lại.',
      }

      return
    }

    if (attempt >= MAX_POLL_ATTEMPTS) {
      isGenerating.value = false
      stopPolling()
      actionAlert.value = {
        type: 'error',
        text: 'Hết thời gian chờ AI xử lý. Vui lòng kiểm tra queue worker hoặc thử tạo lại.',
      }

      return
    }

    pollTimer = setTimeout(() => {
      pollJobStatus(jobId, runSeq, modelTitleSnapshot, resolutionSnapshot, attempt + 1)
    }, POLL_INTERVAL_MS)
  } catch (err) {
    if (runSeq !== generationSeq)
      return

    isGenerating.value = false
    stopPolling()
    actionAlert.value = {
      type: 'error',
      text: firstErrorMessage(err) || 'Không thể kiểm tra tiến độ tạo ảnh AI.',
    }
  }
}

/**
 * =====================================================================
 * CHỨC NĂNG: Khởi chạy tiến trình gọi AI tạo bộ ảnh preview (Queue 202 + Polling).
 * =====================================================================
 * INPUT: đọc prompt, description, aspect_ratio, ai_model_id, batchCount.
 * SIDE EFFECT: gọi mediaApi.generateAiImages -> cập nhật state & bắt đầu polling.
 */
async function generateImages() {
  if (isBusy.value)
    return

  actionAlert.value = null

  const cleanPrompt = aiPromptText.value.trim()

  if (!cleanPrompt) {
    promptError.value = 'Vui lòng nhập prompt mô tả hình ảnh cần tạo.'
    await nextTick()
    promptInputRef.value?.$el?.querySelector('textarea')?.focus()

    return
  }

  promptError.value = ''

  if (hasNoAvailableModel.value) {
    actionAlert.value = {
      type: 'warning',
      text: 'Chưa có model AI tạo ảnh nào đang bật trong CoreAi.',
    }

    return
  }

  stopPolling()

  const runSeq = ++generationSeq
  const modelTitleSnapshot = selectedModelTitle.value
  const resolutionSnapshot = currentResolutionText.value

  isGenerating.value = true
  jobTotal.value = batchCount.value
  jobCompleted.value = 0
  generatedResults.value = []
  selectedResultId.value = null

  try {
    const job = await mediaApi.generateAiImages({
      prompt: cleanPrompt,
      description: promptText.value.trim() || null,
      aspect_ratio: selectedRatio.value, // eslint-disable-line camelcase
      ai_model_id: selectedModel.value || null, // eslint-disable-line camelcase
      count: batchCount.value,
    })

    if (runSeq !== generationSeq)
      return

    applyJobSnapshot(job, modelTitleSnapshot, resolutionSnapshot)

    if (job.status === 'ready') {
      isGenerating.value = false
      actionAlert.value = {
        type: 'success',
        text: `Đã tạo xong ${generatedResults.value.length} ảnh preview.`,
      }

      return
    }

    if (job.status === 'failed') {
      isGenerating.value = false
      actionAlert.value = {
        type: 'error',
        text: job.error || 'Quá trình tạo ảnh AI thất bại.',
      }

      return
    }

    pollTimer = setTimeout(() => {
      pollJobStatus(job.job_id, runSeq, modelTitleSnapshot, resolutionSnapshot, 1)
    }, POLL_INTERVAL_MS)
  } catch (err) {
    if (runSeq !== generationSeq)
      return

    isGenerating.value = false
    actionAlert.value = {
      type: 'error',
      text: firstErrorMessage(err) || 'Không thể gửi yêu cầu tạo ảnh AI.',
    }
  }
}

/**
 * Tạo tiêu đề mặc định gọn gàng cho ảnh lưu vào thư viện từ mô tả/prompt.
 */
function buildSaveTitle() {
  const raw = (promptText.value || aiPromptText.value || 'Ảnh AI tạo').replace(/\s+/g, ' ').trim()

  return raw.length > 120 ? raw.slice(0, 120) : raw
}

/**
 * =====================================================================
 * CHỨC NĂNG: Lưu ảnh preview đang chọn thành MediaAsset chính thức.
 * =====================================================================
 * SIDE EFFECT: gọi mediaApi.saveAiImage(), đánh dấu savedAssetId và emit('saved').
 */
async function saveToLibrary() {
  const target = selectedResult.value
  if (!target || isBusy.value)
    return

  if (target.savedAssetId) {
    actionAlert.value = {
      type: 'info',
      text: 'Ảnh này đã được lưu vào thư viện trước đó.',
    }

    return
  }

  isSaving.value = true
  actionAlert.value = null

  try {
    const title = buildSaveTitle()

    const asset = await mediaApi.saveAiImage({
      preview_id: target.preview_id, // eslint-disable-line camelcase
      folder: props.folder || 'root',
      title,
      alt_text: title, // eslint-disable-line camelcase
    })

    target.savedAssetId = asset.id
    actionAlert.value = {
      type: 'success',
      text: `Đã lưu "${asset.title}" vào thư viện Media.`,
    }
    emit('saved', asset)
  } catch (err) {
    actionAlert.value = {
      type: 'error',
      text: firstErrorMessage(err) || 'Lưu ảnh vào thư viện thất bại.',
    }
  } finally {
    isSaving.value = false
  }
}

/**
 * Kích hoạt tải file preview qua signed URL ?download=1 do backend gắn attachment.
 */
function triggerDownload(item) {
  if (!item?.download_url || typeof document === 'undefined')
    return

  const link = document.createElement('a')

  link.href = item.download_url
  link.rel = 'noopener'
  link.download = `ai-image-${item.preview_id.slice(0, 8)}.png`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

function downloadSelected() {
  if (!selectedResult.value || isBusy.value)
    return

  triggerDownload(selectedResult.value)
}

function downloadItem(item) {
  if (!item || isBusy.value)
    return

  selectedResultId.value = item.preview_id
  triggerDownload(item)
}

function closeDialog() {
  if (!isBusy.value)
    isOpen.value = false
}

/**
 * Đặt lại state cục bộ về trạng thái empty state mặc định sau khi hiệu ứng đóng kết thúc.
 */
function resetAfterLeave() {
  generationSeq += 1
  stopPolling()
  clearReferenceImage()
  isReferenceDragging.value = false
  isGenerating.value = false
  isSaving.value = false
  jobTotal.value = 0
  jobCompleted.value = 0
  promptText.value = DEFAULT_DESCRIPTION
  aiPromptText.value = DEFAULT_AI_PROMPT
  promptError.value = ''
  selectedRatio.value = '1:1'
  selectedStyle.value = 'watercolor'
  selectedModel.value = pickDefaultModelId(aiModels.value, defaultModelId.value)
  batchCount.value = 4
  resultViewMode.value = 'grid'
  selectedResultId.value = null
  actionAlert.value = null
  samplePromptIndex.value = 0
  generatedResults.value = []
}

watch(isOpen, open => {
  if (open) {
    loadAiModels()
  } else {
    generationSeq += 1
    stopPolling()
  }
})

onBeforeUnmount(() => {
  generationSeq += 1
  stopPolling()
  revokeReferencePreviewUrl()
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="1320"
    height="min(960px, calc(100dvh - 36px))"
    max-height="calc(100dvh - 36px)"
    :persistent="isBusy"
    @after-leave="resetAfterLeave"
  >
    <AppDialogLayout
      :body-scroll="false"
      height="100%"
      close-label="Đóng hộp thoại tạo ảnh từ văn bản"
      :close-disabled="isBusy"
      @close="closeDialog"
    >
      <template #header>
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
              size="40"
              rounded="lg"
            >
              <VIcon
                icon="tabler-photo"
                size="22"
              />
            </VAvatar>
          </template>
          <VCardTitle>Tạo ảnh từ văn bản</VCardTitle>
          <VCardSubtitle>
            Biến ý tưởng, mô tả của bạn thành hình ảnh theo phong cách mong muốn.
          </VCardSubtitle>
        </VCardItem>
      </template>

      <VRow
        no-gutters
        class="ai-generate-body"
      >
        <!-- Cột trái: Tham số cấu hình tạo ảnh -->
        <VCol
          cols="12"
          md="6"
          class="px-5 py-4 ai-generate-left"
        >
          <div class="d-flex flex-column ga-3">
            <!-- 1. Mô tả hình ảnh -->
            <div>
              <div class="d-flex align-center justify-space-between ga-2 mb-2">
                <VLabel
                  :for="descriptionInputId"
                  class="text-body-2 font-weight-medium text-high-emphasis"
                >
                  Mô tả hình ảnh
                </VLabel>
                <VBtn
                  size="small"
                  variant="tonal"
                  color="primary"
                  prepend-icon="tabler-wand"
                  :disabled="isBusy"
                  @click="applySamplePrompt"
                >
                  Gợi ý prompt
                </VBtn>
              </div>

              <AppTextarea
                :id="descriptionInputId"
                v-model="promptText"
                rows="3"
                maxlength="1000"
                counter="1000"
                persistent-counter
                no-resize
                placeholder="Nhập mô tả chi tiết hình ảnh bạn muốn tạo..."
              />
            </div>

            <!-- 2. Prompt -->
            <AppTextarea
              ref="promptInputRef"
              v-model="aiPromptText"
              label="Prompt *"
              rows="3"
              maxlength="2000"
              counter="2000"
              persistent-counter
              no-resize
              required
              :error-messages="promptError"
              :aria-invalid="Boolean(promptError)"
              placeholder="Nhập hoặc tinh chỉnh prompt chi tiết gửi cho mô hình tạo ảnh..."
              @update:model-value="onPromptUpdate"
            />

            <!-- 3. Ảnh tham chiếu (giữ UI cục bộ) -->
            <div>
              <div class="d-flex align-center justify-space-between ga-2 mb-2">
                <VLabel
                  :for="referenceInputId"
                  class="text-body-2 font-weight-medium text-high-emphasis"
                >
                  Ảnh tham chiếu
                </VLabel>
                <span class="text-caption text-medium-emphasis">
                  Tùy chọn • JPG, PNG, WEBP (tối đa 10 MB)
                </span>
              </div>

              <input
                :id="referenceInputId"
                ref="referenceFileInput"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,.avif,.gif,.bmp"
                class="d-none"
                @change="onReferenceFileChange"
              >

              <div
                v-if="!referenceImage"
                class="ai-ref-dropzone"
                :class="{
                  'ai-ref-dropzone--dragging': isReferenceDragging,
                  'ai-ref-dropzone--disabled': isBusy,
                }"
                @click="onPickReferenceImage"
                @dragenter.prevent="onReferenceDragOver"
                @dragover.prevent="onReferenceDragOver"
                @dragleave="onReferenceDragLeave"
                @drop.prevent="onReferenceDrop"
              >
                <div class="ai-ref-dropzone__content">
                  <VAvatar
                    color="primary"
                    variant="tonal"
                    size="38"
                    rounded="lg"
                    class="flex-shrink-0"
                  >
                    <VIcon
                      icon="tabler-photo-plus"
                      size="20"
                    />
                  </VAvatar>

                  <div class="ai-ref-dropzone__text">
                    <div class="text-body-2 font-weight-medium text-high-emphasis text-truncate">
                      Kéo thả hoặc chọn ảnh tham chiếu
                    </div>
                    <div class="text-caption text-medium-emphasis text-truncate">
                      Dùng làm ảnh mẫu bố cục hoặc phong cách cho AI
                    </div>
                  </div>
                </div>

                <VBtn
                  variant="tonal"
                  color="primary"
                  size="small"
                  prepend-icon="tabler-upload"
                  class="flex-shrink-0"
                  :disabled="isBusy"
                  @click.stop="onPickReferenceImage"
                >
                  Chọn ảnh
                </VBtn>
              </div>

              <div
                v-else
                class="ai-ref-card"
                :class="{ 'ai-ref-card--dragging': isReferenceDragging }"
                @dragenter.prevent="onReferenceDragOver"
                @dragover.prevent="onReferenceDragOver"
                @dragleave="onReferenceDragLeave"
                @drop.prevent="onReferenceDrop"
              >
                <div class="ai-ref-card__thumb">
                  <img
                    :src="referenceImage.previewUrl"
                    :alt="referenceImage.name"
                    class="ai-ref-card__img"
                  >
                </div>

                <div class="ai-ref-card__info">
                  <div class="text-body-2 font-weight-medium text-high-emphasis text-truncate">
                    {{ referenceImage.name }}
                  </div>
                  <div class="text-caption text-medium-emphasis text-truncate">
                    {{ referenceImageMetaText || 'Ảnh tham chiếu đã chọn' }}
                  </div>
                </div>

                <div class="d-flex align-center ga-1 flex-shrink-0">
                  <VBtn
                    variant="tonal"
                    color="primary"
                    size="small"
                    prepend-icon="tabler-replace"
                    :disabled="isBusy"
                    @click="onPickReferenceImage"
                  >
                    Đổi ảnh
                  </VBtn>
                  <VBtn
                    variant="tonal"
                    color="error"
                    size="small"
                    class="ai-ref-card__remove"
                    aria-label="Xóa ảnh tham chiếu"
                    :disabled="isBusy"
                    @click="clearReferenceImage"
                  >
                    <VIcon
                      icon="tabler-trash"
                      size="16"
                    />
                    <VTooltip
                      activator="parent"
                      location="top"
                    >
                      Bỏ ảnh tham chiếu
                    </VTooltip>
                  </VBtn>
                </div>
              </div>

              <div
                v-if="referenceError"
                class="text-caption text-error mt-1"
                role="alert"
              >
                {{ referenceError }}
              </div>
            </div>

            <!-- 4. Tỷ lệ khung hình -->
            <div
              role="group"
              :aria-labelledby="ratioGroupId"
            >
              <span
                :id="ratioGroupId"
                class="d-block text-body-2 font-weight-medium text-high-emphasis mb-2"
              >
                Tỷ lệ khung hình
              </span>
              <div class="ai-ratio-grid">
                <button
                  v-for="ratio in aspectRatios"
                  :key="ratio.value"
                  type="button"
                  class="ai-ratio-card"
                  :class="{ 'ai-ratio-card--selected': selectedRatio === ratio.value }"
                  :aria-pressed="selectedRatio === ratio.value"
                  :disabled="isBusy"
                  @click="selectRatio(ratio.value)"
                >
                  <VAvatar
                    v-if="selectedRatio === ratio.value"
                    color="primary"
                    size="18"
                    class="ai-ratio-card__badge"
                  >
                    <VIcon
                      icon="tabler-check"
                      size="12"
                    />
                  </VAvatar>

                  <div
                    class="ai-ratio-card__preview"
                    aria-hidden="true"
                  >
                    <span
                      class="ai-ratio-card__shape"
                      :style="{ inlineSize: `${ratio.boxWidth}px`, blockSize: `${ratio.boxHeight}px` }"
                    />
                  </div>
                  <span class="text-caption font-weight-semibold text-high-emphasis">
                    {{ ratio.value }}
                  </span>
                  <span class="text-caption text-medium-emphasis">
                    {{ ratio.label }}
                  </span>
                </button>
              </div>
            </div>

            <!-- 5. Phong cách hình ảnh (giữ UI cục bộ) -->
            <div
              role="group"
              :aria-labelledby="styleGroupId"
            >
              <span
                :id="styleGroupId"
                class="d-block text-body-2 font-weight-medium text-high-emphasis mb-2"
              >
                Phong cách hình ảnh
              </span>
              <div class="ai-style-grid">
                <button
                  v-for="styleItem in visualStyles"
                  :key="styleItem.value"
                  type="button"
                  class="ai-style-card"
                  :class="{ 'ai-style-card--selected': selectedStyle === styleItem.value }"
                  :aria-pressed="selectedStyle === styleItem.value"
                  @click="selectStyle(styleItem.value)"
                >
                  <div class="ai-style-card__thumb">
                    <VImg
                      :src="styleItem.image"
                      alt=""
                      height="56"
                      cover
                    >
                      <template #error>
                        <div class="d-flex align-center justify-center h-100 w-100 ai-style-card__fallback">
                          <VIcon
                            icon="tabler-photo"
                            size="20"
                            class="text-disabled"
                          />
                        </div>
                      </template>
                    </VImg>
                    <VAvatar
                      v-if="selectedStyle === styleItem.value"
                      color="primary"
                      size="18"
                      class="ai-style-card__badge"
                    >
                      <VIcon
                        icon="tabler-check"
                        size="12"
                      />
                    </VAvatar>
                  </div>
                  <span class="ai-style-card__title text-caption font-weight-medium text-high-emphasis">
                    {{ styleItem.title }}
                  </span>
                </button>
              </div>
            </div>

            <!-- 6. Model tạo ảnh & Số lượng ảnh tạo -->
            <VRow>
              <VCol
                cols="12"
                sm="7"
              >
                <AppSelect
                  v-model="selectedModel"
                  label="Model tạo ảnh"
                  :items="modelSelectOptions"
                  :loading="modelsLoading"
                  :disabled="isBusy || modelsLoading || hasNoAvailableModel"
                  placeholder="Chọn model AI tạo ảnh"
                  prepend-inner-icon="tabler-cpu"
                />
              </VCol>

              <VCol
                cols="12"
                sm="5"
              >
                <AppSelect
                  v-model="batchCount"
                  label="Số lượng ảnh tạo"
                  :items="batchCountOptions"
                  :disabled="isBusy"
                  prepend-inner-icon="tabler-photo-plus"
                />
              </VCol>
            </VRow>

            <VAlert
              v-if="modelsLoadError"
              variant="tonal"
              color="error"
              role="alert"
            >
              <div class="d-flex flex-wrap align-center justify-space-between ga-2">
                <span>{{ modelsLoadError }}</span>
                <VBtn
                  size="x-small"
                  variant="tonal"
                  color="error"
                  @click="loadAiModels({ force: true })"
                >
                  Thử lại
                </VBtn>
              </div>
            </VAlert>

            <VAlert
              v-else-if="hasNoAvailableModel"
              variant="tonal"
              color="warning"
              role="status"
            >
              Chưa có model AI hỗ trợ tạo ảnh nào được bật trong CoreAi. Vui lòng kích hoạt model trước khi tạo ảnh.
            </VAlert>
          </div>
        </VCol>

        <!-- Cột phải: Kết quả tạo ảnh -->
        <VCol
          cols="12"
          md="6"
          class="ai-generate-right"
        >
          <div class="ai-generate-right__header pa-5 pb-4">
            <div class="d-flex flex-wrap align-center justify-space-between ga-3">
              <div>
                <h3 class="text-subtitle-1 font-weight-semibold text-high-emphasis mb-0">
                  Kết quả tạo ảnh
                </h3>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{ resultHeaderSubtitle }}
                </p>
              </div>

              <div class="d-flex flex-wrap align-center ga-2">
                <div
                  class="ai-view-toggle"
                  role="group"
                  aria-label="Chế độ xem kết quả"
                >
                  <VBtn
                    size="small"
                    class="ai-view-toggle__btn"
                    aria-label="Xem dạng lưới"
                    :aria-pressed="resultViewMode === 'grid'"
                    :variant="resultViewMode === 'grid' ? 'flat' : 'text'"
                    :color="resultViewMode === 'grid' ? 'primary' : 'secondary'"
                    @click="setResultViewMode('grid')"
                  >
                    <VIcon
                      icon="tabler-layout-grid"
                      size="18"
                    />
                    <VTooltip
                      activator="parent"
                      location="top"
                    >
                      Dạng lưới
                    </VTooltip>
                  </VBtn>
                  <VBtn
                    size="small"
                    class="ai-view-toggle__btn"
                    aria-label="Xem dạng danh sách"
                    :aria-pressed="resultViewMode === 'list'"
                    :variant="resultViewMode === 'list' ? 'flat' : 'text'"
                    :color="resultViewMode === 'list' ? 'primary' : 'secondary'"
                    @click="setResultViewMode('list')"
                  >
                    <VIcon
                      icon="tabler-list"
                      size="18"
                    />
                    <VTooltip
                      activator="parent"
                      location="top"
                    >
                      Dạng danh sách
                    </VTooltip>
                  </VBtn>
                </div>

                <VBtn
                  variant="tonal"
                  color="secondary"
                  size="small"
                  class="ai-toolbar-icon-btn"
                  aria-label="Tải về ảnh đang chọn"
                  :disabled="isBusy || !selectedResult"
                  @click="downloadSelected"
                >
                  <VIcon
                    icon="tabler-download"
                    size="18"
                  />
                  <VTooltip
                    activator="parent"
                    location="top"
                  >
                    Tải về ảnh đang chọn
                  </VTooltip>
                </VBtn>

                <VBtn
                  color="primary"
                  variant="flat"
                  size="small"
                  :prepend-icon="generatedResults.length === 0 && !isGenerating ? 'tabler-sparkles' : 'tabler-refresh'"
                  class="ai-toolbar-action-btn"
                  :loading="isGenerating"
                  :disabled="isSaving || hasNoAvailableModel || modelsLoading"
                  @click="generateImages"
                >
                  {{ generatedResults.length === 0 && !isGenerating ? 'Tạo ảnh' : 'Tạo lại' }}
                </VBtn>
              </div>
            </div>

            <VAlert
              v-if="actionAlert"
              variant="tonal"
              :color="actionAlert.type"
              :role="actionAlert.type === 'error' ? 'alert' : 'status'"
              aria-live="polite"
              closable
              class="mt-4 mb-0"
              @click:close="clearActionAlert"
            >
              {{ actionAlert.text }}
            </VAlert>
          </div>

          <VDivider class="flex-shrink-0" />

          <div class="ai-generate-right__scroll pa-5">
            <!-- Phương án 1: Empty state kèm nút Tạo ảnh khi chưa tạo lần nào -->
            <div
              v-if="!isGenerating && generatedResults.length === 0"
              class="ai-empty-state"
            >
              <VAvatar
                color="primary"
                variant="tonal"
                size="56"
                rounded="xl"
                class="mb-4"
              >
                <VIcon
                  icon="tabler-sparkles"
                  size="28"
                />
              </VAvatar>

              <h4 class="text-subtitle-1 font-weight-semibold text-high-emphasis mb-1">
                Chưa có ảnh nào được tạo
              </h4>
              <p class="text-body-2 text-medium-emphasis mb-5 ai-empty-state__desc">
                Nhập Prompt ở cột trái, chọn tỷ lệ khung hình, model tạo ảnh và số lượng mong muốn rồi bấm nút bên dưới để AI bắt đầu vẽ.
              </p>

              <VBtn
                color="primary"
                variant="flat"
                prepend-icon="tabler-sparkles"
                :disabled="hasNoAvailableModel || modelsLoading || isSaving"
                @click="generateImages"
              >
                Tạo ảnh
              </VBtn>
            </div>

            <!-- Danh sách ảnh thật + khung chờ khi đang sinh nhiều ảnh -->
            <div
              v-else
              :class="resultViewMode === 'grid' ? 'ai-results-grid' : 'ai-results-list'"
            >
              <div
                v-for="(item, index) in generatedResults"
                :key="item.preview_id"
                class="ai-result-card"
                :class="[
                  resultViewMode === 'list' ? 'ai-result-card--list' : 'ai-result-card--grid',
                  { 'ai-result-card--selected': selectedResultId === item.preview_id },
                ]"
              >
                <button
                  type="button"
                  class="ai-result-card__trigger"
                  :aria-label="`Chọn phương án ảnh ${index + 1}`"
                  :aria-pressed="selectedResultId === item.preview_id"
                  @click="selectResult(item.preview_id)"
                >
                  <div class="ai-result-card__canvas">
                    <VAvatar
                      v-if="selectedResultId === item.preview_id"
                      color="primary"
                      size="22"
                      class="ai-result-card__check"
                    >
                      <VIcon
                        icon="tabler-check"
                        size="13"
                      />
                    </VAvatar>

                    <VChip
                      v-if="item.savedAssetId"
                      color="success"
                      size="x-small"
                      variant="flat"
                      prepend-icon="tabler-check"
                      class="ai-result-card__saved-badge"
                    >
                      Đã lưu
                    </VChip>

                    <img
                      :src="item.preview_url"
                      :alt="`Ảnh AI phương án ${index + 1}`"
                      decoding="async"
                      class="ai-result-card__img"
                    >
                  </div>

                  <div
                    v-if="resultViewMode === 'list'"
                    class="ai-result-card__meta"
                  >
                    <div class="d-flex flex-wrap align-center ga-2 mb-1">
                      <span class="text-body-2 font-weight-semibold text-high-emphasis">
                        Phương án #{{ index + 1 }}
                      </span>
                      <VChip
                        v-if="selectedResultId === item.preview_id"
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        prepend-icon="tabler-check"
                      >
                        Đang chọn
                      </VChip>
                      <VChip
                        v-if="item.savedAssetId"
                        size="x-small"
                        color="success"
                        variant="tonal"
                        prepend-icon="tabler-folder-check"
                      >
                        Đã lưu
                      </VChip>
                    </div>
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ item.resolutionText }} • {{ item.modelName }}
                    </div>
                    <div class="text-caption text-medium-emphasis text-truncate">
                      {{ aiPromptText }}
                    </div>
                  </div>
                </button>

                <div class="ai-result-card__actions">
                  <VBtn
                    size="x-small"
                    variant="flat"
                    class="ai-result-action-btn"
                    :color="item.liked ? 'error' : 'surface'"
                    :aria-label="item.liked ? `Bỏ thích phương án ${index + 1}` : `Yêu thích phương án ${index + 1}`"
                    :aria-pressed="item.liked"
                    @click="toggleLike(item)"
                  >
                    <VIcon
                      :icon="item.liked ? 'tabler-heart-filled' : 'tabler-heart'"
                      size="16"
                    />
                    <VTooltip
                      activator="parent"
                      location="top"
                    >
                      {{ item.liked ? 'Bỏ yêu thích' : 'Yêu thích' }}
                    </VTooltip>
                  </VBtn>

                  <VBtn
                    size="x-small"
                    variant="flat"
                    color="surface"
                    class="ai-result-action-btn"
                    :aria-label="`Tải xuống phương án ${index + 1}`"
                    :disabled="isBusy"
                    @click="downloadItem(item)"
                  >
                    <VIcon
                      icon="tabler-download"
                      size="16"
                    />
                    <VTooltip
                      activator="parent"
                      location="top"
                    >
                      Tải xuống
                    </VTooltip>
                  </VBtn>
                </div>
              </div>

              <!-- Placeholder từng ô đang đợi AI sinh xong trong batch -->
              <div
                v-for="slot in pendingSlotsCount"
                :key="`pending-${slot}`"
                class="ai-result-card ai-result-card--pending"
                :class="resultViewMode === 'list' ? 'ai-result-card--list' : 'ai-result-card--grid'"
                aria-busy="true"
              >
                <div class="ai-result-card__canvas ai-result-card__canvas--pending">
                  <div class="d-flex flex-column align-center justify-center h-100 ga-2">
                    <VProgressCircular
                      indeterminate
                      color="primary"
                      size="28"
                      width="3"
                    />
                    <span
                      v-if="resultViewMode === 'grid'"
                      class="text-caption text-medium-emphasis"
                    >
                      Đang tạo ảnh...
                    </span>
                  </div>
                </div>
                <div
                  v-if="resultViewMode === 'list'"
                  class="ai-result-card__meta"
                >
                  <div class="text-body-2 font-weight-medium text-medium-emphasis">
                    Đang xử lý ảnh AI...
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ selectedModelTitle }} • {{ currentResolutionText }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </VCol>
      </VRow>

      <template #footer>
        <VCardActions class="justify-end">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isBusy"
            @click="closeDialog"
          >
            Đóng
          </VBtn>
          <VBtn
            variant="tonal"
            color="primary"
            prepend-icon="tabler-folder"
            :loading="isSaving"
            :disabled="isGenerating || !selectedResult || Boolean(selectedResult?.savedAssetId)"
            @click="saveToLibrary"
          >
            {{ selectedResult?.savedAssetId ? 'Đã lưu vào thư viện' : 'Lưu vào thư viện' }}
          </VBtn>
          <VBtn
            variant="flat"
            color="primary"
            prepend-icon="tabler-download"
            :disabled="isBusy || !selectedResult"
            @click="downloadSelected"
          >
            Tải về ảnh đã chọn
          </VBtn>
        </VCardActions>
      </template>
    </AppDialogLayout>
  </VDialog>
</template>

<style scoped lang="scss">
.ai-generate-body {
  align-content: flex-start;
  block-size: 100%;
  min-block-size: 0;
  overflow-y: auto;
  overscroll-behavior: contain;

  @media (min-width: 960px) {
    flex-wrap: nowrap;
    overflow: hidden;
  }
}

.ai-generate-left {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  min-inline-size: 0;

  @media (min-width: 960px) {
    align-self: stretch;
    block-size: 100%;
    border-block-end: none;
    border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    min-block-size: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
    scrollbar-width: thin;
  }
}

.ai-generate-right {
  display: flex;
  flex-direction: column;
  background-color: rgba(var(--v-theme-on-surface), 0.02);
  min-inline-size: 0;

  @media (min-width: 960px) {
    align-self: stretch;
    block-size: 100%;
    min-block-size: 0;
    overflow: hidden;
  }
}

.ai-generate-right__header {
  flex: 0 0 auto;
}

.ai-generate-right__scroll {
  display: flex;
  flex-direction: column;
  max-block-size: 420px;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
  scrollbar-width: thin;

  @media (min-width: 960px) {
    flex: 1 1 0%;
    max-block-size: none;
    min-block-size: 0;
  }
}

.ai-empty-state {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px dashed rgba(var(--v-border-color), calc(var(--v-border-opacity) + 0.1));
  border-radius: 12px;
  background-color: rgba(var(--v-theme-surface), 0.72);
  min-block-size: 300px;
  padding: 32px 24px;
  text-align: center;
}

.ai-empty-state__desc {
  max-inline-size: 380px;
}

.ai-ratio-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fit, minmax(84px, 1fr));

  @media (min-width: 600px) {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}

.ai-ref-dropzone {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border: 1px dashed rgba(var(--v-border-color), calc(var(--v-border-opacity) + 0.12));
  border-radius: 8px;
  background-color: rgba(var(--v-theme-on-surface), 0.02);
  cursor: pointer;
  padding: 10px 12px;
  transition: border-color 0.18s ease, background-color 0.18s ease;

  &:hover:not(.ai-ref-dropzone--disabled),
  &--dragging {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgba(var(--v-theme-primary), 0.06);
  }

  &--disabled {
    cursor: not-allowed;
    opacity: 0.6;
  }
}

.ai-ref-dropzone__content {
  display: flex;
  flex: 1 1 0%;
  align-items: center;
  gap: 12px;
  min-inline-size: 0;
}

.ai-ref-dropzone__text {
  flex: 1 1 auto;
  min-inline-size: 0;
}

.ai-ref-card {
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background-color: rgb(var(--v-theme-surface));
  padding: 8px 10px;
  transition: border-color 0.18s ease, background-color 0.18s ease;

  &--dragging {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgba(var(--v-theme-primary), 0.06);
  }
}

.ai-ref-card__thumb {
  flex: 0 0 auto;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  background-color: rgba(var(--v-theme-on-surface), 0.04);
  block-size: 46px;
  inline-size: 46px;
}

.ai-ref-card__img {
  display: block;
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.ai-ref-card__info {
  flex: 1 1 auto;
  min-inline-size: 0;
}

.ai-ref-card__remove {
  border-radius: 6px;
  block-size: 30px;
  inline-size: 32px;
  min-inline-size: 32px;
  padding: 0;
}

.ai-ratio-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background-color: rgb(var(--v-theme-surface));
  cursor: pointer;
  padding-block: 10px;
  padding-inline: 6px;
  text-align: center;
  transition: border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;

  &:hover:not(:disabled) {
    border-color: rgba(var(--v-theme-primary), 0.5);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  &:disabled {
    cursor: not-allowed;
    opacity: 0.65;
  }

  &--selected {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgba(var(--v-theme-primary), 0.08);
    box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
  }
}

.ai-ratio-card__badge {
  position: absolute;
  inset-block-start: -7px;
  inset-inline-end: -7px;
}

.ai-ratio-card__preview {
  display: flex;
  align-items: center;
  justify-content: center;
  block-size: 22px;
  margin-block-end: 6px;
}

.ai-ratio-card__shape {
  border: 1.5px solid rgba(var(--v-theme-on-surface), 0.45);
  border-radius: 3px;
  transition: border-color 0.18s ease, background-color 0.18s ease;
}

.ai-ratio-card--selected .ai-ratio-card__shape {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.14);
}

.ai-style-grid {
  display: grid;
  gap: 8px;
  grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));

  @media (min-width: 600px) {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}

.ai-style-card {
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background-color: rgb(var(--v-theme-surface));
  cursor: pointer;
  overflow: hidden;
  padding: 0;
  text-align: center;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }

  &--selected {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
  }
}

.ai-style-card__thumb {
  position: relative;
  inline-size: 100%;
}

.ai-style-card__fallback {
  background-color: rgba(var(--v-theme-on-surface), 0.06);
}

.ai-style-card__badge {
  position: absolute;
  inset-block-start: 4px;
  inset-inline-end: 4px;
}

.ai-style-card__title {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  padding: 6px;
}

.ai-view-toggle {
  display: inline-flex;
  align-items: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  overflow: hidden;
}

.ai-view-toggle__btn {
  border-radius: 0;
  block-size: 32px;
  inline-size: 34px;
  min-inline-size: 34px;
  padding: 0;
}

.ai-toolbar-icon-btn {
  border-radius: 6px;
  block-size: 32px;
  inline-size: 34px;
  min-inline-size: 34px;
  padding: 0;
}

.ai-toolbar-action-btn {
  border-radius: 6px;
  block-size: 32px;
}

.ai-results-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: minmax(0, 1fr);

  @media (min-width: 600px) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.ai-results-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ai-result-card {
  position: relative;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background-color: rgb(var(--v-theme-surface));
  overflow: hidden;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;

  &:hover:not(.ai-result-card--pending) {
    border-color: rgba(var(--v-theme-primary), 0.5);
  }

  &--selected {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
  }

  &--list {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
  }

  &--pending {
    border-style: dashed;
  }
}

.ai-result-card__trigger {
  position: relative;
  display: block;
  border: 0;
  background: transparent;
  cursor: pointer;
  inline-size: 100%;
  padding: 0;

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
}

.ai-result-card--list .ai-result-card__trigger {
  display: flex;
  flex: 1 1 auto;
  align-items: center;
  gap: 14px;
  min-inline-size: 0;
}

.ai-result-card__canvas {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1 / 1;
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  overflow: hidden;
  padding: 8px;
}

.ai-result-card--list .ai-result-card__canvas {
  flex: 0 0 auto;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  block-size: 84px;
  inline-size: 84px;
  padding: 4px;
}

.ai-result-card__img {
  display: block;
  border-radius: 6px;
  block-size: 100%;
  inline-size: 100%;
  object-fit: contain;
}

.ai-result-card--list .ai-result-card__img {
  object-fit: cover;
}

.ai-result-card__meta {
  flex: 1 1 auto;
  min-inline-size: 0;
  text-align: start;
}

.ai-result-card__check {
  position: absolute;
  z-index: 2;
  inset-block-start: 10px;
  inset-inline-start: 10px;
}

.ai-result-card--list .ai-result-card__check {
  inset-block-start: 4px;
  inset-inline-start: 4px;
}

.ai-result-card__saved-badge {
  position: absolute;
  z-index: 2;
  inset-block-end: 10px;
  inset-inline-start: 10px;
}

.ai-result-card--list .ai-result-card__saved-badge {
  display: none;
}

.ai-result-card__actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.ai-result-card--grid .ai-result-card__actions {
  position: absolute;
  z-index: 2;
  inset-block-start: 8px;
  inset-inline-end: 8px;
}

.ai-result-card--list .ai-result-card__actions {
  flex: 0 0 auto;
}

.ai-result-action-btn {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  block-size: 30px;
  box-shadow: 0 2px 6px rgba(var(--v-shadow-key-umbra-color), 0.08);
  inline-size: 30px;
  min-inline-size: 30px;
  padding: 0;
}
</style>
