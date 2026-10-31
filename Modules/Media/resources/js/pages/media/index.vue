<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang Quản lý Media — layout 3 cột chuẩn PerfectScrollbar.
  =====================================================================
  Cột trái   : filter (kind/visibility/folder/search) + folder tree.
  Cột giữa   : grid media + toolbar (upload/sort/view) + phân trang.
  Cột phải   : panel chi tiết asset đã chọn (preview, metadata, usages,
               nút sửa/xóa/stream).
  =====================================================================
-->
<script setup>
import { computed, onMounted, ref } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useMediaAssetStore } from '../../stores/mediaAsset'
import { mediaApi } from '../../services/mediaApi'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'
import AppSelect from '@core/components/app-form-elements/AppSelect.vue'
import { firstErrorMessage } from '@/utils/api'

definePage({ meta: { layout: 'default' } })

const store = useMediaAssetStore()

const search = ref('')
const kindFilter = ref('')
const folderFilter = ref('')
const visibilityFilter = ref('')
const selectedAsset = ref(null)
const deleting = ref(false)
const snack = ref({ show: false, text: '', color: 'success' })

const kindOptions = [
  { title: 'Tất cả loại', value: '' },
  { title: 'Ảnh', value: 'image' },
  { title: 'Video', value: 'video' },
  { title: 'Âm thanh', value: 'audio' },
  { title: 'Tài liệu', value: 'document' },
  { title: 'File nén', value: 'archive' },
]

const visibilityOptions = [
  { title: 'Tất cả', value: '' },
  { title: 'Công khai', value: 'public' },
  { title: 'Riêng tư', value: 'private' },
]

/* Chi tiết asset đang chọn. */
const detail = computed(() => selectedAsset.value)

/* Watch filter -> refresh store. */
function applyFilters() {
  store.filters.search = search.value
  store.filters.kind = kindFilter.value
  store.filters.folder = folderFilter.value
  store.filters.visibility = visibilityFilter.value
  store.pagination.page = 1
  store.refresh()
}

let debounceTimer = null
function onSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 350)
}

async function onUpload(e) {
  const files = [...e.target.files]
  if (!files.length) { return }
  try {
    await store.uploadMany(files, { folder: folderFilter.value || 'root' })
    notify(`Đã tải lên ${files.length} file`, 'success')
  } catch (err) {
    notify(firstErrorMessage(err) || 'Tải lên thất bại', 'error')
  }
  e.target.value = ''
}

async function selectAsset(asset) {
  selectedAsset.value = asset
  try {
    selectedAsset.value = await mediaApi.asset(asset.id)
  } catch (e) {
    // giữ bản item cũ
  }
}

async function removeAsset() {
  if (!selectedAsset.value || deleting.value) { return }
  deleting.value = true
  try {
    await mediaApi.deleteAsset(selectedAsset.value.id)
    store.remove(selectedAsset.value.id)
    selectedAsset.value = null
    notify('Đã xóa media', 'success')
  } catch (e) {
    notify(firstErrorMessage(e) || 'Xóa thất bại', 'error')
  } finally {
    deleting.value = false
  }
}

function streamUrl(id) {
  return mediaApi.streamUrl(id)
}

function notify(text, color = 'success') {
  snack.value = { show: true, text, color }
}

function formatSize(bytes) {
  if (!bytes) { return '—' }
  const units = ['B', 'KB', 'MB', 'GB']
  let n = bytes
  let i = 0
  while (n >= 1024 && i < units.length - 1) { n /= 1024; i++ }
  return `${n.toFixed(1)} ${units[i]}`
}

