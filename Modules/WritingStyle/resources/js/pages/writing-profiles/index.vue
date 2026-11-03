<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang quản lý DNA Văn phong (Writing Profiles).
  =====================================================================
  Hiển thị danh sách hồ sơ văn phong (16 RULE_KEYS) — giao diện demo tĩnh
  chờ nối API /api/v1/writing-style/profiles (P2-02).

  CHUẨN UI (docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §2–3):
  - Icon Tabler (tabler-*), KHÔNG mdi-*.
  - Màu theme Vuetify / rgb(var(--v-theme-*)) cho hiệu ứng alpha —
    không hex cứng (#7367F0, #fff...); Dark Mode tự đúng.
  - VCard/VBtn/VChip/VAvatar PascalCase; border thay elevation.
  - Không inline font-size / !important — dùng text-h4/body/caption.
  - Spacing class Vuetify chuẩn (gap-2, pa-4, mb-3...).

  CÁC HÀM/METHOD TRONG FILE: chỉ filter computed (demo).
  INPUT/OUTPUT: route /admin/writing-style/writing-profiles.
  SIDE EFFECT: không.
  =====================================================================
-->
<template>
  <div class="d-flex flex-column gap-6 writing-profiles-page">
    <!-- ================= 1. HEADER TRANG ================= -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 align-center">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium mb-1">
          Danh sách Writing Profile
        </h4>
        <div class="text-body-2 text-medium-emphasis">
          Quản lý các DNA văn phong, giọng điệu và quy tắc nội dung cho nhiều kênh và chiến dịch.
        </div>
      </div>

      <div class="d-flex gap-2 align-center">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-upload"
        >
          Nhập từ mẫu
        </VBtn>

        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
        >
          Tạo Writing Profile
        </VBtn>
      </div>
    </div>

    <!-- ================= 2. HÀNG 4 THẺ THỐNG KÊ (KPI CARDS) ================= -->
    <VRow dense>
      <VCol
        v-for="kpi in kpiCards"
        :key="kpi.label"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard
          border
          class="pa-4 h-100 d-flex align-center"
        >
          <VAvatar
            :color="kpi.color"
            variant="tonal"
            size="46"
            rounded="circle"
            class="me-3 flex-shrink-0"
          >
            <VIcon
              :color="kpi.color"
              size="22"
              :icon="kpi.icon"
            />
          </VAvatar>

          <div>
            <div class="text-caption text-medium-emphasis mb-1">
              {{ kpi.label }}
            </div>
            <div class="d-flex align-baseline gap-2">
              <span class="text-h5 font-weight-medium">{{ kpi.value }}</span>
              <span
                v-if="kpi.trend"
                class="text-caption font-weight-medium text-success"
              >
                {{ kpi.trend }}
              </span>
            </div>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- ================= 3. KHUNG CHÍNH: BỘ LỌC & LƯỚI WRITING PROFILE ================= -->
    <VCard
      border
      class="pa-5"
    >
      <!-- Thanh Tabs Trạng thái & Bộ lọc Tìm kiếm -->
      <div class="d-flex align-center justify-space-between flex-wrap gap-3 pb-3 mb-5 border-b">
        <!-- Tabs lọc theo trạng thái (underline custom, giữ nguyên thiết kế) -->
        <div class="d-flex align-center flex-wrap gap-1">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            type="button"
            class="status-tab-btn px-3 py-2 text-caption d-flex align-center cursor-pointer"
            :class="{ 'status-tab-active': activeStatusTab === tab.value }"
            @click="activeStatusTab = tab.value"
          >
            <span
              v-if="tab.dotColor"
              class="tab-dot me-2"
              :style="{ backgroundColor: `rgb(var(--v-theme-${tab.dotColor}))` }"
            ></span>
            <span>{{ tab.label }} ({{ tab.count }})</span>
          </button>
        </div>

        <!-- Cụm Tìm kiếm, Lọc kênh, Sắp xếp & Chuyển đổi chế độ xem -->
        <div class="d-flex align-center flex-wrap gap-2 flex-grow-1 justify-md-end">
          <AppTextField
            v-model="searchQuery"
            placeholder="Tìm theo tên, mô tả..."
            prepend-inner-icon="tabler-search"
            density="compact"
            hide-details
            style="min-width: 210px; max-width: 250px;"
          />

          <AppSelect
            v-model="selectedChannel"
            :items="['Tất cả kênh', 'Facebook', 'Instagram', 'TikTok', 'Website / Blog']"
            density="compact"
            hide-details
            style="width: 135px;"
          />

          <AppSelect
            v-model="sortBy"
            :items="['Sắp xếp: Mới nhất', 'Sắp xếp: Cũ nhất', 'Độ hoàn thiện cao']"
            density="compact"
            hide-details
            style="width: 165px;"
          />

          <VBtnToggle
            v-model="viewMode"
            mandatory
            density="compact"
            color="primary"
            rounded="lg"
            class="border"
          >
            <VBtn
              value="grid"
              icon="tabler-layout-grid"
              size="small"
            />
            <VBtn
              value="list"
              icon="tabler-list"
              size="small"
            />
          </VBtnToggle>
        </div>
      </div>

      <!-- ================= LƯỚI PROFILE (5 ITEM/HÀNG, RESPONSIVE) ================= -->
      <div
        v-if="viewMode === 'grid'"
        class="profile-grid"
      >
        <div
          v-for="profile in filteredProfiles"
          :key="profile.id"
        >
          <VCard
            class="profile-grid-card pa-4 h-100 d-flex flex-column justify-space-between position-relative overflow-hidden cursor-pointer"
          >
            <!-- Quầng màu tonal trang trí góc trên bên trái (màu theme) -->
            <div
              class="card-corner-glow"
              :style="{ background: `radial-gradient(circle at top left, rgba(var(--v-theme-${profile.color}), 0.14), transparent 70%)` }"
            ></div>

            <div
              class="position-relative"
              style="z-index: 2;"
            >
              <!-- Hàng đầu: Icon box + Status badge & Menu -->
              <div class="d-flex justify-space-between align-start mb-4">
                <VAvatar
                  :color="profile.color"
                  variant="tonal"
                  size="54"
                  rounded="lg"
                >
                  <VIcon
                    :color="profile.color"
                    size="30"
                    :icon="profile.icon"
                  />
                </VAvatar>

                <div class="d-flex align-center gap-1">
                  <VChip
                    size="x-small"
                    :color="profile.statusColor"
                    variant="tonal"
                  >
                    <span
                      class="status-mini-dot me-1"
                      :style="{ backgroundColor: `rgb(var(--v-theme-${profile.statusColor}))` }"
                    ></span>
                    {{ profile.statusLabel }}
                  </VChip>

                  <IconBtn
                    size="x-small"
                    @click.stop
                  >
                    <VIcon icon="tabler-dots-vertical" />
                  </IconBtn>
                </div>
              </div>

              <!-- Tiêu đề & Nhãn phiên bản -->
              <div class="d-flex align-center flex-wrap gap-1 mb-2">
                <span class="text-subtitle-2 font-weight-medium">{{ profile.title }}</span>
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ profile.version }}
                </VChip>
              </div>

              <!-- Mô tả (2 dòng) -->
              <div class="text-caption text-medium-emphasis line-clamp-2 mb-3">
                {{ profile.description }}
              </div>

              <!-- Tags chủ đề -->
              <div class="d-flex flex-wrap gap-1 mb-4">
                <VChip
                  v-for="tag in profile.tags"
                  :key="tag.text"
                  size="x-small"
                  :color="tag.color"
                  variant="tonal"
                >
                  {{ tag.text }}
                </VChip>
              </div>
            </div>

            <!-- Phần đáy: Tiến độ & Metadata -->
            <div
              class="position-relative"
              style="z-index: 2;"
            >
              <div class="d-flex align-center gap-3 mb-3">
                <VProgressLinear
                  :model-value="profile.score"
                  :color="scoreColor(profile.score)"
                  height="6"
                  rounded
                  class="flex-grow-1"
                />
                <span
                  class="text-caption font-weight-medium"
                  :class="`text-${scoreColor(profile.score)}`"
                >{{ profile.score }}%</span>
              </div>

              <div class="d-flex justify-space-between align-center pt-3 border-t text-caption text-disabled">
                <span class="d-flex align-center gap-1">
                  <VIcon
                    size="14"
                    icon="tabler-calendar"
                  />
                  Cập nhật: {{ profile.updatedAt }}
                </span>

                <span class="d-flex align-center gap-1">
                  <VIcon
                    size="14"
                    icon="tabler-user"
                  />
                  {{ profile.author }}
                </span>
              </div>
            </div>
          </VCard>
        </div>
      </div>

      <!-- ================= CHẾ ĐỘ XEM DANH SÁCH (LIST VIEW) ================= -->
      <VTable
        v-else
        density="comfortable"
        class="border rounded-lg mb-2"
      >
        <thead>
          <tr class="text-caption text-medium-emphasis">
            <th>Writing Profile</th>
            <th>Phiên bản</th>
            <th>Thẻ chủ đề</th>
            <th>Độ hoàn thiện</th>
            <th>Trạng thái</th>
            <th>Cập nhật</th>
            <th class="text-end">
              Thao tác
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="profile in filteredProfiles"
            :key="profile.id"
          >
            <td class="py-3">
              <div class="d-flex align-center gap-3">
                <VAvatar
                  :color="profile.color"
                  variant="tonal"
                  size="36"
                  rounded="lg"
                >
                  <VIcon
                    :color="profile.color"
                    size="20"
                    :icon="profile.icon"
                  />
                </VAvatar>
                <div>
                  <div class="text-body-2 font-weight-medium">
                    {{ profile.title }}
                  </div>
                  <div class="text-caption text-disabled">
                    {{ profile.description }}
                  </div>
                </div>
              </div>
            </td>
            <td>
              <VChip
                size="x-small"
                color="primary"
                variant="tonal"
              >
                {{ profile.version }}
              </VChip>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <VChip
                  v-for="tag in profile.tags"
                  :key="tag.text"
                  size="x-small"
                  :color="tag.color"
                  variant="tonal"
                >
                  {{ tag.text }}
                </VChip>
              </div>
            </td>
            <td style="min-width: 130px;">
              <div class="d-flex align-center gap-2">
                <VProgressLinear
                  :model-value="profile.score"
                  :color="scoreColor(profile.score)"
                  height="5"
                  rounded
                  class="flex-grow-1"
                />
                <span class="text-caption font-weight-medium">{{ profile.score }}%</span>
              </div>
            </td>
            <td>
              <VChip
                size="x-small"
                :color="profile.statusColor"
                variant="tonal"
              >
                {{ profile.statusLabel }}
              </VChip>
            </td>
            <td class="text-caption text-disabled">
              {{ profile.updatedAt }}
            </td>
            <td class="text-end">
              <IconBtn size="small">
                <VIcon icon="tabler-dots-vertical" />
              </IconBtn>
            </td>
          </tr>
        </tbody>
      </VTable>

      <!-- ================= 4. PHÂN TRANG (PAGINATION FOOTER) ================= -->
      <div class="d-flex justify-space-between align-center flex-wrap gap-3 pt-4 mt-2 text-caption text-medium-emphasis">
        <div>Hiển thị {{ filteredProfiles.length }} trên {{ writingProfiles.length }} kết quả</div>

        <div class="d-flex align-center gap-3">
          <AppSelect
            v-model="perPage"
            :items="['8 / trang', '12 / trang', '24 / trang']"
            density="compact"
            hide-details
            style="width: 105px;"
          />

          <VPagination
            :length="2"
            :model-value="1"
            density="compact"
            rounded="lg"
            total-visible="3"
            prev-icon="tabler-chevron-left"
            next-icon="tabler-chevron-right"
          />
        </div>
      </div>
    </VCard>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const activeStatusTab = ref('all')
