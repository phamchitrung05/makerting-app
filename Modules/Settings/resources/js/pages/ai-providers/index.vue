<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab Cài đặt AI Providers (API keys, models).
  =====================================================================
  CÁC HÀM/METHOD TRONG FILE: không (placeholder).
  INPUT/OUTPUT CỦA CLASS (tổng thể): route /admin/settings/ai-providers -> render.
  SIDE EFFECT: không.
  =====================================================================
-->
<script setup>
definePage({ meta: { layout: 'default' } })
</script>

<template>
  <VCard>
    <VCardItem title="AI Providers" subtitle="Quản lý OpenAI, Gemini, Anthropic, ElevenLabs — P2-01" />
    <VCardText>
      <VAlert color="info" variant="tonal">
        P1-03 Settings + P2-01 CoreAi chưa implement. Đây sẽ là trang quản lý
        ai_providers (api_key, base_url, models, quota) qua SettingsRegistry.
      </VAlert>
    </VCardText>
  </VCard>
</template>
