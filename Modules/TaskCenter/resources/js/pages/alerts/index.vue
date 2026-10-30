<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang cảnh báo hệ thống (system_alerts).
  =====================================================================
  - Danh sách alerts + lọc severity/category/unread.
  - Đánh dấu đã đọc / đọc tất cả.
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
const filters = ref({ severity: '', category: '', unread: false })
const snack = ref({ show: false, text: '', color: 'success' })

const severityOptions = ['info', 'warning', 'critical']

const headers = [
  { title: 'Mức độ', key: 'severity', sortable: false },
  { title: 'Tiêu đề', key: 'title' },
  { title: 'Hạng mục', key: 'category' },
  { title: 'Thời gian', key: 'created_at', sortable: false },
  { title: 'Trạng thái', key: 'read_at', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]

function sevColor(s) {
  return { info: 'info', warning: 'warning', critical: 'error' }[s] || 'secondary'
}

function notify(t, c = 'success') {
  snack.value = { show: true, text: t, color: c }
}

async function load() {
  loading.value = true
  try {
    const res = await taskCenterApi.alerts({
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

async function markRead(a) {
  await taskCenterApi.markAlertRead(a.id)
  notify('Đã đánh dấu đã đọc')
  load()
}

async function markAll() {
  await taskCenterApi.markAllRead()
  notify('Đã đánh dấu tất cả đã đọc')
  load()
}

load()
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Cảnh báo hệ thống
        </h4>
        <div class="text-body-1">
          Theo dõi cảnh báo sức khỏe tài khoản & lỗi tiến trình
        </div>
      </div>
      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-check"
          @click="markAll"
        >
          Đọc tất cả
        </VBtn>
      </div>
    </div>

    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="filters.severity"
              :items="severityOptions"
              label="Mức độ"
              clearable
              @update:model-value="load"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <AppTextField
              v-model="filters.category"
              label="Hạng mục"
              clearable
              @update:model-value="load"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
            class="d-flex align-center"
          >
            <VSwitch
              v-model="filters.unread"
              label="Chỉ chưa đọc"
              color="primary"
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
        <template #item.severity="{ item }">
          <VChip
            size="small"
            :color="sevColor(item.severity)"
            variant="tonal"
          >
            {{ item.severity }}
          </VChip>
        </template>
        <template #item.read_at="{ item }">
          <VChip
            size="x-small"
            :color="item.read_at ? 'secondary' : 'warning'"
            variant="tonal"
          >
            {{ item.read_at ? 'Đã đọc' : 'Chưa đọc' }}
          </VChip>
        </template>
        <template #item.created_at="{ item }">
          <span class="text-caption">{{ item.created_at }}</span>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            v-if="!item.read_at"
            icon
            size="x-small"
            variant="text"
            color="success"
            @click="markRead(item)"
          >
            <VIcon
              icon="tabler-check"
              size="16"
            />
          </VBtn>
        </template>
      </VDataTableServer>
    </VCard>

    <VSnackbar
      v-model="snack.show"
      :color="snack.color"
      timeout="2500"
    >
      {{ snack.text }}
    </VSnackbar>
  </div>
</template>