const searchQuery = ref('')
const selectedChannel = ref('Tất cả kênh')
const sortBy = ref('Sắp xếp: Mới nhất')
const viewMode = ref('grid')
const perPage = ref('8 / trang')

// 4 Thẻ thống kê KPI trên đầu trang
const kpiCards = [
  { label: 'Tổng văn phong', value: '12', trend: '↑ +3', icon: 'tabler-layers-subtract', color: 'primary' },
  { label: 'Đang sử dụng', value: '8', icon: 'tabler-circle-check', color: 'success' },
  { label: 'Nháp', value: '3', icon: 'tabler-file-pencil', color: 'warning' },
  { label: 'Đã lưu trữ', value: '1', icon: 'tabler-archive', color: 'secondary' },
]

// 4 Tabs lọc trạng thái
const statusTabs = [
  { value: 'all', label: 'Tất cả', count: 12, dotColor: '' },
  { value: 'active', label: 'Đang sử dụng', count: 8, dotColor: 'success' },
  { value: 'draft', label: 'Nháp', count: 3, dotColor: 'warning' },
  { value: 'archived', label: 'Đã lưu trữ', count: 1, dotColor: 'primary' },
]

// Màu thanh tiến độ — theo màu hệ thống (primary), không theo ngưỡng điểm.
const scoreColor = () => 'primary'

