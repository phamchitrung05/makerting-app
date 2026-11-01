<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang Quản lý Thư viện Media — bố cục 3 cột cố định viewport.
  =====================================================================

  Chuẩn hoá theo trang `resources/js/pages/apps/email` (Vuexy fixed-height):
  - `VLayout.media-app-layout` nhận đổ bóng + bo góc qua
  `mixins.elevation(vuetify.$card-elevation)` (KHÔNG prop elevation).
  - `VMain` > `VCard flat h-100 d-flex flex-column` chứa 1 `VRow no-gutters`
  3 `VCol` (hệ col-md-2/8/2) — dùng grid chuẩn project, không tự dựng flex.
  - Mỗi `VCol` là `d-flex flex-column` + `min-block-size:0`; vùng cuộn là
  `PerfectScrollbar` (trái/phải = toàn bộ cột, giữa = lưới), toolbar +
  action bar + `TablePagination` đứng yên.
  - Toàn trang KHÔNG cuộn; chỉ vùng lưới (giữa) & chi tiết (phải) cuộn.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - toggleFolder(folder)   : mở/đóng nhánh thư mục cha + chọn folder cha.
  - selectQuickFilter(id)  : đổi bộ lọc nhanh cột trái.
  - onSelectFile(file)     : chọn 1 media -> hiển thị chi tiết cột phải.
  - pagedFiles             : computed cắt mediaFiles theo page/itemsPerPage.
  - thumbOf(file)          : lấy url thumbnail/preview cho item.
  - pagedFiles             : computed cắt mediaFiles theo page/itemsPerPage.
  - thumbOf(file)          : lấy url thumbnail/preview cho item.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : dữ liệu mock trong file (quickFilters/folderTree/mediaFiles);
  sau này thay bằng useMediaAssetStore + mediaApi.
  - OUTPUT: UI 3 cột; chọn file -> panel chi tiết phải; phân trang giữa.
  =====================================================================
-->
<script setup>
import { computed, reactive, ref } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

definePage({
  meta: {
    layout: 'default',
    layoutWrapperClasses: 'layout-content-height-fixed',
  },
})

const isRightSidebarOpen = ref(true)

const activeQuickFilter = ref('all')
const activeFolder = ref('products')
const activeSubFolder = ref('prod_images')
const viewMode = ref('grid')
const searchQuery = ref('')
const kindFilter = ref('all')
const sortFilter = ref('newest')
const previewTab = ref('info')

/* Phân trang cột giữa (mock client-side — khi nối API sẽ do server trả về). */
const page = ref(1)
const itemsPerPage = ref(18)   /* bội số của 6 -> lưới 6 item/hàng đều */
const totalFiles = ref(36)

/* Bộ lọc nhanh cột trái (mock — sẽ bind số liệu thật từ store). */
const quickFilters = [
  { id: 'all', name: 'Tất cả tệp tin', count: '1,284', icon: 'tabler-folders' },
  { id: 'recent', name: 'Gần đây', count: '32', icon: 'tabler-clock' },
  { id: 'favorites', name: 'Yêu thích', count: '86', icon: 'tabler-heart' },
  { id: 'shared', name: 'Đã chia sẻ', count: '12', icon: 'tabler-share' },
  { id: 'trash', name: 'Thùng rác', count: '8', icon: 'tabler-trash' },
]

const kindOptions = [
  { title: 'Mọi loại', value: 'all' },
  { title: 'Hình ảnh', value: 'image' },
  { title: 'Video', value: 'video' },
  { title: 'Tài liệu', value: 'document' },
]

const sortOptions = [
  { title: 'Mới nhất', value: 'newest' },
  { title: 'Cũ nhất', value: 'oldest' },
  { title: 'Tên A–Z', value: 'name' },
  { title: 'Dung lượng', value: 'size' },
]

