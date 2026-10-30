<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab "AI & Mô hình" trong trang Cài đặt (không phải route).
  =====================================================================
  Đây là component tab nội bộ — được render động bên trong
  pages/general/index.vue khi bấm mục tương ứng trên sidebar.

  GIAO DIỆN TĨNH (static preview) — dữ liệu demo reactive cục bộ, chưa nối
  backend (ai_providers/ai_models/CoreAi triển khai ở P2-01). Chuẩn Vuexy:
  tabler-* icons + Vuetify components + typography/mật độ/rounded mặc định.

  CÁC HÀM/METHOD TRONG FILE: không — chỉ data + template.
  INPUT/OUTPUT CỦA CLASS (tổng thể): không — render UI tĩnh.
  SIDE EFFECT: không.
  =====================================================================
-->
<script setup>
import { reactive, ref } from 'vue'

// ---- Tab ngang bên trong phần AI & Mô hình (không phải route) ----
const activeTab = ref('overview')

const horizontalTabs = [
  { value: 'overview', label: 'Tổng quan', icon: 'tabler-dashboard' },
  { value: 'providers', label: 'Nhà cung cấp', icon: 'tabler-server' },
  { value: 'models', label: 'Mô hình AI', icon: 'tabler-cpu' },
  { value: 'prompts', label: 'Mẫu Prompt', icon: 'tabler-file-text' },
  { value: 'writing-styles', label: 'Phong cách viết', icon: 'tabler-pencil' },
  { value: 'image-styles', label: 'Phong cách hình ảnh', icon: 'tabler-photo' },
  { value: 'content-gen', label: 'Tạo nội dung', icon: 'tabler-wand' },
  { value: 'advanced', label: 'Cài đặt nâng cao', icon: 'tabler-adjustments' },
]

// ---- Dữ liệu tĩnh cho Tab Tổng quan ----
const overviewKpis = [
  { title: 'Nhà cung cấp', value: '6', subtext: 'Đang kết nối', icon: 'tabler-server', color: 'primary' },
  { title: 'Mô hình AI', value: '18', subtext: 'Đang sử dụng', icon: 'tabler-cpu', color: 'primary' },
  { title: 'Lượt tạo nội dung', value: '1,256', trend: '+12.5%', trendColor: 'text-success', subtext: '7 ngày qua', icon: 'tabler-file-analytics', color: 'info' },
  { title: 'Chi phí ước tính', value: '$12.34', trend: '+8.2%', trendColor: 'text-error', subtext: 'Tháng này', icon: 'tabler-credit-card', color: 'error' },
]

const activeProviders = [
  { name: 'OpenAI', active: true, statusText: 'Đang hoạt động', modelCount: '3 mô hình', pricing: '$2.45 / 1M tokens', icon: 'tabler-sparkles', bg: '#10a37f', color: 'white' },
  { name: 'Anthropic', active: true, statusText: 'Đang hoạt động', modelCount: '2 mô hình', pricing: '$2.90 / 1M tokens', shortText: 'A\\', bg: '#f3e5d8', color: '#1f1f1f' },
  { name: 'Google AI (Gemini)', active: true, statusText: 'Đang hoạt động', modelCount: '4 mô hình', pricing: '$0.70 / 1M tokens', icon: 'tabler-star-four-points', bg: '#eef2ff', color: '#4285F4' },
  { name: 'Stability AI', active: false, statusText: 'Chưa cấu hình', modelCount: '2 mô hình', pricing: '-', icon: 'tabler-photo', bg: '#5b4ce0', color: 'white' },
  { name: 'Runway', active: true, statusText: 'Đang hoạt động', modelCount: '3 mô hình', pricing: '$0.50 / 1M tokens', shortText: 'R', bg: '#18181b', color: 'white' },
  { name: 'Replicate', active: false, statusText: 'Chưa cấu hình', modelCount: '1 mô hình', pricing: '-', icon: 'tabler-code', bg: '#f4f4f5', color: '#18181b' },
]

const popularModels = [
  { name: 'GPT-4o', provider: 'OpenAI', calls: 420, percent: 33.4, icon: 'tabler-sparkles', bg: '#27272a', color: 'white' },
  { name: 'Claude 3.5 Sonnet', provider: 'Anthropic', calls: 318, percent: 25.3, shortText: 'A\\', bg: '#f3e5d8', color: '#18181b' },
  { name: 'Gemini 1.5 Flash', provider: 'Google', calls: 215, percent: 17.1, icon: 'tabler-star-four-points', bg: '#eef2ff', color: '#4285F4' },
  { name: 'DALL-E 3', provider: 'OpenAI', calls: 140, percent: 11.1, icon: 'tabler-photo', bg: '#18181b', color: 'white' },
  { name: 'Stable Diffusion XL', provider: 'Stability AI', calls: 98, percent: 7.8, shortText: 'S', bg: '#f4f2fe', color: '#5b4ce0' },
]

