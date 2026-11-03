<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang quản lý DNA Văn phong (Writing Profiles).
  =====================================================================
  Hiển thị danh sách hồ sơ văn phong (16 RULE_KEYS) theo chuẩn giao diện
  Admin Vuexy/Vuetify 3: header page chuẩn, KPI card, lưới profile dạng
  card có thanh điểm + list view fallback. Hiện dữ liệu demo tĩnh chờ
  nối API /api/v1/writing-style/profiles (P2-02).

  CHUẨN UI (docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §2–3):
  - Icon Tabler (tabler-*) — KHÔNG mdi-*.
  - Màu theme Vuetify (primary/success/warning/error/info), không hex cứng.
  - VCard border + title/subtitle; typography text-h4/subtitle-1/body/caption.
  - Không inline font-size; spacing class Vuetify chuẩn.

  CÁC HÀM/METHOD TRONG FILE: chỉ filter computed (demo). INPUT/OUTPUT:
  route /admin/writing-style/writing-profiles. SIDE EFFECT: không.
  =====================================================================
-->
<script setup>
import { computed, ref } from 'vue'

// ---- Bộ lọc (demo) ----
const search = ref('')
const statusFilter = ref('all')
const viewMode = ref('grid')

const statusTabs = [
  { value: 'all', label: 'Tất cả' },
  { value: 'active', label: 'Đang dùng' },
  { value: 'draft', label: 'Nháp' },
  { value: 'archived', label: 'Lưu trữ' },
]

const kpis = [
  { label: 'Tổng văn phong', value: 12, icon: 'tabler-writing', color: 'primary' },
  { label: 'Đang sử dụng', value: 8, icon: 'tabler-circle-check', color: 'success' },
  { label: 'Nháp', value: 3, icon: 'tabler-file-pencil', color: 'warning' },
  { label: 'Đã lưu trữ', value: 1, icon: 'tabler-archive', color: 'secondary' },
]

// ---- Danh sách profile (demo tĩnh) ----
const profiles = ref([
  { id: 1, name: 'Brand Voice – Mộc Cà Phê', version: 'v1.4', status: 'active', desc: 'Giọng gần gũi, ấm áp, truyền cảm hứng về cà phê và nhịp sống chậm.', tags: ['Cà phê', 'Lifestyle', 'Truyền cảm hứng'], score: 92, updatedAt: '30/10/2026', icon: 'tabler-coffee', color: 'primary' },
  { id: 2, name: 'Travel Storytelling', version: 'v1.2', status: 'draft', desc: 'Kể chuyện du lịch, khám phá, lịch sử và văn hoá cuốn hút.', tags: ['Du lịch', 'Khám phá'], score: 88, updatedAt: '29/10/2026', icon: 'tabler-plane', color: 'info' },
  { id: 3, name: 'Ecommerce – Bán hàng', version: 'v2.0', status: 'archived', desc: 'Văn phong thuyết phục, tập trung lợi ích và hành động mua.', tags: ['Bán hàng', 'Chuyển đổi'], score: 76, updatedAt: '28/10/2026', icon: 'tabler-shopping-cart', color: 'error' },
  { id: 4, name: 'Giáo dục – Chia sẻ kiến thức', version: 'v1.1', status: 'active', desc: 'Trình bày kiến thức dễ hiểu, logic, đáng tin cậy.', tags: ['Giáo dục', 'Kiến thức'], score: 60, updatedAt: '27/10/2026', icon: 'tabler-school', color: 'success' },
  { id: 5, name: 'Kịch bản Video – Story', version: 'v1.3', status: 'draft', desc: 'Cấu trúc kịch bản video ngắn, giữ chân và tạo cảm xúc.', tags: ['Video', 'Story'], score: 85, updatedAt: '26/10/2026', icon: 'tabler-movie', color: 'warning' },
  { id: 6, name: 'Sống Xanh – Lifestyle', version: 'v1.0', status: 'active', desc: 'Văn phong tích cực về lối sống lành mạnh, bền vững.', tags: ['Sống xanh', 'Lối sống'], score: 70, updatedAt: '24/10/2026', icon: 'tabler-leaf', color: 'success' },
  { id: 7, name: 'AI & Công nghệ', version: 'v1.1', status: 'active', desc: 'Giới thiệu công cụ, mẹo AI và xu hướng công nghệ dễ hiểu.', tags: ['AI', 'Công nghệ'], score: 89, updatedAt: '22/10/2026', icon: 'tabler-cpu', color: 'primary' },
  { id: 8, name: 'Cảm hứng – Động lực', version: 'v1.0', status: 'archived', desc: 'Câu chuyện, trích dẫn truyền động lực trong cuộc sống.', tags: ['Cảm hứng', 'Tích cực'], score: 74, updatedAt: '20/10/2026', icon: 'tabler-heart', color: 'error' },
])

