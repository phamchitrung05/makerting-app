<!--
  =====================================================================
  CHỨC NĂNG FILE: Danh sách Writing Profile lấy từ backend.
  =====================================================================

  Hiển thị danh sách hồ sơ văn phong (16 RULE_KEYS) — giao diện dùng dữ liệu API
  nạp qua store từ API /v1/admin/writing-style/writing-profiles.

  CHUẨN UI (docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §2–3):
  - Icon Tabler (tabler-*), KHÔNG mdi-*.
  - Màu theme Vuetify / rgb(var(--v-theme-*)) cho hiệu ứng alpha —
  không hex cứng (#7367F0, #fff...); Dark Mode tự đúng.
  - VCard/VBtn/VChip/VAvatar PascalCase; border thay elevation.
  - Không inline font-size / !important — dùng text-h4/body/caption.
  - Spacing class Vuetify chuẩn (gap-2, pa-4, mb-3...).


  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - fmtDate(): Định dạng ngày cập nhật theo locale vi-VN.
  - dnaScore(): Ước tính độ đầy đủ DNA theo số giá trị có nội dung chia cho 16.
  - toCardProps(): Ánh xạ resource hồ sơ sang dữ liệu card danh sách.
  - onAnalyze(): Chuyển payload nhập mẫu sang trang tạo để phân tích và duyệt.
  - openReverseDialog(): Mở dialog nhập bài mẫu trên trang danh sách.
  - openCreateDialog(): Chuyển tới trang tạo Writing Profile.
  - scoreColor(): Cấp màu primary cho thanh độ đầy đủ DNA.
  - Computed: profileCards, filteredProfiles, kpiCards, statusTabs, sortedProfiles, pagedProfiles.
  - Watcher/lifecycle: onMounted, onBeforeUnmount.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : Route, form và state useWritingStyleStore; không có props/emits riêng.
  - OUTPUT: Render UI, gọi store/API, cập nhật snackbar và điều hướng theo thao tác.
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
          @click="openReverseDialog"
        >
          Nhập từ mẫu
        </VBtn>

        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreateDialog"
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

    <!-- ================= 3. CARD BỘ LỌC (tabs trái + inputs phải) ================= -->
    <VCard border>
      <VCardText class="d-flex align-center justify-space-between flex-wrap gap-3">
        <!-- Trái: Tabs lọc theo trạng thái (chuẩn VTabs/VTab) -->
        <VTabs
          v-model="activeStatusTab"
          color="primary"
          density="comfortable"
        >
          <VTab
            v-for="tab in statusTabs"
            :key="tab.value"
            :value="tab.value"
            class="text-none font-weight-medium text-caption"
          >
            <VIcon
              v-if="tab.icon"
              size="18"
              class="me-2"
              :icon="tab.icon"
            />
            {{ tab.label }} ({{ tab.count }})
          </VTab>
        </VTabs>

        <!-- Phải: inputs tìm kiếm + lọc kênh + sắp xếp -->
        <div class="d-flex align-center flex-wrap gap-2">
          <AppTextField
            v-model="searchQuery"
            placeholder="Tìm theo tên, mô tả..."
            prepend-inner-icon="tabler-search"
            density="compact"
            hide-details
            style="min-width: 210px; max-width: 250px;"
          />

          <AppSelect
            v-model="selectedOrigin"
            :items="originOptions"
            density="compact"
            hide-details
            style="width: 135px;"
          />

          <AppSelect
            v-model="sortBy"
            :items="sortOptions"
            density="compact"
            hide-details
            style="width: 165px;"
          />
        </div>
      </VCardText>
    </VCard>

    <!-- ================= ITEMS (không card bao) ================= -->
    <div>
      <!-- Loading (Skeleton Grid - 10 card mô phỏng lưới 5 cột x 2 hàng) -->
      <div
        v-if="store.loadingProfiles && !profileCards.length"
        class="profile-grid"
      >
        <VCard
          v-for="n in 10"
          :key="`skeleton-${n}`"
          class="profile-grid-card pa-4 d-flex flex-column justify-space-between"
          style="min-height: 260px;"
        >
          <div>
            <div class="d-flex justify-space-between align-start mb-4">
              <VSkeletonLoader
                type="avatar"
                class="bg-transparent"
              />
              <VSkeletonLoader
                type="chip"
                class="bg-transparent"
                style="width: 70px;"
              />
            </div>

            <VSkeletonLoader
              type="heading"
              class="bg-transparent mb-2"
            />
            <VSkeletonLoader
              type="paragraph"
              class="bg-transparent mb-4"
            />
          </div>

          <div class="pt-3 border-t">
            <VSkeletonLoader
              type="text"
              class="bg-transparent"
            />
          </div>
        </VCard>
      </div>

      <!-- Empty state -->
      <VCard
        v-else-if="!sortedProfiles.length"
        border
      >
        <div class="pa-12 text-center text-medium-emphasis">
          <VIcon
            icon="tabler-file-text-off"
            size="44"
            class="mb-3"
          />
          <div class="text-body-1">
            Chưa có văn phong nào. Bấm "Nhập từ mẫu" để giải mã DNA đầu tiên.
          </div>
        </div>
      </VCard>

      <!-- ================= LƯỚI PROFILE (5 ITEM/HÀNG, RESPONSIVE) ================= -->
      <div
        v-else
        class="profile-grid"
      >
        <div
          v-for="profile in pagedProfiles"
          :key="profile.id"
        >
          <VCard
            class="profile-grid-card pa-4 d-flex flex-column justify-space-between position-relative overflow-hidden cursor-pointer"
            :to="{ name: 'admin-writing-style-writing-profiles-edit-id', params: { id: profile.id } }"
          >
            <!-- Quầng màu tonal trang trí góc trên bên trái (màu theme) -->
            <div
              class="card-corner-glow"
              :style="{ background: `radial-gradient(circle at top left, rgba(var(--v-theme-${profile.color}), 0.14), transparent 70%)` }"
            />

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
                    />
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
                <span class="text-h6 font-weight-bold">{{ profile.title }}</span>
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  class="font-weight-bold"
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
              </div>
            </div>
          </VCard>
        </div>
      </div>
    </div>

    <!-- ================= 4. CARD PHÂN TRANG (chuẩn TablePagination) ================= -->
    <VCard
      v-if="sortedProfiles.length > itemsPerPage"
      border
      class="pa-0 overflow-hidden"
    >
      <TablePagination
        v-model:page="page"
        :items-per-page="itemsPerPage"
        :total-items="sortedProfiles.length"
      />
    </VCard>

    <!-- Dialog giải mã DNA từ bài mẫu (dùng chung cho 2 nút header). -->
    <ProfileReverseEngineerDialog
      v-model="reverseDialog"
      @analyze="onAnalyze"
    />
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useWritingStyleStore } from '../../../stores/writingStyle'
import ProfileReverseEngineerDialog from '../../../components/ProfileReverseEngineerDialog.vue'

