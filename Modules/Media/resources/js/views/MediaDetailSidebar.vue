<!--
  =====================================================================
  CHỨC NĂNG FILE: Panel chi tiết media (CỘT PHẢI: col-md-2) nối API MediaAsset.
  =====================================================================

  Chuẩn hóa theo `docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md`:
  - Tách UI chi tiết vào `views/` (pages ghép layout 3 cột 2/8/2).
  - Giữ cố định phần đầu (Header Xem trước + Preview + Tabs); chỉ vùng
  nội dung bên trong tab (`VWindow`) cuộn qua `PerfectScrollbar`.
  - Kết nối dữ liệu động từ MediaAssetResource (`title`, `url`/`/stream`,
  `folder`, `visibility`, `alt_text`, `kind`, `mime`, `size`, `width`/`height`,
  `creator`, `channel_group`, `usages_count`, `updated_at`, `ai_generation`)
  kèm lưu cập nhật (`PUT /assets/{id}`), tải xuống (`/stream?download=1`)
  và xóa mềm (`DELETE /assets/{id}`).
  - Các trường chưa có trên API (`Mô tả`, `Nhãn/Tags`, hoặc tham số Prompt khi
  asset chưa có `ai_generation`) vẫn giữ ở chế độ dữ liệu tĩnh mặc định.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - hydrateFromAsset(asset) : Nạp metadata động + fallback tĩnh cho field thiếu.
  - loadAssetDetail(id)     : Gọi GET /assets/{id} làm mới chi tiết asset.
  - refreshPreviewStream()  : Tải blob qua Bearer auth cho asset private (/stream).
  - saveMetadata()          : Gửi PUT /assets/{id} cập nhật title/folder/visibility/alt_text.
  - downloadCurrentAsset()  : Tải tệp qua /stream?download=1 (hoặc URL public fallback).
  - deleteCurrentAsset()    : Xóa mềm asset qua store.deleteAsset() & báo trang cha.
  - resetPromptForm()       : Khôi phục tab Prompt về dữ liệu AI/mặc định của asset.
  - copyFieldValue()        : Sao chép nhanh giá trị vào clipboard.
  - addTag() / removeTag()  : Thêm/xóa nhãn tĩnh cục bộ (chưa có cột API).
  - syncTabScroll()         : Gắn ResizeObserver đồng bộ PerfectScrollbar trong tab.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - PROPS : asset (MediaAsset|null), canPrev (Boolean), canNext (Boolean), trashed (Boolean).
  - EMITS : close, prev, next, updated(asset), deleted(id).
  =====================================================================
-->
<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { $api, fieldErrors, firstErrorMessage, isValidationError } from '@/utils/api'
import { mediaApi } from '../services/mediaApi'
import { useMediaAssetStore } from '../stores/mediaAsset'
import { deriveAspectRatio, extOf, fmtDateTime, fmtDuration, fmtSize, previewOf } from '../utils/mediaFormat'

const props = defineProps({
  asset: {
    type: Object,
    default: null,
  },
  canPrev: {
    type: Boolean,
    default: false,
  },
  canNext: {
    type: Boolean,
    default: false,
  },
  trashed: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'prev', 'next', 'updated', 'deleted'])

const store = useMediaAssetStore()

/* Dữ liệu tĩnh giữ lại cho những field API/DB chưa hỗ trợ. */
const STATIC_INFO_DEFAULTS = {
  description: 'Ảnh sản phẩm chả ram tôm Xứ Nẫu, trình bày trên mẹt tre, kèm rau sống và nước chấm, chụp thực tế.',
  tags: ['sản phẩm', 'chả ram', 'tôm', 'đặc sản', 'bình định'],
}

const STATIC_PROMPT_DEFAULTS = {
  aiModel: 'Leonardo AI',
  stylePreset: 'Ảnh chụp thực tế',
  prompt: 'Vietnamese crispy shrimp spring rolls served on a woven bamboo tray with fresh herbs and dipping sauce, warm studio lighting, editorial food photography, 8k, shallow depth of field.',
  negativePrompt: 'blurry, oversaturated, plastic texture, watermark, low resolution',
  aspectRatio: '3:2',
  steps: '30',
  cfgScale: '7.5',
  seed: '48291044',
  sampler: 'DPM++ 2M Karras',
  qualityTags: ['studio lighting', '8k uhd', 'editorial food', 'shallow dof'],
  provider: 'Leonardo Phoenix v2',
  dimensions: '1920 × 1280',
  generatedDuration: '4.2 giây',
  generatedAt: '28/10/2026 14:30',
}

const AI_MODEL_OPTIONS = [
  'Leonardo AI',
  'Flux.1',
  'Midjourney',
  'SDXL',
  'DALL-E 3',
]

const STYLE_PRESET_OPTIONS = [
  'Ảnh chụp thực tế',
  'Thương mại cao cấp',
  'Tối giản hiện đại',
  'Cinematic Ấm',
]

const ASPECT_RATIO_OPTIONS = [
  '1:1',
  '3:2',
  '4:5',
  '16:9',
  '9:16',
]