// Danh sách 8 Writing Profile (dữ liệu demo tĩnh)
const writingProfiles = ref([
  {
    id: 1, title: 'Brand Voice – Mộc Cà Phê', version: 'v1.4',
    status: 'active', statusLabel: 'Đang sử dụng', statusColor: 'success',
    description: 'Văn phong gần gũi, ấm áp, truyền cảm hứng về cà phê và nhịp sống chậm.',
    tags: [
      { text: 'Cà phê', color: 'primary' },
      { text: 'Lifestyle', color: 'secondary' },
      { text: 'Truyền cảm hứng', color: 'success' },
    ],
    score: 92, updatedAt: '30/10/2026', author: 'bởi Phạm Chí Trung',
    icon: 'tabler-speakerphone', color: 'primary',
  },
  {
    id: 2, title: 'Travel Storytelling', version: 'v1.2',
    status: 'draft', statusLabel: 'Nháp', statusColor: 'warning',
    description: 'Kể chuyện du lịch, khám phá, lịch sử và văn hoá một cách cuốn hút.',
    tags: [
      { text: 'Du lịch', color: 'info' },
      { text: 'Khám phá', color: 'secondary' },
      { text: 'Cảm hứng', color: 'primary' },
    ],
    score: 88, updatedAt: '29/10/2026', author: 'bởi Phạm Chí Trung',
    icon: 'tabler-plane', color: 'info',
  },
  {
    id: 3, title: 'Ecommerce – Bán hàng', version: 'v2.0',
    status: 'archived', statusLabel: 'Đã lưu trữ', statusColor: 'primary',
    description: 'Văn phong thuyết phục, tập trung vào lợi ích và hành động mua.',
    tags: [
      { text: 'Bán hàng', color: 'info' },
      { text: 'Ưu đãi', color: 'secondary' },
      { text: 'Chuyển đổi', color: 'warning' },
    ],
    score: 76, updatedAt: '28/10/2026', author: 'bởi Phạm Chí Trung',
    icon: 'tabler-shopping-cart', color: 'error',
  },
  {
    id: 4, title: 'Giáo dục – Chia sẻ kiến thức', version: 'v1.1',
    status: 'active', statusLabel: 'Đang sử dụng', statusColor: 'success',
    description: 'Trình bày kiến thức dễ hiểu, logic, đáng tin cậy và hữu ích.',
    tags: [
      { text: 'Giáo dục', color: 'info' },
      { text: 'Kiến thức', color: 'success' },
      { text: 'Học tập', color: 'secondary' },
    ],
    score: 60, updatedAt: '27/10/2026', author: 'bởi Phạm Chí Trung',
    icon: 'tabler-school', color: 'success',
  },
  {
    id: 5, title: 'Kịch bản Video – Story', version: 'v1.3',
    status: 'draft', statusLabel: 'Nháp', statusColor: 'warning',
    description: 'Cấu trúc kịch bản video ngắn, giữ chân và tạo cảm xúc.',
    tags: [
      { text: 'Video', color: 'error' },
      { text: 'Story', color: 'secondary' },
      { text: 'Giải trí', color: 'primary' },
    ],
    score: 85, updatedAt: '26/10/2026', author: 'bởi Phạm Chí Trung',
    icon: 'tabler-movie', color: 'warning',
  },
  {
    id: 6, title: 'Sống Xanh – Lifestyle', version: 'v1.0',
    status: 'active', statusLabel: 'Đang sử dụng', statusColor: 'success',
    description: 'Văn phong tích cực, truyền cảm hứng về lối sống lành mạnh, bền vững.',
    tags: [
      { text: 'Sống xanh', color: 'success' },
      { text: 'Lối sống', color: 'info' },
      { text: 'Bền vững', color: 'primary' },
    ],
    score: 70, updatedAt: '24/10/2026', author: 'bởi Phạm Chí Trung',
    icon: 'tabler-leaf', color: 'success',
  },
  {
    id: 7, title: 'AI & Công nghệ', version: 'v1.1',
    status: 'active', statusLabel: 'Đang sử dụng', statusColor: 'success',
    description: 'Giới thiệu công cụ, mẹo AI và xu hướng công nghệ theo cách dễ hiểu.',
    tags: [
      { text: 'AI', color: 'primary' },
      { text: 'Công nghệ', color: 'info' },
      { text: 'Thủ thuật', color: 'error' },
    ],
    score: 89, updatedAt: '22/10/2026', author: 'bởi Phạm Chí Trung',
    icon: 'tabler-cpu', color: 'primary',
  },
  {
    id: 8, title: 'Cảm hứng – Động lực', version: 'v1.0',
    status: 'archived', statusLabel: 'Đã lưu trữ', statusColor: 'warning',
    description: 'Những câu chuyện, trích dẫn và bài học truyền động lực trong cuộc sống.',
    tags: [
      { text: 'Truyền cảm hứng', color: 'error' },
      { text: 'Tích cực', color: 'secondary' },
      { text: 'Thành công', color: 'primary' },
    ],
    score: 74, updatedAt: '20/10/2026', author: 'bởi Phạm Chí Trung',
    icon: 'tabler-heart', color: 'error',
  },
])

