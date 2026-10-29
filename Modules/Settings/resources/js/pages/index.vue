<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang cài đặt tổng quan (placeholder, kiểm chứng routing module).
  =====================================================================
  CÁC HÀM/METHOD TRONG FILE: không.
  INPUT/OUTPUT CỦA CLASS (tổng thể): route /admin/settings -> render khối tiêu đề.
  SIDE EFFECT: không.
  =====================================================================
-->
<script setup>
definePage({ meta: { layout: 'default' } })
</script>

<template>
  <VCard>
    <VCardItem title="Cài đặt hệ thống" />
    <VCardText>Trang Settings module — route /admin/settings đã được gom tự động.</VCardText>
  </VCard>
</template>