const VISIBILITY_OPTIONS = [
  { title: 'Công khai (Public)', value: 'public' },
  { title: 'Riêng tư (Private)', value: 'private' },
]

const KIND_LABELS = {
  image: 'Hình ảnh',
  video: 'Video',
  audio: 'Âm thanh',
  document: 'Tài liệu',
  archive: 'File nén',
}

const KIND_ICONS = {
  image: 'tabler-photo-off',
  video: 'tabler-video',
  audio: 'tabler-music',
  document: 'tabler-file-text',
  archive: 'tabler-archive',
}

const liveAsset = ref(null)
const previewTab = ref('info')
const copiedKey = ref('')
const isAddingTag = ref(false)
const newTagText = ref('')
const saving = ref(false)
const deleting = ref(false)
const downloading = ref(false)
const previewFailed = ref(false)
const privateObjectUrl = ref('')
const folderSearch = ref('root')
const titleInputRef = ref(null)
const tabScrollRef = ref(null)

let tabScrollObserver = null
let detailSeq = 0
let streamSeq = 0

const snack = reactive({
  show: false,
  color: 'success',
  text: '',
})

const formErrors = reactive({
  title: '',
  folder: '',
  visibility: '',
  altText: '',
})

const detailForm = reactive({
  title: '',
  folder: 'root',
  visibility: 'public',
  altText: '',
  description: STATIC_INFO_DEFAULTS.description,
  tags: [...STATIC_INFO_DEFAULTS.tags],
  aiModel: STATIC_PROMPT_DEFAULTS.aiModel,
})

const promptForm = reactive({
  stylePreset: STATIC_PROMPT_DEFAULTS.stylePreset,
  prompt: STATIC_PROMPT_DEFAULTS.prompt,
  negativePrompt: STATIC_PROMPT_DEFAULTS.negativePrompt,
  aspectRatio: STATIC_PROMPT_DEFAULTS.aspectRatio,
  steps: STATIC_PROMPT_DEFAULTS.steps,
  cfgScale: STATIC_PROMPT_DEFAULTS.cfgScale,
  seed: STATIC_PROMPT_DEFAULTS.seed,
  qualityTags: [...STATIC_PROMPT_DEFAULTS.qualityTags],
})

const aiMeta = computed(() => {
  const raw = liveAsset.value?.ai_generation

  return raw && typeof raw === 'object' ? raw : null
})

const hasAiMetadata = computed(() => Boolean(
  aiMeta.value && (
    aiMeta.value.prompt
    || aiMeta.value.prompt_used
    || aiMeta.value.model
    || aiMeta.value.ai_model
    || aiMeta.value.source
  ),
))

const folderSuggestions = computed(() => {
  const names = (store.folders || []).map(f => f.name).filter(Boolean)
  if (detailForm.folder && !names.includes(detailForm.folder))
    names.unshift(detailForm.folder)

  return names.length ? names : ['root']
})

const modelOptions = computed(() => {
  const set = new Set(AI_MODEL_OPTIONS)

  if (detailForm.aiModel)
    set.add(detailForm.aiModel)

  return [...set]
})

const stylePresetOptions = computed(() => {
  const set = new Set(STYLE_PRESET_OPTIONS)

  if (promptForm.stylePreset)
    set.add(promptForm.stylePreset)

  return [...set]
})

const aspectRatioOptions = computed(() => {
  const set = new Set(ASPECT_RATIO_OPTIONS)

  if (promptForm.aspectRatio)
    set.add(promptForm.aspectRatio)

  return [...set]
})

const resolvedPreviewUrl = computed(() => {
  const asset = liveAsset.value
  if (!asset)
    return null

  if (asset.visibility === 'private')
    return privateObjectUrl.value || null

  if (asset.kind === 'image')
    return previewOf(asset)

  return asset.url || null
})

const resolvedPathText = computed(() => {
  const asset = liveAsset.value
  if (!asset)
    return ''

  return asset.url || mediaApi.streamUrl(asset.id)
})

const shareLink = computed(() => {
  const asset = liveAsset.value
  if (!asset)
    return ''

  if (asset.url)
    return asset.url

  const streamPath = mediaApi.streamUrl(asset.id)
  if (typeof window === 'undefined')
    return streamPath

  return `${window.location.origin}${streamPath}`
})

const normalizedFolder = computed(() => String(folderSearch.value ?? '').trim() || 'root')

const isMetadataDirty = computed(() => {
  const asset = liveAsset.value
  if (!asset)
    return false

  return (
    detailForm.title.trim() !== (asset.title || '').trim()
    || (detailForm.altText || '') !== (asset.alt_text || '')
    || normalizedFolder.value !== (asset.folder || 'root')
    || detailForm.visibility !== (asset.visibility || 'public')
  )
})

const dimensionsText = computed(() => {
  const asset = liveAsset.value
  if (!asset)
    return '—'

  if (asset.width && asset.height)
    return `${asset.width} × ${asset.height}`

  const dur = fmtDuration(asset.duration_ms)

  return dur || '—'
})

