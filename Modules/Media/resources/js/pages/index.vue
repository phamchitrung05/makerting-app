<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang Quản lý Thư viện Media — bố cục 3 cột cố định viewport.
  =====================================================================

  Chuẩn hoá theo trang `resources/js/pages/apps/email` (Vuexy fixed-height):
  - `VLayout.media-app-layout` nhận đổ bóng + bo góc qua
  `mixins.elevation(vuetify.$card-elevation)` (KHÔNG prop elevation).
  - `VMain` > `VCard flat h-100 d-flex flex-column` chứa 1 `VRow no-gutters`
  3 `VCol` (hệ col-md-2/8/2) — dùng grid chuẩn project, không tự dựng flex.
  - Mỗi `VCol` là `d-flex flex-column` + `min-block-size:0`; vùng cuộn `PerfectScrollbar`
  chỉ áp dụng cho danh sách điều hướng (cột trái, khối dung lượng ghim đáy), lưới (cột giữa,
  toolbar + action bar + `TablePagination` ghim cố định) và nội dung từng tab trong
  `MediaDetailSidebar` (cột phải, xem trước + tabs ghim cố định).
  - Toàn trang KHÔNG cuộn; mỗi cột chỉ cuộn đúng vùng nội dung co giãn.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - selectQuickFilter(id)  : đổi bộ lọc nhanh cột trái -> store.setQuickFilter.
  - selectFolder(name)     : lọc theo folder (bật/tắt) -> store.setFilter.
  - onSelectFile(asset)    : chọn 1 asset trên lưới & mở lại cột chi tiết phải.
  - toggleSelected(asset)  : checkbox đa chọn trên thumbnail.
  - onUploadClick/onFilesChosen : mở input file -> store.uploadMany.
  - onDeleteSelected       : xóa nhiều asset (soft-delete).
  - syncScrollbarObservers : gắn ResizeObserver cập nhật PerfectScrollbar khi nội dung đổi.
  - pagedItems             : computed dùng trực tiếp store.items (server đã phân trang).
  - thumbOf(asset)         : lấy url thumbnail/conversions/url cho item.
  - fmtSize(fmtDate/fmtDuration): định dạng bytes/ngày/thời lượng cho UI.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : useMediaAssetStore (items/filters/folders/pagination).
  - OUTPUT: UI 3 cột (2/8/2); cột trái/giữa/phải đều nối store & API MediaAsset (riêng field chưa có API ở cột phải giữ tĩnh).
  =====================================================================
-->
<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useMediaAssetStore } from '../stores/mediaAsset'
import { extOf, fmtDate, fmtDuration, fmtSize, thumbOf } from '../utils/mediaFormat'
import MediaDetailSidebar from '../views/MediaDetailSidebar.vue'

definePage({
  meta: {
    layout: 'default',
    layoutWrapperClasses: 'layout-content-height-fixed',
  },
})

const store = useMediaAssetStore()

const isRightSidebarOpen = ref(true)

const activeQuickFilter = ref('all')
const activeFolder = ref('')
const viewMode = ref('grid')
const searchQuery = ref('')
const kindFilter = ref('')
const sortFilter = ref('newest')
const expandedFolders = ref({})

/* Phân trang cột giữa — hai chiều với store.pagination. */
const page = computed({
  get: () => store.pagination.page,
  set: v => store.setPage(v),
})

const itemsPerPage = ref(18)

/* Bộ lọc nhanh cột trái — count bind từ store. */
const quickFilters = computed(() => [
  { id: 'all',     name: 'Tất cả tệp tin', icon: 'tabler-folders', count: store.total },
  { id: 'recent',  name: 'Gần đây',        icon: 'tabler-clock',   count: null },
  { id: 'shared',  name: 'Đã chia sẻ',     icon: 'tabler-share',   count: null },
  { id: 'ai',      name: 'Ảnh AI',          icon: 'tabler-wand',    count: null },
  { id: 'trash',   name: 'Thùng rác',       icon: 'tabler-trash',   count: null },
])

const kindOptions = [
  { title: 'Mọi loại', value: '' },
  { title: 'Hình ảnh', value: 'image' },
  { title: 'Video', value: 'video' },
  { title: 'Âm thanh', value: 'audio' },
  { title: 'Tài liệu', value: 'document' },
]