/* Cây thư mục (mock). */
const folderTree = ref([
  {
    id: 'products',
    name: 'Sản phẩm',
    count: '328',
    expanded: true,
    children: [
      { id: 'prod_images', name: 'Ảnh sản phẩm', count: '210' },
      { id: 'prod_variants', name: 'Ảnh biến thể', count: '56' },
      { id: 'prod_videos', name: 'Video sản phẩm', count: '62' },
    ],
  },
  { id: 'posts', name: 'Bài viết', count: '156', expanded: false },
  { id: 'banners', name: 'Banner', count: '98', expanded: false },
  { id: 'designs', name: 'Thiết kế', count: '124', expanded: false },
  { id: 'logos', name: 'Logo', count: '42', expanded: false },
  { id: 'docs', name: 'Tài liệu', count: '68', expanded: false },
  {
    id: 'social',
    name: 'Mạng xã hội',
    count: '186',
    expanded: true,
    children: [
      { id: 'fb', name: 'Facebook', count: '64' },
      { id: 'ig', name: 'Instagram', count: '52' },
      { id: 'tt', name: 'TikTok', count: '48' },
      { id: 'yt', name: 'YouTube', count: '22' },
    ],
  },
  { id: 'others', name: 'Khác', count: '302', expanded: false },
])