const sizeDetailText = computed(() => {
  const asset = liveAsset.value
  if (!asset || asset.size === null || asset.size === undefined)
    return '—'

  return `${fmtSize(asset.size)} (${Number(asset.size).toLocaleString('vi-VN')} bytes)`
})

const promptInfo = computed(() => {
  const ai = aiMeta.value
  const asset = liveAsset.value

  const durationText = ai?.generated_duration
    || (ai?.duration_ms ? `${(Number(ai.duration_ms) / 1000).toFixed(1)} giây` : STATIC_PROMPT_DEFAULTS.generatedDuration)

  const dimText = (asset?.width && asset?.height)
    ? `${asset.width} × ${asset.height}`
    : STATIC_PROMPT_DEFAULTS.dimensions

  return {
    provider: ai?.provider || ai?.source || STATIC_PROMPT_DEFAULTS.provider,
    sampler: ai?.sampler || STATIC_PROMPT_DEFAULTS.sampler,
    dimensions: dimText,
    generatedDuration: durationText,
    generatedAt: fmtDateTime(ai?.generated_at || asset?.created_at) || STATIC_PROMPT_DEFAULTS.generatedAt,
  }
})

/*
 * CHỨC NĂNG: Hiển thị thông báo snackbar góc trên phải màn hình.
 */
function notify(text, color = 'success') {
  snack.text = text
  snack.color = color
  snack.show = true
}

/*
 * CHỨC NĂNG: Xóa toàn bộ thông báo lỗi validate đang gắn trên form.
 */
function clearFormErrors() {
  formErrors.title = ''
  formErrors.folder = ''
  formErrors.visibility = ''
  formErrors.altText = ''
}

/*
 * CHỨC NĂNG: Giải phóng Object URL tạm của file private để tránh rò rỉ bộ nhớ.
 */
function revokePrivateUrl() {
  if (!privateObjectUrl.value)
    return

  URL.revokeObjectURL(privateObjectUrl.value)
  privateObjectUrl.value = ''
}

/*
 * CHỨC NĂNG: Gắn ResizeObserver vào vùng cuộn bên trong tab để thanh cuộn
 * tự cập nhật khi chuyển tab, đổi asset hoặc mở rộng nội dung textarea.
 */
function syncTabScroll() {
  const el = tabScrollRef.value?.$el
  if (!el)
    return

  if (typeof ResizeObserver !== 'undefined') {
    if (!tabScrollObserver) {
      tabScrollObserver = new ResizeObserver(() => {
        tabScrollRef.value?.ps?.update()
      })
    } else {
      tabScrollObserver.disconnect()
    }

    tabScrollObserver.observe(el)
    for (const child of el.children) {
      if (!child.classList.contains('ps__rail-x') && !child.classList.contains('ps__rail-y'))
        tabScrollObserver.observe(child)
    }
  }

  tabScrollRef.value?.ps?.update()
}

/*
 * CHỨC NĂNG: Nạp thông số AI từ asset.ai_generation (fallback dữ liệu tĩnh nếu thiếu).
 */
function resetPromptForm() {
  const ai = aiMeta.value
  const asset = liveAsset.value

  detailForm.aiModel = ai?.model || ai?.ai_model || STATIC_PROMPT_DEFAULTS.aiModel
  promptForm.stylePreset = ai?.style_preset || ai?.stylePreset || ai?.style_name || ai?.purpose || STATIC_PROMPT_DEFAULTS.stylePreset
  promptForm.prompt = ai?.prompt || ai?.prompt_used || STATIC_PROMPT_DEFAULTS.prompt
  promptForm.negativePrompt = ai?.negative_prompt || ai?.negativePrompt || STATIC_PROMPT_DEFAULTS.negativePrompt
  promptForm.aspectRatio = ai?.aspect_ratio || ai?.aspectRatio || deriveAspectRatio(asset?.width, asset?.height) || STATIC_PROMPT_DEFAULTS.aspectRatio
  promptForm.steps = ai?.steps !== undefined && ai?.steps !== null ? String(ai.steps) : STATIC_PROMPT_DEFAULTS.steps
  promptForm.cfgScale = (ai?.cfg_scale ?? ai?.cfgScale) !== undefined && (ai?.cfg_scale ?? ai?.cfgScale) !== null
    ? String(ai?.cfg_scale ?? ai?.cfgScale)
    : STATIC_PROMPT_DEFAULTS.cfgScale
  promptForm.seed = ai?.seed !== undefined && ai?.seed !== null ? String(ai.seed) : STATIC_PROMPT_DEFAULTS.seed
  promptForm.qualityTags = Array.isArray(ai?.quality_tags) && ai.quality_tags.length
    ? [...ai.quality_tags]
    : [...STATIC_PROMPT_DEFAULTS.qualityTags]
}

/*
 * CHỨC NĂNG: Đổ dữ liệu MediaAsset vào form (field nào API không có giữ nguyên tĩnh).
 */
