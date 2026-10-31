<!--
  =====================================================================
  CHỨC NĂNG FILE: Dropzone upload media — kéo thả file hoặc bấm chọn.
  =====================================================================
  - Validate client-side theo allowedKinds (extension + mime hint).
  - Upload qua store.upload/uploadMany -> prepend vào library.
  - Emit('uploaded', assets[]) cho caller (MediaPickerDialog auto chọn).
  PROPS:
  allowedKinds  : array kind được phép (để validate client-side).
  multiple      : cho phép nhiều file.
  folder        : folder gán cho file mới (mặc định 'root').
  channelGroupId: kênh chủ sở hữu (tùy chọn).
  autoUpload    : tự upload ngay khi chọn file (mặc định true).
  EMITS:
  uploaded(assets[]) — upload xong (mỗi asset MediaItemDto-shaped).
  error(message)     — lỗi client/server.
  =====================================================================
-->
<script setup>
import { computed, ref } from 'vue'
import { useMediaAssetStore } from '../stores/mediaAsset'

const props = defineProps({
  allowedKinds: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: false },
  folder: { type: String, default: 'root' },
  channelGroupId: { type: [Number, String], default: null },
  autoUpload: { type: Boolean, default: true },
})

const emit = defineEmits(['uploaded', 'error'])

const store = useMediaAssetStore()

const dragging = ref(false)
const uploading = ref(false)
const error = ref('')
const fileInput = ref(null)

/* Extension map để validate client-side. */
const KIND_EXT = {
  image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg', 'ico'],
  video: ['mp4', 'mov', 'webm', 'mkv', 'avi', 'm4v', 'mpg', 'mpeg'],
  audio: ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac', 'opus'],
  document: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'md', 'json', 'xml'],
  archive: ['zip', 'rar', '7z', 'gz', 'tar', 'bz2'],
}

const allowedExts = computed(() => {
  if (!props.allowedKinds.length) { return [] }
  
  return props.allowedKinds.flatMap(k => KIND_EXT[k] || [])
})

const acceptAttr = computed(() => {
  if (!props.allowedKinds.length) { return '' }
  
  return allowedExts.value.map(e => '.' + e).join(',')
})

function pick() { fileInput.value?.click() }

function onDrop(e) {
  dragging.value = false
  handle(e.dataTransfer.files)
}

function onPick(e) {
  handle(e.target.files)
  e.target.value = ''
}

function ext(name) {
  const i = name.lastIndexOf('.')
  
  return i >= 0 ? name.slice(i + 1).toLowerCase() : ''
}

function validate(file) {
  const e = ext(file.name)
  if (allowedExts.value.length && !allowedExts.value.includes(e)) {
    return `Đuôi .${e} không được phép (cho phép: ${allowedExts.value.join(', ')})`
  }
  
  return null
}

async function handle(files) {
  const list = [...files]
  if (!props.multiple && list.length > 1) {
    list.splice(1)
  }

  const valid = []
  for (const f of list) {
    const err = validate(f)
    if (err) {
      error.value = err
      emit('error', err)
      
      return
    }
    valid.push(f)
  }

  if (!valid.length) { return }
  if (!props.autoUpload) { return }

  uploading.value = true
  error.value = ''
  try {
    const opts = { folder: props.folder }
    if (props.channelGroupId) {
      // eslint-disable-next-line camelcase
      opts.channel_group_id = props.channelGroupId
    }
    if (props.allowedKinds.length) {
      // eslint-disable-next-line camelcase
      opts.allowed_kinds = props.allowedKinds
    }

    const assets = props.multiple
      ? await store.uploadMany(valid, opts)
      : [await store.upload(valid[0], opts)]

    emit('uploaded', assets)
  } catch (e) {
    error.value = e?.message || 'Tải lên thất bại'
    emit('error', error.value)
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <div>
    <div
      class="upload-zone rounded border-2 border-dashed pa-8 text-center cursor-pointer"
      :class="{ 'upload-zone--dragging': dragging }"
      @click="pick"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <input
        ref="fileInput"
        type="file"
        class="d-none"
        :multiple="multiple"
        :accept="acceptAttr"
        @change="onPick"
      >

      <VProgressCircular
        v-if="uploading"
        indeterminate
        color="primary"
        class="mb-3"
      />
      <template v-else>
        <VAvatar
          color="primary"
          variant="tonal"
          size="48"
          class="mb-3"
        >
          <VIcon
            icon="tabler-cloud-upload"
            size="24"
          />
        </VAvatar>
        <div class="text-body-1 font-weight-medium mb-1">
          Kéo thả file vào đây hoặc <span class="text-primary">bấm chọn</span>
        </div>
        <div class="text-caption text-medium-emphasis">
          {{ allowedExts.length ? `Chấp nhận: ${allowedExts.join(', ')}` : 'Mọi loại file' }}
          {{ multiple ? ' (nhiều file)' : '' }}
        </div>
      </template>
    </div>

    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mt-4"
      closable
      @click:close="error = ''"
    >
      {{ error }}
    </VAlert>
  </div>
</template>

<style scoped lang="scss">
.upload-zone {
  border-color: rgba(var(--v-theme-on-surface), 0.2);
  transition: border-color 0.15s ease, background 0.15s ease;

  &:hover,
  &--dragging {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.04);
  }
}
</style>
