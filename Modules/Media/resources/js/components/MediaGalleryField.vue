<!--
  =====================================================================
  CHỨC NĂNG FILE: Field quản lý gallery (nhiều media, sortable).
  =====================================================================
  - Grid ảnh/video đã gán (thumbnail + nút X từng item).
  - Kéo thả sắp xếp (HTML5 drag) -> đổi sort_order.
  - Nút "+" mở <MediaPickerDialog multiple> để thêm nhiều.
  - model-value = mảng media_asset_id đã sắp xếp (id[]).
  PROPS:
    modelValue   : number[] media_asset_id đã chọn, theo thứ tự.
    slot         : slot đích ('post.gallery'...).
    label        : nhãn field.
    allowedKinds : whitelist kind (lấy từ slot khi không truyền).
    maxItems     : giới hạn số media (0 = không giới hạn; mặc định lấy slot).
    channelGroupId: lọc theo nhóm kênh khi mở picker.
    disabled     : khóa field.
  EMITS:
    update:modelValue(ids[]) — mảng id theo sort_order mới.
    change(ids[])            — giống update (cho parent thuận tiện).
  =====================================================================
-->
<script setup>
import { computed, ref, watch } from 'vue'
import { useMediaAssetStore } from '../stores/mediaAsset'
import { mediaApi } from '../services/mediaApi'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  slot: { type: String, required: true },
  label: { type: String, default: '' },
  allowedKinds: { type: Array, default: () => [] },
  maxItems: { type: Number, default: null },
  channelGroupId: { type: [Number, String], default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const store = useMediaAssetStore()
const picker = ref(null)
const items = ref([]) // [{id,title,kind,url,conversions}] theo sort_order
const dragIndex = ref(null)

/* Slot metadata. */
const slotInfo = computed(() => store.slotInfo(props.slot))
const effectiveKinds = computed(() =>
  props.allowedKinds.length ? props.allowedKinds : (slotInfo.value?.allowedKinds || []),
)
const effectiveMax = computed(() =>
  props.maxItems !== null ? props.maxItems : (slotInfo.value?.maxItems ?? 0),
)

/* Sync từ modelValue -> items (nạp asset info khi thiếu). */
watch(() => props.modelValue, async ids => {
  const list = ids || []
  // Giữ thứ tự id; chỉ nạp asset mới khi chưa có trong items.
  const known = new Map(items.value.map(i => [i.id, i]))
  const out = []
  for (const id of list) {
    if (known.has(id)) {
      out.push(known.get(id))
    } else {
      try {
        out.push(await mediaApi.asset(id))
      } catch (e) {
        out.push({ id, title: `#${id}` })
      }
    }
  }
  items.value = out
}, { immediate: true, deep: true })

function openPicker() {
  if (props.disabled) { return }
  if (effectiveMax.value > 0 && items.value.length >= effectiveMax.value) { return }
  picker.value?.open()
}

function onSelect(assets) {
  // Gộp assets mới vào cuối (tránh trùng id) rồi emit.
  const existing = new Set(items.value.map(i => i.id))
  for (const a of assets || []) {
    if (!existing.has(a.id)) {
      items.value.push(a)
    }
  }
  emitAll()
}

function removeAt(idx) {
  items.value.splice(idx, 1)
  emitAll()
}

function onDragStart(idx) { dragIndex.value = idx }
function onDragOver(e) { e.preventDefault() }
function onDrop(target) {
  if (dragIndex.value === null || dragIndex.value === target) { return }
  const moved = items.value.splice(dragIndex.value, 1)[0]
  items.value.splice(target, 0, moved)
  dragIndex.value = null
  emitAll()
}

function emitAll() {
  const ids = items.value.map(i => i.id)
  emit('update:modelValue', ids)
  emit('change', ids)
}
</script>

<template>
  <div class="media-gallery-field">
    <VLabel v-if="label" class="mb-2 text-body-2 font-weight-medium">
      {{ label }}
      <span v-if="effectiveMax > 0" class="text-caption text-medium-emphasis">
        ({{ items.length }}/{{ effectiveMax }})
      </span>
    </VLabel>

    <div class="media-gallery-field__grid">
      <div
        v-for="(item, idx) in items"
        :key="item.id"
        class="media-gallery-field__item rounded"
        :draggable="!disabled"
        @dragstart="onDragStart(idx)"
        @dragover="onDragOver"
        @drop="onDrop(idx)"
      >
        <div class="media-gallery-field__thumb">
          <VImg
            v-if="item.kind === 'image'"
            :src="item.conversions?.thumb || item.url"
            aspect-ratio="1"
            cover
          />
          <div v-else class="media-gallery-field__icon">
            <VIcon
              :icon="item.kind === 'video' ? 'tabler-video' : (item.kind === 'audio' ? 'tabler-music' : 'tabler-file')"
              size="24"
            />
            <span class="text-caption">{{ item.kind }}</span>
          </div>

          <div class="media-gallery-field__actions">
            <VBtn icon="tabler-trash" size="x-small" variant="flat" color="error" @click.stop="removeAt(idx)" />
          </div>
        </div>
        <div class="text-caption text-truncate pa-1 text-center">{{ item.title }}</div>
      </div>

      <button
        v-if="!disabled && (effectiveMax === 0 || items.length < effectiveMax)"
        type="button"
        class="media-gallery-field__add rounded d-flex flex-column align-center justify-center"
        @click="openPicker"
      >
        <VIcon icon="tabler-plus" size="28" />
        <span class="text-caption mt-1">Thêm media</span>
      </button>
    </div>

    <MediaPickerDialog
      ref="picker"
      :model-value="modelValue"
      :slot="slot"
      :allowed-kinds="effectiveKinds"
      :max-items="effectiveMax"
      :channel-group-id="channelGroupId"
      :multiple="true"
      :title="`Chọn ${slotInfo?.label || 'media'}`"
      @select="onSelect"
    />
  </div>
</template>

<style scoped lang="scss">
.media-gallery-field__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 8px;
}

.media-gallery-field__item {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  overflow: hidden;
  cursor: grab;

  &:active { cursor: grabbing; }
}

.media-gallery-field__thumb {
  position: relative;
  aspect-ratio: 1;
  background: rgba(var(--v-theme-surface-variant), 0.3);
}

.media-gallery-field__icon {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  gap: 4px;
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.media-gallery-field__actions {
  position: absolute;
  top: 4px;
  right: 4px;
  opacity: 0;
  transition: opacity 0.15s ease;

  .media-gallery-field__item:hover & {
    opacity: 1;
  }
}

.media-gallery-field__add {
  aspect-ratio: 1;
  border: 2px dashed rgba(var(--v-theme-on-surface), 0.2);
  background: transparent;
  color: rgba(var(--v-theme-on-surface), 0.5);
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-primary));
  }
}
</style>