function hydrateFromAsset(asset) {
  liveAsset.value = asset || null
  previewFailed.value = false
  clearFormErrors()

  if (!asset) {
    detailForm.title = ''
    detailForm.folder = 'root'
    folderSearch.value = 'root'
    detailForm.visibility = 'public'
    detailForm.altText = ''

    return
  }

  detailForm.title = asset.title || ''
  detailForm.folder = asset.folder || 'root'
  folderSearch.value = detailForm.folder
  detailForm.visibility = asset.visibility || 'public'
  detailForm.altText = asset.alt_text || ''
  isAddingTag.value = false
  newTagText.value = ''
  resetPromptForm()
}

/*
 * CHỨC NĂNG: Nạp blob qua endpoint /stream có Bearer auth cho asset private.
 */
async function refreshPreviewStream(asset) {
  streamSeq += 1

  const seq = streamSeq

  revokePrivateUrl()

  if (!asset || props.trashed || asset.visibility !== 'private')
    return

  if (!['image', 'video', 'audio'].includes(asset.kind))
    return

  try {
    const blob = await $api(`/v1/admin/media/assets/${asset.id}/stream`, {
      responseType: 'blob',
    })

    const nextUrl = URL.createObjectURL(blob)

    if (seq !== streamSeq) {
      URL.revokeObjectURL(nextUrl)

      return
    }

    privateObjectUrl.value = nextUrl
  } catch {
    if (seq === streamSeq)
      previewFailed.value = true
  }
}

/*
 * CHỨC NĂNG: Gọi GET /assets/{id} để đồng bộ metadata chi tiết mới nhất từ server.
 */
async function loadAssetDetail(id) {
  detailSeq += 1

  const seq = detailSeq

  try {
    const detail = await mediaApi.asset(id)
    if (seq !== detailSeq || !detail || props.asset?.id !== id)
      return

    const needStreamReload = detail.visibility === 'private'
      && detail.visibility !== props.asset?.visibility

    if (!isMetadataDirty.value)
      hydrateFromAsset(detail)
    else
      liveAsset.value = detail

    if (needStreamReload)
      refreshPreviewStream(detail)
  } catch {
    // Giữ dữ liệu bản tóm tắt từ grid nếu gọi chi tiết gặp lỗi mạng.
  }
}

/*
 * CHỨC NĂNG: Gửi PUT /assets/{id} lưu metadata đã sửa (title/folder/visibility/alt_text).
 */
async function saveMetadata() {
  if (!liveAsset.value || props.trashed || !isMetadataDirty.value)
    return

  if (!detailForm.title.trim()) {
    formErrors.title = 'Tên tệp không được để trống.'

    return
  }

  clearFormErrors()
  saving.value = true
  try {
    const payload = {
      title: detailForm.title.trim(),
      folder: normalizedFolder.value,
      visibility: detailForm.visibility,
    }

    payload['alt_text'] = detailForm.altText?.trim() || null

    const updated = await store.updateAsset(liveAsset.value.id, payload)

    hydrateFromAsset(updated)
    emit('updated', updated)
    notify('Đã lưu thông tin tệp thành công')
  } catch (err) {
    if (isValidationError(err)) {
      const errs = fieldErrors(err)

      formErrors.title = errs.title || ''
      formErrors.folder = errs.folder || ''
      formErrors.visibility = errs.visibility || ''
      formErrors.altText = errs.alt_text || errs.altText || ''
    }

    notify(firstErrorMessage(err), 'error')
  } finally {
    saving.value = false
  }
}

/*
 * CHỨC NĂNG: Tải tệp về máy qua endpoint stream (?download=1) hoặc URL public fallback.
 */
