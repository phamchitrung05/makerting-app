<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang Cài đặt Hệ thống — khung chính gồm sidebar
  12 mục (SettingsShell) + vùng nội dung render động theo tab.
  =====================================================================
  - Bấm mục trên sidebar -> emit('select') -> activeTab đổi ->
  <component :is="tabComponent"> render tab tương ứng.
  - Các tab nằm trong components/tabs/*Tab.vue (component, KHÔNG route).
  - Tab 'general' là nội dung mặc định.
  =====================================================================
-->
<script setup>
import { computed, ref } from 'vue'

// Tab components (auto-import global từ components/tabs)
import GeneralTab from '../../components/tabs/GeneralTab.vue'
import AppearanceTab from '../../components/tabs/AppearanceTab.vue'
import AiModelsTab from '../../components/tabs/AiModelsTab.vue'
import SocialTab from '../../components/tabs/SocialTab.vue'
import EmailNotifyTab from '../../components/tabs/EmailNotifyTab.vue'
import StorageTab from '../../components/tabs/StorageTab.vue'
import ApiTab from '../../components/tabs/ApiTab.vue'
import AutomationTab from '../../components/tabs/AutomationTab.vue'
import SecurityTab from '../../components/tabs/SecurityTab.vue'
import UsersRolesTab from '../../components/tabs/UsersRolesTab.vue'
import BackupTab from '../../components/tabs/BackupTab.vue'
import OtherTab from '../../components/tabs/OtherTab.vue'
import SettingsShell from '../../components/SettingsShell.vue'

definePage({ meta: { layout: 'default' } })

const activeTab = ref('general')

// Ánh xạ id tab -> component. Mặc định 'general'.
const tabMap = {
  'general': GeneralTab,
  'appearance': AppearanceTab,
  'ai-models': AiModelsTab,
  'social': SocialTab,
  'email-notify': EmailNotifyTab,
  'storage': StorageTab,
  'api': ApiTab,
  'automation': AutomationTab,
  'security': SecurityTab,
  'users-roles': UsersRolesTab,
  'backup': BackupTab,
  'other': OtherTab,
}

const tabComponent = computed(() => tabMap[activeTab.value] ?? GeneralTab)

function onSelect(id) {
  activeTab.value = id
}
</script>

<template>
  <div>
    <!-- Page header (chuẩn product/add §3.1) -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Cài đặt hệ thống
        </h4>
        <div class="text-body-1">
          Quản lý các thiết lập, tùy chỉnh và cấu hình cho toàn bộ hệ thống
        </div>
      </div>
    </div>

    <VRow>
      <!-- Cột trái: menu cài đặt (dùng chung) -->
      <VCol
        cols="12"
        md="4"
        lg="3"
      >
        <SettingsShell
          :active-tab="activeTab"
          @select="onSelect"
        />
      </VCol>

      <!-- Cột phải: nội dung tab động -->
      <VCol
        cols="12"
        md="8"
        lg="9"
      >
        <component :is="tabComponent" />
      </VCol>
    </VRow>
  </div>
</template>
