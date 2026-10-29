<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab Cài đặt Chung (General Settings).
  =====================================================================
  CÁC HÀM/METHOD TRONG FILE: không (placeholder).
  INPUT/OUTPUT CỦA CLASS (tổng thể): route /admin/settings/general -> render.
  SIDE EFFECT: không.
  =====================================================================
-->
<script setup>
definePage({ meta: { layout: 'default' } })
</script>

<template>
  <VCard>
    <VCardItem title="Cài đặt Chung" subtitle="SiteSettings, timezone, ngôn ngữ, tiêu đề trang" />
    <VCardText>
      <VAlert color="info" variant="tonal">
        P1-03 Settings module chưa implement. Đây sẽ là form chỉnh SiteSettings
        (title, description, timezone, locale) qua SettingsRegistry.
      </VAlert>
    </VCardText>
  </VCard>
</template>