const quickTools = [
  { title: 'Kiểm tra kết nối API', desc: 'Kiểm tra trạng thái các nhà cung cấp', icon: 'tabler-plug-connected', color: 'success', targetTab: 'providers' },
  { title: 'Xem nhật ký sử dụng', desc: 'Xem chi tiết lượt sử dụng và chi phí', icon: 'tabler-file-analytics', color: 'primary', targetTab: 'models' },
  { title: 'Quản lý mẫu Prompt', desc: 'Tạo, chỉnh sửa và quản lý mẫu', icon: 'tabler-file-text', color: 'primary', targetTab: 'prompts' },
  { title: 'Thư viện phong cách', desc: 'Quản lý phong cách viết và hình ảnh', icon: 'tabler-palette', color: 'error', targetTab: 'writing-styles' },
  { title: 'Xóa cache mô hình', desc: 'Xóa dữ liệu tạm, làm mới cấu hình', icon: 'tabler-refresh', color: 'warning', targetTab: 'advanced' },
]

const defaults = reactive({ textModel: 'GPT-4o', imageModel: 'DALL-E 3', videoModel: 'Runway Gen-3', language: 'Tiếng Việt' })
const limits = reactive({ dailyCostUsd: '10', rpm: '60', costControl: true, alert80: true, autoPause: false, detailedLogs: true })

// ---- Dữ liệu Tab Nhà cung cấp ----
const providerDetails = ref([
  { name: 'OpenAI', latency: '320ms', modelsCount: 3, enabled: true, apiKey: 'sk-proj-98a7sdf987a98sdf798a7sdf', endpoint: 'https://api.openai.com/v1', monthlyCap: '50', showKey: false, icon: 'tabler-sparkles', bg: '#10a37f', color: 'white' },
  { name: 'Anthropic (Claude)', latency: '410ms', modelsCount: 2, enabled: true, apiKey: 'sk-ant-api03-876sdf876sdf876s', endpoint: 'https://api.anthropic.com/v1', monthlyCap: '40', showKey: false, icon: 'tabler-brain', bg: '#f3e5d8', color: '#18181b' },
  { name: 'Google AI (Gemini)', latency: '240ms', modelsCount: 4, enabled: true, apiKey: 'AIzaSyB9876sdf876sdf876sdf87', endpoint: 'https://generativelanguage.googleapis.com', monthlyCap: '30', showKey: false, icon: 'tabler-star-four-points', bg: '#eef2ff', color: '#4285F4' },
  { name: 'RunwayML Video', latency: '1,200ms', modelsCount: 3, enabled: true, apiKey: 'rw-live-987sdf987sdf987sdf', endpoint: 'https://api.runwayml.com/v1', monthlyCap: '80', showKey: false, icon: 'tabler-video', bg: '#18181b', color: 'white' },
])

// ---- Dữ liệu Tab Mô hình AI ----
const aiModelCatalog = ref([
  { id: 1, name: 'GPT-4o', provider: 'OpenAI', type: 'Văn bản (LLM)', typeColor: 'primary', context: '128K tokens', cost: '$2.50 / 1M', speed: 'Rất nhanh', isDefault: true, active: true },
  { id: 2, name: 'Claude 3.5 Sonnet', provider: 'Anthropic', type: 'Văn bản (LLM)', typeColor: 'primary', context: '200K tokens', cost: '$3.00 / 1M', speed: 'Nhanh', isDefault: false, active: true },
  { id: 3, name: 'Gemini 1.5 Flash', provider: 'Google AI', type: 'Văn bản (LLM)', typeColor: 'primary', context: '1M tokens', cost: '$0.35 / 1M', speed: 'Siêu tốc', isDefault: false, active: true },
  { id: 4, name: 'DALL-E 3 HD', provider: 'OpenAI', type: 'Hình ảnh', typeColor: 'info', context: '1024x1024px', cost: '$0.04 / ảnh', speed: 'Trung bình', isDefault: true, active: true },
  { id: 5, name: 'Stable Diffusion XL', provider: 'Stability AI', type: 'Hình ảnh', typeColor: 'info', context: '1024x1024px', cost: '$0.01 / ảnh', speed: 'Nhanh', isDefault: false, active: false },
  { id: 6, name: 'Runway Gen-3 Alpha', provider: 'Runway', type: 'Video', typeColor: 'warning', context: '10s 1080p', cost: '$0.50 / video', speed: 'Chậm', isDefault: true, active: true },
])

// ---- Dữ liệu Tab Mẫu Prompt ----
const promptTemplates = ref([
  { id: 1, category: 'Social Post', title: 'Bài đăng giới thiệu sản phẩm chuẩn chuyển đổi', uses: 342, template: 'Hãy đóng vai chuyên gia Content Marketing. Viết bài đăng cho {{platform}} giới thiệu sản phẩm {{product_name}} với giọng văn {{tone}}. Nhấn mạnh 3 lợi ích chính và kết thúc bằng CTA.', variables: ['{{platform}}', '{{product_name}}', '{{tone}}'] },
  { id: 2, category: 'Image Prompt', title: 'Tạo câu lệnh ảnh sản phẩm thương mại', uses: 218, template: 'A professional commercial product photography of {{subject}}, placed on {{surface}}, {{lighting}} lighting, shallow depth of field, 8k photorealistic.', variables: ['{{subject}}', '{{surface}}', '{{lighting}}'] },
])

