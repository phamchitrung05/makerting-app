<!--
  =====================================================================
  CHỨC NĂNG FILE: Thẻ vuông tóm tắt 1 hồ sơ Visual Style (DNA ảnh).
  =====================================================================

  Render từng hồ sơ DNA thị giác với ảnh đại diện tỷ lệ vuông 1:1 tối ưu
  cho lưới 6 cột:
  - Ảnh thumbnail vuông `aspect-ratio="1"` đi kèm badge trạng thái nổi
    góc trên trái và nút menu thao tác nền `surface` góc trên phải.
  - Phần thân gọn (`pa-3`) cân đối chiều cao gồm tên phong cách + phiên bản,
    dòng ánh sáng & ống kính, dải Color Swatches (`paletteHex`) + tỷ lệ
    khung hình, tag phân loại, thanh độ tin cậy và chân thẻ.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - statusMeta(): Ánh xạ status sang nhãn tiếng Việt và màu semantic.
  - confidenceColor(): Quy đổi điểm confidence (0-100) sang màu semantic.
  - visibleTags(): Giới hạn tối đa 2 tag phân loại để giữ chiều cao đồng đều.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : prop `styleItem` (Object Visual Style), `previewOnly` (Boolean ẩn menu thao tác).
  - OUTPUT: Emit `preview`, `edit`, `duplicate`, `toggle-archive`, `delete`.
  =====================================================================
-->
<script setup>
const props = defineProps({
  styleItem: {
    type: Object,
    required: true,
  },
  previewOnly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'preview',
  'edit',
  'duplicate',
  'toggle-archive',
  'delete',
])

const STATUS_META_MAP = {
  active: { label: 'Hoạt động', color: 'success' },
  analyzed: { label: 'Đã giải mã', color: 'info' },
  analyzing: { label: 'Đang giải mã', color: 'warning' },
  draft: { label: 'Nháp', color: 'secondary' },
  archived: { label: 'Lưu trữ', color: 'secondary' },
  failed: { label: 'Thất bại', color: 'error' },
}

/*
 * CHỨC NĂNG: Trả về nhãn và màu Vuetify chuẩn theo trạng thái VisualStyleStatus.
 * INPUT: props.styleItem.status.
 * OUTPUT: Object { label, color }.
 */
const statusMeta = computed(() => {
  return STATUS_META_MAP[props.styleItem.status] || { label: 'Nháp', color: 'secondary' }
})

/*
 * CHỨC NĂNG: Xác định màu semantic cho thanh độ tin cậy DNA.
 * INPUT: props.styleItem.confidence (Number 0-100).
 * OUTPUT: Tên màu theme ('success' | 'info' | 'warning' | 'secondary').
 */
const confidenceColor = computed(() => {
  const score = Number(props.styleItem.confidence ?? 0)
  if (score >= 85)
    return 'success'
  if (score >= 65)
    return 'info'
  if (score >= 45)
    return 'warning'

  return 'secondary'
})

/*
 * CHỨC NĂNG: Giới hạn danh sách nhãn taxonomy hiển thị trên thẻ gọn 6 cột.
 * INPUT: props.styleItem.taxonomyTags.
 * OUTPUT: Mảng tối đa 2 nhãn string.
 */
const visibleTags = computed(() => {
  return Array.isArray(props.styleItem.taxonomyTags)
    ? props.styleItem.taxonomyTags.slice(0, 2)
    : []
})
</script>