/* Lưới media (mock — cấu trúc gần MediaItemDto: id/kind/title/size/date/thumb). */
const mediaFiles = ref([
  { id: 1, name: 'cha-ram-tom-xu-nau.jpg', size: '2.4 MB', date: '28/10/2026', type: 'image', extBadge: 'JPG', aiModel: 'Leonardo AI', thumbnail: 'https://images.unsplash.com/photo-1544025162-d76694265947?w=300', previewImg: 'https://images.unsplash.com/photo-1544025162-d76694265947?w=600', selected: true },
  { id: 2, name: 'tra-sua-matcha.jpg', size: '1.8 MB', date: '28/10/2026', type: 'image', aiModel: 'Midjourney', thumbnail: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=300', previewImg: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600', selected: false },
  { id: 3, name: 'ca-phe-hat.jpg', size: '3.1 MB', date: '27/10/2026', type: 'image', aiModel: 'DALL-E 3', thumbnail: 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=300', previewImg: 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=600', selected: false },
  { id: 4, name: 'video-san-pham.mp4', size: '12.6 MB', date: '27/10/2026', type: 'video', duration: '00:28', aiModel: 'Runway', thumbnail: 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=300', previewImg: 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=600', selected: false },
  { id: 5, name: 'quy-nhon-bien.jpg', size: '4.2 MB', date: '26/10/2026', type: 'image', aiModel: 'Midjourney', thumbnail: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=300', selected: false },
  { id: 6, name: 'menu-quan-cafe.jpg', size: '2.1 MB', date: '26/10/2026', type: 'image', aiModel: 'Firefly', thumbnail: 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=300', selected: false },
  { id: 7, name: 'logo-thuong-hieu.png', size: '850 KB', date: '25/10/2026', type: 'image', aiModel: 'Ideogram', thumbnail: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=300', selected: false },
  { id: 8, name: 'nhan-san-pham.jpg', size: '1.9 MB', date: '25/10/2026', type: 'image', aiModel: 'GPT-4o', thumbnail: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300', selected: false },
  { id: 9, name: 'quy-trinh-san-xuat.mp4', size: '28.4 MB', date: '24/10/2026', type: 'video', duration: '01:15', aiModel: 'Kling', thumbnail: 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=300', selected: false },
  { id: 10, name: 'khong-gian-quan.jpg', size: '3.6 MB', date: '24/10/2026', type: 'image', aiModel: 'Midjourney', thumbnail: 'https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=300', selected: false },
  { id: 11, name: 'thiet-ke-bao-bi.psd', size: '120 MB', date: '23/10/2026', type: 'psd', selected: false },
  { id: 12, name: 'menu-in-an.pdf', size: '8.5 MB', date: '23/10/2026', type: 'pdf', selected: false },
  { id: 13, name: 'do-uong-moi.jpg', size: '2.8 MB', date: '22/10/2026', type: 'image', aiModel: 'SDXL', thumbnail: 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=300', selected: false },
  { id: 14, name: 'hieu-ung-quang-cao.gif', size: '3.2 MB', date: '22/10/2026', type: 'image', extBadge: 'GIF', aiModel: 'Pika', thumbnail: 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=300', selected: false },
  { id: 15, name: 'mon-an-dac-san.jpg', size: '2.4 MB', date: '21/10/2026', type: 'image', aiModel: 'Flux.1', thumbnail: 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=300', selected: false },
  { id: 16, name: 'mat-tien-quan.jpg', size: '4.1 MB', date: '21/10/2026', type: 'image', aiModel: 'Realistic Vision', thumbnail: 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=300', selected: false },
  { id: 17, name: 'banh-mi-dac-biet.jpg', size: '2.2 MB', date: '20/10/2026', type: 'image', aiModel: 'Midjourney', thumbnail: 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=300', selected: false },
  { id: 18, name: 'pho-bo-ha-noi.jpg', size: '3.4 MB', date: '20/10/2026', type: 'image', aiModel: 'Leonardo AI', thumbnail: 'https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?w=300', selected: false },
  { id: 19, name: 'intro-video.mp4', size: '18.9 MB', date: '20/10/2026', type: 'video', duration: '00:45', aiModel: 'Runway', thumbnail: 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?w=300', selected: false },
  { id: 20, name: 'ca-phe-sua-da.jpg', size: '2.7 MB', date: '19/10/2026', type: 'image', aiModel: 'DALL-E 3', thumbnail: 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=300', selected: false },
  { id: 21, name: 'sinh-to-xoai.jpg', size: '1.6 MB', date: '19/10/2026', type: 'image', aiModel: 'Flux.1', thumbnail: 'https://images.unsplash.com/photo-1546173159-315724a31696?w=300', selected: false },
  { id: 22, name: 'brochure-quan.pdf', size: '5.4 MB', date: '19/10/2026', type: 'pdf', selected: false },
  { id: 23, name: 'tra-dao-cam-sa.jpg', size: '2.0 MB', date: '18/10/2026', type: 'image', aiModel: 'SDXL', thumbnail: 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=300', selected: false },
  { id: 24, name: 'banh-flan-cafe.jpg', size: '2.9 MB', date: '18/10/2026', type: 'image', aiModel: 'Firefly', thumbnail: 'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=300', selected: false },
  { id: 25, name: 'banner-khuyen-mai.psd', size: '95 MB', date: '18/10/2026', type: 'psd', selected: false },
  { id: 26, name: 'goi-cuon-tom.jpg', size: '2.5 MB', date: '17/10/2026', type: 'image', aiModel: 'Midjourney', thumbnail: 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=300', selected: false },
  { id: 27, name: 'khong-gian-tang-2.jpg', size: '3.8 MB', date: '17/10/2026', type: 'image', aiModel: 'Realistic Vision', thumbnail: 'https://images.unsplash.com/photo-1559925393-8be0ec4767c8?w=300', selected: false },
  { id: 28, name: 'nuoc-ep-cam.jpg', size: '1.4 MB', date: '17/10/2026', type: 'image', extBadge: 'JPG', aiModel: 'Ideogram', thumbnail: 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=300', selected: false },
  { id: 29, name: 'review-mon-an.mp4', size: '24.2 MB', date: '16/10/2026', type: 'video', duration: '01:32', aiModel: 'Kling', thumbnail: 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=300', selected: false },
  { id: 30, name: 'combo-trua.jpg', size: '2.1 MB', date: '16/10/2026', type: 'image', aiModel: 'GPT-4o', thumbnail: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300', selected: false },
  { id: 31, name: 'menu-do-uong.pdf', size: '4.7 MB', date: '16/10/2026', type: 'pdf', selected: false },
  { id: 32, name: 'banh-ngot-sang.jpg', size: '3.3 MB', date: '15/10/2026', type: 'image', aiModel: 'Pika', thumbnail: 'https://images.unsplash.com/photo-1509365465985-25d11c17e812?w=300', selected: false },
  { id: 33, name: 'logo-phien-ban-2.png', size: '720 KB', date: '15/10/2026', type: 'image', aiModel: 'Ideogram', thumbnail: 'https://images.unsplash.com/photo-1558655146-9f40138edfeb?w=300', selected: false },
  { id: 34, name: 'ca-phe-pour-over.jpg', size: '2.6 MB', date: '15/10/2026', type: 'image', aiModel: 'DALL-E 3', thumbnail: 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=300', selected: false },
  { id: 35, name: 'tra-sua-tran-chau.jpg', size: '2.4 MB', date: '14/10/2026', type: 'image', aiModel: 'Midjourney', thumbnail: 'https://images.unsplash.com/photo-1525385133517-0d4f7a8f8e1f?w=300', selected: false },
  { id: 36, name: 'thanh-pho-ve-dem.gif', size: '6.1 MB', date: '14/10/2026', type: 'image', extBadge: 'GIF', aiModel: 'Pika', thumbnail: 'https://images.unsplash.com/photo-1519501025264-65ba15a82390?w=300', selected: false },
])

/* File đang chọn xem chi tiết + ảnh preview mặc định. */
const selectedFile = ref(mediaFiles.value[0])
const defaultPreviewImg = 'https://images.unsplash.com/photo-1544025162-d76694265947?w=600'

/* Form thuộc tính chi tiết cột phải (mock). */
const detailForm = reactive({
  fileName: 'cha-ram-tom-xu-nau.jpg',
  filePath: '/san-pham/anh-san-pham/',
  altText: 'Chả ram tôm Xứ Nẫu, món đặc sản Bình Định',
  description: 'Ảnh sản phẩm chả ram tôm Xứ Nẫu, trình bày trên mẹt tre, kèm rau sống và nước chấm, chụp thực tế.',
  tags: ['sản phẩm', 'chả ram', 'tôm', 'đặc sản', 'bình định'],
})

/*
 * Media hiển thị ở trang hiện tại — cắt mediaFiles theo page/itemsPerPage.
 * (Mock client-side; khi nối API sẽ do server phân trang, computed này bỏ.)
 */
const pagedFiles = computed(() => {
  const start = (page.value - 1) * itemsPerPage.value

  return mediaFiles.value.slice(start, start + itemsPerPage.value)
})

/*
 * CHỨC NĂNG: Mở/đóng nhánh thư mục cha trong cây cột trái.
 * INPUT: folder (node trong folderTree). OUTPUT: đảo folder.expanded.
 * SIDE EFFECT: chọn luôn folder cha (activeFolder).
 */
function toggleFolder(folder) {
  folder.expanded = !folder.expanded
  activeFolder.value = folder.id
}

/*
 * CHỨC NĂNG: Chọn bộ lọc nhanh (tất cả/gần đây/yêu thích/chia sẻ/rác).
 * INPUT: id bộ lọc. OUTPUT: activeQuickFilter = id.
 * SIDE EFFECT: (nối API sau) sẽ gọi store.setFilter/refresh.
 */
function selectQuickFilter(id) {
  activeQuickFilter.value = id
}

/*
 * CHỨC NĂNG: Chọn 1 media trong lưới -> cột phải hiển thị preview + metadata.
 * INPUT: file (item mediaFiles). OUTPUT: selectedFile = file; sync detailForm.
 */
function onSelectFile(file) {
  selectedFile.value = file
  detailForm.fileName = file.name
  isRightSidebarOpen.value = true
}

/* Nguồn ảnh thumbnail cho item: ưu tiên thumbnail, fallback previewImg. */
function thumbOf(file) {
  return file.thumbnail || file.previewImg || defaultPreviewImg
}
</script>

<template>
  <VLayout
    class="media-app-layout"
    style="min-block-size: 100%; z-index: 0"
  >
    <VMain>
      <VCard
        flat
        class="media-content h-100 d-flex flex-column"
      >
        <VRow
          no-gutters
          class="flex-grow-1"
          style="min-block-size: 0"
        >
          <!-- ============ CỘT TRÁI: ĐIỀU HƯỚNG (col-md-2) ============ -->
          <VCol
            cols="12"
            md="2"
            class="border-e d-flex flex-column media-col"
          >
            <PerfectScrollbar
              :options="{ wheelPropagation: false, suppressScrollX: true }"
              class="media-scroll pa-5"
            >
              <!-- Bộ lọc nhanh -->
              <div class="d-flex flex-column gap-2 mb-5">
                <div
                  v-for="item in quickFilters"
                  :key="item.id"
                  class="nav-item rounded pa-2 d-flex align-center justify-space-between cursor-pointer"
                  :class="{ 'nav-item--active': activeQuickFilter === item.id }"
                  @click="selectQuickFilter(item.id)"
                >
                  <div class="d-flex align-center text-truncate">
                    <VIcon
                      :icon="item.icon"
                      size="18"
                      :color="activeQuickFilter === item.id ? 'white' : undefined"
                      class="me-2"
                    />
                    <span
                      class="text-body-2 font-weight-medium text-truncate"
                      :class="activeQuickFilter === item.id ? 'text-white' : 'text-high-emphasis'"
                    >
                      {{ item.name }}
                    </span>
                  </div>
                  <VChip
                    size="x-small"
                    :variant="activeQuickFilter === item.id ? 'flat' : 'tonal'"
                    :color="activeQuickFilter === item.id ? undefined : 'secondary'"
                    :class="activeQuickFilter === item.id ? 'nav-count--active' : ''"
                    label
                  >
                    {{ item.count }}
                  </VChip>
                </div>
              </div>

              <!-- Cây thư mục -->
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-caption font-weight-medium text-medium-emphasis text-uppercase">
                  Thư mục
                </span>
                <IconBtn size="small">
                  <VIcon icon="tabler-plus" />
                </IconBtn>
              </div>

              <div class="d-flex flex-column gap-2">
                <div
                  v-for="folder in folderTree"
                  :key="folder.id"
                  class="d-flex flex-column"
                >
                  <div
                    class="nav-item rounded px-2 py-1 d-flex align-center justify-space-between cursor-pointer"
                    :class="{ 'nav-item--parent': activeFolder === folder.id }"
                    @click="toggleFolder(folder)"
                  >
                    <div class="d-flex align-center text-truncate">
                      <VIcon
                        v-if="folder.children"
                        :icon="folder.expanded ? 'tabler-chevron-down' : 'tabler-chevron-right'"
                        size="15"
                        class="me-1"
                      />
                      <VIcon
                        v-else
                        icon="tabler-point"
                        size="15"
                        class="me-1"
                      />
                      <VIcon
                        :icon="folder.expanded ? 'tabler-folder-open' : 'tabler-folder'"
                        size="17"
                        color="primary"
                        class="me-2"
                      />
                      <span class="text-body-2 text-high-emphasis font-weight-medium text-truncate">
                        {{ folder.name }}
                      </span>
                    </div>
                    <span class="text-caption text-medium-emphasis">
                      {{ folder.count }}
                    </span>
                  </div>

                  <!-- Thư mục con -->
                  <div
                    v-if="folder.children && folder.expanded"
                    class="d-flex flex-column ms-5 gap-2 my-1"
                  >
                    <div
                      v-for="sub in folder.children"
                      :key="sub.id"
                      class="nav-item rounded px-2 py-1 d-flex align-center justify-space-between cursor-pointer"
                      :class="{ 'nav-item--sub': activeSubFolder === sub.id }"
                      @click="activeSubFolder = sub.id"
                    >
                      <div class="d-flex align-center text-truncate">
                        <VIcon
                          icon="tabler-folder"
                          size="15"
                          color="primary"
                          class="me-2"
                        />
                        <span
                          class="text-body-2 text-truncate"
                          :class="activeSubFolder === sub.id ? 'text-primary font-weight-medium' : 'text-medium-emphasis'"
                        >
                          {{ sub.name }}
                        </span>
                      </div>
                      <span class="text-caption text-medium-emphasis">
                        {{ sub.count }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Dung lượng lưu trữ -->
              <div class="pt-4 border-t mt-4">
                <div class="text-body-2 font-weight-medium text-high-emphasis mb-2">
                  Dung lượng lưu trữ
                </div>
                <VProgressLinear
                  model-value="25"
                  color="primary"
                  height="6"
                  rounded
                  class="mb-2"
                />
                <div class="d-flex justify-space-between text-caption text-medium-emphasis">
                  <span>12.5 GB / 50 GB</span>
                  <span class="font-weight-medium text-high-emphasis">25%</span>
                </div>
              </div>
            </PerfectScrollbar>
          </VCol>

          <!-- ============ CỘT GIỮA: LƯỚI MEDIA (col-md-8) ============ -->
          <VCol
            cols="12"
            md="8"
            class="d-flex flex-column media-col"
          >
            <!-- Header trang + nút -->
            <div class="d-flex flex-wrap justify-start justify-sm-space-between align-center gap-y-4 gap-x-6 pa-4">
              <h4 class="text-h4 font-weight-medium">
                Thư viện media
              </h4>
              <div class="d-flex gap-3 align-center flex-wrap">
                <VBtn
                  variant="tonal"
                  color="secondary"
                  prepend-icon="tabler-checkbox"
                >
                  Chọn
                </VBtn>
                <VBtn
                  variant="tonal"
                  color="secondary"
                  prepend-icon="tabler-folder-plus"
                >
                  Tạo thư mục
                </VBtn>
                <VBtn
                  color="primary"
                  prepend-icon="tabler-upload"
                >
                  Tải lên
                </VBtn>
              </div>
            </div>
            <VDivider />

            <!-- Toolbar tìm kiếm + lọc + view (đứng yên) -->
            <div class="d-flex align-center flex-wrap gap-3 pa-4">
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
            <VDivider />

            <!-- Lưới media (vùng cuộn duy nhất của cột giữa) -->
            <PerfectScrollbar
              :options="{ wheelPropagation: false, suppressScrollX: true }"
              class="media-grid-scroll pa-5"
            >
              <div class="media-grid">
                <div
                  v-for="file in pagedFiles"
                  :key="file.id"
                  class="media-card rounded border pa-2 cursor-pointer"
                  :class="{ 'media-card--selected': selectedFile && selectedFile.id === file.id }"
                  @click="onSelectFile(file)"
                >
                  <div>
                    <!-- Thumbnail (vuông) — checkbox + menu phủ lên góc ảnh -->
                    <div class="position-relative rounded overflow-hidden mb-2 thumb-wrap thumb-square">
                      <VImg
                        v-if="file.type === 'image' || file.type === 'video'"
                        :src="thumbOf(file)"
                        aspect-ratio="1"
                      />
                      <div
                        v-else-if="file.type === 'psd'"
                        class="thumb-fallback d-flex align-center justify-center bg-primary"
                      >
                        <span class="text-white text-h5 font-weight-medium">Ps</span>
                      </div>
                      <div
                        v-else-if="file.type === 'pdf'"
                        class="thumb-fallback d-flex align-center justify-center bg-error"
                      >
                        <span class="text-white text-h5 font-weight-medium">PDF</span>
                      </div>

                      <!-- Checkbox góc trên-trái -->
                      <div class="thumb-corner thumb-corner--tl">
                        <VCheckboxBtn
                          v-model="file.selected"
                          color="primary"
                          density="compact"
                          class="ma-0 pa-0 bg-surface rounded"
                          @click.stop
                        />
                      </div>
                      <!-- Menu góc trên-phải -->
                      <div class="thumb-corner thumb-corner--tr">
                        <IconBtn
                          size="x-small"
                          class="bg-surface"
                          @click.stop
                        >
                          <VIcon icon="tabler-dots-vertical" />
                        </IconBtn>
                      </div>

                      <span
                        v-if="file.extBadge"
                        class="media-badge"
                      >{{ file.extBadge }}</span>
                      <span
                        v-if="file.duration"
                        class="media-badge media-badge--dur d-flex align-center"
                      >
                        <VIcon
                          icon="tabler-player-play"
                          size="10"
                          color="white"
                          class="me-1"
                        />{{ file.duration }}
                      </span>
                    </div>

                    <div class="text-body-2 font-weight-medium text-high-emphasis text-truncate mb-1">
                      {{ file.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis text-truncate">
                      {{ file.size }} • {{ file.date }}
                    </div>
                  </div>
                </div>
              </div>
            </PerfectScrollbar>

            <VDivider />

            <!-- Thanh thao tác trên item đã chọn (đứng yên) -->
            <div class="d-flex align-center flex-wrap gap-2 px-4 py-2">
              <span class="text-body-2 font-weight-medium text-high-emphasis me-1">
                1 tệp tin đã chọn
              </span>
              <span class="text-caption text-medium-emphasis me-2">(2.4 MB)</span>
              <VBtn
                variant="tonal"
                color="primary"
                size="small"
                prepend-icon="tabler-download"
              >
                Tải xuống
              </VBtn>
              <VBtn
                variant="tonal"
                color="secondary"
                size="small"
                prepend-icon="tabler-folder-share"
              >
                Di chuyển
              </VBtn>
              <VBtn
                variant="tonal"
                color="secondary"
                size="small"
                prepend-icon="tabler-copy"
              >
                Sao chép
              </VBtn>
              <VBtn
                variant="tonal"
                color="error"
                size="small"
                prepend-icon="tabler-trash"
              >
                Xóa
              </VBtn>
            </div>

            <TablePagination
              v-model:page="page"
              :items-per-page="itemsPerPage"
              :total-items="totalFiles"
            />
          </VCol>

          <!-- ============ CỘT PHẢI: CHI TIẾT (col-md-2) ============ -->
          <VCol
            v-if="isRightSidebarOpen"
            cols="12"
            md="2"
            class="border-s d-flex flex-column media-col"
          >
            <PerfectScrollbar
              :options="{ wheelPropagation: false, suppressScrollX: true }"
              class="media-scroll pa-5"
            >
              <!-- Header xem trước -->
              <div class="d-flex justify-space-between align-center mb-3">
                <span class="text-subtitle-1 font-weight-medium text-high-emphasis">
                  Xem trước
                </span>
                <div class="d-flex align-center">
                  <IconBtn size="small">
                    <VIcon icon="tabler-chevron-left" />
                  </IconBtn>
                  <IconBtn size="small">
                    <VIcon icon="tabler-chevron-right" />
                  </IconBtn>
                  <IconBtn
                    size="small"
                    @click="isRightSidebarOpen = false"
                  >
                    <VIcon icon="tabler-x" />
                  </IconBtn>
                </div>
              </div>

              <!-- Ảnh xem trước -->
              <div class="rounded overflow-hidden mb-4 border">
                <VImg
                  :src="selectedFile ? (selectedFile.previewImg || selectedFile.thumbnail) : defaultPreviewImg"
                  height="180"
                  cover
                  class="bg-grey-lighten-3"
                />
              </div>

              <!-- Tabs -->
              <VTabs
                v-model="previewTab"
                density="compact"
                class="mb-4"
              >
                <VTab value="info">
                  <VIcon
                    icon="tabler-photo"
                    size="15"
                    class="me-2"
                  />Thông tin
                </VTab>
                <VTab value="prompt">
                  <VIcon
                    icon="tabler-wand"
                    size="15"
                    class="me-2"
                  />Prompt
                </VTab>
              </VTabs>

              <!-- Nút hành động nhanh -->
              <div class="d-flex gap-2 mb-5">
                <VBtn
                  color="primary"
                  size="small"
                  prepend-icon="tabler-download"
                  class="flex-grow-1"
                >
                  Tải xuống
                </VBtn>
                <VBtn
                  variant="tonal"
                  color="secondary"
                  size="small"
                  icon="tabler-share"
                />
                <VBtn
                  variant="tonal"
                  color="secondary"
                  size="small"
                  icon="tabler-edit"
                />
                <VBtn
                  variant="tonal"
                  color="error"
                  size="small"
                  icon="tabler-trash"
                />
              </div>

              <!-- Form thuộc tính -->
              <div class="d-flex flex-column gap-4 mb-5">
                <AppTextField
                  v-model="detailForm.fileName"
                  label="Tên tệp"
                />
                <AppTextField
                  v-model="detailForm.filePath"
                  label="Đường dẫn"
                  readonly
                />
                <AppTextarea
                  v-model="detailForm.altText"
                  label="Alt ảnh"
                  rows="2"
                  auto-grow
                  counter="255"
                />
                <AppTextarea
                  v-model="detailForm.description"
                  label="Mô tả"
                  rows="3"
                  auto-grow
                  counter="500"
                />
                <div>
                  <VLabel class="mb-2 text-body-2">
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
                    >
                      {{ tag }}
                    </VChip>
                    <VChip
                      size="small"
                      variant="outlined"
                      color="secondary"
                      prepend-icon="tabler-plus"
                    >
                      Thêm thẻ
                    </VChip>
                  </div>
                </div>
              </div>

              <VDivider class="mb-4" />

              <!-- Thông tin khác -->
              <div>
                <div class="text-subtitle-1 font-weight-medium text-high-emphasis mb-3">
                  Thông tin khác
                </div>
                <div class="d-flex flex-column gap-2 text-body-2">
                  <div class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Kích thước</span>
                    <span class="text-high-emphasis">1920 × 1280</span>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Định dạng</span>
                    <span class="text-high-emphasis">image/jpeg</span>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Dung lượng</span>
                    <span class="text-high-emphasis">2.4 MB</span>
                  </div>
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-medium-emphasis">Trạng thái</span>
                    <VChip
                      size="small"
                      color="success"
                      variant="tonal"
                      label
                    >
                      Đang hoạt động
                    </VChip>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Người tạo</span>
                    <span class="text-high-emphasis">Admin</span>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span class="text-medium-emphasis">Cập nhật</span>
                    <span class="text-high-emphasis">28/10/2026 14:32</span>
                  </div>
                </div>
              </div>
            </PerfectScrollbar>
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

  @include mixins.elevation(vuetify.$card-elevation);
}

/* Card nội dung: flat + bo góc mép tiếp giáp = 0. */
.media-content {
  border-end-start-radius: 0;
  border-start-start-radius: 0;
  border-end-end-radius: 0;
}

/* Mỗi VCol: flex-column + min-height:0 để PerfectScrollbar bên trong bound được. */
.media-col {
  min-block-size: 0;
  overflow: hidden;
}

/* Vùng cuộn: PerfectScrollbar cao 100% khung chứa (cột trái/phải). */
.media-scroll {
  block-size: 100%;
  min-block-size: 0;
}

/* Lưới cuộn (vùng duy nhất co giãn của cột giữa). */
.media-grid-scroll {
  flex: 1 1 0;
  min-block-size: 0;
}

/* Lưới media: tối đa 6 item/hàng, co giãn theo chiều ngang cột giữa. */
.media-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
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
