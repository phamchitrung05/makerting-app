<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab Cài đặt Bảo mật (Security Settings).
  =====================================================================
  CÁC HÀM/METHOD TRONG FILE: không (placeholder).
  INPUT/OUTPUT CỦA CLASS (tổng thể): route /admin/settings/security -> render.
  SIDE EFFECT: không.
  =====================================================================
-->
<script setup>
definePage({ meta: { layout: 'default' } })
</script>

<template>
  <VCard>
    <VCardItem title="Cài đặt Bảo mật" subtitle="SecuritySettings — thời hạn token, giới hạn login, IP whitelist" />
    <VCardText>
      <VAlert color="info" variant="tonal">
        P1-03 Settings module chưa implement. Form SecuritySettings (token TTL,
        login attempt limit, IP allowlist, 2FA) sẽ ở đây.
      </VAlert>
    </VCardText>
  </VCard>
</template>