async function downloadCurrentAsset() {
  const asset = liveAsset.value
  if (!asset || props.trashed)
    return

  downloading.value = true
  try {
    const blob = await $api(`/v1/admin/media/assets/${asset.id}/stream`, {
      params: { download: 1 },
      responseType: 'blob',
    })

    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')

    link.href = objectUrl
    link.download = asset.title || `media-${asset.id}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(objectUrl), 1000)
  } catch (err) {
    if (asset.visibility === 'public' && asset.url)
      window.open(asset.url, '_blank', 'noopener,noreferrer')
    else
      notify(firstErrorMessage(err), 'error')
  } finally {
    downloading.value = false
  }
}

/*
 * CHỨC NĂNG: Đưa con trỏ vào ô Tên tệp để người dùng chỉnh sửa nhanh metadata.
 */
function focusTitleInput() {
  const target = titleInputRef.value?.$el?.querySelector('input')

  target?.focus()
}

/*
 * CHỨC NĂNG: Xóa mềm asset đang xem và thông báo về trang chính cập nhật lưới/thư mục.
 */
async function deleteCurrentAsset() {
  const asset = liveAsset.value
  if (!asset || props.trashed)
    return

  const deletedId = asset.id

  deleting.value = true
  try {
    await store.deleteAsset(deletedId)
    emit('deleted', deletedId)
    notify('Đã xóa tệp tin')
  } catch (err) {
    notify(firstErrorMessage(err), 'error')
  } finally {
    deleting.value = false
  }
}

/*
 * CHỨC NĂNG: Sao chép nhanh chuỗi thuộc tính vào clipboard và hiển thị trạng thái đã chép.
 */
async function copyFieldValue(key, text) {
  if (!text)
    return

  try {
    await navigator?.clipboard?.writeText(text)
  } catch {
    // Bỏ qua lỗi quyền clipboard trong môi trường không có HTTPS.
  }

  copiedKey.value = key
  setTimeout(() => {
    if (copiedKey.value === key)
      copiedKey.value = ''
  }, 1500)
}

/*
 * CHỨC NĂNG: Xóa 1 nhãn tĩnh khỏi danh sách tags đang hiển thị.
 */
function removeTag(tag) {
  detailForm.tags = detailForm.tags.filter(item => item !== tag)
}

/*
 * CHỨC NĂNG: Thêm nhãn tĩnh mới vào danh sách tags cục bộ nếu chưa tồn tại.
 */
function addTag() {
  const normalized = newTagText.value.trim().toLowerCase()
  if (normalized && !detailForm.tags.includes(normalized))
    detailForm.tags.push(normalized)

  newTagText.value = ''
  isAddingTag.value = false
}

watch(
  [() => props.asset?.id, () => props.asset?.visibility, () => props.trashed],
  () => {
    refreshPreviewStream(props.asset)
  },
  { immediate: true },
)

watch(
  [() => props.asset?.id, () => props.asset?.updated_at, () => props.trashed],
  ([id, , trashed], oldVal) => {
    const prevId = oldVal?.[0]
    const prevTrashed = oldVal?.[2]

    hydrateFromAsset(props.asset)

    if (id && !trashed && (id !== prevId || trashed !== prevTrashed))
      loadAssetDetail(id)
  },
  { immediate: true },
)

watch(previewTab, () => {
  nextTick(() => {
    const el = tabScrollRef.value?.$el
    if (el)
      el.scrollTop = 0

    syncTabScroll()
  })
})

watch([() => liveAsset.value?.id, isAddingTag, () => store.folders.length], () => {
  nextTick(syncTabScroll)
})

onMounted(() => {
  nextTick(syncTabScroll)
})

onBeforeUnmount(() => {
  tabScrollObserver?.disconnect()
  tabScrollObserver = null
  revokePrivateUrl()
})
</script>

<template>
  <div class="media-detail-sidebar d-flex flex-column h-100">
    <!-- Phần đầu cố định: Header + Preview + Tabs (không cuộn) -->
    <div class="flex-shrink-0 px-5 pt-5">
      <!-- Header xem trước -->
      <div class="d-flex justify-space-between align-center mb-3">
        <span class="text-subtitle-1 font-weight-medium text-high-emphasis">
          Xem trước
        </span>

        <div class="d-flex align-center">
          <IconBtn
            size="small"
            :disabled="!canPrev"
            aria-label="Tệp trước"
            @click="emit('prev')"
          >
            <VIcon icon="tabler-chevron-left" />
          </IconBtn>
          <IconBtn
            size="small"
            :disabled="!canNext"
            aria-label="Tệp tiếp theo"
            @click="emit('next')"
          >
            <VIcon icon="tabler-chevron-right" />
          </IconBtn>
          <IconBtn
            size="small"
            aria-label="Đóng chi tiết"
            @click="emit('close')"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>
      </div>

      <template v-if="liveAsset">
        <!-- Khung xem trước media (hỗ trợ cả public URL và private /stream) -->
        <div class="position-relative rounded overflow-hidden mb-4 border preview-thumb-wrap">
          <VImg
            v-if="liveAsset.kind === 'image' && resolvedPreviewUrl && !previewFailed"
            :src="resolvedPreviewUrl"
            :alt="detailForm.altText || detailForm.title"
            height="180"
            cover
            @error="previewFailed = true"
          />

          <video
            v-else-if="liveAsset.kind === 'video' && resolvedPreviewUrl"
            :src="resolvedPreviewUrl"
            controls
            preload="metadata"
            class="preview-media-el"
          />

          <div
            v-else-if="liveAsset.kind === 'audio'"
            class="preview-fallback d-flex flex-column align-center justify-center pa-4 gap-3"
          >
            <VAvatar
              color="info"
              variant="tonal"
              size="44"
            >
              <VIcon
                icon="tabler-music"
                size="24"
              />
            </VAvatar>
            <audio
              v-if="resolvedPreviewUrl"
              :src="resolvedPreviewUrl"
              controls
              class="w-100"
            />
          </div>

          <div
            v-else
            class="preview-fallback d-flex flex-column align-center justify-center pa-4 text-medium-emphasis gap-2"
          >
            <VAvatar
              color="secondary"
              variant="tonal"
              size="48"
            >
              <VIcon
                :icon="KIND_ICONS[liveAsset.kind] || 'tabler-file'"
                size="24"
              />
            </VAvatar>
            <span class="text-caption font-weight-medium">
              {{ extOf(liveAsset) || KIND_LABELS[liveAsset.kind] || 'MEDIA' }}
            </span>
          </div>

          <VChip
            v-if="liveAsset.visibility === 'private'"
            size="x-small"
            color="warning"
            variant="flat"
            prepend-icon="tabler-lock"
            label
            class="preview-visibility-badge"
          >
            Private
          </VChip>
        </div>

        <!-- Tabs: Thông tin / Prompt -->
        <VTabs
          v-model="previewTab"
          color="primary"
          density="comfortable"
          show-arrows
          class="border-b"
        >
          <VTab value="info">
            <VIcon
              icon="tabler-photo"
              size="16"
              class="me-2"
            />
            Thông tin
          </VTab>
          <VTab value="prompt">
            <VIcon
              icon="tabler-wand"
              size="16"
              class="me-2"
            />
            Prompt
          </VTab>
        </VTabs>
      </template>
    </div>

    <!-- Empty state khi danh sách chưa chọn hoặc rỗng -->
    <div
      v-if="!liveAsset"
      class="flex-grow-1 d-flex flex-column align-center justify-center text-center pa-6"
    >
      <VAvatar
        color="secondary"
        variant="tonal"
        size="56"
        class="mb-4"
      >
        <VIcon
          icon="tabler-photo-search"
          size="28"
        />
      </VAvatar>
      <div class="text-subtitle-1 font-weight-medium text-high-emphasis mb-1">
        Chưa chọn tệp tin
      </div>
      <p class="text-body-2 text-medium-emphasis">
        Chọn một tệp ở lưới bên cạnh để xem thông tin chi tiết và chỉnh sửa metadata.
      </p>
    </div>

    <!-- Chỉ vùng nội dung bên trong tab được phép cuộn -->
    <PerfectScrollbar
      v-else
      ref="tabScrollRef"
      :options="{ wheelPropagation: false, suppressScrollX: true }"
      class="media-detail-scroll pa-5"
    >
      <VWindow
        v-model="previewTab"
        :touch="false"
        class="disable-tab-transition"
      >
        <!-- Tab Thông tin chi tiết -->
        <VWindowItem value="info">
          <!-- Nhóm nút hành động nhanh -->
          <div class="d-flex align-center gap-2 mb-5">
            <VBtn
              color="primary"
              size="small"
              prepend-icon="tabler-download"
              class="flex-grow-1"
              :loading="downloading"
              :disabled="trashed"
              @click="downloadCurrentAsset"
            >
              Tải xuống
            </VBtn>

            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              icon
              aria-label="Chia sẻ"
              @click="copyFieldValue('share', shareLink)"
            >
              <VIcon :icon="copiedKey === 'share' ? 'tabler-check' : 'tabler-share'" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ copiedKey === 'share' ? 'Đã chép liên kết' : 'Chia sẻ' }}
              </VTooltip>
            </VBtn>

            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              icon
              aria-label="Chỉnh sửa"
              :disabled="trashed"
              @click="focusTitleInput"
            >
              <VIcon icon="tabler-edit" />
              <VTooltip
                activator="parent"
                location="top"
              >
                Sửa thông tin
              </VTooltip>
            </VBtn>

            <VBtn
              variant="tonal"
              color="error"
              size="small"
              icon
              aria-label="Xóa"
              :loading="deleting"
              :disabled="trashed"
              @click="deleteCurrentAsset"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip
                activator="parent"
                location="top"
              >
                Xóa
              </VTooltip>
            </VBtn>
          </div>

          <!-- Form metadata nối API (PUT /assets/{id}) -->
          <div class="d-flex flex-column gap-4 mb-5">
            <AppTextField
              ref="titleInputRef"
              v-model="detailForm.title"
              label="Tên tệp *"
              :readonly="trashed"
              :error-messages="formErrors.title"
              :append-inner-icon="copiedKey === 'title' ? 'tabler-check' : 'tabler-copy'"
              @update:model-value="formErrors.title = ''"
              @click:append-inner="copyFieldValue('title', detailForm.title)"
            />

            <AppTextField
              :model-value="resolvedPathText"
              label="Đường dẫn"
              readonly
              :append-inner-icon="copiedKey === 'path' ? 'tabler-check' : 'tabler-copy'"
              @click:append-inner="copyFieldValue('path', resolvedPathText)"
            />

            <AppCombobox
              v-model="detailForm.folder"
              v-model:search="folderSearch"
              :items="folderSuggestions"
              label="Thư mục"
              :readonly="trashed"
              :error-messages="formErrors.folder"
              @update:search="formErrors.folder = ''"
              @update:model-value="formErrors.folder = ''"
            />

            <AppSelect
              v-model="detailForm.visibility"
              :items="VISIBILITY_OPTIONS"
              label="Quyền hiển thị"
              :readonly="trashed"
              :error-messages="formErrors.visibility"
              @update:model-value="formErrors.visibility = ''"
            />

            <AppTextarea
              v-model="detailForm.altText"
              label="Alt ảnh"
              rows="2"
              auto-grow
              counter="255"
              :readonly="trashed"
              :error-messages="formErrors.altText"
              @update:model-value="formErrors.altText = ''"
            />

            <VBtn
              v-if="!trashed"
              color="primary"
              variant="tonal"
              size="small"
              block
              prepend-icon="tabler-device-floppy"
              :loading="saving"
              :disabled="!isMetadataDirty"
              @click="saveMetadata"
            >
              Lưu thay đổi
            </VBtn>

            <!-- Các field API chưa hỗ trợ -> giữ ở chế độ tĩnh -->
            <AppTextarea
              v-model="detailForm.description"
              label="Mô tả"
              rows="3"
              auto-grow
              counter="500"
            />

            <!-- Nhãn (Tags — tĩnh) -->
            <div>
              <VLabel class="mb-2 text-body-2 font-weight-medium text-high-emphasis">
                Nhãn (Tags)
              </VLabel>
              <div class="d-flex flex-wrap gap-2">
                <VChip
                  v-for="tag in detailForm.tags"
                  :key="tag"
                  size="small"
                  color="primary"
                  variant="tonal"
                  closable
                  @click:close="removeTag(tag)"
                >
                  {{ tag }}
                </VChip>

                <VChip
                  v-if="!isAddingTag"
                  size="small"
                  variant="outlined"
                  color="secondary"
                  prepend-icon="tabler-plus"
                  class="cursor-pointer"
                  @click="isAddingTag = true"
                >
                  Thêm thẻ
                </VChip>
              </div>

              <div
                v-if="isAddingTag"
                class="d-flex align-center gap-2 mt-2"
              >
                <AppTextField
                  v-model="newTagText"
                  placeholder="Nhập thẻ mới..."
                  class="flex-grow-1"
                  @keyup.enter="addTag"
                  @keyup.esc="isAddingTag = false"
                />
                <IconBtn
                  size="small"
                  color="primary"
                  @click="addTag"
                >
                  <VIcon icon="tabler-check" />
                </IconBtn>
              </div>
            </div>

            <!-- Mô hình AI (lấy từ ai_generation.model nếu có, ngược lại dùng tĩnh) -->
            <div>
              <div class="d-flex align-center gap-1 mb-1">
                <VLabel class="text-body-2 font-weight-medium text-high-emphasis">
                  Mô hình AI
                </VLabel>
                <VIcon
                  icon="tabler-info-circle"
                  size="15"
                  color="medium-emphasis"
                />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ hasAiMetadata ? 'Lấy từ metadata AI trên hệ thống' : 'Dữ liệu mặc định tĩnh (tệp chưa có metadata AI)' }}
                </VTooltip>
              </div>

              <AppSelect
                v-model="detailForm.aiModel"
                :items="modelOptions"
                clearable
              >
                <template #selection="{ item }">
                  <div class="d-flex align-center">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      size="20"
                      class="me-2"
                    >
                      <VIcon
                        icon="tabler-wand"
                        size="12"
                      />
                    </VAvatar>
                    <span class="text-body-2 font-weight-medium text-high-emphasis">
                      {{ item.title }}
                    </span>
                  </div>
                </template>
              </AppSelect>
            </div>
          </div>

          <VDivider class="mb-4" />

          <!-- Thông tin khác (Metadata động từ MediaAssetResource) -->
          <div>
            <div class="text-subtitle-1 font-weight-medium text-high-emphasis mb-3">
              Thông tin khác
            </div>

            <div class="d-flex flex-column gap-2 text-body-2">
              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Loại tệp</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ KIND_LABELS[liveAsset.kind] || liveAsset.kind }}</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Kích thước</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ dimensionsText }}</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Định dạng</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ liveAsset.mime || '—' }}</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Dung lượng</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ sizeDetailText }}</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Lượt sử dụng</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ liveAsset.usages_count ?? 0 }} vị trí</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Nhóm kênh</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ liveAsset.channel_group?.name || 'Dùng chung' }}</span>
              </div>

              <div class="d-flex justify-space-between align-center gap-2">
                <span class="text-medium-emphasis">Trạng thái</span>
                <VChip
                  size="small"
                  :color="trashed ? 'error' : 'success'"
                  variant="tonal"
                  label
                >
                  {{ trashed ? 'Đã xóa' : 'Đang hoạt động' }}
                </VChip>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Người tạo</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ liveAsset.creator?.name || 'Hệ thống' }}</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Cập nhật</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ fmtDateTime(liveAsset.updated_at) || '—' }}</span>
              </div>
            </div>
          </div>
        </VWindowItem>

        <!-- Tab Prompt AI (đọc ai_generation từ API; field thiếu giữ tĩnh) -->
        <VWindowItem value="prompt">
          <!-- Nhãn nguồn dữ liệu AI / Tĩnh -->
          <div class="mb-4">
            <VChip
              size="x-small"
              :color="hasAiMetadata ? 'primary' : 'secondary'"
              variant="tonal"
              :prepend-icon="hasAiMetadata ? 'tabler-sparkles' : 'tabler-database-off'"
              label
            >
              {{ hasAiMetadata ? 'Dữ liệu AI từ hệ thống' : 'Chế độ tĩnh (tệp chưa có metadata AI)' }}
            </VChip>
          </div>

          <!-- Hành động nhanh cho Prompt -->
          <div class="d-flex align-center gap-2 mb-5">
            <VBtn
              color="primary"
              size="small"
              :prepend-icon="copiedKey === 'prompt' ? 'tabler-check' : 'tabler-copy'"
              class="flex-grow-1"
              @click="copyFieldValue('prompt', promptForm.prompt)"
            >
              {{ copiedKey === 'prompt' ? 'Đã chép' : 'Chép Prompt' }}
            </VBtn>

            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              icon
              aria-label="Khôi phục Prompt mặc định"
              @click="resetPromptForm"
            >
              <VIcon icon="tabler-refresh" />
              <VTooltip
                activator="parent"
                location="top"
              >
                Khôi phục mặc định
              </VTooltip>
            </VBtn>
          </div>

          <!-- Form cấu hình Prompt -->
          <div class="d-flex flex-column gap-4 mb-5">
            <AppSelect
              v-model="detailForm.aiModel"
              :items="modelOptions"
              label="Mô hình AI"
              clearable
            >
              <template #selection="{ item }">
                <div class="d-flex align-center">
                  <VAvatar
                    color="primary"
                    variant="tonal"
                    size="20"
                    class="me-2"
                  >
                    <VIcon
                      icon="tabler-wand"
                      size="12"
                    />
                  </VAvatar>
                  <span class="text-body-2 font-weight-medium text-high-emphasis">
                    {{ item.title }}
                  </span>
                </div>
              </template>
            </AppSelect>

            <AppSelect
              v-model="promptForm.stylePreset"
              :items="stylePresetOptions"
              label="Phong cách"
            />

            <AppTextarea
              v-model="promptForm.prompt"
              label="Prompt tạo ảnh *"
              rows="4"
              auto-grow
              counter="1000"
            />

            <AppTextarea
              v-model="promptForm.negativePrompt"
              label="Negative Prompt"
              rows="2"
              auto-grow
              counter="500"
            />

            <AppSelect
              v-model="promptForm.aspectRatio"
              :items="aspectRatioOptions"
              label="Tỷ lệ khung"
            />

            <VRow dense>
              <VCol cols="6">
                <AppTextField
                  v-model="promptForm.steps"
                  label="Số bước"
                />
              </VCol>
              <VCol cols="6">
                <AppTextField
                  v-model="promptForm.cfgScale"
                  label="CFG Scale"
                />
              </VCol>
            </VRow>

            <AppTextField
              v-model="promptForm.seed"
              label="Seed"
              :append-inner-icon="copiedKey === 'seed' ? 'tabler-check' : 'tabler-copy'"
              @click:append-inner="copyFieldValue('seed', promptForm.seed)"
            />

            <!-- Từ khóa bổ sung -->
            <div>
              <VLabel class="mb-2 text-body-2 font-weight-medium text-high-emphasis">
                Từ khóa chất lượng
              </VLabel>
              <div class="d-flex flex-wrap gap-2">
                <VChip
                  v-for="keyword in promptForm.qualityTags"
                  :key="keyword"
                  size="small"
                  color="primary"
                  variant="tonal"
                >
                  {{ keyword }}
                </VChip>
              </div>
            </div>
          </div>

          <VDivider class="mb-4" />

          <!-- Thông số sinh ảnh -->
          <div>
            <div class="text-subtitle-1 font-weight-medium text-high-emphasis mb-3">
              Thông số sinh ảnh
            </div>

            <div class="d-flex flex-column gap-2 text-body-2">
              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Nhà cung cấp</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ promptInfo.provider }}</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Sampler</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ promptInfo.sampler }}</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Độ phân giải</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ promptInfo.dimensions }}</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Thời gian tạo</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ promptInfo.generatedDuration }}</span>
              </div>

              <div class="d-flex justify-space-between gap-2">
                <span class="text-medium-emphasis">Thời điểm</span>
                <span class="text-high-emphasis font-weight-medium text-end">{{ promptInfo.generatedAt }}</span>
              </div>
            </div>
          </div>
        </VWindowItem>
      </VWindow>
    </PerfectScrollbar>

    <VSnackbar
      v-model="snack.show"
      :color="snack.color"
      location="top end"
    >
      {{ snack.text }}
    </VSnackbar>
  </div>
</template>

<style scoped lang="scss">
.media-detail-sidebar {
  min-block-size: 0;
  overflow: hidden;
}

/* Vùng cuộn duy nhất trong tab: chiếm toàn bộ chiều cao còn lại dưới Tabs. */
.media-detail-scroll {
  position: relative;
  flex: 1 1 0;
  min-block-size: 0;
}

/* Hiện nhẹ thanh cuộn trong tab khi nội dung dài hơn khung. */
.media-detail-scroll.ps--active-y > :deep(.ps__rail-y) {
  opacity: 0.45;
}

/* Khung xem trước: cố định 180px để đồng nhất chiều cao giữa các loại file. */
.preview-thumb-wrap {
  block-size: 180px;
  background: rgba(var(--v-theme-on-surface), 0.08);
}

.preview-fallback,
.preview-media-el {
  inline-size: 100%;
  block-size: 100%;
  object-fit: contain;
}

.preview-visibility-badge {
  position: absolute;
  inset-block-start: 8px;
  inset-inline-start: 8px;
}
</style>
