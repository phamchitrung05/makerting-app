<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab "Sao lưu & Khôi phục" trong trang Cài đặt (không phải route).
  =====================================================================
  Đây là component tab nội bộ — được render động bên trong
  pages/general/index.vue khi bấm mục tương ứng trên sidebar.
  Bạn điền nội dung cấu hình vào vùng <VCard> bên dưới.
  =====================================================================
-->
<script setup>

// Props/emit nếu tab cần giao tiếp với trang cha.
</script>

<template>
  <VCard
    title="Sao lưu & Khôi phục"
    subtitle="Sao lưu dữ liệu, khôi phục hệ thống"
  >
    <VCardText>
      <VAlert
        color="info"
        variant="tonal"
        icon="tabler-info-circle"
      >
        Nội dung tab <b>Sao lưu & Khôi phục</b> đang trong quá trình phát triển.
      </VAlert>
    </VCardText>
  </VCard>
</template>