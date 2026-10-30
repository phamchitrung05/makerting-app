<!--
  =====================================================================
  CHỨC NĂNG FILE: Khay theo dõi tiến trình ngầm — góc dưới bên phải Admin.
  =====================================================================
  - Floating FAB -> bấm mở panel danh sách task đang queued/running.
  - Polling /tasks/active qua useTaskCenterStore khi panel mở.
  - Hiển thị progress bar + trạng thái + nút hủy từng task.
  CÁC HÀM/METHOD: open/close panel, refresh, cancelTask.
  INPUT/OUTPUT: gọi useTaskCenterStore; không emit.
  SIDE EFFECT: polling bật khi panel mở, tắt khi đóng/unmount.
  =====================================================================
-->
<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { useTaskCenterStore } from '../stores/taskCenter'

const store = useTaskCenterStore()
const open = ref(false)

const items = computed(() => store.items)
const unread = computed(() => store.unread)
const activeCount = computed(() => store.activeCount)

function toggle() {
  open.value = !open.value
  if (open.value) {
    store.startPolling()
  } else {
    store.stopPolling()
  }
}

async function cancel(t) {
  try {
    await store.cancelTask(t.id)
  } catch (e) {
    // lỗi đã xử lý silent trong store/API.
  }
}

function statusColor(s) {
  return {
    queued: 'secondary',
    running: 'primary',
    completed: 'success',
    partial: 'warning',
    failed: 'error',
    cancelled: 'secondary',
  }[s] || 'secondary'
}

function statusLabel(s) {
  return {
    queued: 'Xếp hàng',
    running: 'Đang chạy',
    completed: 'Hoàn thành',
    partial: 'Một phần',
    failed: 'Lỗi',
    cancelled: 'Đã hủy',
  }[s] || s
}

onBeforeUnmount(() => store.stopPolling())
</script>

<template>
  <!-- Nút nổi góc dưới phải -->
  <div class="task-queue-fab">
    <VBtn
      icon
      color="primary"
      size="large"
      elevation="4"
      @click="toggle"
    >
      <VIcon
        icon="tabler-player-play"
        size="22"
      />
      <VBadge
        v-if="activeCount > 0"
        :content="activeCount"
        color="error"
        location="top end"
        floating
      />
    </VBtn>
  </div>

  <!-- Panel danh sách task -->
  <VCard
    v-show="open"
    class="task-queue-panel"
    elevation="8"
    rounded="lg"
  >
    <div class="d-flex align-center justify-space-between pa-4 border-b">
      <div class="d-flex align-center">
        <VIcon
          icon="tabler-player-play"
          color="primary"
          size="20"
          class="me-2"
        />
        <div class="text-subtitle-1 font-weight-medium">
          Tiến trình nền
        </div>
      </div>
      <div class="d-flex align-center gap-2">
        <VChip
          v-if="activeCount > 0"
          size="small"
          color="primary"
          variant="tonal"
        >
          {{ activeCount }} đang chạy
        </VChip>
        <VBtn
          icon
          size="x-small"
          variant="text"
          @click="toggle"
        >
          <VIcon
            icon="tabler-x"
            size="18"
          />
        </VBtn>
      </div>
    </div>

    <div class="task-queue-list">
      <div
        v-if="items.length === 0"
        class="pa-6 text-center text-medium-emphasis text-body-2"
      >
        <VIcon
          icon="tabler-check"
          size="32"
          color="success"
          class="mb-2"
        />
        <div>Không có tác vụ nào đang chạy.</div>
      </div>

      <div
        v-for="t in items"
        :key="t.id"
        class="task-queue-item pa-4 border-b"
      >
        <div class="d-flex align-start justify-space-between mb-2">
          <div class="d-flex align-center flex-1 min-w-0">
            <VIcon
              :icon="t.ui?.icon || 'tabler-player-play'"
              size="18"
              color="primary"
              class="me-2 flex-shrink-0"
            />
            <div class="min-w-0">
              <div class="text-body-2 font-weight-medium text-truncate">
                {{ t.title }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ t.ui?.label || t.task_type }} · {{ t.module }}
              </div>
            </div>
          </div>
          <div class="d-flex align-center gap-1 flex-shrink-0 ms-2">
            <VChip
              size="x-small"
              :color="statusColor(t.status)"
              variant="tonal"
            >
              {{ statusLabel(t.status) }}
            </VChip>
            <VBtn
              v-if="t.status === 'queued' || t.status === 'running'"
              icon
              size="x-small"
              variant="text"
              color="error"
              @click="cancel(t)"
            >
              <VIcon
                icon="tabler-x"
                size="14"
              />
            </VBtn>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <VProgressLinear
            :model-value="t.progress"
            :color="statusColor(t.status)"
            height="6"
            rounded
            class="flex-1"
          />
          <span
            class="text-caption text-medium-emphasis flex-shrink-0"
            style="width:34px;text-align:right;"
          >
            {{ t.progress }}%
          </span>
        </div>
        <div
          v-if="t.meta?.last_message"
          class="text-caption text-medium-emphasis mt-1 text-truncate"
        >
          {{ t.meta.last_message }}
        </div>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.task-queue-fab {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 1200;
}
.task-queue-panel {
  position: fixed;
  right: 24px;
  bottom: 88px;
  width: 380px;
  max-width: calc(100vw - 48px);
  z-index: 1200;
}
.task-queue-list {
  max-height: 420px;
  overflow-y: auto;
}
</style>