// ---- Dữ liệu Tab Phong cách viết ----
const writingVoices = [
  { name: 'Thân thiện & Trẻ trung', desc: 'Gần gũi, bắt trend, dùng từ ngữ đời thường thu hút tương tác GenZ.', traits: ['Gần gũi', 'Bắt trend', 'Có Emoji'], icon: 'tabler-mood-smile', bg: 'primary', color: 'primary', isDefault: true },
  { name: 'Chuyên gia & Tin cậy', desc: 'Rõ ràng, mạch lạc, dẫn chứng số liệu thuyết phục khách hàng B2B.', traits: ['Chuyên sâu', 'Trang trọng', 'Số liệu'], icon: 'tabler-briefcase', bg: 'info', color: 'info', isDefault: false },
  { name: 'Bán hàng Trực diện (Direct Sales)', desc: 'Đi thẳng vào ưu đãi, đánh trúng tâm lý FOMO và thúc đẩy chốt đơn.', traits: ['Chốt đơn', 'FOMO', 'CTA mạnh'], icon: 'tabler-speakerphone', bg: 'success', color: 'success', isDefault: false },
]

// ---- Dữ liệu Tab Phong cách hình ảnh ----
const imagePresetConfig = reactive({
  styleSuffix: 'photorealistic, 8k resolution, cinematic lighting, commercial photography, sharp focus, natural warm tones',
  negativePrompt: 'blurry, low quality, distorted hands, extra fingers, watermark, text, oversaturated, ugly',
  autoUpscale: true,
  autoWatermark: false,
  convertWebp: true,
})

// ---- Dữ liệu Tab Tạo nội dung ----
const contentPipeline = reactive({
  hashtagCount: '5 – 10 Hashtags',
  emojiLevel: 'Vừa phải (2–4 emoji/đoạn)',
  autoCta: true,
  policyCheck: true,
  autoTranslate: false,
})

// ---- Dữ liệu Tab Cài đặt nâng cao ----
const advancedAi = reactive({
  temperature: 0.7,
  fallbackModel: 'Claude 3.5 Sonnet (Anthropic)',
  webhookUrl: 'https://api.yourdomain.com/webhooks/ai-completed',
  semanticCache: true,
})

const popularTimeframe = ref('7 ngày qua')
const modelTypeFilter = ref('Tất cả loại')