<template>
  <VCard
    class="visual-dna-card d-flex flex-column overflow-hidden"
    :class="{ 'h-100 cursor-pointer': !previewOnly }"
    @click="!previewOnly && emit('preview', styleItem)"
  >
    <div class="position-relative">
      <VImg
        :src="styleItem.thumbnailUrl"
        :alt="styleItem.name"
        :aspect-ratio="1"
        cover
      >
        <template #placeholder>
          <div class="d-flex align-center justify-center fill-height">
            <VIcon
              :icon="styleItem.icon || 'tabler-photo-spark'"
              size="32"
              color="secondary"
            />
          </div>
        </template>
      </VImg>

      <VChip
        size="x-small"
        variant="flat"
        :color="statusMeta.color"
        class="card-status-badge"
      >
        {{ statusMeta.label }}
      </VChip>

      <VBtn
        v-if="!previewOnly"
        icon
        size="x-small"
        color="surface"
        variant="flat"
        aria-label="Thao tác Visual Style"
        class="card-menu-btn"
        @click.stop
      >
        <VIcon
          icon="tabler-dots-vertical"
          size="16"
        />

        <VMenu activator="parent">
          <VList>
            <VListItem
              prepend-icon="tabler-eye"
              title="Xem chi tiết DNA"
              @click="emit('preview', styleItem)"
            />
            <VListItem
              prepend-icon="tabler-pencil"
              title="Chỉnh sửa"
              @click="emit('edit', styleItem)"
            />
            <VListItem
              prepend-icon="tabler-copy"
              title="Nhân bản hồ sơ"
              @click="emit('duplicate', styleItem)"
            />
            <VListItem
              :prepend-icon="styleItem.status === 'archived' ? 'tabler-refresh' : 'tabler-archive'"
              :title="styleItem.status === 'archived' ? 'Khôi phục từ lưu trữ' : 'Chuyển vào lưu trữ'"
              @click="emit('toggle-archive', styleItem)"
            />
            <VDivider class="my-1" />
            <VListItem
              prepend-icon="tabler-trash"
              title="Xóa bản mẫu"
              base-color="error"
              @click="emit('delete', styleItem)"
            />
          </VList>
        </VMenu>
      </VBtn>
    </div>

    <VCardText class="pa-3 d-flex flex-column flex-grow-1">
      <div class="d-flex align-center justify-space-between gap-2 mb-1">
        <div class="text-body-1 font-weight-medium text-truncate">
          {{ styleItem.name }}
        </div>
        <VChip
          size="x-small"
          variant="tonal"
          color="secondary"
          class="flex-shrink-0"
        >
          v{{ styleItem.version }}
        </VChip>
      </div>

      <div class="text-caption text-medium-emphasis text-truncate mb-2">
        <VIcon
          icon="tabler-sun"
          size="13"
          class="me-1"
        />
        {{ styleItem.dna.lighting.toneLabel }} • {{ styleItem.dna.camera.lensLabel }}
      </div>

      <div class="border rounded pa-2 d-flex align-center justify-space-between gap-2 mb-2">
        <div class="d-flex align-center gap-1">
          <span
            v-for="hex in styleItem.dna.colorMood.paletteHex"
            :key="hex"
            class="palette-swatch"
            :title="hex"
            :style="{ backgroundColor: hex }"
          />
        </div>

        <VChip
          v-if="styleItem.aspectRatios?.length"
          size="x-small"
          variant="tonal"
          color="primary"
          class="flex-shrink-0"
        >
          {{ styleItem.aspectRatios[0] }}
          <span
            v-if="styleItem.aspectRatios.length > 1"
            class="ms-1"
          >
            +{{ styleItem.aspectRatios.length - 1 }}
          </span>
        </VChip>
      </div>

      <div
        v-if="visibleTags.length"
        class="d-flex flex-wrap gap-1 mb-3"
      >
        <VChip
          v-for="tag in visibleTags"
          :key="tag"
          size="x-small"
          variant="tonal"
          color="secondary"
        >
          #{{ tag }}
        </VChip>
      </div>

      <div class="mt-auto">
        <div class="d-flex align-center justify-space-between text-caption mb-1">
          <span class="text-medium-emphasis">
            Độ tin cậy
          </span>
          <span class="font-weight-medium">
            {{ styleItem.confidence }}%
          </span>
        </div>

        <VProgressLinear
          :model-value="styleItem.confidence"
          :color="confidenceColor"
          height="4"
          rounded
          class="mb-2"
        />

        <VDivider class="mb-2" />

        <div class="d-flex align-center justify-space-between text-caption text-medium-emphasis">
          <span class="d-inline-flex align-center">
            <VIcon
              icon="tabler-frame"
              size="13"
              class="me-1"
            />
            {{ styleItem.shotsCount }} góc
          </span>
          <span class="text-truncate ms-2">
            {{ styleItem.updatedAt }}
          </span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.visual-dna-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.visual-dna-card:hover {
  transform: translateY(-2px);
}

.card-status-badge {
  position: absolute;
  inset-block-start: 10px;
  inset-inline-start: 10px;
}

.card-menu-btn {
  position: absolute;
  inset-block-start: 8px;
  inset-inline-end: 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  box-shadow: 0 2px 6px rgba(var(--v-shadow-key-umbra-color), 0.16);
}

.palette-swatch {
  display: inline-block;
  inline-size: 12px;
  block-size: 12px;
  border-radius: 50%;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
