<!--
  =====================================================================
  CHỨC NĂNG FILE: Hộp thoại xác nhận thao tác nguy hiểm (xóa vĩnh viễn...).
  =====================================================================
  Dùng chung cho trang thư viện Media (xóa hàng loạt trong thùng rác)
  và MediaDetailSidebar (xóa vĩnh viễn 1 tệp). Dùng khung AppDialogLayout.
  =====================================================================
-->
<script setup>
import AppDialogLayout from '@/components/dialogs/AppDialogLayout.vue'

const props = defineProps({
  title: { type: String, default: 'Xác nhận thao tác' },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'Xác nhận' },
  confirmColor: { type: String, default: 'error' },
  icon: { type: String, default: 'tabler-alert-triangle' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm'])

const isOpen = defineModel({ type: Boolean, default: false })

function close() {
  if (!props.loading)
    isOpen.value = false
}

function submit() {
  if (!props.loading)
    emit('confirm')
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="460"
    :persistent="loading"
  >
    <AppDialogLayout
      close-label="Đóng hộp thoại xác nhận"
      :close-disabled="loading"
      @close="close"
    >
      <template #header>
        <VCardItem>
          <template #prepend>
            <VAvatar
              :color="confirmColor"
              variant="tonal"
              size="38"
              rounded="lg"
            >
              <VIcon
                :icon="icon"
                size="20"
              />
            </VAvatar>
          </template>
          <VCardTitle>{{ title }}</VCardTitle>
        </VCardItem>
      </template>

      <div class="pa-5 text-body-1 text-high-emphasis">
        {{ message }}
      </div>

      <template #footer>
        <VCardActions class="justify-end ga-2 pa-4">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="loading"
            @click="close"
          >
            Hủy
          </VBtn>
          <VBtn
            :color="confirmColor"
            variant="flat"
            :loading="loading"
            @click="submit"
          >
            {{ confirmText }}
          </VBtn>
        </VCardActions>
      </template>
    </AppDialogLayout>
  </VDialog>
</template>
