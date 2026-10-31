<!--
  =====================================================================
  CHỨC NĂNG FILE: Dialog "Thêm mô hình AI" trong tab AI & Mô hình.
  =====================================================================
  Component dialog riêng (components/dialogs/) dùng khung 3 vùng bắt buộc
  AppDialogLayout (header cố định -> body cuộn -> footer cố định), đúng chuẩn
  docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §4.

  GIAO DIỆN TĨNH (static preview) — chỉ emit dữ liệu form ra ngoài qua sự
  kiện `saved`; chưa nối backend ai_models (CoreAi triển khai ở P2-01).

  CÁC HÀM/METHOD TRONG FILE: closeDialog(), resetAfterLeave(), saveModel().
  INPUT: v-model (boolean) điều khiển mở/đóng qua defineModel.
  OUTPUT: emit('saved', form) — caller quyết định thêm vào danh sách / gọi API.
  SIDE EFFECT: không tự gọi API, không sửa state ngoài.
  =====================================================================
-->
<script setup>
import { ref, useId } from 'vue'

const isOpen = defineModel({ type: Boolean, default: false })
const emit = defineEmits(['saved'])

const saving = ref(false)
const formId = useId()

const blankModel = () => ({
  name: '',
  provider: 'OpenAI',
  type: 'Văn bản (LLM)',
  context: '',
  cost: '',
  speed: 'Trung bình',
  isDefault: false,
  active: true,
})

const form = ref(blankModel())

const providerOptions = [
  'OpenAI',
  'Anthropic',
  'Google AI',
  'Stability AI',
  'Runway',
  'Replicate',
]

const modelTypeOptions = [
  { title: 'Văn bản (LLM)', value: 'Văn bản (LLM)' },
  { title: 'Hình ảnh', value: 'Hình ảnh' },
  { title: 'Video', value: 'Video' },
  { title: 'Âm thanh / Giọng nói', value: 'Âm thanh' },
]

const speedOptions = ['Siêu tốc', 'Rất nhanh', 'Nhanh', 'Trung bình', 'Chậm']

const typeColorMap = {
  'Văn bản (LLM)': 'primary',
  'Hình ảnh': 'info',
  'Video': 'warning',
  'Âm thanh': 'success',
}

/*
 * CHỨC NĂNG: Đóng dialog nếu không đang lưu.
 * INPUT: không tham số (đọc saving).
 * OUTPUT: isOpen=false.
 */
function closeDialog() {
  if (!saving.value) isOpen.value = false
}

/*
 * CHỨC NĂNG: Reset form chỉ sau khi animation đóng hoàn tất (chuẩn §4.2,
 * tránh giật trống). Không reset khi dialog vẫn mở.
 * INPUT: không.
 * OUTPUT: form về blankModel().
 */
function resetAfterLeave() {
  if (isOpen.value) return
  form.value = blankModel()
}

/*
 * CHỨC NĂNG: Emit payload mô hình mới ra ngoài rồi đóng dialog.
 * Demo tĩnh: không gọi API; caller (AiModelsTab) push vào danh sách cục bộ.
 * INPUT: đọc form.
 * OUTPUT: emit('saved', {...form, typeColor}).
 */
function saveModel() {
  saving.value = true

  emit('saved', {
    ...form.value,
    typeColor: typeColorMap[form.value.type] ?? 'primary',
  })

  saving.value = false
  isOpen.value = false
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="680"
    scrollable
    :persistent="saving"
    @after-leave="resetAfterLeave"
  >
    <AppDialogLayout
      title="Thêm mô hình AI"
      subtitle="Khai báo mô hình mới được phép sử dụng trong hệ thống."
      :close-disabled="saving"
      @close="closeDialog"
    >
      <VCardText>
        <VForm
          :id="formId"
          @submit.prevent="saveModel"
        >
          <VRow>
            <VCol
              cols="12"
              md="8"
            >
              <AppTextField
                v-model="form.name"
                label="Tên mô hình"
                placeholder="VD: GPT-4o Mini, Claude 3.5 Haiku..."
                prepend-inner-icon="tabler-cpu"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.provider"
                :items="providerOptions"
                label="Nhà cung cấp"
                prepend-inner-icon="tabler-server"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.type"
                :items="modelTypeOptions"
                label="Phân loại"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.speed"
                :items="speedOptions"
                label="Tốc độ xử lý"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.context"
                label="Context / Độ phân giải"
                placeholder="VD: 128K tokens, 1024x1024px"
                prepend-inner-icon="tabler-arrows-diagonal"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.cost"
                label="Chi phí"
                placeholder="VD: $2.50 / 1M tokens"
                prepend-inner-icon="tabler-cash"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="form.isDefault"
                label="Đặt làm mô hình mặc định"
                color="primary"
                hide-details
                inset
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="form.active"
                label="Kích hoạt ngay"
                color="primary"
                hide-details
                inset
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <template #footer>
        <VCardActions class="justify-end">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="saving"
            @click="closeDialog"
          >
            Hủy
          </VBtn>
          <VBtn
            type="submit"
            :form="formId"
            variant="flat"
            color="primary"
            :loading="saving"
          >
            Thêm mô hình
          </VBtn>
        </VCardActions>
      </template>
    </AppDialogLayout>
  </VDialog>
</template>
