<!--
  =====================================================================
  CHỨC NĂNG FILE: Ô nhập slug tái sử dụng cho mọi tài nguyên có URL (Core).
  =====================================================================

  Component "dumb" — KHÔNG tự gọi API. Mỗi module muốn dùng slug truyền:
  - v-model          : chuỗi slug hiện tại (bind hai chiều).
  - suggesting       : cờ đang gọi API gợi ý (spinner góc phải).
  - available        : null | true | false — base slug còn trống/đã có/trùng.
  - takenBy          : base đang bị record khác chiếm (để hiện "đã đổi -1").
  - sluggableType    : alias morph ('writing_profile', 'post', 'category'...) —
  chỉ hiển thị, module truyền khi gọi API (không lưu ở component).
  - placeholder/hint/disabled/readonly/prefix: passthrough AppTextField.

  Emit:
  - update:modelValue (string) — user gõ tay.
  - requestSuggest   (void)   — yêu cầu parent gọi API gợi ý (blur/khi source đổi).

  PARENT chịu trách gọi store.suggestSlug({source, sluggable_type, sluggable_id})
  và truyền kết quả xuống props. Component chỉ render ô + trạng thái.
  =====================================================================
-->
<script setup>
import { computed } from 'vue'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  suggesting: { type: Boolean, default: false },
  available: { type: [Boolean, null], default: null },
  takenBy: { type: [String, null], default: null },
  sluggableType: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'requestSuggest'])

defineOptions({ name: 'CoreSlugField', inheritAttrs: false })

/* Trạng thái hiển thị góc phải input: spinner > check/warning > nothing. */
const suffixMeta = computed(() => {
  if (props.suggesting) return { icon: 'tabler-loader-2 animate-spin', color: 'secondary' }
  if (props.available === true) return { icon: 'tabler-circle-check', color: 'success' }
  if (props.available === false) return { icon: 'tabler-alert-circle', color: 'warning' }

  return null
})
</script>

<template>
  <AppTextField
    :model-value="modelValue"
    v-bind="$attrs"
    @update:model-value="emit('update:modelValue', $event)"
    @blur="emit('requestSuggest')"
  >
    <template
      v-if="suffixMeta"
      #append-inner
    >
      <VIcon
        :icon="suffixMeta.icon"
        :color="suffixMeta.color"
        size="20"
      />
    </template>
  </AppTextField>
</template>
