<!--
  =====================================================================
  CHỨC NĂNG FILE: Component <TaxonomyField /> — chọn Category/Tag theo
  scope + hỗ trợ thêm nhanh Tag do AI gợi ý.
  =====================================================================

  Hai chế độ:
  - type="category" : VSelect chọn từ /categories/options (lọc scope).
  - type="tag"      : VCombobox chọn từ /tags/options + gõ tên mới
  (enter) để tạo Tag tức thì — phục vụ "thêm nhanh Tag do AI gợi ý".

  model-value nhận/trả mảng id number[] cho categories (multi), hoặc
  mảng (id|name)[] cho tags: item chuỗi = tag chưa có trong DB sẽ được
  gửi lên endpoint tạo nhanh khi create-on-blur hoặc qua slot.

  Sự kiện:
  - update:model-value — mảng id/name đã chọn.
  - created(tag)      — tag mới vừa tạo (dành cho parent thông báo).

  CÁC HÀM/METHOD TRONG FILE:
  - loadOptions()    : nạp options theo type+scope.
  - createTag(name)  : POST /tags nhanh rồi gán vào model.
  INPUT/OUTPUT CỦA CLASS (tổng thể):
  - INPUT : props {type,scope,multiple,label,modelValue}.
  - OUTPUT: update:model-value + created.
  SIDE EFFECT: gọi taxonomyApi.*Options + createTag.
  =====================================================================
-->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { taxonomyApi } from '../services/taxonomyApi'

const props = defineProps({
  /* 'category' | 'tag'. */
  type: { type: String, default: 'tag' },

  /* Phân vùng — truyền '' hoặc bỏ trống = dùng chung + mọi scope (trừ
     khi strict). 'blog', 'visual_style', 'youtube', 'campaign'. */
  scope: { type: String, default: '' },

  /* Cho phép chọn nhiều. */
  multiple: { type: Boolean, default: true },

  /* Label hiển thị trên field. */
  label: { type: String, default: '' },

  /* Cho phép tạo tag mới (gõ tên -> Enter) khi type='tag'. */
  allowCreate: { type: Boolean, default: true },

  /* Mảng id number[] (category/tag có sẵn) hoặc (id|name)[] (tag). */
  modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'created'])

const options = ref([])
const loading = ref(false)
const error = ref('')

const isCategory = computed(() => props.type === 'category')

const items = computed(() => options.value)

const inner = computed({
  get: () => props.modelValue,
  set: setValue,
})

/* Nạp options theo type + scope. */
async function loadOptions() {
  loading.value = true
  error.value = ''
  try {
    const params = props.scope === '' ? {} : { scope: props.scope }

    options.value = isCategory.value
      ? await taxonomyApi.categoryOptions(params)
      : await taxonomyApi.tagOptions(params)
  } catch (e) {
    error.value = 'Không tải được phân loại'
  } finally {
    loading.value = false
  }
}

/* Chuẩn hóa v-model và tạo các chuỗi Tag mới theo thứ tự. */
async function setValue(value) {
  const selected = (Array.isArray(value) ? value : value == null ? [] : [value])
  const ids = selected.filter(item => typeof item !== 'string')

  emit('update:modelValue', ids)

  if (isCategory.value || !props.allowCreate)
    return

  for (const name of selected.filter(item => typeof item === 'string'))
    await createTag(name, ids)
}

/* Tạo Tag nhanh (new tag do AI gợi ý) rồi gán id vào model. */
async function createTag(name, selectedIds) {
  const clean = String(name ?? '').trim()
  if (!clean) return

  const existing = options.value.find(tag => tag.name.toLocaleLowerCase() === clean.toLocaleLowerCase())
  if (existing) {
    if (!selectedIds.includes(existing.id)) {
      selectedIds.push(existing.id)
      emit('update:modelValue', [...selectedIds])
    }

    return
  }

  try {
    const tag = await taxonomyApi.createTag({
      name: clean,
      scope: props.scope || null,
      status: 'active',
    })

    options.value.push(tag)
    emit('created', tag)
    selectedIds.push(tag.id)
    emit('update:modelValue', [...selectedIds])
  } catch (e) {
    error.value = 'Tạo thẻ thất bại'
  }
}

watch(() => [props.type, props.scope], loadOptions)
onMounted(loadOptions)
</script>

<template>
  <div>
    <!-- ===== Category ===== -->
    <AppSelect
      v-if="isCategory"
      v-model="inner"
      :items="items"
      item-title="name"
      item-value="id"
      :label="label || 'Danh mục'"
      :multiple="multiple"
      :loading="loading"
      chips
      closable-chips
      clearable
    />

    <!-- ===== Tag ===== -->
    <VCombobox
      v-else
      v-model="inner"
      :items="items"
      item-title="name"
      item-value="id"
      :label="label || 'Thẻ'"
      :multiple="multiple"
      :loading="loading"
      chips
      closable-chips
      clearable
      :return-object="false"
    />

    <div
      v-if="error"
      class="text-caption text-error mt-1"
    >
      {{ error }}
    </div>
  </div>
</template>
