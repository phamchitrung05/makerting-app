<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang Chỉnh sửa Writing Profile (route edit).
  =====================================================================
  Route: /admin/writing-style/writing-profiles/:id
  Tự nạp qua file-based routing (unplugin-vue-router) — tên route:
  'admin-writing-style-writing-profiles-id'.

  Hiện là SKELETON chờ đổ giao diện (đọc :id từ route, nạp profile qua
  writingStyleApi khi backend P2-02 sẵn sàng). Giữ chuẩn Vuexy:
  tabler-* icons, màu theme, VCard border, không inline font-size.

  CÁC HÀM/METHOD TRONG FILE:
  - goBack() : quay lại danh sách profiles.
  INPUT/OUTPUT: route.params.id -> profile đang sửa (nạp sau).
  SIDE EFFECT: chưa gọi API (demo tĩnh).
  =====================================================================
-->
<script setup>
import { ref } from 'vue'

// Đọc :id từ route theo tên route file-based.
const route = useRoute('admin-writing-style-writing-profiles-id')

// TODO (P2-02): nạp WritingProfile theo route.params.id qua writingStyleApi.
const profileId = route.params.id
const profile = ref(null)
const loading = ref(false)

function goBack() {
  window.history.back()
}
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Header trang (chuẩn §3.1) -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 align-center">
      <div class="d-flex flex-column justify-center">
        <div class="d-flex align-center gap-2 mb-1">
          <VBtn
            variant="text"
            color="secondary"
            size="small"
            prepend-icon="tabler-arrow-left"
            @click="goBack"
          >
            Quay lại
          </VBtn>
        </div>
        <h4 class="text-h4 font-weight-medium">
          Chỉnh sửa Writing Profile
        </h4>
        <div class="text-body-2 text-medium-emphasis">
          ID: <span class="font-mono">{{ profileId }}</span> — cập nhật DNA văn phong, quy tắc và giọng điệu.
        </div>
      </div>
      <div class="d-flex gap-2 align-center">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="goBack"
        >
          Hủy
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-device-floppy"
        >
          Lưu thay đổi
        </VBtn>
      </div>
    </div>

    <!-- TODO: bố cục 2 cột (md=8 nội dung + md=4 cấu hình) — điền UI ở đây -->
    <VCard
      border
      class="pa-6"
    >
      <div class="text-body-2 text-medium-emphasis">
        Skeleton trang edit cho profile <strong>#{{ profileId }}</strong>.
        Đổ giao diện vào đây (form tên, archetype, 16 rules, sample_texts, ...).
      </div>
    </VCard>
  </div>
</template>
