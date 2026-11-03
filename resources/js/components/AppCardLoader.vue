<!--
  =====================================================================
  CHỨC NĂNG FILE: Wrapper loading cho một card/panel — dùng chung.
  =====================================================================
  Mỗi card một AppCardLoader — card nào tải xong hiện trước, card chưa
  xong vẫn loading (độc lập, không liên quan nhau). Tự động chọn kiểu
  hiển thị theo trạng thái:

  loading && !hasData -> Skeleton (giả nội dung — lần tải đầu)
  loading &&  hasData -> Progress mỏng trên đỉnh (refresh, không che data)
  !loading            -> slot nội dung thật

  mode='auto' mặc định; ép 'progress'|'skeleton'|'overlay' khi cần.

  INPUT/OUTPUT:
  - loading : boolean — card đang tải.
  - hasData : boolean — đã có dữ liệu (chọn skeleton vs progress).
  - mode    : 'auto'|'progress'|'skeleton'|'overlay' (default 'auto').
  - skeletonType : loại VSkeletonLoader (default 'list-item-three-line').
  - minHeight : chiều cao tối thiểu khu vực skeleton (default '120px').
  SIDE EFFECT: chỉ render — không gọi API.
  =====================================================================
-->
<script setup>
const props = defineProps({
  loading: { type: Boolean, default: false },
  hasData: { type: Boolean, default: false },
  mode: { type: String, default: 'auto' }, // auto | progress | skeleton | overlay
  skeletonType: { type: String, default: 'list-item-three-line' },
  minHeight: { type: String, default: '120px' },
})

// Skeleton chỉ khi đang tải VÀ chưa có data (lần đầu / làm mới toàn bộ).
const showSkeleton = computed(() => {
  if (!props.loading) return false
  if (props.mode === 'skeleton') return true
  if (props.mode === 'auto') return !props.hasData
  
  return false
})

// Progress mỏng trên đỉnh khi đang tải VÀ đã có data (refresh nhẹ nhàng).
const showProgress = computed(() => {
  if (!props.loading) return false
  if (props.mode === 'progress') return true
  if (props.mode === 'auto') return props.hasData
  
  return false
})

// Overlay spinner che card (chặn tương tác) — chỉ khi mode='overlay'.
const showOverlay = computed(() => props.loading && props.mode === 'overlay')
</script>

<template>
  <div
    class="app-card-loader"
    :class="{ 'is-loading': loading }"
  >
    <!-- Progress mỏng trên đỉnh — không che nội dung -->
    <VProgressLinear
      v-if="showProgress"
      indeterminate
      color="primary"
      height="3"
      class="app-card-loader__bar"
    />

    <!-- Skeleton giả nội dung — lần tải đầu -->
    <div
      v-if="showSkeleton"
      class="app-card-loader__skeleton pa-4"
      :style="{ minHeight }"
    >
      <VSkeletonLoader :type="skeletonType" />
    </div>

    <!-- Nội dung thật -->
    <slot v-else />

    <!-- Overlay spinner che card — chặn tương tác -->
    <VOverlay
      v-if="showOverlay"
      model-value
      contained
      persistent
      class="align-center justify-center"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </VOverlay>
  </div>
</template>

<style scoped>
.app-card-loader {
  position: relative;
  min-height: 40px;
}
.app-card-loader__bar {
  position: absolute;
  inset-block-start: 0;
  inset-inline: 0 0;
  z-index: 2;
}
</style>
