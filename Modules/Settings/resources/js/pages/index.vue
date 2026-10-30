<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang trung tâm Cài đặt — tự động chuyển hướng sang tab
  cài đặt đầu tiên (Chung).
  =====================================================================
  CÁC HÀM/METHOD TRONG FILE: không (chỉ redirect).
  INPUT/OUTPUT: route /admin/settings -> redirect /admin/settings/general.
  SIDE EFFECT: router.replace.
  =====================================================================
-->
<script setup>
import { onMounted } from 'vue'

definePage({ meta: { layout: 'default' } })

const router = useRouter()

onMounted(() => {
  router.replace({ name: 'admin-settings-general' })
})
</script>

<template>
  <div />
</template>
