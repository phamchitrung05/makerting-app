<!--
  =====================================================================
  CHỨC NĂNG FILE: Khối cấu hình SEO dùng chung (SeoMetaCard).
  =====================================================================
  Card gắn vào trang Add/Edit của domain (Post, Category, Landing...) để
  người dùng ghi đè meta SEO/OG/Canonical của bản ghi đang mở. Component
  chỉ giữ state form + emit; logic fallback chạy ở backend
  (SeoMetadataService). Chuẩn Vuexy: VCard title/subtitle + AppTextField/
  AppTextarea/AppSelect + VSwitch.

  PROPS:
  - modelValue (Object)  : các trường seo_* hiện tại (chỉ phần ghi đè).
  - resolved   (Object)  : meta đã fallback trả từ GET /meta (preview).
  - loading    (Boolean) : khóa input khi đang lưu.

  EMIT: update:modelValue (object), save (object payload).
  CÁC HÀM/METHOD TRONG FILE: update(field,val), emitSave(), reset().
  =====================================================================
-->
<script setup>
import { reactive, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  resolved: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save'])

const blank = () => ({
  focus_keyword: '',
  seo_title: '',
  seo_description: '',
  canonical_url: '',
  robots_index: true,
  robots_follow: true,
  og_title: '',
  og_description: '',
  og_image_id: null,
  schema_org_json: null,
})

const form = reactive({ ...blank(), ...props.modelValue })

watch(() => props.modelValue, v => Object.assign(form, { ...blank(), ...v }))

function update(field, val) {
  form[field] = val
  emit('update:modelValue', { ...form })
}

function emitSave() {
  emit('save', { ...form })
}
</script>

<template>
  <VCard
    title="Cấu hình SEO &amp; Meta"
    subtitle="Ghi đè thẻ meta, OpenGraph và Canonical. Để trống sẽ tự lấy theo tiêu đề/ảnh đại diện."
    border
  >
    <VCardText>
      <!-- Preview meta đã fallback -->
      <div
        v-if="resolved && (resolved.title || resolved.description)"
        class="seo-preview pa-3 rounded-lg border mb-4 bg-grey-lighten-5"
      >
        <div class="text-caption text-disabled mb-1">
          Xem trước kết quả (đã áp dụng fallback)
        </div>
        <div class="text-body-1 font-weight-medium text-primary">
          {{ resolved.title }}
        </div>
        <div
          v-if="resolved.canonical"
          class="text-caption text-success font-mono"
        >
          {{ resolved.canonical }}
        </div>
        <div class="text-caption text-medium-emphasis">
          {{ resolved.description }}
        </div>
        <VChip
          size="x-small"
          variant="tonal"
          color="secondary"
          class="mt-1"
        >
          {{ resolved.robots }}
        </VChip>
      </div>

      <VRow dense>
        <VCol cols="12">
          <AppTextField
            :model-value="form.focus_keyword"
            label="Từ khóa chính (Focus Keyword)"
            placeholder="VD: review iphone 16"
            prepend-inner-icon="tabler-key"
            :disabled="loading"
            @update:model-value="update('focus_keyword', $event)"
          />
        </VCol>
        <VCol cols="12">
          <AppTextField
            :model-value="form.seo_title"
            label="SEO Title (ghi đè)"
            placeholder="Để trống = tiêu đề + mẫu mặc định"
            prepend-inner-icon="tabler-heading"
            counter
            maxlength="255"
            :disabled="loading"
            @update:model-value="update('seo_title', $event)"
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            :model-value="form.seo_description"
            label="Meta Description"
            placeholder="Mô tả ngắn hiển thị trên Google (khuyên 150–160 ký tự)"
            rows="3"
            counter
            maxlength="500"
            :disabled="loading"
            @update:model-value="update('seo_description', $event)"
          />
        </VCol>
        <VCol cols="12">
          <AppTextField
            :model-value="form.canonical_url"
            label="Canonical URL (ghi đè)"
            placeholder="Để trống = slug tự động"
            prepend-inner-icon="tabler-link"
            :disabled="loading"
            @update:model-value="update('canonical_url', $event)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="form.og_title"
            label="OG Title (chia sẻ MXH)"
            placeholder="Để trống = SEO title"
            :disabled="loading"
            @update:model-value="update('og_title', $event)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppTextField
            :model-value="form.og_image_id"
            label="OG Image ID (Media)"
            placeholder="Chọn từ Media Library"
            prepend-inner-icon="tabler-photo"
            type="number"
            :disabled="loading"
            @update:model-value="update('og_image_id', $event)"
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            :model-value="form.og_description"
            label="OG Description"
            rows="2"
            maxlength="500"
            :disabled="loading"
            @update:model-value="update('og_description', $event)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <VSwitch
            :model-value="form.robots_index"
            label="Cho phép lập chỉ mục (index)"
            color="primary"
            hide-details
            inset
            :disabled="loading"
            @update:model-value="update('robots_index', $event)"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <VSwitch
            :model-value="form.robots_follow"
            label="Cho phép theo liên kết (follow)"
            color="primary"
            hide-details
            inset
            :disabled="loading"
            @update:model-value="update('robots_follow', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VCardActions class="justify-end px-6 pb-4">
      <VBtn
        variant="flat"
        color="primary"
        prepend-icon="tabler-device-floppy"
        :loading="loading"
        @click="emitSave"
      >
        Lưu cấu hình SEO
      </VBtn>
    </VCardActions>
  </VCard>
</template>

<style scoped>
.seo-preview {
  border-left: 3px solid rgb(var(--v-theme-primary));
}
</style>