const sortOptions = [
  { title: 'Mới nhất', value: 'newest' },
  { title: 'Cũ nhất', value: 'oldest' },
  { title: 'Tên A–Z', value: 'name' },
]

/* Cây thư mục từ API (mỗi node {id,name,count,children?}). */
const folderTree = computed(() => store.folderTree)

/* Lưới media — items server-side đã lọc + phân trang. */
const pagedItems = computed(() => store.items)

/* File đang chọn xem chi tiết. */
const selectedFile = ref(null)

/* Checkbox đa chọn (Set id) — dùng cho action bar + chọn/xóa hàng loạt. */
const selectedIds = ref(new Set())

/* Đang upload/xóa — disable nút + spinner. */
const uploading = ref(false)
const deleting = ref(false)
const fileInput = ref(null)

/* Ref tới vùng PerfectScrollbar của cột trái và lưới giữa để đồng bộ thanh cuộn. */
const leftScrollRef = ref(null)
const gridScrollRef = ref(null)
let scrollbarResizeObserver = null

/*
 * CHỨC NĂNG: Cập nhật kích thước thanh cuộn cho cột trái và vùng lưới giữa.
 */
function updateAllScrollbars() {
  leftScrollRef.value?.ps?.update()
  gridScrollRef.value?.ps?.update()
}

/*
 * CHỨC NĂNG: Gắn ResizeObserver lên container và phần tử con trong PerfectScrollbar
 * để tự cập nhật rail cuộn khi thay đổi thư mục, nạp dữ liệu hoặc đóng/mở cột phải.
 */
function syncScrollbarObservers() {
  if (typeof ResizeObserver === 'undefined') return

  if (!scrollbarResizeObserver) {
    scrollbarResizeObserver = new ResizeObserver(() => {
      updateAllScrollbars()
    })
  } else {
    scrollbarResizeObserver.disconnect()
  }

  for (const scrollRef of [leftScrollRef, gridScrollRef]) {
    const el = scrollRef.value?.$el
    if (!el) continue

    scrollbarResizeObserver.observe(el)
    for (const child of el.children) {
      if (!child.classList.contains('ps__rail-x') && !child.classList.contains('ps__rail-y'))
        scrollbarResizeObserver.observe(child)
    }
  }

  updateAllScrollbars()
}

/* Debounce search -> store.filters.search. */
let searchTimer = null
watch(searchQuery, v => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => store.setFilter('search', v || ''), 350)
})
watch(kindFilter, v => store.setFilter('kind', v || ''))
watch(sortFilter, v => {
  const map = { newest: ['id', 'desc'], oldest: ['id', 'asc'], name: ['title', 'asc'] }
  const [sortBy, orderBy] = map[v] || map.newest

  store.pagination.sortBy = sortBy
  store.pagination.orderBy = orderBy
  store.pagination.page = 1
  store.refresh()
})

watch([() => store.items.length, () => store.loading, isRightSidebarOpen], () => {
  nextTick(syncScrollbarObservers)
})

/* Giữ selectedFile đồng bộ với danh sách trang hiện tại (tự chọn mục đầu nếu chưa chọn hoặc vừa đổi trang/lọc). */
watch(pagedItems, items => {
  if (!items.length) {
    selectedFile.value = null

    return
  }

  const currentId = selectedFile.value?.id
  const matched = currentId ? items.find(f => f.id === currentId) : null

  selectedFile.value = matched || items[0]
})

onMounted(async () => {
  store.pagination.itemsPerPage = itemsPerPage.value
  await Promise.all([store.refresh(), store.loadFolders(), store.loadSlots()])
  await nextTick()
  syncScrollbarObservers()
})

onBeforeUnmount(() => {
  scrollbarResizeObserver?.disconnect()
  scrollbarResizeObserver = null
})

/* ============ ĐIỀU HƯỚNG CỘT TRÁI ============ */
function selectQuickFilter(id) {
  activeQuickFilter.value = id
  store.setQuickFilter(id)
}

/* Chọn folder (click lần 2 = bỏ lọc). Node con cũng đi qua cùng hàm. */
function selectFolder(node) {
  activeFolder.value = activeFolder.value === node.id ? '' : node.id
  store.setFilter('folder', activeFolder.value)
}

