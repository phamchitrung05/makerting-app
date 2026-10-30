<!--
  =====================================================================
  CHỨC NĂNG FILE: Khung sidebar menu 12 nhóm cài đặt (cột trái).
  =====================================================================
  - Ô tìm kiếm + danh sách 12 mục; bấm mục -> emit('select', item.id).
  - Tab nội bộ (không phải route): activeTab đánh dấu mục đang chọn.
  PROPS:  activeTab (string id đang chọn).
  EMITS:  select (item.id) -> trang cha chuyển tab.
  =====================================================================
-->
<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  activeTab: { type: String, required: true },
})

const emit = defineEmits(['select'])

const searchSetting = ref('')

const menuItems = [
  { id: 'general', title: 'Thông tin chung', subtitle: 'Thông tin ứng dụng và doanh nghiệp', icon: 'tabler-settings' },
  { id: 'appearance', title: 'Giao diện', subtitle: 'Logo, màu sắc, bố cục, giao diện', icon: 'tabler-palette' },
  { id: 'ai-models', title: 'AI & Mô hình', subtitle: 'Cấu hình AI, mẫu viết, tạo ảnh/video', icon: 'tabler-wand' },
  { id: 'social', title: 'Mạng xã hội', subtitle: 'Facebook, TikTok, Instagram, ...', icon: 'tabler-share' },
  { id: 'email-notify', title: 'Email & Thông báo', subtitle: 'SMTP, mẫu email, cảnh báo', icon: 'tabler-mail' },
  { id: 'storage', title: 'Lưu trữ & Tài nguyên', subtitle: 'Ảnh, file, dung lượng, CDN', icon: 'tabler-database' },
  { id: 'api', title: 'API & Tích hợp', subtitle: 'API, webhooks, dịch vụ bên ngoài', icon: 'tabler-code' },
  { id: 'automation', title: 'Tự động hóa', subtitle: 'Lịch chạy, tác vụ nền, cron', icon: 'tabler-refresh-dot' },
  { id: 'security', title: 'Bảo mật', subtitle: 'Xác thực, bảo mật, nhật ký', icon: 'tabler-shield-check' },
  { id: 'users-roles', title: 'Người dùng & Phân quyền', subtitle: 'Quản lý người dùng, vai trò, quyền', icon: 'tabler-users' },
  { id: 'backup', title: 'Sao lưu & Khôi phục', subtitle: 'Sao lưu dữ liệu, khôi phục hệ thống', icon: 'tabler-cloud-upload' },
  { id: 'other', title: 'Khác', subtitle: 'Cài đặt nâng cao và tiện ích khác', icon: 'tabler-dots' },
]

const filteredMenu = computed(() => {
  const q = searchSetting.value.trim().toLowerCase()
  if (!q) { return menuItems }

  return menuItems.filter(i =>
    i.title.toLowerCase().includes(q) || i.subtitle.toLowerCase().includes(q),
  )
})

function select(item) {
  emit('select', item.id)
}
</script>

<template>
  <VCard class="settings-nav-card h-100">
    <VCardText>
      <AppTextField
        v-model="searchSetting"
        placeholder="Tìm kiếm cài đặt..."
        prepend-inner-icon="tabler-search"
        class="mb-4"
        hide-details
      />

      <div class="d-flex flex-column gap-1">
        <div
          v-for="item in filteredMenu"
          :key="item.id"
          class="settings-nav-item pa-3 rounded d-flex align-center cursor-pointer"
          :class="{ 'settings-nav-item--active': activeTab === item.id }"
          @click="select(item)"
        >
          <VAvatar
            :color="activeTab === item.id ? 'primary' : 'secondary'"
            variant="tonal"
            size="36"
            rounded
            class="me-3 flex-shrink-0"
          >
            <VIcon
              :icon="item.icon"
              size="20"
            />
          </VAvatar>
          <div class="text-truncate">
            <div
              class="text-body-2 font-weight-medium text-truncate"
              :class="{ 'text-primary': activeTab === item.id }"
            >
              {{ item.title }}
            </div>
            <div class="text-caption text-medium-emphasis text-truncate">
              {{ item.subtitle }}
            </div>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.settings-nav-item {
  transition: background-color 0.15s ease;
}
.settings-nav-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.06);
}
.settings-nav-item--active {
  background-color: rgba(var(--v-theme-primary), 0.12);
}
</style>
