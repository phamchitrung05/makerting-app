<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab "Thông tin chung" — thông tin ứng dụng, liên hệ,
  cài đặt cơ bản, ngôn ngữ & định dạng (GIAO DIỆN TĨNH).
  =====================================================================
  Reactive cục bộ; saveSettings/resetDefaults chỉ console.log — sẽ nối
  useSettingsGroup khi tích hợp backend sau.
  =====================================================================
-->
<script setup>
import { reactive, ref } from 'vue'

const appInfo = reactive({
  name: 'Vuexy',
  website: 'https://example.com',
  slogan: 'Nền tảng quản trị hiện đại cho doanh nghiệp',
  shortDesc: 'Một hệ thống quản trị mạnh mẽ và linh hoạt, giúp bạn quản lý doanh nghiệp hiệu quả hơn.',
})

const contactInfo = reactive({
  email: 'info@example.com',
  phone: '+84 90 123 4567',
  address: '123 Đường ABC, Quận 1, TP. Hồ Chí Minh',
  timezone: '(GMT+07:00) Hồ Chí Minh',
})

const basicOptions = reactive({
  allowRegister: true,
  maintenance: false,
  multiDeviceLogin: true,
  showLandingPage: true,
  enableNotifications: true,
  activityLog: true,
})

const formatConfig = reactive({
  language: 'Tiếng Việt',
  dateFormat: 'DD/MM/YYYY',
  timeFormat: '24 giờ (14:30)',
  numberFormat: '1,234,567.89',
  currency: 'VND',
})

const logoInput = ref(null)
const logoPreview = ref(null)

function onPickLogo(e) {
  const file = e.target.files && e.target.files[0]
  if (!file) { return }
  logoPreview.value = URL.createObjectURL(file)
}

const saveSettings = () => {
  console.log('Đã lưu cài đặt:', { appInfo, contactInfo, basicOptions, formatConfig })
}

const resetDefaults = () => { console.log('Khôi phục mặc định') }