// Lọc danh sách theo Tab trạng thái & Từ khóa tìm kiếm
const filteredProfiles = computed(() => {
  return writingProfiles.value.filter((item) => {
    const matchTab
      = activeStatusTab.value === 'all' || item.status === activeStatusTab.value
    const q = searchQuery.value.trim().toLowerCase()
    const matchSearch
      = !q
      || item.title.toLowerCase().includes(q)
      || item.description.toLowerCase().includes(q)

    return matchTab && matchSearch
  })
})
</script>

<style scoped>
/* Thanh Tabs Trạng thái — giữ thiết kế gạch chân, màu lấy từ theme. */
.status-tab-btn {
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-weight: 500;
  transition: color 0.15s ease, border-color 0.15s ease;
  margin-bottom: -13px;
  padding-bottom: 12px !important;
}
.status-tab-btn:hover {
  color: rgb(var(--v-theme-primary));
}
.status-tab-active {
  color: rgb(var(--v-theme-primary)) !important;
  font-weight: 600 !important;
  border-bottom: 2px solid rgb(var(--v-theme-primary)) !important;
}

.tab-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  display: inline-block;
}
.status-mini-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
}

/* Lưới profile — 5 item/hàng, co giãn theo màn hình (không lồng card). */
.profile-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
}
@media (max-width: 1600px) {
  .profile-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (max-width: 1280px) {
  .profile-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 960px) {
  .profile-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 600px) {
  .profile-grid { grid-template-columns: 1fr; }
}

/* Card Writing Profile — không border (tránh card lồng card), hover nổi nhẹ. */
.profile-grid-card {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.profile-grid-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 22px rgba(var(--v-theme-primary), 0.12) !important;
}

/* Quầng sáng màu tonal góc trái trên của Card (alpha của màu theme). */
.card-corner-glow {
  position: absolute;
  top: 0;
  left: 0;
  width: 170px;
  height: 130px;
  pointer-events: none;
  z-index: 1;
}

/* Giới hạn 2 dòng cho mô tả. */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 34px;
}
</style>