onMounted(async () => {
  await Promise.all([store.refresh(), store.loadFolders()])
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h4 class="text-h4 font-weight-medium mb-1">
        Quản lý Media
      </h4>
      <p class="text-body-1">
        Thư viện ảnh, video, tài liệu dùng chung cho toàn hệ thống.
      </p>
    </div>

    <VRow class="media-page">
      <!-- CỘT TRÁI: BỘ LỌC -->
      <VCol cols="12" md="3" lg="2">
        <VCard>
          <VCardText>
            <AppTextField
              v-model="search"
              placeholder="Tìm kiếm..."
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
              class="mb-4"
              @update:model-value="onSearch"
            />

            <AppSelect
              v-model="kindFilter"
              :items="kindOptions"
              label="Loại media"
              hide-details
              class="mb-4"
              @update:model-value="applyFilters"
            />

            <AppSelect
              v-model="visibilityFilter"
              :items="visibilityOptions"
              label="Chế độ"
              hide-details
              class="mb-4"
              @update:model-value="applyFilters"
            />

            <div class="text-body-2 font-weight-medium mb-2">Thư mục</div>
            <div class="d-flex flex-column gap-1">
              <button
                type="button"
                class="folder-item pa-2 rounded text-start"
                :class="{ 'folder-item--active': folderFilter === '' }"
                @click="folderFilter = ''; applyFilters()"
              >
                <VIcon icon="tabler-folder" size="16" class="me-2" />
                Tất cả
              </button>
              <button
                v-for="f in store.folders"
                :key="f"
                type="button"
                class="folder-item pa-2 rounded text-start"
                :class="{ 'folder-item--active': folderFilter === f }"
                @click="folderFilter = f; applyFilters()"
              >
                <VIcon icon="tabler-folder" size="16" class="me-2" />
                {{ f }}
              </button>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- CỘT GIỮA: GRID MEDIA -->
      <VCol cols="12" :md="selectedAsset ? 6 : 9" :lg="selectedAsset ? 7 : 10">
        <VCard>
          <VCardItem>
            <VCardTitle>Thư viện</VCardTitle>
            <VCardSubtitle>{{ store.total }} media</VCardSubtitle>
            <template #append>
              <VBtn color="primary" @click="$refs.fileInput?.click()">
                <VIcon icon="tabler-upload" class="me-2" /> Tải lên
              </VBtn>
              <input
                ref="fileInput"
                type="file"
                multiple
                class="d-none"
                @change="onUpload"
              >
            </template>
          </VCardItem>

          <VDivider />

          <VCardText class="pa-0">
            <PerfectScrollbar :options="{ wheelPropagation: false, suppressScrollX: true }" style="max-height: calc(100vh - 280px)">
              <div class="pa-4">
                <div v-if="store.loading && !store.items.length" class="text-center py-12">
                  <VProgressCircular indeterminate color="primary" />
                </div>

                <div v-else-if="!store.items.length" class="text-center py-12 text-body-2 text-medium-emphasis">
                  <VIcon icon="tabler-photo-off" size="40" class="mb-2" />
                  <div>Chưa có media nào</div>
                </div>

                <div v-else class="media-grid">
                  <div
                    v-for="asset in store.items"
                    :key="asset.id"
                    class="media-item rounded cursor-pointer"
                    :class="{ 'media-item--selected': selectedAsset?.id === asset.id }"
                    @click="selectAsset(asset)"
                  >
                    <div class="media-item__thumb">
                      <VImg
                        v-if="asset.kind === 'image'"
                        :src="asset.conversions?.thumb || asset.url"
                        aspect-ratio="1"
                        cover
                      />
                      <div v-else class="media-item__icon">
                        <VIcon
                          :icon="asset.kind === 'video' ? 'tabler-video' : (asset.kind === 'audio' ? 'tabler-music' : 'tabler-file')"
                          size="28"
                        />
                        <span class="text-caption">{{ asset.kind }}</span>
                      </div>
                      <div v-if="asset.visibility === 'private'" class="media-item__lock">
                        <VIcon icon="tabler-lock" size="14" color="warning" />
                      </div>
                    </div>
                    <div class="media-item__meta pa-2">
                      <div class="text-body-2 text-truncate">{{ asset.title }}</div>
                      <div class="text-caption text-medium-emphasis">
                        {{ asset.folder }} · {{ asset.usages_count ?? 0 }} dùng
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="store.total > store.pagination.itemsPerPage" class="d-flex justify-center mt-4">
                  <VPagination
                    :model-value="store.pagination.page"
                    :length="Math.ceil(store.total / store.pagination.itemsPerPage)"
                    :total-visible="7"
                    @update:model-value="store.setPage"
                  />
                </div>
              </div>
            </PerfectScrollbar>
          </VCardText>
        </VCard>
      </VCol>

      <!-- CỘT PHẢI: CHI TIẾT -->
      <VCol v-if="selectedAsset" cols="12" md="3" lg="3">
        <VCard>
          <VCardItem>
            <VCardTitle class="text-h6">Chi tiết</VCardTitle>
            <template #append>
              <VBtn icon="tabler-x" variant="text" size="small" @click="selectedAsset = null" />
            </template>
          </VCardItem>

          <VDivider />

          <VCardText class="pa-0">
            <PerfectScrollbar :options="{ wheelPropagation: false, suppressScrollX: true }" style="max-height: calc(100vh - 280px)">
              <div class="pa-4">
                <VImg
                  v-if="detail?.kind === 'image'"
                  :src="detail.conversions?.featured || detail.url"
                  aspect-ratio="16/9"
                  cover
                  class="rounded mb-4"
                />
                <div v-else class="media-detail__icon pa-8 text-center rounded mb-4">
                  <VIcon
                    :icon="detail?.kind === 'video' ? 'tabler-video' : (detail?.kind === 'audio' ? 'tabler-music' : 'tabler-file')"
                    size="48"
                  />
                  <div class="text-body-2 mt-2">{{ detail?.kind }}</div>
                </div>

                <div class="detail-list">
                  <div class="detail-row">
                    <span class="detail-label">Tên</span>
                    <span class="detail-value text-body-2">{{ detail?.title }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Kind</span>
                    <VChip size="x-small" :color="detail?.kind === 'image' ? 'success' : 'secondary'">
                      {{ detail?.kind }}
                    </VChip>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Chế độ</span>
                    <VChip size="x-small" :color="detail?.visibility === 'private' ? 'warning' : 'info'">
                      {{ detail?.visibility }}
                    </VChip>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Folder</span>
                    <span class="detail-value">{{ detail?.folder }}</span>
                  </div>
                  <div v-if="detail?.mime" class="detail-row">
                    <span class="detail-label">MIME</span>
                    <span class="detail-value">{{ detail.mime }}</span>
                  </div>
                  <div v-if="detail?.size" class="detail-row">
                    <span class="detail-label">Dung lượng</span>
                    <span class="detail-value">{{ formatSize(detail.size) }}</span>
                  </div>
                  <div v-if="detail?.width && detail?.height" class="detail-row">
                    <span class="detail-label">Kích thước</span>
                    <span class="detail-value">{{ detail.width }} × {{ detail.height }}</span>
                  </div>
                  <div v-if="detail?.duration_ms" class="detail-row">
                    <span class="detail-label">Thời lượng</span>
                    <span class="detail-value">{{ (detail.duration_ms / 1000).toFixed(1) }}s</span>
                  </div>
                  <div v-if="detail?.channel_group" class="detail-row">
                    <span class="detail-label">Nhóm kênh</span>
                    <span class="detail-value">{{ detail.channel_group.name }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Dùng</span>
                    <span class="detail-value">{{ detail?.usages_count ?? 0 }} chỗ</span>
                  </div>
                </div>

                <VBtn
                  :href="streamUrl(detail?.id)"
                  target="_blank"
                  variant="tonal"
                  block
                  class="mt-4"
                >
                  <VIcon icon="tabler-external-link" class="me-2" /> Mở file
                </VBtn>

                <VBtn
                  color="error"
                  variant="tonal"
                  block
                  class="mt-2"
                  :loading="deleting"
                  @click="removeAsset"
                >
                  <VIcon icon="tabler-trash" class="me-2" /> Xóa
                </VBtn>
              </div>
            </PerfectScrollbar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VSnackbar v-model="snack.show" :color="snack.color" location="top end">
      {{ snack.text }}
    </VSnackbar>
  </div>
</template>

<style scoped lang="scss">
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}

.media-item {
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

  &__lock {
    position: absolute;
    top: 6px;
    right: 6px;
  }
}

.folder-item {
  background: transparent;
  border: none;
  color: rgba(var(--v-theme-on-surface), 0.7);
  cursor: pointer;
  display: flex;
  align-items: center;
  font-size: 0.875rem;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.08);
  }

  &--active {
    background: rgba(var(--v-theme-primary), 0.12);
    color: rgb(var(--v-theme-primary));
    font-weight: 500;
  }
}

.detail-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.detail-label {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  flex-shrink: 0;
}

.detail-value {
  font-size: 0.8125rem;
  text-align: right;
  word-break: break-word;
}
</style>
