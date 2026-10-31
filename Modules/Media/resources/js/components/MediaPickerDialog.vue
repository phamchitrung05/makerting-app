<!--
  =====================================================================
  CHỨC NĂNG FILE: Dialog chọn media dùng chung — 3 tab:
  =====================================================================
  - Tab "Thư viện"  : grid media từ store (lọc kind/folder/channel_group/
                      search), phân trang, bấm chọn (single/multi).
  - Tab "Tải lên"   : dropzone upload file -> store.upload() -> auto chọn.
  - Tab "Tạo bằng AI": placeholder gọi VisualStyle/CoreAi (P2/P3) —
                      hiện hiển thị empty state + nút chuyển tab upload.

  model-value: id | id[] (tùy multiple).
  PROPS:
    modelValue     : number|number[]|null (media_asset_id đã chọn).
    multiple       : cho phép chọn nhiều (mặc định false).
    slot           : tên slot đích (để lấy allowedKinds/maxItems).
    allowedKinds   : mảng kind lọc (image|video|audio|document|archive).
    maxItems       : giới hạn chọn (0 = không giới hạn; mặc định 1 khi !multiple).
    channelGroupId : lọc theo nhóm kênh.
    title          : tiêu đề dialog.
  EMITS:
    update:modelValue(id|ids) — id/ids media đã chọn.
    select(assets[])          — asset object(s) đầy đủ vừa chọn.
    upload(assets[])          — asset(s) vừa upload thành công.
  =====================================================================
-->
<script setup>
import { computed, ref, watch } from 'vue'
import { useMediaAssetStore } from '../stores/mediaAsset'

const props = defineProps({
  modelValue: { type: [Number, Array], default: null },
  multiple: { type: Boolean, default: false },
  slot: { type: String, default: '' },
  allowedKinds: { type: Array, default: () => [] },
  maxItems: { type: Number, default: null },
  channelGroupId: { type: [Number, String], default: null },
  title: { type: String, default: 'Chọn media' },
})

const emit = defineEmits(['update:modelValue', 'select', 'upload'])

const store = useMediaAssetStore()

const dialog = ref(false)
const activeTab = ref('library')
const search = ref('')
const kindFilter = ref('')
const folderFilter = ref('')
const selected = ref(new Set())

/* Giới hạn chọn thực tế: prop -> mặc định theo multiple. */
const effectiveMax = computed(() => {
  if (props.maxItems !== null) { return props.maxItems }
  return props.multiple ? 0 : 1
})

/* Slot metadata nếu có (để lấy allowedKinds/maxItems). */
const slotInfo = computed(() => props.slot ? store.slotInfo(props.slot) : null)

/* Whitelist kind: ưu tiên prop -> slot -> rỗng (mọi kind). */
const allowedKinds = computed(() => {
  if (props.allowedKinds.length) { return props.allowedKinds }
  if (slotInfo.value?.allowedKinds?.length) { return slotInfo.value.allowedKinds }
  return []
})

/* Items đã lọc theo allowedKinds + kind filter người dùng. */
const filteredItems = computed(() => {
  let items = store.items
  if (allowedKinds.value.length) {
    items = items.filter(i => allowedKinds.value.includes(i.kind))
  }
  if (kindFilter.value) {
    items = items.filter(i => i.kind === kindFilter.value)
  }
  return items
})

/* Nạp dữ liệu khi mở dialog. */
watch(dialog, async open => {
  if (!open) { return }
  store.pagination.page = 1
  if (allowedKinds.value.length === 1) {
    store.filters.kind = allowedKinds.value[0]
  }
  await Promise.all([
    store.refresh(),
    store.loadFolders(),
    store.loadSlots(),
  ])
  // Đồng bộ lựa chọn ban đầu từ modelValue.
  selected.value = new Set(
    Array.isArray(props.modelValue) ? props.modelValue :
      (props.modelValue ? [props.modelValue] : []),
  )
})

