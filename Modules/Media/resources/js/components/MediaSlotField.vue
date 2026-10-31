<!--
  =====================================================================
  CHỨC NĂNG FILE: Field gắn 1 media vào form (thumbnail, cover, video...).
  =====================================================================
  - Preview thumbnail hiện tại (image thumb/conversion) + nút X để xóa.
  - Nút "Chọn media" mở <MediaPickerDialog /> (slot + allowedKinds).
  - model-value = media_asset_id (number|null).
  PROPS:
    modelValue   : number|null media_asset_id.
    slot         : slot đích trong MediaSlotRegistry ('post.thumbnail'...).
    label        : nhãn field.
    allowedKinds : override whitelist kind (lấy từ slot khi không truyền).
    channelGroupId: lọc theo nhóm kênh khi mở picker.
    disabled     : khóa field.
  EMITS:
    update:modelValue(id|null) — id asset đã chọn / null khi xóa.
    select(asset)              — asset object vừa chọn (cho parent dùng thêm).
  =====================================================================
-->
<script setup>
import { computed, ref, watch } from 'vue'
import { useMediaAssetStore } from '../stores/mediaAsset'

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  slot: { type: String, required: true },
  label: { type: String, default: '' },
  allowedKinds: { type: Array, default: () => [] },
  channelGroupId: { type: [Number, String], default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'select'])

const store = useMediaAssetStore()
const picker = ref(null)
const current = ref(null) // asset object đang hiển thị

/* Slot metadata (cho allowedKinds mặc định). */
const slotInfo = computed(() => store.slotInfo(props.slot))
const effectiveKinds = computed(() =>
  props.allowedKinds.length ? props.allowedKinds : (slotInfo.value?.allowedKinds || []),
)

/* Preview url: thumb conversion khi là ảnh, gốc cho loại khác. */
const previewUrl = computed(() => {
  if (!current.value) { return null }
  if (current.value.kind === 'image') {
    return current.value.conversions?.thumb || current.value.url
  }
  return current.value.url
})

const previewAlt = computed(() => current.value?.altText || current.value?.title || '')

/* Khi modelValue đổi từ ngoài (form load) -> nạp asset nếu chưa có. */
watch(() => props.modelValue, async id => {
  if (!id) {
    current.value = null
    return
  }
  if (current.value?.id === id) { return }
  try {
    current.value = await store.items.find(i => i.id === id) ||
      await import('../services/mediaApi').then(m => m.mediaApi.asset(id))
  } catch (e) {
    current.value = { id, title: `#${id}` }
  }
}, { immediate: true })

function openPicker() {
  if (props.disabled) { return }
  picker.value?.open()
}

function onSelect(assets) {
  const asset = assets?.[0]
  if (!asset) { return }
  current.value = asset
  emit('update:modelValue', asset.id)
  emit('select', asset)
}

function clear() {
  current.value = null
  emit('update:modelValue', null)
}
</script>

<template>
  <div class="media-slot-field">
    <VLabel v-if="label" class="mb-2 text-body-2 font-weight-medium">
      {{ label }}
    </VLabel>

    <div
      class="media-slot-field__box rounded border-1 d-flex align-center justify-center"
      :class="{ 'media-slot-field__box--has': !!current }"
    >
      <template v-if="current">
        <div class="media-slot-field__preview">
          <VImg
            v-if="previewUrl && current.kind === 'image'"
            :src="previewUrl"
            :alt="previewAlt"
            aspect-ratio="16/9"
            cover
          />
          <div v-else class="media-slot-field__icon d-flex flex-column align-center justify-center pa-6">
            <VIcon
              :icon="current.kind === 'video' ? 'tabler-video' : (current.kind === 'audio' ? 'tabler-music' : 'tabler-file')"
              size="40"
              color="primary"
            />
            <div class="text-body-2 mt-2 text-truncate" style="max-width: 100%">{{ current.title }}</div>
          </div>

          <div class="media-slot-field__overlay d-flex align-center justify-center gap-2">
            <VBtn icon="tabler-pencil" size="small" variant="tonal" color="white" @click.stop="openPicker" />
            <VBtn icon="tabler-trash" size="small" variant="tonal" color="error" @click.stop="clear" />
          </div>
        </div>
      </template>

      <button
        v-else
        type="button"
        class="media-slot-field__empty d-flex flex-column align-center justify-center pa-8 w-100"
        :disabled="disabled"
        @click="openPicker"
      >
        <VIcon icon="tabler-photo-plus" size="32" class="mb-2" />
        <span class="text-body-2">Chọn {{ slotInfo?.label || 'media' }}</span>
        <span v-if="effectiveKinds.length" class="text-caption text-medium-emphasis mt-1">
          ({{ effectiveKinds.join(', ') }})
        </span>
      </button>
    </div>

    <MediaPickerDialog
      ref="picker"
      :model-value="modelValue"
      :slot="slot"
      :allowed-kinds="effectiveKinds"
      :channel-group-id="channelGroupId"
      :multiple="false"
      :title="`Chọn ${slotInfo?.label || 'media'}`"
      @update:model-value="id => { if (!id) clear() }"
      @select="onSelect"
    />
  </div>
</template>

<style scoped lang="scss">
.media-slot-field__box {
  border-color: rgba(var(--v-theme-on-surface), 0.2);
  min-height: 120px;
  overflow: hidden;
  position: relative;

  &--has {
    border-style: solid;
  }
}

.media-slot-field__preview {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
}

.media-slot-field__overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  opacity: 0;
  transition: opacity 0.15s ease;

  .media-slot-field__preview:hover & {
    opacity: 1;
  }
}

.media-slot-field__empty {
  background: transparent;
  border: none;
  color: rgba(var(--v-theme-on-surface), 0.6);
  cursor: pointer;

  &:hover:not(:disabled) {
    color: rgb(var(--v-theme-primary));
  }
}
</style>