function goToTab(tab) {
  if (tab) { activeTab.value = tab }
}
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Banner giới thiệu -->
    <VCard border>
      <!-- 8 tabs ngang bên trong -->
      <VTabs
        v-model="activeTab"
        color="primary"
        density="comfortable"
        show-arrows
        class="border-b"
      >
        <VTab
          v-for="tab in horizontalTabs"
          :key="tab.value"
          :value="tab.value"
          class="text-none font-weight-medium text-caption"
        >
          <VIcon
            size="18"
            class="me-2"
            :icon="tab.icon"
          />
          {{ tab.label }}
        </VTab>
      </VTabs>
    </VCard>

    <!-- Nội dung từng tab -->
    <VWindow v-model="activeTab">
      <!-- TAB 1: TỔNG QUAN -->
      <VWindowItem value="overview">
        <div class="d-flex flex-column gap-4">
          <!-- Hàng 4 thẻ KPI -->
          <VRow dense>
            <VCol
              v-for="kpi in overviewKpis"
              :key="kpi.title"
              cols="12"
              sm="6"
              lg="3"
            >
              <VCard border>
                <VCardText class="d-flex align-center justify-space-between pa-4">
                  <div>
                    <div class="text-body-2 text-medium-emphasis mb-1">
                      {{ kpi.title }}
                    </div>
                    <div class="d-flex align-baseline gap-2 mb-1">
                      <span class="text-h5 font-weight-medium">{{ kpi.value }}</span>
                      <span
                        v-if="kpi.trend"
                        class="text-caption font-weight-medium"
                        :class="kpi.trendColor"
                      >
                        {{ kpi.trend }}
                      </span>
                    </div>
                    <div class="text-caption text-disabled">
                      {{ kpi.subtext }}
                    </div>
                  </div>
                  <VAvatar
                    :color="kpi.color"
                    variant="tonal"
                    rounded="lg"
                    size="40"
                  >
                    <VIcon
                      :color="kpi.color"
                      size="20"
                      :icon="kpi.icon"
                    />
                  </VAvatar>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- Nhà cung cấp + Mô hình phổ biến -->
          <VRow dense>
            <VCol
              cols="12"
              lg="7"
            >
              <VCard
                title="Nhà cung cấp đang sử dụng"
                subtitle="Quản lý kết nối và trạng thái các nhà cung cấp AI."
                border
              >
                <template #append>
                  <VBtn
                    size="small"
                    prepend-icon="tabler-plus"
                    @click="goToTab('providers')"
                  >
                    Thêm nhà cung cấp
                  </VBtn>
                </template>
                <VCardText class="pa-0">
                  <div class="d-flex flex-column">
                    <div
                      v-for="(prov, idx) in activeProviders"
                      :key="prov.name"
                      class="d-flex align-center justify-space-between pa-4"
                      :class="{ 'border-b': idx < activeProviders.length - 1 }"
                    >
                      <div
                        class="d-flex align-center"
                        style="min-width: 195px;"
                      >
                        <VAvatar
                          :color="prov.bg"
                          size="36"
                          rounded="lg"
                          class="me-3 font-weight-bold"
                        >
                          <VIcon
                            v-if="prov.icon"
                            :color="prov.color"
                            size="20"
                            :icon="prov.icon"
                          />
                          <span
                            v-else
                            :style="{ color: prov.color, fontSize: '13px' }"
                          >{{ prov.shortText }}</span>
                        </VAvatar>
                        <div>
                          <div class="text-body-1 font-weight-medium">
                            {{ prov.name }}
                          </div>
                          <div class="d-flex align-center gap-1">
                            <span
                              class="status-dot"
                              :style="{ backgroundColor: prov.active ? 'rgb(var(--v-theme-success))' : 'rgb(var(--v-theme-warning))' }"
                            />
                            <span
                              class="text-caption"
                              :class="prov.active ? 'text-success' : 'text-warning'"
                            >
                              {{ prov.statusText }}
                            </span>
                          </div>
                        </div>
                      </div>
                      <div class="d-none d-sm-block text-left">
                        <div class="text-body-2 font-weight-medium">
                          {{ prov.modelCount }}
                        </div>
                        <div class="text-caption text-disabled">
                          {{ prov.pricing }}
                        </div>
                      </div>
                      <div class="d-flex align-center gap-2">
                        <VBtn
                          variant="tonal"
                          size="small"
                          @click="goToTab('providers')"
                        >
                          Cấu hình
                        </VBtn>
                        <IconBtn size="small">
                          <VIcon icon="tabler-dots-vertical" />
                        </IconBtn>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              lg="5"
            >
              <VCard
                title="Mô hình AI phổ biến"
                subtitle="Các mô hình được sử dụng nhiều nhất trong hệ thống."
                border
              >
                <template #append>
                  <AppSelect
                    v-model="popularTimeframe"
                    :items="['7 ngày qua', '30 ngày qua', 'Tháng này']"
                    hide-details
                    style="min-width: 110px;"
                  />
                </template>
                <VCardText class="pa-0">
                  <div class="d-flex flex-column">
                    <div
                      v-for="(m, idx) in popularModels"
                      :key="m.name"
                      class="d-flex align-center justify-space-between pa-4"
                      :class="{ 'border-b': idx < popularModels.length - 1 }"
                    >
                      <div class="d-flex align-center flex-grow-1 me-3">
                        <span
                          class="text-caption font-weight-medium text-disabled me-3"
                          style="width: 12px;"
                        >
                          {{ idx + 1 }}
                        </span>
                        <VAvatar
                          :color="m.bg"
                          size="34"
                          rounded="lg"
                          class="me-3 flex-shrink-0 font-weight-bold"
                        >
                          <VIcon
                            v-if="m.icon"
                            :color="m.color"
                            size="18"
                            :icon="m.icon"
                          />
                          <span
                            v-else
                            :style="{ color: m.color, fontSize: '13px' }"
                          >{{ m.shortText }}</span>
                        </VAvatar>
                        <div class="flex-grow-1 me-3">
                          <div class="text-body-1 font-weight-medium">
                            {{ m.name }}
                          </div>
                          <div class="text-caption text-disabled">
                            {{ m.provider }}
                          </div>
                        </div>
                        <div
                          class="flex-grow-1 d-none d-sm-block"
                          style="max-width: 110px;"
                        >
                          <VProgressLinear
                            :model-value="m.percent * 2.2"
                            color="primary"
                            bg-color="grey-lighten-3"
                            height="5"
                            rounded
                          />
                        </div>
                      </div>
                      <div
                        class="text-end flex-shrink-0"
                        style="min-width: 42px;"
                      >
                        <div class="text-body-1 font-weight-medium">
                          {{ m.calls }}
                        </div>
                        <div class="text-caption text-disabled">
                          {{ m.percent }}%
                        </div>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <!-- 3 card dưới -->
          <VRow dense>
            <VCol
              cols="12"
              lg="4"
            >
              <VCard
                title="Cài đặt mặc định"
                subtitle="Cấu hình mặc định cho các tác vụ tạo nội dung."
                border
              >
                <VCardText>
                  <div class="mb-4">
                    <div class="text-body-2 font-weight-medium mb-2">
                      Mô hình mặc định (Văn bản)
                    </div>
                    <AppSelect
                      v-model="defaults.textModel"
                      :items="['GPT-4o', 'Claude 3.5 Sonnet', 'Gemini 1.5 Pro']"
                      prepend-inner-icon="tabler-sparkles"
                      hide-details
                    />
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 font-weight-medium mb-2">
                      Mô hình mặc định (Hình ảnh)
                    </div>
                    <AppSelect
                      v-model="defaults.imageModel"
                      :items="['DALL-E 3', 'Midjourney v6', 'Stable Diffusion XL']"
                      prepend-inner-icon="tabler-photo"
                      hide-details
                    />
                  </div>
                  <div class="mb-4">
                    <div class="text-body-2 font-weight-medium mb-2">
                      Mô hình mặc định (Video)
                    </div>
                    <AppSelect
                      v-model="defaults.videoModel"
                      :items="['Runway Gen-3', 'Luma Dream Machine', 'Kling AI']"
                      prepend-inner-icon="tabler-video"
                      hide-details
                    />
                  </div>
                  <div>
                    <div class="text-body-2 font-weight-medium mb-2">
                      Ngôn ngữ mặc định
                    </div>
                    <AppSelect
                      v-model="defaults.language"
                      :items="['Tiếng Việt', 'English', '日本語']"
                      prepend-inner-icon="tabler-language"
                      hide-details
                    />
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              lg="4"
            >
              <VCard
                title="Giới hạn và kiểm soát"
                subtitle="Thiết lập giới hạn sử dụng và kiểm soát chi phí."
                border
              >
                <VCardText>
                  <VRow
                    dense
                    class="mb-4"
                  >
                    <VCol cols="6">
                      <div class="text-body-2 font-weight-medium mb-2">
                        Giới hạn chi phí/ngày (USD)
                      </div>
                      <AppTextField
                        v-model="limits.dailyCostUsd"
                        hide-details
                      />
                    </VCol>
                    <VCol cols="6">
                      <div class="text-body-2 font-weight-medium mb-2">
                        Giới hạn yêu cầu/phút
                      </div>
                      <AppTextField
                        v-model="limits.rpm"
                        hide-details
                      />
                    </VCol>
                  </VRow>
                  <div class="d-flex flex-column gap-4">
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center text-body-2">
                        <VIcon
                          size="18"
                          color="primary"
                          class="me-2"
                          icon="tabler-cash"
                        />
                        <span>Bật kiểm soát chi phí</span>
                      </div>
                      <VSwitch
                        v-model="limits.costControl"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center text-body-2">
                        <VIcon
                          size="18"
                          color="primary"
                          class="me-2"
                          icon="tabler-bell-ringing"
                        />
                        <span>Cảnh báo khi vượt 80%</span>
                      </div>
                      <VSwitch
                        v-model="limits.alert80"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center text-body-2">
                        <VIcon
                          size="18"
                          class="me-2"
                          icon="tabler-pause-circle"
                        />
                        <span>Tự động tạm dừng khi vượt giới hạn</span>
                      </div>
                      <VSwitch
                        v-model="limits.autoPause"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <div class="d-flex align-center text-body-2">
                        <VIcon
                          size="18"
                          color="primary"
                          class="me-2"
                          icon="tabler-file-analytics"
                        />
                        <span>Ghi nhật ký chi tiết</span>
                      </div>
                      <VSwitch
                        v-model="limits.detailedLogs"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              lg="4"
            >
              <VCard
                title="Công cụ nhanh"
                subtitle="Các công cụ hỗ trợ quản lý AI."
                border
              >
                <VCardText>
                  <div class="d-flex flex-column gap-1">
                    <div
                      v-for="tool in quickTools"
                      :key="tool.title"
                      class="quick-tool-row pa-3 rounded-lg d-flex align-center justify-space-between cursor-pointer"
                      @click="goToTab(tool.targetTab)"
                    >
                      <div class="d-flex align-center">
                        <VAvatar
                          :color="tool.color"
                          variant="tonal"
                          size="32"
                          rounded="lg"
                          class="me-3"
                        >
                          <VIcon
                            :color="tool.color"
                            size="16"
                            :icon="tool.icon"
                          />
                        </VAvatar>
                        <div>
                          <div class="text-body-2 font-weight-medium">
                            {{ tool.title }}
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ tool.desc }}
                          </div>
                        </div>
                      </div>
                      <VIcon
                        size="16"
                        color="secondary"
                        icon="tabler-chevron-right"
                      />
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VWindowItem>

      <!-- TAB 2: NHÀ CUNG CẤP -->
      <VWindowItem value="providers">
        <VCard
          title="Cấu hình Nhà cung cấp AI (API Keys &amp; Endpoints)"
          subtitle="Thiết lập khóa xác thực API, kiểm tra độ trễ phản hồi và giới hạn ngân sách cho từng nhà cung cấp."
          border
        >
          <template #append>
            <VBtn
              prepend-icon="tabler-plug-connected"
              size="small"
            >
              Kiểm tra tất cả kết nối
            </VBtn>
          </template>
          <VCardText>
            <VRow>
              <VCol
                v-for="prov in providerDetails"
                :key="prov.name"
                cols="12"
                md="6"
              >
                <VCard
                  border
                  class="pa-4 h-100 d-flex flex-column justify-space-between"
                >
                  <div>
                    <div class="d-flex justify-space-between align-start mb-4">
                      <div class="d-flex align-center">
                        <VAvatar
                          :color="prov.bg"
                          size="40"
                          rounded="lg"
                          class="me-3 font-weight-bold"
                        >
                          <VIcon
                            :color="prov.color"
                            size="20"
                            :icon="prov.icon"
                          />
                        </VAvatar>
                        <div>
                          <div class="text-body-1 font-weight-medium">
                            {{ prov.name }}
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            Độ trễ TB: <strong>{{ prov.latency }}</strong> • {{ prov.modelsCount }} mô hình
                          </div>
                        </div>
                      </div>
                      <VSwitch
                        v-model="prov.enabled"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="mb-4">
                      <div class="text-body-2 font-weight-medium mb-2">
                        API Secret Key
                      </div>
                      <AppTextField
                        v-model="prov.apiKey"
                        :type="prov.showKey ? 'text' : 'password'"
                        :append-inner-icon="prov.showKey ? 'tabler-eye-off' : 'tabler-eye'"
                        hide-details
                        @click:append-inner="prov.showKey = !prov.showKey"
                      />
                    </div>
                    <VRow
                      dense
                      class="mb-4"
                    >
                      <VCol cols="7">
                        <div class="text-body-2 font-weight-medium mb-2">
                          Base URL / Endpoint
                        </div>
                        <AppTextField
                          v-model="prov.endpoint"
                          hide-details
                        />
                      </VCol>
                      <VCol cols="5">
                        <div class="text-body-2 font-weight-medium mb-2">
                          Hạn mức tháng ($)
                        </div>
                        <AppTextField
                          v-model="prov.monthlyCap"
                          prefix="$"
                          hide-details
                        />
                      </VCol>
                    </VRow>
                  </div>
                  <div class="d-flex justify-space-between align-center pt-3 border-t">
                    <VChip
                      size="small"
                      :color="prov.enabled ? 'success' : 'warning'"
                      variant="tonal"
                    >
                      {{ prov.enabled ? 'Đã xác thực API' : 'Chưa kích hoạt' }}
                    </VChip>
                    <VBtn
                      variant="text"
                      size="small"
                      prepend-icon="tabler-plug-connected"
                    >
                      Ping thử
                    </VBtn>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 3: MÔ HÌNH AI -->
      <VWindowItem value="models">
        <VCard
          title="Danh mục Mô hình AI"
          subtitle="Bật/tắt các mô hình được phép sử dụng trong hệ thống tạo nội dung, hình ảnh và video."
          border
        >
          <template #append>
            <div class="d-flex align-center gap-2">
              <AppSelect
                v-model="modelTypeFilter"
                :items="['Tất cả loại', 'Văn bản (LLM)', 'Hình ảnh', 'Video']"
                hide-details
                style="min-width: 160px;"
              />
              <VBtn
                prepend-icon="tabler-plus"
                size="small"
              >
                Thêm Model
              </VBtn>
            </div>
          </template>
          <VCardText class="pa-0">
            <VTable
              density="comfortable"
              class="border-t"
            >
              <thead>
                <tr class="bg-grey-lighten-5">
                  <th>Mô hình</th>
                  <th>Nhà cung cấp</th>
                  <th>Phân loại</th>
                  <th>Context / Độ phân giải</th>
                  <th>Chi phí</th>
                  <th>Tốc độ</th>
                  <th>Kích hoạt</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="m in aiModelCatalog"
                  :key="m.id"
                >
                  <td class="font-weight-medium">
                    {{ m.name }}
                    <VChip
                      v-if="m.isDefault"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                      class="ms-2"
                    >
                      Mặc định
                    </VChip>
                  </td>
                  <td>{{ m.provider }}</td>
                  <td>
                    <VChip
                      size="small"
                      :color="m.typeColor"
                      variant="tonal"
                    >
                      {{ m.type }}
                    </VChip>
                  </td>
                  <td class="font-mono">
                    {{ m.context }}
                  </td>
                  <td class="font-weight-medium">
                    {{ m.cost }}
                  </td>
                  <td>
                    <VChip
                      size="small"
                      color="success"
                      variant="outlined"
                    >
                      {{ m.speed }}
                    </VChip>
                  </td>
                  <td>
                    <VSwitch
                      v-model="m.active"
                      color="primary"
                      hide-details
                      inset
                    />
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 4: MẪU PROMPT -->
      <VWindowItem value="prompts">
        <VCard
          title="Thư viện Mẫu Prompt Hệ thống"
          subtitle="Quản lý các mẫu câu lệnh chuẩn có chứa biến động để AI tự động sinh nội dung đồng nhất."
          border
        >
          <template #append>
            <VBtn
              prepend-icon="tabler-plus"
              size="small"
            >
              Tạo mẫu Prompt mới
            </VBtn>
          </template>
          <VCardText>
            <VRow>
              <VCol
                v-for="tpl in promptTemplates"
                :key="tpl.id"
                cols="12"
                md="6"
              >
                <VCard
                  border
                  class="pa-4 h-100 d-flex flex-column justify-space-between bg-grey-lighten-5"
                >
                  <div>
                    <div class="d-flex justify-space-between align-center mb-2">
                      <VChip
                        size="small"
                        color="primary"
                        variant="tonal"
                      >
                        {{ tpl.category }}
                      </VChip>
                      <span class="text-caption text-disabled">{{ tpl.uses }} lượt dùng</span>
                    </div>
                    <div class="text-body-1 font-weight-medium mb-2">
                      {{ tpl.title }}
                    </div>
                    <div
                      class="pa-3 rounded-lg bg-white border text-caption font-mono mb-3"
                      style="line-height: 1.5;"
                    >
                      {{ tpl.template }}
                    </div>
                  </div>
                  <div class="d-flex justify-space-between align-center">
                    <div class="d-flex flex-wrap gap-1">
                      <VChip
                        v-for="v in tpl.variables"
                        :key="v"
                        size="x-small"
                        variant="outlined"
                        color="primary"
                        class="font-mono"
                      >
                        {{ v }}
                      </VChip>
                    </div>
                    <IconBtn size="small">
                      <VIcon icon="tabler-pencil" />
                    </IconBtn>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 5: PHONG CÁCH VIẾT -->
      <VWindowItem value="writing-styles">
        <VCard
          title="Cấu hình Phong cách viết (Brand Voice)"
          subtitle="Thiết lập giọng điệu thương hiệu, mật độ sử dụng Emoji và từ khóa cấm khi AI viết bài."
          border
        >
          <template #append>
            <VBtn
              prepend-icon="tabler-plus"
              size="small"
            >
              Thêm giọng văn
            </VBtn>
          </template>
          <VCardText>
            <VRow>
              <VCol
                v-for="voice in writingVoices"
                :key="voice.name"
                cols="12"
                md="4"
              >
                <VCard
                  border
                  class="pa-4 h-100"
                >
                  <div class="d-flex justify-space-between align-center mb-3">
                    <VAvatar
                      :color="voice.bg"
                      variant="tonal"
                      size="40"
                      rounded="lg"
                    >
                      <VIcon
                        :color="voice.color"
                        size="20"
                        :icon="voice.icon"
                      />
                    </VAvatar>
                    <VChip
                      v-if="voice.isDefault"
                      size="small"
                      color="success"
                      variant="tonal"
                    >
                      Mặc định
                    </VChip>
                  </div>
                  <div class="text-body-1 font-weight-medium mb-1">
                    {{ voice.name }}
                  </div>
                  <div class="text-caption text-medium-emphasis mb-3">
                    {{ voice.desc }}
                  </div>
                  <div class="d-flex flex-wrap gap-1">
                    <VChip
                      v-for="t in voice.traits"
                      :key="t"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ t }}
                    </VChip>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 6: PHONG CÁCH HÌNH ẢNH -->
      <VWindowItem value="image-styles">
        <VCard
          title="Cấu hình Phong cách Hình ảnh Mặc định"
          subtitle="Thiết lập hậu tố mỹ thuật (Style Suffix), Negative Prompt và đóng dấu Watermark tự động."
          border
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="7"
              >
                <div class="mb-4">
                  <div class="text-body-2 font-weight-medium mb-2">
                    Hậu tố phong cách mặc định (Global Style Suffix)
                  </div>
                  <AppTextarea
                    v-model="imagePresetConfig.styleSuffix"
                    rows="2"
                    hide-details
                  />
                </div>
                <div>
                  <div class="text-body-2 font-weight-medium mb-2">
                    Negative Prompt mặc định (Chi tiết cần loại bỏ)
                  </div>
                  <AppTextarea
                    v-model="imagePresetConfig.negativePrompt"
                    rows="2"
                    hide-details
                  />
                </div>
              </VCol>
              <VCol
                cols="12"
                md="5"
              >
                <VCard
                  border
                  class="pa-4 bg-grey-lighten-5 h-100"
                >
                  <div class="text-body-2 font-weight-medium mb-3">
                    Tùy chọn hậu kỳ ảnh AI
                  </div>
                  <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-body-2">Tự động Upscale 2x độ nét cao</span>
                      <VSwitch
                        v-model="imagePresetConfig.autoUpscale"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-body-2">Tự động chèn Logo Watermark góc ảnh</span>
                      <VSwitch
                        v-model="imagePresetConfig.autoWatermark"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-body-2">Chuyển đổi định dạng sang WebP tối ưu</span>
                      <VSwitch
                        v-model="imagePresetConfig.convertWebp"
                        color="primary"
                        hide-details
                        inset
                      />
                    </div>
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 7: TẠO NỘI DUNG -->
      <VWindowItem value="content-gen">
        <VCard
          title="Quy trình Tự động Tạo Nội dung (Content Pipeline)"
          subtitle="Tùy chỉnh cấu trúc bài đăng mạng xã hội, số lượng Hashtag và bộ lọc an toàn nội dung."
          border
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <div class="mb-4">
                  <div class="text-body-2 font-weight-medium mb-2">
                    Số lượng Hashtag tự động mỗi bài
                  </div>
                  <AppSelect
                    v-model="contentPipeline.hashtagCount"
                    :items="['3 – 5 Hashtags', '5 – 10 Hashtags', 'Không dùng Hashtag']"
                    hide-details
                  />
                </div>
                <div>
                  <div class="text-body-2 font-weight-medium mb-2">
                    Mức độ sử dụng biểu tượng cảm xúc (Emoji)
                  </div>
                  <AppSelect
                    v-model="contentPipeline.emojiLevel"
                    :items="['Vừa phải (2–4 emoji/đoạn)', 'Nhiều & Sinh động', 'Tối giản (Chỉ ở tiêu đề)']"
                    hide-details
                  />
                </div>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <div class="d-flex flex-column gap-4">
                  <div class="d-flex align-center justify-space-between">
                    <span class="text-body-2">Tự động thêm câu kêu gọi hành động (CTA) cuối bài</span>
                    <VSwitch
                      v-model="contentPipeline.autoCta"
                      color="primary"
                      hide-details
                      inset
                    />
                  </div>
                  <div class="d-flex align-center justify-space-between">
                    <span class="text-body-2">Kiểm duyệt từ ngữ vi phạm chính sách quảng cáo</span>
                    <VSwitch
                      v-model="contentPipeline.policyCheck"
                      color="primary"
                      hide-details
                      inset
                    />
                  </div>
                  <div class="d-flex align-center justify-space-between">
                    <span class="text-body-2">Tự động dịch đa ngôn ngữ khi đăng chéo nền tảng</span>
                    <VSwitch
                      v-model="contentPipeline.autoTranslate"
                      color="primary"
                      hide-details
                      inset
                    />
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 8: CÀI ĐẶT NÂNG CAO -->
      <VWindowItem value="advanced">
        <VCard
          title="Cài đặt Kỹ thuật &amp; Siêu tham số (Hyperparameters)"
          subtitle="Điều chỉnh độ sáng tạo của mô hình (Temperature), cơ chế chuyển đổi dự phòng (Fallback) và bộ nhớ đệm."
          border
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <div class="mb-4">
                  <div class="d-flex justify-space-between align-center mb-2">
                    <span class="text-body-2 font-weight-medium">Độ sáng tạo (Temperature: {{ advancedAi.temperature }})</span>
                    <span class="text-caption text-medium-emphasis">0.0 = Chính xác • 1.0 = Sáng tạo</span>
                  </div>
                  <VSlider
                    v-model="advancedAi.temperature"
                    min="0"
                    max="1"
                    step="0.1"
                    color="primary"
                    hide-details
                  />
                </div>
                <div>
                  <div class="text-body-2 font-weight-medium mb-2">
                    Mô hình dự phòng khi quá tải (Fallback Model)
                  </div>
                  <AppSelect
                    v-model="advancedAi.fallbackModel"
                    :items="['Claude 3.5 Sonnet (Anthropic)', 'Gemini 1.5 Flash (Google)', 'GPT-4o Mini (OpenAI)']"
                    hide-details
                  />
                </div>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <div class="mb-4">
                  <div class="text-body-2 font-weight-medium mb-2">
                    Webhook nhận sự kiện hoàn tất tác vụ AI
                  </div>
                  <AppTextField
                    v-model="advancedAi.webhookUrl"
                    prepend-inner-icon="tabler-webhook"
                    hide-details
                  />
                </div>
                <div class="d-flex align-center justify-space-between">
                  <div>
                    <div class="text-body-2 font-weight-medium">
                      Bật Semantic Cache
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      Lưu đệm câu trả lời tương tự — tiết kiệm tới 35% chi phí token.
                    </div>
                  </div>
                  <VSwitch
                    v-model="advancedAi.semanticCache"
                    color="primary"
                    hide-details
                    inset
                  />
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>
    </VWindow>
  </div>
</template>

<style scoped>
/* Chấm trạng thái nhà cung cấp. */
.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
}

/* Hàng công cụ nhanh. */
.quick-tool-row {
  transition: background-color 0.15s ease;
}
.quick-tool-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.06);
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
