<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang viết bài bằng AI (placeholder — P3-02 chưa implement).
  =====================================================================
  CÁC HÀM/METHOD TRONG FILE: không (composition surface tĩnh).
  INPUT/OUTPUT CỦA CLASS (tổng thể): route /admin/blog/ai-writer -> thông báo
  đang phát triển.
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
    <VCardItem
      title="Viết bài bằng AI"
      subtitle="Chức năng đang trong giai đoạn phát triển (P3-02 Pipeline AI 3 bước)"
    />
    <VCardText>
      <VAlert
        color="info"
        variant="tonal"
      >
        Pipeline AnalyzerPlannerAgent → WriterAgent → EditorAgent sẽ được
        tích hợp tại đây sau khi Modules/WritingStyle, Modules/CoreAi và
        Modules/Blog P3-01 → P3-03 hoàn tất.
      </VAlert>
    </VCardText>
  </VCard>
</template>
