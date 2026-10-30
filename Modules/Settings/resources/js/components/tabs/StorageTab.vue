<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab "Lưu trữ & Tài nguyên" trong trang Cài đặt (không phải route).
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
    title="Lưu trữ & Tài nguyên"
    subtitle="Ảnh, file, dung lượng, CDN"
  >
    <VCardText>
      <VAlert
        color="info"
        variant="tonal"
        icon="tabler-info-circle"
      >
        Nội dung tab <b>Lưu trữ & Tài nguyên</b> đang trong quá trình phát triển.
      </VAlert>
    </VCardText>
  </VCard>
</template>