function toggleFolderExpand(node) {
  expandedFolders.value = { ...expandedFolders.value, [node.id]: !expandedFolders.value[node.id] }
}

/* ============ LƯỚI GIỮA & PANEL PHẢI ============ */
const selectedIndex = computed(() => {
  if (!selectedFile.value) return -1

  return pagedItems.value.findIndex(f => f.id === selectedFile.value.id)
})

const canSelectPrev = computed(() => pagedItems.value.length > 1 && selectedIndex.value >= 0)
const canSelectNext = computed(() => pagedItems.value.length > 1 && selectedIndex.value >= 0)

function onSelectFile(file) {
  selectedFile.value = file
  isRightSidebarOpen.value = true
}

function stepSelectedFile(offset) {
  const len = pagedItems.value.length
  if (len <= 1) return

  const cur = selectedIndex.value >= 0 ? selectedIndex.value : 0

  selectedFile.value = pagedItems.value[(cur + offset + len) % len]
}

async function onAssetUpdated(updatedAsset) {
  if (updatedAsset)
    selectedFile.value = updatedAsset

  await store.loadFolders()
}

async function onAssetDeleted(deletedId) {
  if (selectedIds.value.has(deletedId)) {
    const next = new Set(selectedIds.value)

    next.delete(deletedId)
    selectedIds.value = next
  }

  await store.loadFolders()
}

function toggleSelected(file) {
  const s = new Set(selectedIds.value)

  s.has(file.id) ? s.delete(file.id) : s.add(file.id)
  selectedIds.value = s
}

const selectedAssets = computed(() => pagedItems.value.filter(f => selectedIds.value.has(f.id)))
const selectedSizeText = computed(() => fmtSize(store.selectedSizeBytes(selectedAssets.value)))

/* ============ ACTION: UPLOAD / DELETE / DOWNLOAD / SAVE ============ */
function onUploadClick() {
  fileInput.value?.click()
}

async function onFilesChosen(event) {
  const files = Array.from(event.target.files || [])

  event.target.value = ''        // cho phép chọn lại cùng file
  if (!files.length) return
  uploading.value = true
  try {
    const uploaded = await store.uploadMany(files, { folder: activeFolder.value || 'root' })

    if (uploaded?.length)
      selectedFile.value = uploaded[0]

    await store.loadFolders()    // folder mới có thể xuất hiện
  } finally {
    uploading.value = false
  }
}

