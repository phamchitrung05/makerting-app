/*
 * =====================================================================
 * CHỨC NĂNG FILE: Composable quản lý load/save một nhóm cài đặt.
 * =====================================================================
 * Dùng chung cho các trang tab Settings (general/security/mail/...):
 * - load()   : GET group -> đổ vào `form` (bỏ key hệ thống) + giữ `version`.
 * - save()   : PUT kèm expected_version; bắt 409 -> cờ `conflict`.
 * - Trường bí mật: form giữ key `{name}` rỗng; khi `{name}_configured` true
 *   hiển thị placeholder "đã cấu hình — để trống giữ nguyên".
 *
 * Expose: form(ref), version(ref), loading, saving, conflict, errors,
 *         secrets(computed map name->_configured), load(), save(), resetSecret().
 * =====================================================================
 */
import { computed, ref } from 'vue'
import { settingsApi } from '../services/settingsApi'
import { isConflictError, isValidationError, fieldErrors, firstErrorMessage } from '@/utils/api'

export function useSettingsGroup(group) {
  const loading = ref(false)
  const saving = ref(false)
  const conflict = ref(false)
  const version = ref(0)
  const secrets = ref([])          // danh sách tên trường bí mật của group
  const form = ref({})
  const errors = ref({})
  const snack = ref({ show: false, text: '', color: 'success' })

  // Map nhanh: trường bí mật đã cấu hình chưa (để disable/placeholder).
  const secretConfigured = computed(() => {
    const map = {}
    for (const name of secrets.value) {
      map[name] = !!form.value[`${name}_configured`]
    }

    return map
  })

  function notify(text, color = 'success') {
    snack.value = { show: true, text, color }
  }

  /*
   * CHỨC NĂNG: Tải cấu hình group -> đổ vào form + version + secrets.
   * Bỏ các key meta (group/secrets) khỏi form; giữ `*_configured` cờ.
   */
  async function load() {
    loading.value = true
    conflict.value = false
    errors.value = {}
    try {
      const res = await settingsApi.get(group)

      version.value = res.version ?? 0
      secrets.value = res.secrets ?? []

      const { values = {} } = res

      // Trường bí mật: giữ key rỗng để bind input, cộng cờ _configured.
      for (const name of secrets.value) {
        if (!(name in values)) { values[name] = '' }
      }
      form.value = { ...values }
    } catch (e) {
      notify(firstErrorMessage(e) || 'Không tải được cấu hình', 'error')
    } finally {
      loading.value = false
    }
  }

  /*
   * CHỨC NĂNG: Lưu cấu hình. Gửi toàn bộ form trừ key meta; bí mật rỗng
   * được backend giữ nguyên. Bắt 409 -> cờ conflict + thông báo.
   */
  async function save() {
    saving.value = true
    errors.value = {}
    conflict.value = false
    try {
      const payload = { ...form.value }

      delete payload.version

      const res = await settingsApi.update(group, payload, version.value)

      version.value = res.version ?? version.value
      notify('Đã lưu cấu hình')
      await load() // Nạp lại để đồng bộ secret flags + version.
      
      return true
    } catch (e) {
      if (isConflictError(e)) {
        conflict.value = true
        notify('Cấu hình đã bị người khác sửa. Tải lại để cập nhật mới nhất.', 'warning')
      } else if (isValidationError(e)) {
        errors.value = fieldErrors(e)
        notify('Dữ liệu không hợp lệ', 'error')
      } else {
        notify(firstErrorMessage(e) || 'Lưu thất bại', 'error')
      }

      return false
    } finally {
      saving.value = false
    }
  }

  return {
    form, version, secrets, secretConfigured,
    loading, saving, conflict, errors, snack,
    load, save, notify,
  }
}
