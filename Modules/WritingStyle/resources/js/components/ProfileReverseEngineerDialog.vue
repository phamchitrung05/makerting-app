<!--
  =====================================================================
  CHỨC NĂNG FILE: Nhập nguồn bài mẫu và chuyển sang trang tạo để phân tích.
  =====================================================================

  Form paste/url/file -> emit 'analyze' {name,source_type,source_url,
  reference_text} để trang cha chuyển sang màn tạo, phân tích và duyệt trước khi lưu.
  Không gọi API.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - submit(): Phát payload giải mã cho trang cha và đánh dấu chuyển trang.
  - Computed: show.
  - Watcher/lifecycle: watch show.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : modelValue bool mở dialog; form tên/nguồn paste, URL hoặc file.
  - OUTPUT: Emit analyze với nguồn mẫu và update:modelValue để đóng/mở; không tự gọi API.
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

/**
 * =====================================================================
 * CHỨC NĂNG: Phát payload giải mã cho trang cha và đánh dấu chuyển trang
 * =====================================================================
 *
 * INPUT:
 * - Không có tham số; đọc props, form hoặc store hiện tại.
 *
 * OUTPUT:
 * - void
 *
 * SIDE EFFECT:
 * - Emit analyze và cập nhật done.
 *
 * EXCEPTION:
 * - Component không bắt lỗi của tác vụ ở trang cha.
 * =====================================================================
 */
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
          Đang chuyển đến trang duyệt kết quả phân tích.
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
            :items="[{title:'Dán nội dung',value:'paste'},{title:'Từ URL',value:'url'},{title:'Từ file',value:'file',props:{disabled:true}}]"
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