// Expose cho trang cha (page header) gọi trực tiếp qua ref.
defineExpose({ saveSettings, resetDefaults })
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <VCard
      title="Thông tin ứng dụng"
      subtitle="Cập nhật các thông tin cơ bản về ứng dụng của bạn."
    >
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="3"
          >
            <div class="text-body-2 mb-2">
              Logo ứng dụng
            </div>
            <div class="logo-upload-box rounded pa-4 d-flex flex-column align-center justify-center text-center">
              <VAvatar
                v-if="logoPreview"
                :image="logoPreview"
                size="56"
                rounded
                class="mb-3"
              />
              <VIcon
                v-else
                icon="tabler-photo"
                size="40"
                color="secondary"
                class="mb-3"
              />
              <VBtn
                variant="tonal"
                size="small"
                color="secondary"
                prepend-icon="tabler-upload"
                @click="logoInput && logoInput.click()"
              >
                Thay đổi logo
              </VBtn>
              <input
                ref="logoInput"
                type="file"
                accept="image/png,image/jpeg,image/svg+xml"
                class="d-none"
                @change="onPickLogo"
              >
            </div>
            <div class="text-caption text-disabled mt-2">
              PNG, JPG hoặc SVG. Kích thước tối đa 2MB.
            </div>
          </VCol>

          <VCol
            cols="12"
            md="9"
          >
            <VRow>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="appInfo.name"
                  label="Tên ứng dụng"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="appInfo.website"
                  label="Địa chỉ website"
                  placeholder="https://example.com"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="appInfo.slogan"
                  label="Khẩu hiệu / Slogan"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextarea
                  v-model="appInfo.shortDesc"
                  label="Mô tả ngắn"
                  rows="2"
                  maxlength="500"
                />
              </VCol>
            </VRow>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      title="Thông tin liên hệ"
      subtitle="Thông tin liên hệ sẽ hiển thị ở email, thông báo và trang công khai."
    >
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="contactInfo.email"
              label="Email liên hệ"
              prepend-inner-icon="tabler-mail"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="contactInfo.phone"
              label="Số điện thoại"
              prepend-inner-icon="tabler-phone"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="contactInfo.address"
              label="Địa chỉ công ty"
              prepend-inner-icon="tabler-map-pin"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="contactInfo.timezone"
              :items="['(GMT+07:00) Hồ Chí Minh', '(GMT+08:00) Singapore', '(GMT+09:00) Tokyo']"
              label="Múi giờ"
              prepend-inner-icon="tabler-world"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      title="Cài đặt cơ bản"
      subtitle="Một số tùy chọn cấu hình chung cho hệ thống."
    >
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <div class="d-flex flex-column gap-3">
              <div class="d-flex align-start">
                <VSwitch
                  v-model="basicOptions.allowRegister"
                  color="primary"
                  inset
                  hide-details
                  class="me-3 mt-n1"
                />
                <div>
                  <div class="text-body-2">
                    Cho phép đăng ký tài khoản mới
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Người dùng có thể tự đăng ký tài khoản.
                  </div>
                </div>
              </div>
              <div class="d-flex align-start">
                <VSwitch
                  v-model="basicOptions.maintenance"
                  color="primary"
                  inset
                  hide-details
                  class="me-3 mt-n1"
                />
                <div>
                  <div class="text-body-2">
                    Bật chế độ bảo trì
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Tạm thời khóa hệ thống để bảo trì.
                  </div>
                </div>
              </div>
              <div class="d-flex align-start">
                <VSwitch
                  v-model="basicOptions.multiDeviceLogin"
                  color="primary"
                  inset
                  hide-details
                  class="me-3 mt-n1"
                />
                <div>
                  <div class="text-body-2">
                    Cho phép đăng nhập nhiều thiết bị
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Người dùng có thể đăng nhập từ nhiều thiết bị.
                  </div>
                </div>
              </div>
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <div class="d-flex flex-column gap-3">
              <div class="d-flex align-start">
                <VSwitch
                  v-model="basicOptions.showLandingPage"
                  color="primary"
                  inset
                  hide-details
                  class="me-3 mt-n1"
                />
                <div>
                  <div class="text-body-2">
                    Hiển thị trang giới thiệu
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Hiển thị trang giới thiệu công khai.
                  </div>
                </div>
              </div>
              <div class="d-flex align-start">
                <VSwitch
                  v-model="basicOptions.enableNotifications"
                  color="primary"
                  inset
                  hide-details
                  class="me-3 mt-n1"
                />
                <div>
                  <div class="text-body-2">
                    Bật thông báo hệ thống
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Gửi thông báo cho người dùng.
                  </div>
                </div>
              </div>
              <div class="d-flex align-start">
                <VSwitch
                  v-model="basicOptions.activityLog"
                  color="primary"
                  inset
                  hide-details
                  class="me-3 mt-n1"
                />
                <div>
                  <div class="text-body-2">
                    Ghi nhật ký hoạt động
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Lưu lại các hoạt động quan trọng trong hệ thống.
                  </div>
                </div>
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      title="Ngôn ngữ & Định dạng"
      subtitle="Cấu hình ngôn ngữ, múi giờ và định dạng hiển thị."
    >
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="formatConfig.language"
              :items="['Tiếng Việt', 'English']"
              label="Ngôn ngữ mặc định"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="formatConfig.dateFormat"
              :items="['DD/MM/YYYY', 'YYYY-MM-DD', 'MM/DD/YYYY']"
              label="Định dạng ngày"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="formatConfig.timeFormat"
              :items="['24 giờ (14:30)', '12 giờ (02:30 PM)']"
              label="Định dạng giờ"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="6"
          >
            <AppSelect
              v-model="formatConfig.numberFormat"
              :items="['1,234,567.89', '1.234.567,89']"
              label="Định dạng số"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="6"
          >
            <AppSelect
              v-model="formatConfig.currency"
              :items="['VND', 'USD']"
              label="Đơn vị tiền tệ"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.logo-upload-box {
  border: 1.5px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  min-height: 128px;
}
</style>
