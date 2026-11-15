<!--
  =====================================================================
  CHỨC NĂNG FILE: Component Global Snackbar dùng chung cho toàn hệ thống.
  =====================================================================
  Được mount 1 lần duy nhất trong App.vue và điều khiển thông qua composable
  `useSnackbar()` (tự động auto-import) hoặc singleton `appSnackbar`.

  Tính năng:
  - Hỗ trợ 4 mức độ chuẩn (success / error / warning / info) kèm icon tương ứng.
  - z-index 10000 để luôn hiển thị nổi phía trên navbar, drawer và VDialog.
  - Tạm dừng auto-close khi người dùng focus vào bên trong và trả focus về control trước đó khi bấm nút đóng.
  - Hỗ trợ nút action tùy chọn và chế độ prefers-reduced-motion.
  =====================================================================
-->
<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { appSnackbar } from '@/composables/useSnackbar'

const { state } = appSnackbar

const isFocused = ref(false)
let activeSnackbarEl = null
let lastFocusedBeforeSnackbar = null

function closeSnackbar({ restoreFocus = false } = {}) {
  const wasFocusedInside = Boolean(
    typeof document !== 'undefined'
    && activeSnackbarEl?.contains(document.activeElement),
  )

  const focusTarget = restoreFocus && wasFocusedInside ? lastFocusedBeforeSnackbar : null

  isFocused.value = false
  activeSnackbarEl = null
  lastFocusedBeforeSnackbar = null
  appSnackbar.hide()

  if (focusTarget?.isConnected && typeof focusTarget.focus === 'function') {
    nextTick(() => {
      focusTarget.focus({ preventScroll: true })
    })
  }
}

function onModelUpdate(visible) {
  if (!visible)
    closeSnackbar()
}

function onFocusIn(event) {
  if (!state.value.show)
    return

  activeSnackbarEl = event?.currentTarget || activeSnackbarEl

  const prev = event?.relatedTarget
  if (prev && typeof prev.focus === 'function' && !activeSnackbarEl?.contains(prev))
    lastFocusedBeforeSnackbar = prev

  isFocused.value = true
}

function onFocusOut(event) {
  const next = event?.relatedTarget
  if (!event?.currentTarget?.contains(next))
    isFocused.value = false
}

async function handleAction() {
  const callback = state.value.action?.onClick

  closeSnackbar()

  if (typeof callback === 'function')
    await callback()
}

onBeforeUnmount(() => {
  activeSnackbarEl = null
  lastFocusedBeforeSnackbar = null
})
</script>

<template>
  <VSnackbar
    :model-value="state.show"
    :color="state.color"
    :location="state.location"
    :timeout="isFocused ? -1 : state.timeout"
    :variant="state.variant"
    :z-index="state.zIndex"
    class="app-global-snackbar"
    @update:model-value="onModelUpdate"
    @focusin="onFocusIn"
    @focusout="onFocusOut"
  >
    <div
      :role="state.color === 'error' ? 'alert' : undefined"
      :aria-live="state.color === 'error' ? 'assertive' : undefined"
      class="d-flex align-center ga-2"
    >
      <VIcon
        v-if="state.icon"
        :icon="state.icon"
        size="20"
        class="flex-shrink-0"
        aria-hidden="true"
      />
      <span class="app-global-snackbar__text">{{ state.text }}</span>
    </div>

    <template
      v-if="state.action || state.closable"
      #actions
    >
      <VBtn
        v-if="state.action"
        variant="text"
        :color="state.action.color || ''"
        size="small"
        @click="handleAction"
      >
        {{ state.action.text }}
      </VBtn>

      <VBtn
        v-if="state.closable"
        variant="text"
        color=""
        size="small"
        icon
        aria-label="Đóng thông báo"
        @click="closeSnackbar({ restoreFocus: true })"
      >
        <VIcon
          icon="tabler-x"
          size="18"
        />
      </VBtn>
    </template>
  </VSnackbar>
</template>

<style lang="scss">
.app-global-snackbar {
  .v-snackbar__wrapper {
    max-inline-size: min(560px, calc(100vw - 24px));
    min-inline-size: min(300px, calc(100vw - 24px));
  }

  .v-snackbar__content {
    min-inline-size: 0;
  }

  &__text {
    overflow-wrap: anywhere;
  }
}

@media (prefers-reduced-motion: reduce) {
  .app-global-snackbar .v-snackbar__wrapper,
  .app-global-snackbar .v-snackbar-transition-enter-active,
  .app-global-snackbar .v-snackbar-transition-leave-active {
    transition: none !important;
  }
}
</style>