/* Đổi filter -> refresh (debounce nhẹ qua watch). */
watch([search, kindFilter, folderFilter], () => {
  store.filters.search = search.value
  store.filters.kind = kindFilter.value || (allowedKinds.value.length === 1 ? allowedKinds.value[0] : '')
  store.filters.folder = folderFilter.value
  store.pagination.page = 1
  store.refresh()
})

function open() { dialog.value = true }
function close() { dialog.value = false }

function toggle(asset) {
  const id = asset.id
  const set = new Set(selected.value)

  if (set.has(id)) {
    set.delete(id)
  } else {
    if (!props.multiple || effectiveMax.value === 1) {
      set.clear()
    }
    if (effectiveMax.value > 0 && set.size >= effectiveMax.value) {
      return
    }
    set.add(id)
  }
  selected.value = set
}

function isSelected(asset) {
  return selected.value.has(asset.id)
}

/* Xác nhận chọn. */
function confirm() {
  const ids = [...selected.value]
  const assets = filteredItems.value.filter(i => selected.value.has(i.id))

  emit('update:modelValue', props.multiple ? ids : (ids[0] ?? null))
  emit('select', assets)
  close()
}

/* Upload thành công -> auto chọn asset mới. */
async function onUploaded(assets) {
  emit('upload', assets)
  const ids = assets.map(a => a.id)

  if (!props.multiple) {
    selected.value = new Set([ids[0]])
  } else {
    const set = new Set(selected.value)
    for (const id of ids) {
      if (effectiveMax.value === 0 || set.size < effectiveMax.value) {
        set.add(id)
      }
    }
    selected.value = set
  }
  activeTab.value = 'library'
}

defineExpose({ open, close })
</script>