const statusMeta = {
  active: { label: 'Đang dùng', color: 'success' },
  draft: { label: 'Nháp', color: 'warning' },
  archived: { label: 'Lưu trữ', color: 'secondary' },
}

const scoreColor = s => (s >= 80 ? 'success' : s >= 60 ? 'warning' : 'error')

const filtered = computed(() => profiles.value.filter((p) => {
  const okStatus = statusFilter.value === 'all' || p.status === statusFilter.value
  const q = search.value.trim().toLowerCase()
  const okSearch = !q || p.name.toLowerCase().includes(q) || p.desc.toLowerCase().includes(q)
  return okStatus && okSearch
}))
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Header trang (chuẩn §3.1) -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Writing Profiles
        </h4>
        <div class="text-body-2 text-medium-emphasis">
          Quản lý DNA văn phong, giọng điệu và quy tắc nội dung (16 Rules).
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

    <!-- 4 KPI card -->
    <VRow dense>
      <VCol
        v-for="kpi in kpis"
        :key="kpi.label"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard border>
          <VCardText class="d-flex align-center gap-3 pa-4">
            <VAvatar
              :color="kpi.color"
              variant="tonal"
              size="44"
              rounded="lg"
            >
              <VIcon
                :color="kpi.color"
                size="22"
                :icon="kpi.icon"
              />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                {{ kpi.label }}
              </div>
              <div class="text-h5 font-weight-medium">
                {{ kpi.value }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Khung chính -->
    <VCard border>
      <!-- Thanh filter -->
      <VCardText class="d-flex align-center justify-space-between flex-wrap gap-3 border-b">
        <VTabs
          v-model="statusFilter"
          density="compact"
          color="primary"
        >
          <VTab
            v-for="t in statusTabs"
            :key="t.value"
            :value="t.value"
            class="text-none text-caption"
          >
            {{ t.label }}
          </VTab>
        </VTabs>
        <div class="d-flex align-center gap-2">
          <AppTextField
            v-model="search"
            placeholder="Tìm theo tên, mô tả..."
            prepend-inner-icon="tabler-search"
            density="compact"
            hide-details
            style="min-width: 220px;"
          />
          <VBtnToggle
            v-model="viewMode"
            mandatory
            density="compact"
            color="primary"
            rounded="lg"
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
      </VCardText>

      <!-- GRID view -->
      <VCardText v-if="viewMode === 'grid'">
        <VRow dense>
          <VCol
            v-for="p in filtered"
            :key="p.id"
            cols="12"
            sm="6"
            md="4"
            lg="3"
          >
            <VCard
              border
              class="profile-card h-100 d-flex flex-column justify-space-between pa-4"
            >
              <div>
                <div class="d-flex justify-space-between align-start mb-3">
                  <VAvatar
                    :color="p.color"
                    variant="tonal"
                    size="44"
                    rounded="lg"
                  >
                    <VIcon
                      :color="p.color"
                      size="24"
                      :icon="p.icon"
                    />
                  </VAvatar>
                  <div class="d-flex align-center gap-1">
                    <VChip
                      size="x-small"
                      :color="statusMeta[p.status].color"
                      variant="tonal"
                    >
                      {{ statusMeta[p.status].label }}
                    </VChip>
                    <IconBtn size="small">
                      <VIcon icon="tabler-dots-vertical" />
                    </IconBtn>
                  </div>
                </div>

                <div class="d-flex align-center flex-wrap gap-1 mb-1">
                  <span class="text-body-1 font-weight-medium">{{ p.name }}</span>
                  <VChip
                    size="x-small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ p.version }}
                  </VChip>
                </div>
                <div class="text-body-2 text-medium-emphasis mb-3 line-clamp-2">
                  {{ p.desc }}
                </div>
                <div class="d-flex flex-wrap gap-1 mb-3">
                  <VChip
                    v-for="t in p.tags"
                    :key="t"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                  >
                    {{ t }}
                  </VChip>
                </div>
              </div>

              <div>
                <div class="d-flex align-center gap-2 mb-2">
                  <VProgressLinear
                    :model-value="p.score"
                    :color="scoreColor(p.score)"
                    height="6"
                    rounded
                    class="flex-grow-1"
                  />
                  <span
                    class="text-caption font-weight-medium"
                    :class="`text-${scoreColor(p.score)}`"
                  >{{ p.score }}%</span>
                </div>
                <div class="d-flex justify-space-between align-center pt-2 border-t text-caption text-disabled">
                  <span class="d-flex align-center gap-1">
                    <VIcon
                      size="14"
                      icon="tabler-calendar"
                    />
                    {{ p.updatedAt }}
                  </span>
                  <VBtn
                    size="x-small"
                    variant="text"
                    color="primary"
                  >
                    Chỉnh sửa
                  </VBtn>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <!-- LIST view -->
      <VTable
        v-else
        density="comfortable"
      >
        <thead>
          <tr>
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
            v-for="p in filtered"
            :key="p.id"
          >
            <td>
              <div class="d-flex align-center gap-3 py-2">
                <VAvatar
                  :color="p.color"
                  variant="tonal"
                  size="36"
                  rounded="lg"
                >
                  <VIcon
                    :color="p.color"
                    size="20"
                    :icon="p.icon"
                  />
                </VAvatar>
                <div>
                  <div class="text-body-2 font-weight-medium">
                    {{ p.name }}
                  </div>
                  <div class="text-caption text-disabled">
                    {{ p.desc }}
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
                {{ p.version }}
              </VChip>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <VChip
                  v-for="t in p.tags"
                  :key="t"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                >
                  {{ t }}
                </VChip>
              </div>
            </td>
            <td style="min-width: 140px;">
              <div class="d-flex align-center gap-2">
                <VProgressLinear
                  :model-value="p.score"
                  :color="scoreColor(p.score)"
                  height="5"
                  rounded
                  class="flex-grow-1"
                />
                <span
                  class="text-caption font-weight-medium"
                  :class="`text-${scoreColor(p.score)}`"
                >{{ p.score }}%</span>
              </div>
            </td>
            <td>
              <VChip
                size="x-small"
                :color="statusMeta[p.status].color"
                variant="tonal"
              >
                {{ statusMeta[p.status].label }}
              </VChip>
            </td>
            <td class="text-caption text-disabled">
              {{ p.updatedAt }}
            </td>
            <td class="text-end">
              <IconBtn size="small">
                <VIcon icon="tabler-dots-vertical" />
              </IconBtn>
            </td>
          </tr>
        </tbody>
      </VTable>

      <!-- Footer phân trang (tĩnh) -->
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-3 border-t">
        <span class="text-caption text-medium-emphasis">Hiển thị {{ filtered.length }} trên {{ profiles.length }} hồ sơ</span>
        <VPagination
          :length="1"
          :model-value="1"
          density="compact"
          rounded="lg"
          total-visible="3"
        />
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
/* Card profile: hover nổi nhẹ theo chuẩn Vuexy. */
.profile-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.profile-card:hover {
  transform: translateY(-2px);
}

/* Mô tả giới hạn 2 dòng. */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