async function onDeleteSelected() {
  if (!selectedIds.value.size) return
  deleting.value = true
  try {
    await Promise.all([...selectedIds.value].map(id => store.deleteAsset(id)))
    selectedIds.value = new Set()
    selectedFile.value = pagedItems.value[0] || null
    await store.loadFolders()
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <VLayout
    class="media-app-layout"
    style="z-index: 0"
  >
    <VMain class="media-main">
      <VCard
        flat
        class="media-content h-100 d-flex flex-column"
      >
        <VRow
          no-gutters
          class="media-row flex-grow-1"
        >
          <!-- ============ CỘT TRÁI: ĐIỀU HƯỚNG (col-md-2) ============ -->
          <VCol
            cols="12"
            md="2"
            class="border-e d-flex flex-column media-col"
          >
            <PerfectScrollbar
              ref="leftScrollRef"
              :options="{ wheelPropagation: false, suppressScrollX: true }"
              class="media-left-scroll pa-4"
            >
              <!-- Bộ lọc nhanh -->
              <div class="d-flex flex-column gap-2">
                <div
                  v-for="item in quickFilters"
                  :key="item.id"
                  class="nav-item rounded pa-2 d-flex align-center justify-space-between gap-2 cursor-pointer"
                  :class="{ 'nav-item--active': activeQuickFilter === item.id }"
                  @click="selectQuickFilter(item.id)"
                >
                  <div class="d-flex align-center flex-grow-1 media-truncate-wrap">
                    <VIcon
                      :icon="item.icon"
                      size="18"
                      :color="activeQuickFilter === item.id ? 'white' : undefined"
                      class="me-2 flex-shrink-0"
                    />
                    <span
                      class="text-body-1 font-weight-medium text-truncate"
                      :class="activeQuickFilter === item.id ? 'text-white' : 'text-high-emphasis'"
                    >
                      {{ item.name }}
                    </span>
                  </div>
                  <VChip
                    v-if="item.count !== null && item.count !== undefined"
                    size="x-small"
                    :variant="activeQuickFilter === item.id ? 'flat' : 'tonal'"
                    :color="activeQuickFilter === item.id ? undefined : 'secondary'"
                    :class="activeQuickFilter === item.id ? 'nav-count--active flex-shrink-0' : 'flex-shrink-0'"
                    label
                  >
                    {{ item.count }}
                  </VChip>
                </div>
              </div>

              <VDivider class="my-4" />

              <!-- Cây thư mục -->
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-caption font-weight-medium text-medium-emphasis text-uppercase">
                  Thư mục
                </span>
                <VBtn
                  v-if="activeFolder"
                  variant="text"
                  size="x-small"
                  color="secondary"
                  @click="selectFolder({ id: '' })"
                >
                  Bỏ lọc
                </VBtn>
              </div>

              <div class="d-flex flex-column gap-2">
                <div
                  v-for="folder in folderTree"
                  :key="folder.id"
                  class="d-flex flex-column"
                >
                  <div
                    class="nav-item rounded px-2 py-1 d-flex align-center justify-space-between gap-2 cursor-pointer"
                    :class="{ 'nav-item--parent': activeFolder === folder.id }"
                    @click="selectFolder(folder)"
                  >
                    <div class="d-flex align-center flex-grow-1 media-truncate-wrap">
                      <VIcon
                        v-if="folder.children"
                        :icon="expandedFolders[folder.id] ? 'tabler-chevron-down' : 'tabler-chevron-right'"
                        size="15"
                        class="me-1 flex-shrink-0"
                        @click.stop="toggleFolderExpand(folder)"
                      />
                      <VIcon
                        :icon="activeFolder === folder.id ? 'tabler-folder-open' : 'tabler-folder'"
                        size="17"
                        color="primary"
                        class="me-2 flex-shrink-0"
                      />
                      <span class="text-body-1 text-high-emphasis font-weight-medium text-truncate">
                        {{ folder.name }}
                      </span>
                    </div>
                    <span class="text-caption text-medium-emphasis flex-shrink-0">
                      {{ folder.count }}
                    </span>
                  </div>

                  <!-- Thư mục con -->
                  <div
                    v-if="folder.children && expandedFolders[folder.id]"
                    class="d-flex flex-column ms-5 gap-2 my-1"
                  >
                    <div
                      v-for="sub in folder.children"
                      :key="sub.id"
                      class="nav-item rounded px-2 py-1 d-flex align-center justify-space-between gap-2 cursor-pointer"
                      :class="{ 'nav-item--sub': activeFolder === sub.id }"
                      @click="selectFolder(sub)"
                    >
                      <div class="d-flex align-center flex-grow-1 media-truncate-wrap">
                        <VIcon
                          icon="tabler-folder"
                          size="15"
                          color="primary"
                          class="me-2 flex-shrink-0"
                        />
                        <span
                          class="text-body-1 text-truncate"
                          :class="activeFolder === sub.id ? 'text-primary font-weight-medium' : 'text-medium-emphasis'"
                        >
                          {{ sub.name }}
                        </span>
                      </div>
                      <span class="text-caption text-medium-emphasis flex-shrink-0">
                        {{ sub.count }}
                      </span>
                    </div>
                  </div>
                </div>
                <p
                  v-if="!folderTree.length"
                  class="text-caption text-medium-emphasis px-2 py-1"
                >
                  Chưa có thư mục nào
                </p>
              </div>
            </PerfectScrollbar>

            <!-- Dung lượng lưu trữ cố định dưới cùng cột trái (tính từ items trang đang xem) -->
            <VDivider class="flex-shrink-0" />
            <div class="pa-4 flex-shrink-0">
              <div class="text-body-1 font-weight-medium text-high-emphasis mb-2">
                Dung lượng (trang này)
              </div>
              <div class="d-flex justify-space-between align-center text-caption text-medium-emphasis">
                <span>{{ fmtSize(store.selectedSizeBytes(pagedItems)) }}</span>
                <span class="font-weight-medium text-high-emphasis">{{ pagedItems.length }} tệp</span>
              </div>
            </div>
          </VCol>

          <!-- ============ CỘT GIỮA: LƯỚI MEDIA (col-md-8) ============ -->
          <VCol
            cols="12"
            :md="isRightSidebarOpen ? 8 : 10"
            class="d-flex flex-column media-col"
          >
            <!-- Header trang + nút -->
            <div class="d-flex flex-wrap justify-start justify-sm-space-between align-center gap-y-4 gap-x-6 pa-4 flex-shrink-0">
              <h4 class="text-h4 font-weight-medium">
                Thư viện media
              </h4>
              <div class="d-flex gap-3 align-center flex-wrap">
                <input
                  ref="fileInput"
                  type="file"
                  multiple
                  class="d-none"
                  @change="onFilesChosen"
                >
                <VBtn
                  color="primary"
                  prepend-icon="tabler-upload"
                  :loading="uploading"
                  @click="onUploadClick"
                >
                  Tải lên
                </VBtn>
              </div>
            </div>
            <VDivider class="flex-shrink-0" />

            <!-- Toolbar tìm kiếm + lọc + view (đứng yên) -->
            <div class="d-flex align-center flex-wrap gap-3 pa-4 flex-shrink-0">
              <AppTextField
                v-model="searchQuery"
                placeholder="Tìm kiếm tệp tin..."
                prepend-inner-icon="tabler-search"
                clearable
                density="compact"
                style="min-inline-size: 200px"
                class="flex-grow-1"
              />
              <AppSelect
                v-model="kindFilter"
                :items="kindOptions"
                density="compact"
                style="inline-size: 140px"
              />
              <AppSelect
                v-model="sortFilter"
                :items="sortOptions"
                density="compact"
                style="inline-size: 130px"
              />
              <div class="d-flex align-center border rounded">
                <VBtn
                  :variant="viewMode === 'grid' ? 'flat' : 'text'"
                  :color="viewMode === 'grid' ? 'primary' : 'secondary'"
                  icon="tabler-layout-grid"
                  size="small"
                  class="rounded-0"
                  @click="viewMode = 'grid'"
                />
                <VBtn
                  :variant="viewMode === 'list' ? 'flat' : 'text'"
                  :color="viewMode === 'list' ? 'primary' : 'secondary'"
                  icon="tabler-list"
                  size="small"
                  class="rounded-0"
                  @click="viewMode = 'list'"
                />
              </div>
            </div>
            <VDivider class="flex-shrink-0" />

            <!-- Vùng lưới media: skeleton card khi nạp lần đầu, progress bar mỏng khi lọc/chuyển trang -->
            <div class="media-grid-body d-flex flex-column position-relative">
              <VProgressLinear
                v-if="store.loading && pagedItems.length"
                indeterminate
                color="primary"
                height="3"
                class="media-grid-progress"
              />

              <PerfectScrollbar
                ref="gridScrollRef"
                :options="{ wheelPropagation: false, suppressScrollX: true }"
                class="media-grid-scroll pa-5"
              >
                <!-- Loading lần đầu: Skeleton grid mô phỏng card thật -->
                <div
                  v-if="store.loading && !pagedItems.length"
                  class="media-grid"
                >
                  <div
                    v-for="n in 12"
                    :key="`skeleton-${n}`"
                    class="media-card rounded border pa-2"
                  >
                    <div class="rounded overflow-hidden mb-2 thumb-wrap thumb-square">
                      <VSkeletonLoader
                        type="image"
                        class="media-skeleton-thumb h-100 w-100 bg-transparent"
                      />
                    </div>
                    <VSkeletonLoader
                      type="text"
                      class="media-skeleton-line bg-transparent mb-1"
                    />
                    <VSkeletonLoader
                      type="text"
                      class="media-skeleton-line media-skeleton-line--short bg-transparent"
                    />
                  </div>
                </div>

                <!-- Empty state -->
                <div
                  v-else-if="!pagedItems.length"
                  class="d-flex flex-column align-center justify-center pa-12 text-center"
                >
                  <VAvatar
                    color="secondary"
                    variant="tonal"
                    size="56"
                    class="mb-4"
                  >
                    <VIcon
                      icon="tabler-photo-off"
                      size="28"
                    />
                  </VAvatar>
                  <h6 class="text-h6 mb-1">
                    Chưa có tệp tin nào
                  </h6>
                  <p class="text-body-2 text-medium-emphasis mb-4">
                    {{ activeQuickFilter === 'trash'
                      ? 'Thùng rác trống — các asset đã xóa sẽ hiện ở đây.'
                      : 'Tải lên ảnh/video đầu tiên để bắt đầu.' }}
                  </p>
                  <VBtn
                    v-if="activeQuickFilter !== 'trash'"
                    color="primary"
                    prepend-icon="tabler-upload"
                    :loading="uploading"
                    @click="onUploadClick"
                  >
                    Tải lên
                  </VBtn>
                </div>

                <div
                  v-else
                  class="media-grid"
                  :class="{ 'media-grid--loading': store.loading }"
                >
                  <div
                    v-for="file in pagedItems"
                    :key="file.id"
                    class="media-card rounded border pa-2 cursor-pointer"
                    :class="{ 'media-card--selected': selectedFile && selectedFile.id === file.id }"
                    @click="onSelectFile(file)"
                  >
                    <div>
                      <!-- Thumbnail (vuông) — checkbox + menu phủ lên góc ảnh -->
                      <div class="position-relative rounded overflow-hidden mb-2 thumb-wrap thumb-square">
                        <VImg
                          v-if="file.kind === 'image' && thumbOf(file)"
                          :src="thumbOf(file)"
                          aspect-ratio="1"
                          cover
                        />
                        <div
                          v-else-if="file.kind === 'image'"
                          class="thumb-fallback d-flex align-center justify-center text-medium-emphasis"
                        >
                          <VIcon
                            icon="tabler-photo-off"
                            size="32"
                          />
                        </div>
                        <div
                          v-else-if="file.kind === 'video'"
                          class="thumb-fallback d-flex align-center justify-center bg-primary"
                        >
                          <VIcon
                            icon="tabler-video"
                            size="32"
                            color="white"
                          />
                        </div>
                        <div
                          v-else-if="file.kind === 'audio'"
                          class="thumb-fallback d-flex align-center justify-center bg-info"
                        >
                          <VIcon
                            icon="tabler-music"
                            size="32"
                            color="white"
                          />
                        </div>
                        <div
                          v-else
                          class="thumb-fallback d-flex align-center justify-center bg-error"
                        >
                          <VIcon
                            icon="tabler-file-text"
                            size="32"
                            color="white"
                          />
                        </div>

                        <!-- Checkbox góc trên-trái -->
                        <div class="thumb-corner thumb-corner--tl">
                          <VCheckboxBtn
                            :model-value="selectedIds.has(file.id)"
                            color="primary"
                            density="compact"
                            class="ma-0 pa-0 bg-surface rounded"
                            @click.stop="toggleSelected(file)"
                          />
                        </div>
                        <!-- Menu góc trên-phải -->
                        <div class="thumb-corner thumb-corner--tr">
                          <IconBtn
                            size="x-small"
                            class="bg-surface"
                            @click.stop="onSelectFile(file)"
                          >
                            <VIcon icon="tabler-dots-vertical" />
                          </IconBtn>
                        </div>

                        <span
                          v-if="file.kind !== 'image' && extOf(file)"
                          class="media-badge"
                        >
                          {{ extOf(file) }}
                        </span>
                        <span
                          v-if="file.kind === 'video' && fmtDuration(file.duration_ms)"
                          class="media-badge media-badge--dur d-flex align-center"
                        >
                          <VIcon
                            icon="tabler-player-play"
                            size="10"
                            color="white"
                            class="me-1"
                          />
                          {{ fmtDuration(file.duration_ms) }}
                        </span>
                      </div>

                      <div class="text-body-2 font-weight-medium text-high-emphasis text-truncate mb-1">
                        {{ file.title }}
                      </div>
                      <div class="text-caption text-medium-emphasis text-truncate">
                        {{ fmtSize(file.size) }} • {{ fmtDate(file.created_at) }}
                      </div>
                    </div>
                  </div>
                </div>
              </PerfectScrollbar>
            </div>

            <VDivider
              v-if="selectedIds.size"
              class="flex-shrink-0"
            />

            <!-- Thanh thao tác trên item đã chọn (đứng yên) -->
            <div
              v-if="selectedIds.size"
              class="d-flex align-center flex-wrap gap-2 px-4 py-2 flex-shrink-0"
            >
              <span class="text-body-2 font-weight-medium text-high-emphasis me-1">
                {{ selectedIds.size }} tệp tin đã chọn
              </span>
              <span class="text-caption text-medium-emphasis me-2">({{ selectedSizeText }})</span>
              <VBtn
                variant="tonal"
                color="error"
                size="small"
                prepend-icon="tabler-trash"
                :loading="deleting"
                @click="onDeleteSelected"
              >
                {{ activeQuickFilter === 'trash' ? 'Xóa vĩnh viễn' : 'Xóa' }}
              </VBtn>
              <VBtn
                variant="tonal"
                color="secondary"
                size="small"
                @click="selectedIds = new Set()"
              >
                Bỏ chọn
              </VBtn>
            </div>

            <TablePagination
              v-model:page="page"
              :items-per-page="store.pagination.itemsPerPage"
              :total-items="store.total"
              class="flex-shrink-0"
            />
          </VCol>

          <!-- ============ CỘT PHẢI: CHI TIẾT (col-md-2) ============ -->
          <VCol
            v-if="isRightSidebarOpen"
            cols="12"
            md="2"
            class="border-s d-flex flex-column media-col"
          >
            <MediaDetailSidebar
              :asset="selectedFile"
              :can-prev="canSelectPrev"
              :can-next="canSelectNext"
              :trashed="activeQuickFilter === 'trash'"
              @prev="stepSelectedFile(-1)"
              @next="stepSelectedFile(1)"
              @updated="onAssetUpdated"
              @deleted="onAssetDeleted"
              @close="isRightSidebarOpen = false"
            />
          </VCol>
        </VRow>
      </VCard>
    </VMain>
  </VLayout>
</template>

<style scoped lang="scss">
@use "@styles/variables/vuetify";
@use "@core-scss/base/mixins";

/* Khung app media: đổ bóng + bo góc theo biến card chuẩn Vuexy (giống email-app-layout). */
.media-app-layout {
  border-radius: vuetify.$card-border-radius;
  min-block-size: 100%;

  @include mixins.elevation(vuetify.$card-elevation);
}

/* Card nội dung: flat + bo góc mép tiếp giáp = 0. */
.media-content {
  border-end-start-radius: 0;
  border-start-start-radius: 0;
  border-end-end-radius: 0;
}

/* Cố định chiều cao 100% khung viewport trên desktop (md >= 960px) để 3 cột
   giữ đúng chiều cao khung nhìn, cột giữa ghim phân trang và 2 cột bên cuộn độc lập. */
@media (min-width: 960px) {
  .media-app-layout {
    block-size: 100%;
    max-block-size: 100%;
    overflow: hidden !important;
  }

  .media-main {
    display: flex;
    flex: 1 1 0;
    flex-direction: column;
    block-size: 100%;
    max-block-size: 100%;
    min-block-size: 0;
    overflow: hidden;
  }

  .media-content,
  .media-row {
    flex: 1 1 0;
    block-size: 100%;
    max-block-size: 100%;
    min-block-size: 0;
    overflow: hidden;
  }

  .media-col {
    block-size: 100%;
    max-block-size: 100%;
    min-block-size: 0;
    overflow: hidden;
  }
}

/* Mỗi VCol: flex-column + min-height:0 để PerfectScrollbar bên trong bound được. */
.media-col {
  min-block-size: 0;
  overflow: hidden;
}

/* Giữ vùng chữ cắt ngắn đúng chiều rộng trong flex container cột trái. */
.media-truncate-wrap {
  min-inline-size: 0;
}

/* Khung bọc lưới giữa: giữ min-height:0 để thanh progress mỏng ghim trên đỉnh không đẩy bố cục. */
.media-grid-body {
  flex: 1 1 0;
  min-block-size: 0;
  overflow: hidden;
}

.media-grid-progress {
  position: absolute;
  z-index: 3;
  inset-block-start: 0;
  inset-inline: 0;
}

/* Vùng cuộn co giãn của cột trái và lưới giữa (nhường phần cố định đầu/cuối cột). */
.media-left-scroll,
.media-grid-scroll {
  position: relative;
  flex: 1 1 0;
  min-block-size: 0;
}

/* Lưới media: tối đa 6 item/hàng, co giãn theo chiều ngang cột giữa. */
.media-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
  transition: opacity 0.15s ease;
}

.media-grid--loading {
  opacity: 0.55;
  pointer-events: none;
}

.media-skeleton-thumb :deep(.v-skeleton-loader__image) {
  block-size: 100%;
  inline-size: 100%;
}

.media-skeleton-line :deep(.v-skeleton-loader__text) {
  margin-block: 4px;
  margin-inline: 0;
}

.media-skeleton-line--short :deep(.v-skeleton-loader__text) {
  inline-size: 65%;
  max-inline-size: 65%;
}
@media (max-width: 1280px) {
  .media-grid { grid-template-columns: repeat(4, 1fr); }
}
@media (max-width: 900px) {
  .media-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 600px) {
  .media-grid { grid-template-columns: repeat(2, 1fr); }
}

/* Điều hướng trái */
.nav-item {
  transition: background 0.15s ease;
}

/* Hover chỉ áp cho item CHƯA active — item đang active giữ nguyên màu primary. */
.nav-item:hover:not(.nav-item--active):not(.nav-item--parent):not(.nav-item--sub) {
  background: rgba(var(--v-theme-primary), 0.06);
}
.nav-item--active {
  background: rgb(var(--v-theme-primary));
}
/* Item đang active khi hover: đậm hơn chút, không bị trắng bệt. */
.nav-item--active:hover {
  background: rgba(var(--v-theme-primary), 0.85);
}
.nav-item--parent:hover {
  background: rgba(var(--v-theme-primary), 0.16);
}
.nav-item--sub:hover {
  background: rgba(var(--v-theme-primary), 0.2);
}
.nav-item--parent {
  background: rgba(var(--v-theme-primary), 0.08);
}
.nav-item--sub {
  background: rgba(var(--v-theme-primary), 0.12);
}

/* Chip số lượng khi item active: nền trắng, chữ primary nổi trên nền tím. */
.nav-count--active {
  background: #fff !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.media-card {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.media-card:hover {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 4px 14px rgba(var(--v-theme-primary), 0.14);
}
.media-card--selected {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.04);
  /* Viền chọn bằng box-shadow (không đổi border-width) -> item không co/nhảy chữ. */
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}
/* Khi đã chọn + hover: giữ nguyên vòng xanh 1px + thêm glow — không đổi độ dày viền. */
.media-card--selected:hover {
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary)), 0 4px 14px rgba(var(--v-theme-primary), 0.18);
}

