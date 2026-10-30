<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab Cài đặt Email (group 'mail') — SMTP, From, queue,
  kèm trường bí mật mail_password che thành mail_password_configured.
  =====================================================================
  - load()/save() qua useSettingsGroup('mail') (nối API thật).
  - secretConfigured['mail_password'] -> trạng thái "đã cấu hình".
  - Dùng chung khung SettingsShell cho cột trái.
  INPUT/OUTPUT: route /admin/settings/mail -> form cấu hình mail.
  SIDE EFFECT: PUT /api/v1/admin/settings/mail.
  =====================================================================
-->
<script setup>
import { ref } from 'vue'
import { useSettingsGroup } from '../../composables/useSettingsGroup'

definePage({ meta: { layout: 'default' } })

const {
  form, loading, saving, conflict, errors, snack, secretConfigured,
  load, save,
} = useSettingsGroup('mail')

const drivers = ['log', 'smtp', 'sendmail', 'ses', 'mailgun', 'postmark']

const encryptions = [
  { title: 'TLS', value: 'tls' },
  { title: 'SSL', value: 'ssl' },
  { title: 'Không mã hóa', value: null },
]

const showPassword = ref(false)

load()
</script>

<template>
  <div>
    <!-- Page header -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Cài đặt hệ thống
        </h4>
        <div class="text-body-1">
          Cấu hình máy chủ gửi mail SMTP và hàng đợi
        </div>
      </div>
      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          prepend-icon="tabler-device-floppy"
          :loading="saving"
          @click="save"
        >
          Lưu thay đổi
        </VBtn>
      </div>
    </div>

    <VRow>
      <!-- Cột trái: menu cài đặt (dùng chung) -->
      <VCol
        cols="12"
        md="4"
        lg="3"
      >
        <SettingsShell active-tab="mail" />
      </VCol>

      <!-- Cột phải: nội dung email -->
      <VCol
        cols="12"
        md="8"
        lg="9"
      >
        <VAlert
          v-if="conflict"
          color="warning"
          variant="tonal"
          class="mb-6"
          icon="tabler-alert-triangle"
        >
          Cấu hình đã bị người khác sửa trước. Bấm tải lại để lấy dữ liệu mới nhất.
          <VBtn
            size="small"
            variant="text"
            class="ms-2"
            @click="load"
          >
            Tải lại
          </VBtn>
        </VAlert>

        <VCard
          class="mb-6"
          title="Máy chủ SMTP"
          subtitle="Thông tin kết nối máy chủ gửi thư."
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="4"
              >
                <AppSelect
                  v-model="form.mail_driver"
                  :items="drivers"
                  label="Driver"
                  :error-messages="errors['values.mail_driver']"
                />
              </VCol>
              <VCol
                cols="12"
                md="5"
              >
                <AppTextField
                  v-model="form.mail_host"
                  label="Máy chủ SMTP"
                  :error-messages="errors['values.mail_host']"
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <AppTextField
                  v-model.number="form.mail_port"
                  label="Cổng"
                  type="number"
                  :error-messages="errors['values.mail_port']"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <AppSelect
                  v-model="form.mail_encryption"
                  :items="encryptions"
                  label="Mã hóa"
                  :error-messages="errors['values.mail_encryption']"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="form.mail_username"
                  label="Username SMTP"
                  :error-messages="errors['values.mail_username']"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="form.mail_password"
                  :type="showPassword ? 'text' : 'password'"
                  label="Mật khẩu SMTP"
                  :placeholder="secretConfigured.mail_password ? 'Đã cấu hình — để trống giữ nguyên' : 'Nhập mật khẩu'"
                  :error-messages="errors['values.mail_password']"
                  autocomplete="new-password"
                >
                  <template #append-inner>
                    <VIcon
                      :icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                      class="cursor-pointer"
                      @click="showPassword = !showPassword"
                    />
                  </template>
                </AppTextField>
                <p
                  v-if="secretConfigured.mail_password"
                  class="text-caption text-success mt-1 mb-0"
                >
                  <VIcon
                    icon="tabler-lock"
                    size="14"
                  /> Mật khẩu đã được lưu. Để trống để giữ nguyên.
                </p>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard
          class="mb-6"
          title="Người gửi & Hàng đợi"
          subtitle="Địa chỉ From mặc định và cách xử lý gửi mail."
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="5"
              >
                <AppTextField
                  v-model="form.mail_from_address"
                  label="Địa chỉ From"
                  type="email"
                  :error-messages="errors['values.mail_from_address']"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="form.mail_from_name"
                  label="Tên hiển thị From"
                  :error-messages="errors['values.mail_from_name']"
                />
              </VCol>
              <VCol
                cols="12"
                md="3"
                class="d-flex align-center"
              >
                <VSwitch
                  v-model="form.mail_queue"
                  label="Gửi qua hàng đợi"
                  color="primary"
                  hide-details
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VSnackbar
      v-model="snack.show"
      :color="snack.color"
      timeout="3000"
    >
      {{ snack.text }}
    </VSnackbar>
  </div>
</template>