const store = useWritingStyleStore()
const router = useRouter()

const activeStatusTab = ref('all')
const searchQuery = ref('')
const selectedOrigin = ref('all')
const sortBy = ref('newest')
const page = ref(1)
const itemsPerPage = ref(10)

/* Dialog "Nhập từ mẫu" (Reverse Engineer) — mở khi bấm nút header.
 * Nút "Tạo Writing Profile" cũng mở dialog này (bạn sẽ tách form tạo tay
 * sau — hiện chỉ nối dialog vào nút theo yêu cầu). */
const reverseDialog = ref(false)

/* =====================================================================
 * Map profile (API) -> props card UI.
 * =====================================================================
 * API trả: {id,name,description,dominant_tier,origin,status,is_enabled,
 *           version,updated_at,analysis_metadata_json,rules_json,...}
 * Card cần: id,title,version,status(+label/color),description,tags[],
 *           score,updatedAt,icon,color,link.
 * ===================================================================== */
const TIER_META = {
  micro: { icon: 'tabler-letter-s',    color: 'error'   },
  short: { icon: 'tabler-file-text',   color: 'warning' },
  medium: { icon: 'tabler-article',     color: 'info'    },
  long: { icon: 'tabler-book',        color: 'primary' },
}

const STATUS_META = {
  active: { label: 'Đang sử dụng', color: 'success'   },
  draft: { label: 'Nháp',         color: 'warning'   },
  archived: { label: 'Đã lưu trữ',   color: 'secondary' },
}

