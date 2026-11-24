<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang tạo hồ sơ Visual Style (Composition Surface).
  =====================================================================

  Đóng vai trò bề mặt ghép nối route `/admin/visual-style/visual-styles/create`
  (tên route tự động: `admin-visual-style-visual-styles-create`). Giữ highlight
  menu cha `Visual Styles` trên sidebar qua `meta.navActiveLink` và tách phần
  giao diện chi tiết sang `views/VisualStyleCreate.vue`.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - Không có (chỉ khai báo definePage và render VisualStyleCreate).

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : Route `/admin/visual-style/visual-styles/create` (tùy chọn `?mode=analyze|manual`).
  - OUTPUT: Render view `VisualStyleCreate` trong layout mặc định `default`.
  =====================================================================
-->
<script setup>
import VisualStyleCreate from '../../../views/VisualStyleCreate.vue'

definePage({
  meta: {
    layout: 'default',
    navActiveLink: 'admin-visual-style-visual-styles-list',
  },
})
</script>

<template>
  <VisualStyleCreate />
</template>
