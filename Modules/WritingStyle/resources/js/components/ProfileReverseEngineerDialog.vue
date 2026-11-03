<!--
  =====================================================================
  CHỨC NĂNG FILE: Dialog "Giải mã văn phong từ bài mẫu" — DEMO TĨNH.
  =====================================================================
  Form paste/url/file -> emit 'analyze' {name,source_type,source_url,
  reference_text} cho parent xử lý (demo: parent tạo profile giả sau ~1s).
  Không gọi API.
  =====================================================================
-->
<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'analyze'])

const name = ref('')
const sourceType = ref('paste')
const sourceUrl = ref('')
const referenceText = ref('')
const done = ref(false)

const show = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})

watch(show, v => {
  if (v) { done.value = false }
})

function submit() {
  emit('analyze', {
    name: name.value || undefined,
    source_type: sourceType.value, // eslint-disable-line camelcase
    source_url: sourceUrl.value || undefined, // eslint-disable-line camelcase
    reference_text: referenceText.value, // eslint-disable-line camelcase
  })
  done.value = true
}
</script>

<template>
  <VDialog
    v-model="show"
    max-width="760"
    persistent
  >
    <VCard>
      <VCardItem title="Giải mã DNA văn phong từ bài mẫu" />
      <VCardText>
        <VAlert
          v-if="done"
          type="success"
          variant="tonal"
          class="mb-4"
        >
          Đã gửi giải mã — đang phân tích trong nền (demo). Khi xong, profile mới
          sẽ hiện trong danh sách.
        </VAlert>
        <AppTextField
          v-model="name"
          label="Tên văn phong (tùy chọn)"
          placeholder="vd: Giọng Chuyên gia SEO"
          class="mb-4"
        />
        <div class="d-flex gap-4 mb-4">
          <AppSelect
            v-model="sourceType"
            :items="[{title:'Dán nội dung',value:'paste'},{title:'Từ URL',value:'url'},{title:'Từ file',value:'file'}]"
            label="Nguồn"
            style="max-width:220px"
          />
          <AppTextField
            v-if="sourceType === 'url'"
            v-model="sourceUrl"
            label="URL bài mẫu"
            placeholder="https://..."
            class="flex-1"
          />
        </div>
        <AppTextarea
          v-model="referenceText"
          label="Nội dung bài mẫu"
          placeholder="Dán toàn văn bài mẫu muốn giải mã văn phong (tối thiểu ~50 ký tự)..."
          rows="10"
          :disabled="sourceType !== 'paste'"
        />
        <div class="text-caption text-medium-emphasis mt-2">
          AI sẽ giải mã 16 khía cạnh DNA (giọng điệu, nhịp câu, cấu trúc...) + trích bằng chứng
          nguyên văn. Chạy nền qua queue ai-text.
        </div>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="show = false"
        >
          {{ done ? 'Đóng' : 'Hủy' }}
        </VBtn>
        <VBtn
          v-if="!done"
          color="primary"
          :disabled="sourceType === 'paste' && (referenceText || '').trim().length < 50"
          @click="submit"
        >
          Giải mã DNA
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