/**
 * =====================================================================
 * CHỨC NĂNG: Định dạng ngày cập nhật theo locale vi-VN
 * =====================================================================
 *
 * INPUT:
 * - iso
 *
 * OUTPUT:
 * - string ngày hiển thị hoặc chuỗi đầu vào khi xảy ra lỗi
 *
 * SIDE EFFECT:
 * - Chỉ xử lý ngày trong bộ nhớ.
 *
 * EXCEPTION:
 * - Trả rỗng khi không có ngày; bắt lỗi định dạng.
 * =====================================================================
 */
function fmtDate(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleDateString('vi-VN')
  } catch { return iso }
}

/* Độ hoàn thiện DNA = % trong số 16 RULE_KEYS có nội dung (không rỗng).
 * Với profile 'manual' mới tạo có thể < 100%. */
/**
 * =====================================================================
 * CHỨC NĂNG: Ước tính độ đầy đủ DNA theo số giá trị có nội dung chia cho 16
 * =====================================================================
 *
 * INPUT:
 * - rules
 *
 * OUTPUT:
 * - number tỷ lệ làm tròn; 0 khi rules không hợp lệ
 *
 * SIDE EFFECT:
 * - Chỉ đếm trường; đây không phải điểm chất lượng văn phong.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function dnaScore(rules) {
  if (!rules || typeof rules !== 'object') return 0
  const total = 16

  const filled = Object.values(rules).filter(v => {
    if (Array.isArray(v)) return v.length > 0
    
    return v !== null && String(v).trim() !== ''
  }).length

  return Math.round((filled / total) * 100)
}

/**
 * =====================================================================
 * CHỨC NĂNG: Ánh xạ resource hồ sơ sang dữ liệu card danh sách
 * =====================================================================
 *
 * INPUT:
 * - p
 *
 * OUTPUT:
 * - Object nhãn/trạng thái/tags/điểm/ngày/icon/màu
 *
 * SIDE EFFECT:
 * - Đọc metadata, fallback tier/origin và trạng thái archived khi tắt hồ sơ.
 *
 * EXCEPTION:
 * - Không gọi API; caller cung cấp resource hồ sơ.
 * =====================================================================
 */
function toCardProps(p) {
  const tier = TIER_META[p.dominant_tier] ?? TIER_META.medium

  // "lưu trữ" = is_enabled=false (spec chỉ có status draft|active).
  const statusKey = p.is_enabled ? p.status : 'archived'
  const status = STATUS_META[statusKey] ?? STATUS_META.draft

  // Tags từ categories + tags thực tế của hồ sơ; fallback tier + origin khi chưa gán.
  const categoryTags = Array.isArray(p.categories)
    ? p.categories.map(c => ({ text: c.name, color: 'primary' }))
    : []

  const labelTags = Array.isArray(p.tags)
    ? p.tags.map(t => ({ text: t.name, color: 'secondary' }))
    : []

  const combinedTaxonomy = [...categoryTags, ...labelTags].slice(0, 4)

  const tags = combinedTaxonomy.length
    ? combinedTaxonomy
    : [
        { text: p.dominant_tier ?? 'medium', color: 'info' },
        { text: p.origin === 'reference' ? 'Giải mã' : 'Thủ công', color: p.origin === 'reference' ? 'info' : 'secondary' },
      ]

  return {
    id: p.id,
    title: p.name,
    version: `v${p.version ?? 1}`,
    status: statusKey,
    statusLabel: status.label,
    statusColor: status.color,
    description: p.description ?? '',
    tags,
    score: dnaScore(p.rules_json),
    updatedAt: fmtDate(p.updated_at),
    icon: tier.icon,
    color: tier.color,
  }
}

/* =====================================================================
 * Danh sách card đã map + lọc client.
 * ===================================================================== */
const profileCards = computed(() => store.profiles.map(toCardProps))

const filteredProfiles = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()

  return profileCards.value.filter(item => {
    // Tab trạng thái: all | active | draft | archived(=tắt).
    const matchTab = activeStatusTab.value === 'all' || item.status === activeStatusTab.value

    const matchSearch = !q
      || item.title.toLowerCase().includes(q)
      || item.description.toLowerCase().includes(q)

    const p = store.profiles.find(x => x.id === item.id)
    const matchOrigin = selectedOrigin.value === 'all' || p?.origin === selectedOrigin.value

    return matchTab && matchSearch && matchOrigin
  })
})

