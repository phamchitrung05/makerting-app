<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang danh sách bài viết (placeholder, kiểm chứng routing module).
  =====================================================================
  CÁC HÀM/METHOD TRONG FILE: không (composition surface tĩnh).
  INPUT/OUTPUT CỦA CLASS (tổng thể): route /admin/blog/posts -> render khối tiêu đề.
  SIDE EFFECT: không gọi API, không đổi state.
  =====================================================================
-->
<script setup>
definePage({
  meta: {
    layout: 'default',
  },
})
</script>

<template>
  <VCard>
    <VCardItem title="Danh sách bài viết" />
    <VCardText>Trang Blog module — route /admin/blog/posts đã được gom tự động.</VCardText>
  </VCard>
</template>
