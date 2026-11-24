<!--
  =====================================================================
  CHỨC NĂNG FILE: Hộp thoại chuyển thư mục cho nhiều tệp media đã chọn.
  =====================================================================
  Cho phép chọn thư mục hiện có từ danh sách hoặc nhập đường dẫn thư mục
  mới (vd: 'campaign/2026').
  =====================================================================
-->
<script setup>
import { computed, ref, watch } from 'vue'
import AppDialogLayout from '@/components/dialogs/AppDialogLayout.vue'

const props = defineProps({
  folders: { type: Array, default: () => [] },
  selectedCount: { type: Number, default: 0 },
  initialFolder: { type: String, default: 'root' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['submit'])

const isOpen = defineModel({ type: Boolean, default: false })

const targetFolder = ref('root')
const folderSearch = ref('root')
const errorText = ref('')

const folderOptions = computed(() => {
  const set = new Set(['root'])
  for (const f of props.folders || []) {
    const name = typeof f === 'string' ? f : f?.name
    if (name)
      set.add(name)
  }

  return [...set].sort((a, b) => a.localeCompare(b))
})

watch(isOpen, open => {
  if (!open)
    return

  const initial = props.initialFolder || 'root'

  targetFolder.value = initial
  folderSearch.value = initial
  errorText.value = ''
})

function close() {
  if (!props.loading)
    isOpen.value = false
}

function submit() {
  if (props.loading)
    return

  const normalized = String(folderSearch.value || targetFolder.value || '')
    .replace(/\\/g, '/')
    .replace(/\/+/g, '/')
    .replace(/^\/+|\/+$/g, '')
    .trim() || 'root'

  if (normalized.length > 60) {
    errorText.value = 'Tên thư mục tối đa 60 ký tự.'

    return
  }

  if (/(^|\/)\.\.?(\/|$)/.test(normalized)) {
    errorText.value = 'Tên thư mục không được chứa "." hoặc "..".'

    return
  }

  emit('submit', normalized)
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="460"
    :persistent="loading"
  >
    <AppDialogLayout
      close-label="Đóng hộp thoại chuyển thư mục"
      :close-disabled="loading"
      @close="close"
    >
      <template #header>
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
              size="38"
              rounded="lg"
            >
              <VIcon
                icon="tabler-folder-share"
                size="20"
              />
            </VAvatar>
          </template>
          <VCardTitle>Chuyển thư mục</VCardTitle>
          <VCardSubtitle>
            Áp dụng cho {{ selectedCount }} tệp tin đã chọn
          </VCardSubtitle>
        </VCardItem>
      </template>

      <div class="pa-5">
        <AppCombobox
          v-model="targetFolder"
          v-model:search="folderSearch"
          :items="folderOptions"
          label="Thư mục đích *"
          placeholder="Nhập hoặc chọn thư mục (vd: campaign/summer)"
          persistent-placeholder
          :error-messages="errorText"
          @update:search="errorText = ''"
          @update:model-value="errorText = ''"
        />
        <p class="text-caption text-medium-emphasis mt-2 mb-0">
          Có thể nhập thư mục mới dạng phân cấp bằng dấu `/` (ví dụ: <code>products/shoes</code>).
        </p>
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
            color="primary"
            variant="flat"
            :loading="loading"
            @click="submit"
          >
            Chuyển
          </VBtn>
        </VCardActions>
      </template>
    </AppDialogLayout>
  </VDialog>
</template>