/* KPI 4 thẻ tính từ store.statusCounts. */
const kpiCards = computed(() => {
  const c = store.statusCounts

  return [
    { label: 'Tổng văn phong', value: String(c.all), icon: 'tabler-layers-subtract', color: 'primary' },
    { label: 'Đang sử dụng', value: String(c.active), icon: 'tabler-circle-check', color: 'success' },
    { label: 'Nháp', value: String(c.draft), icon: 'tabler-file-pencil', color: 'warning' },
    { label: 'Đã lưu trữ', value: String(c.archived), icon: 'tabler-archive', color: 'secondary' },
  ]
})

/* Status tabs theo statusCounts. */
const statusTabs = computed(() => {
  const c = store.statusCounts

  return [
    { value: 'all', label: 'Tất cả', count: c.all, icon: 'tabler-list' },
    { value: 'active', label: 'Đang sử dụng', count: c.active, icon: 'tabler-circle-check' },
    { value: 'draft', label: 'Nháp', count: c.draft, icon: 'tabler-file-pencil' },
    { value: 'archived', label: 'Đã lưu trữ', count: c.archived, icon: 'tabler-archive' },
  ]
})

const originOptions = [
  { title: 'Tất cả nguồn', value: 'all' },
  { title: 'Thủ công', value: 'manual' },
  { title: 'Giải mã', value: 'reference' },
]

const sortOptions = [
  { title: 'Sắp xếp: Mới nhất', value: 'newest' },
  { title: 'Sắp xếp: Cũ nhất', value: 'oldest' },
  { title: 'Sắp xếp: Tên A–Z', value: 'name' },
]

const sortedProfiles = computed(() => {
  const list = [...filteredProfiles.value]
  if (sortBy.value === 'oldest') list.reverse()
  else if (sortBy.value === 'name') list.sort((a, b) => a.title.localeCompare(b.title, 'vi'))

  return list
})

const pagedProfiles = computed(() => {
  const start = (page.value - 1) * itemsPerPage.value

  return sortedProfiles.value.slice(start, start + itemsPerPage.value)
})

/**
 * =====================================================================
 * CHỨC NĂNG: Chuyển payload nhập mẫu sang trang tạo để phân tích và duyệt
 * =====================================================================
 *
 * INPUT:
 * - payload
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Gán store.pendingImport, đóng dialog và điều hướng đến trang create.
 *
 * EXCEPTION:
 * - Lỗi điều hướng có thể được router xử lý.
 * =====================================================================
 */
function onAnalyze(payload) {
  store.pendingImport = payload
  reverseDialog.value = false
  openCreateDialog()
}

/**
 * =====================================================================
 * CHỨC NĂNG: Mở dialog nhập bài mẫu trên trang danh sách
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Đặt reverseDialog=true.
 *
 * EXCEPTION:
 * - Không gọi API.
 * =====================================================================
 */
function openReverseDialog() { reverseDialog.value = true }

/**
 * =====================================================================
 * CHỨC NĂNG: Chuyển tới trang tạo Writing Profile
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Gọi router.push route create.
 *
 * EXCEPTION:
 * - Lỗi điều hướng do router quản lý.
 * =====================================================================
 */
function openCreateDialog() {
  router.push({ name: 'admin-writing-style-writing-profiles-create' })
}

/**
 * =====================================================================
 * CHỨC NĂNG: Cấp màu primary cho thanh độ đầy đủ DNA
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - string primary
 *
 * SIDE EFFECT:
 * - Không đổi state hoặc gọi API.
 *
 * EXCEPTION:
 * - Không ném lỗi chủ động.
 * =====================================================================
 */
const scoreColor = () => 'primary'

onMounted(() => {
  store.fetchProfiles()
})

// Ngắt vòng poll khi rời trang — tránh giữ timer chạy ngầm.
onBeforeUnmount(() => {
  store.stopPolling()
})
</script>

<style scoped>
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

/* Card Writing Profile — nền surface (trắng/Dark tự đổi), hover nổi nhẹ. */
.profile-grid-card {
  background-color: rgb(var(--v-theme-surface));
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
