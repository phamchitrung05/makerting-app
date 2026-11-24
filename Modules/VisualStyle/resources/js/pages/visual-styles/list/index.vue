<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang danh sách Visual Style (Composition Surface).
  =====================================================================

  Đóng vai trò bề mặt ghép nối route `/admin/visual-style/visual-styles/list`
  (tên route tự động: `admin-visual-style-visual-styles-list`, khớp với
  `VisualStyleAnalyzeAdapter::detailRoute()`). Phần giao diện chi tiết được
  tách sang `views/VisualStyleList.vue` theo chuẩn boundary kiến trúc.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - Không có (chỉ khai báo definePage và render VisualStyleList).

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : Route `/admin/visual-style/visual-styles/list`.
  - OUTPUT: Render view `VisualStyleList` trong layout mặc định `default`.
  =====================================================================
-->
<script setup>
import VisualStyleList from '../../../views/VisualStyleList.vue'

definePage({
  meta: {
    layout: 'default',
  },
})
</script>

<template>
  <VisualStyleList />
</template>