<template>
  <VDialog v-model="dialog" max-width="960" scrollable>
    <VCard>
      <VCardItem class="pb-2">
        <VCardTitle>{{ title }}</VCardTitle>
        <VCardSubtitle v-if="slotInfo">
          Slot "{{ slot }}" — {{ slotInfo.label }}
          <template v-if="effectiveMax > 0">
            (tối đa {{ effectiveMax }} media)
          </template>
        </VCardSubtitle>
        <template #append>
          <DialogCloseBtn @click="close" />
        </template>
      </VCardItem>

      <VDivider />

      <VTabs v-model="activeTab" class="px-4" density="compact">
        <VTab value="library"><VIcon icon="tabler-photo" class="me-2" /> Thư viện</VTab>
        <VTab value="upload"><VIcon icon="tabler-upload" class="me-2" /> Tải lên</VTab>
        <VTab value="ai"><VIcon icon="tabler-wand" class="me-2" /> Tạo bằng AI</VTab>
      </VTabs>

      <VDivider />

      <VCardText class="pa-0">
        <VWindow v-model="activeTab">
          <!-- TAB 1: THƯ VIỆN -->
          <VWindowItem value="library">
            <div class="pa-4">
              <VRow class="mb-4 g-3">
                <VCol cols="12" md="4">
                  <AppTextField
                    v-model="search"
                    placeholder="Tìm kiếm tên, alt..."
                    prepend-inner-icon="tabler-search"
                    clearable
                    hide-details
                  />
                </VCol>
                <VCol cols="12" sm="6" md="3">
                  <AppSelect
                    v-model="kindFilter"
                    :items="[
                      { title: 'Tất cả loại', value: '' },
                      { title: 'Ảnh', value: 'image' },
                      { title: 'Video', value: 'video' },
                      { title: 'Âm thanh', value: 'audio' },
                      { title: 'Tài liệu', value: 'document' },
                      { title: 'File nén', value: 'archive' },
                    ]"
                    placeholder="Loại media"
                    hide-details
                  />
                </VCol>
                <VCol cols="12" sm="6" md="3">
                  <AppSelect
                    v-model="folderFilter"
                    :items="[{ title: 'Tất cả thư mục', value: '' }, ...store.folders.map(f => ({ title: f, value: f }))]"
                    placeholder="Thư mục"
                    hide-details
                  />
                </VCol>
                <VCol cols="12" md="2" class="d-flex align-center justify-end">
                  <VBtn variant="tonal" color="secondary" size="small" :loading="store.loading" @click="store.refresh()">
                    <VIcon icon="tabler-refresh" />
                  </VBtn>
                </VCol>
              </VRow>

              <div v-if="store.loading && !filteredItems.length" class="text-center py-12">
                <VProgressCircular indeterminate color="primary" />
              </div>

              <div v-else-if="!filteredItems.length" class="text-center py-12 text-body-2 text-medium-emphasis">
                <VIcon icon="tabler-photo-off" size="40" class="mb-2" />
                <div>Chưa có media nào phù hợp</div>
              </div>

              <div v-else class="media-grid">
                <div
                  v-for="asset in filteredItems"
                  :key="asset.id"
                  class="media-card rounded cursor-pointer"
                  :class="{ 'media-card--selected': isSelected(asset) }"
                  @click="toggle(asset)"
                >
                  <div class="media-card__thumb">
                    <VImg
                      v-if="asset.kind === 'image'"
                      :src="asset.conversions?.thumb || asset.url"
                      aspect-ratio="1"
                      cover
                    />
                    <div v-else class="media-card__icon">
                      <VIcon :icon="asset.kind === 'video' ? 'tabler-video' : (asset.kind === 'audio' ? 'tabler-music' : 'tabler-file')" size="32" />
                      <span class="text-caption">{{ asset.kind }}</span>
                    </div>
                    <div v-if="isSelected(asset)" class="media-card__check">
                      <VIcon icon="tabler-circle-check-filled" color="primary" size="22" />
                    </div>
                  </div>
                  <div class="media-card__meta pa-2">
                    <div class="text-body-2 font-weight-medium text-truncate">{{ asset.title }}</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ asset.width && asset.height ? `${asset.width}×${asset.height}` : asset.mime || asset.kind }}
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="store.total > store.pagination.itemsPerPage" class="d-flex justify-center mt-4">
                <VPagination
                  :model-value="store.pagination.page"
                  :length="Math.ceil(store.total / store.pagination.itemsPerPage)"
                  :total-visible="5"
                  @update:model-value="store.setPage"
                />
              </div>
            </div>
          </VWindowItem>

          <!-- TAB 2: TẢI LÊN -->
          <VWindowItem value="upload">
            <div class="pa-6">
              <MediaUploadZone
                :allowed-kinds="allowedKinds"
                :multiple="multiple"
                :folder="folderFilter || 'root'"
                :channel-group-id="channelGroupId"
                @uploaded="onUploaded"
              />
            </div>
          </VWindowItem>

          <!-- TAB 3: TẠO BẰNG AI -->
          <VWindowItem value="ai">
            <div class="pa-8 text-center">
              <VAvatar color="primary" variant="tonal" size="56" class="mb-4">
                <VIcon icon="tabler-wand" size="28" />
              </VAvatar>
              <h6 class="text-h6 mb-2">Tạo ảnh bằng AI</h6>
              <p class="text-body-2 text-medium-emphasis mb-6">
                Tính năng sẽ nối với VisualStyle DNA + CoreAi ở Phase 2 —
                hiện chưa khả dụng. Bạn có thể tải lên ảnh có sẵn ở tab "Tải lên".
              </p>
              <VBtn variant="tonal" @click="activeTab = 'upload'">
                Chuyển sang Tải lên
              </VBtn>
            </div>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn variant="tonal" color="secondary" @click="close">Hủy</VBtn>
        <VBtn color="primary" :disabled="selected.size === 0" @click="confirm">
          Chọn {{ selected.size > 0 ? `(${selected.size})` : '' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped lang="scss">
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}

.media-card {
  border: 2px solid transparent;
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.4);
  }

  &--selected {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
  }

  &__thumb {
    position: relative;
    aspect-ratio: 1;
    background: rgba(var(--v-theme-surface-variant), 0.3);
  }

  &__icon {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    gap: 4px;
    color: rgba(var(--v-theme-on-surface), 0.5);
  }

  &__check {
    position: absolute;
    top: 6px;
    right: 6px;
    background: white;
    border-radius: 50%;
  }

  &__meta {
    background: rgb(var(--v-theme-surface));
  }
}
</style>
