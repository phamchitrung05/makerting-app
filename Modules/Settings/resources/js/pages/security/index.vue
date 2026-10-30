<!--
  =====================================================================
  CHỨC NĂNG FILE: Tab Cài đặt Bảo mật (group 'security') — chính sách
  mật khẩu, giới hạn đăng nhập, session, IP whitelist, 2FA.
  =====================================================================
  - load()/save() qua useSettingsGroup('security') (nối API thật).
  - allowedIpsText: textarea <-> mảng allowed_ips (mỗi dòng 1 IP/CIDR).
  - Dùng chung khung SettingsShell cho cột trái.
  INPUT/OUTPUT: route /admin/settings/security -> form bảo mật.
  SIDE EFFECT: PUT /api/v1/admin/settings/security.
  =====================================================================
-->
<script setup>
import { computed } from 'vue'
import { useSettingsGroup } from '../../composables/useSettingsGroup'

definePage({ meta: { layout: 'default' } })

const {
  form, loading, saving, conflict, errors, snack,
  load, save,
} = useSettingsGroup('security')

// Textarea 1 dòng/1 IP <-> mảng allowed_ips.
const allowedIpsText = computed({
  get: () => (form.value.allowed_ips || []).join('\n'),
  set: v => {
    // eslint-disable-next-line camelcase
    form.value.allowed_ips = v
      .split('\n')
      .map(s => s.trim())
      .filter(Boolean)
  },
})

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
          Chính sách đăng nhập, phiên làm việc và giới hạn truy cập
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
        <SettingsShell active-tab="security" />
      </VCol>

      <!-- Cột phải: nội dung bảo mật -->
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
          title="Chính sách mật khẩu"
          subtitle="Yêu cầu độ mạnh cho mật khẩu tài khoản admin."
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.password_min_length"
                  label="Độ dài tối thiểu"
                  type="number"
                  :error-messages="errors['values.password_min_length']"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
                class="d-flex align-center"
              >
                <VSwitch
                  v-model="form.password_require_mixed"
                  label="Bắt buộc chữ hoa + số"
                  color="primary"
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
                class="d-flex align-center"
              >
                <VSwitch
                  v-model="form.password_require_symbols"
                  label="Bắt buộc ký tự đặc biệt"
                  color="primary"
                  hide-details
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard
          class="mb-6"
          title="Đăng nhập & Phiên"
          subtitle="Giới hạn số lần đăng nhập sai và thời hạn session."
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.login_max_attempts"
                  label="Số lần sai tối đa (0=tắt)"
                  type="number"
                  :error-messages="errors['values.login_max_attempts']"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.login_lockout_minutes"
                  label="Khóa (phút)"
                  type="number"
                  :error-messages="errors['values.login_lockout_minutes']"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.session_lifetime_minutes"
                  label="Thời hạn session (phút)"
                  type="number"
                  :error-messages="errors['values.session_lifetime_minutes']"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard
          class="mb-6"
          title="Truy cập"
          subtitle="Giới hạn IP và xác thực hai lớp cho vai trò quản trị."
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextarea
                  v-model="allowedIpsText"
                  label="IP được phép (mỗi dòng 1 IP/CIDR — trống = mọi IP)"
                  rows="4"
                  :error-messages="errors['values.allowed_ips']"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
                class="d-flex align-start"
              >
                <VSwitch
                  v-model="form.two_factor_required"
                  label="Bắt buộc xác thực 2 lớp (2FA) cho vai trò quản trị"
                  color="primary"
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
