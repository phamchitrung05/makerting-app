<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang danh sách tiến trình nền (system_task_runs).
  =====================================================================
  - DataTable phân trang + filter status/module/task_type + search.
  - Gọi taskCenterApi.tasks(); hủy task đang chạy.
  =====================================================================
-->
<script setup>
import { ref } from 'vue'
import { taskCenterApi } from '../../services/taskCenterApi'

definePage({ meta: { layout: 'default' } })

const loading = ref(false)
const items = ref([])
const total = ref(0)
const options = ref({ page: 1, itemsPerPage: 15 })
// eslint-disable-next-line camelcase
const filters = ref({ status: '', module: '', task_type: '', search: '' })

const statusOptions = ['queued', 'running', 'completed', 'partial', 'failed', 'cancelled']

const headers = [
  { title: 'Tiêu đề', key: 'title' },
  { title: 'Module', key: 'module' },
  { title: 'Loại', key: 'task_type' },
  { title: 'Trạng thái', key: 'status' },
  { title: 'Tiến độ', key: 'progress', sortable: false },
  { title: 'Thời gian', key: 'created_at', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]

function statusColor(s) {
  return { queued: 'secondary', running: 'primary', completed: 'success', partial: 'warning', failed: 'error', cancelled: 'secondary' }[s] || 'secondary'
}

async function load() {
  loading.value = true
  try {
    const res = await taskCenterApi.tasks({
      ...filters.value,
      page: options.value.page,
      per_page: options.value.itemsPerPage, // eslint-disable-line camelcase
    })

    items.value = res.items || []
    total.value = res.itemsLength || 0
  } finally {
    loading.value = false
  }
}

async function cancel(t) {
  await taskCenterApi.cancelTask(t.id)
  load()
}

load()
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Tiến trình nền
        </h4>
        <div class="text-body-1">
          Theo dõi mọi tác vụ chạy ngầm của hệ thống
        </div>
      </div>
    </div>

    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="3"
          >
            <AppTextField
              v-model="filters.search"
              placeholder="Tìm theo tiêu đề..."
              clearable
              @update:model-value="load"
            />
          </VCol>
          <VCol
            cols="12"
            sm="3"
          >
            <AppSelect
              v-model="filters.status"
              :items="statusOptions"
              label="Trạng thái"
              clearable
              @update:model-value="load"
            />
          </VCol>
          <VCol
            cols="12"
            sm="3"
          >
            <AppTextField
              v-model="filters.module"
              label="Module"
              clearable
              @update:model-value="load"
            />
          </VCol>
          <VCol
            cols="12"
            sm="3"
          >
            <AppTextField
              v-model="filters.task_type"
              label="Loại tác vụ"
              clearable
              @update:model-value="load"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDataTableServer
        v-model:options="options"
        :headers="headers"
        :items="items"
        :items-length="total"
        :loading="loading"
        @update:options="load"
      >
        <template #item.progress="{ item }">
          <div
            class="d-flex align-center gap-2"
            style="min-width:120px;"
          >
            <VProgressLinear
              :model-value="item.progress"
              :color="statusColor(item.status)"
              height="6"
              rounded
              class="flex-1"
            />
            <span class="text-caption">{{ item.progress }}%</span>
          </div>
        </template>
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="statusColor(item.status)"
            variant="tonal"
          >
            {{ item.status }}
          </VChip>
        </template>
        <template #item.created_at="{ item }">
          <span class="text-caption">{{ item.created_at }}</span>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            v-if="item.status === 'queued' || item.status === 'running'"
            icon
            size="x-small"
            variant="text"
            color="error"
            @click="cancel(item)"
          >
            <VIcon
              icon="tabler-x"
              size="16"
            />
          </VBtn>
        </template>
      </VDataTableServer>
    </VCard>
  </div>
</template>