/* Khung thumbnail: nền xám sáng lộ ra ở phần dư khi ảnh không vuông. */
.thumb-wrap {
  background: rgba(var(--v-theme-on-surface), 0.08);
}

/* Ô thumbnail vuông: khung 1:1 + VImg contain -> chiều dài ảnh lấp đầy,
   phần nền dư (letterbox) là màu thumb-wrap. */
.thumb-square {
  aspect-ratio: 1;
  inline-size: 100%;
}
.thumb-square :deep(.v-img__img) {
  object-fit: contain;
}

/* Hiệu ứng zoom nhẹ khi hover vào thumbnail. */
.thumb-square :deep(.v-img) {
  transition: transform 0.3s ease;
  will-change: transform;
}
.thumb-square:hover :deep(.v-img) {
  transform: scale(1.08);
}
.thumb-fallback {
  inline-size: 100%;
  block-size: 100%;
  transition: transform 0.3s ease;
}
.thumb-square:hover .thumb-fallback {
  transform: scale(1.08);
}

/* Checkbox + menu phủ lên 2 góc trên của thumbnail vuông. */
.thumb-corner {
  position: absolute;
  z-index: 2;
}
.thumb-corner--tl { inset-block-start: 4px; inset-inline-start: 4px; }
.thumb-corner--tr { inset-block-start: 4px; inset-inline-end: 4px; }

.media-badge {
  position: absolute;
  inset-block-end: 6px;
  inset-inline-end: 6px;
  background: rgba(0, 0, 0, 0.65);
  color: #fff;
  font-size: 10px;
  font-weight: 500;
  padding: 1px 5px;
  border-radius: 4px;
}
</style>
