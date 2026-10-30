<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang quản lý Thẻ (Tag) — list + CRUD + scope.
  =====================================================================
-->
<script setup>
import { onMounted, ref, watch } from 'vue'
import AppDialogLayout from '@/components/dialogs/AppDialogLayout.vue'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'
import AppTextarea from '@core/components/app-form-elements/AppTextarea.vue'
import AppSelect from '@core/components/app-form-elements/AppSelect.vue'
import { taxonomyApi } from '../../services/taxonomyApi'
import { fieldErrors, firstErrorMessage } from '@/utils/api'

definePage({ meta: { layout: 'default' } })

const items = ref([])
const itemsLength = ref(0)
const loading = ref(false)
const search = ref('')
const scope = ref('')
const options = ref({ page: 1, itemsPerPage: 15 })
const errors = ref({})
const snack = ref({ show: false, text: '', color: 'success' })

const dialog = ref(false)
const saving = ref(false)
const editing = ref(null)

// Bộ lọc danh sách (gồm "Tất cả"=không lọc + "Dùng chung"=scope NULL).
const filterOptions = [
  { title: 'Tất cả', value: '' },
  { title: 'Dùng chung', value: 'shared' },
  { title: 'Blog', value: 'blog' },
  { title: 'Visual Style', value: 'visual_style' },
  { title: 'YouTube', value: 'youtube' },
  { title: 'Campaign', value: 'campaign' },
]

// Options trong Dialog — '' = dùng chung (scope NULL).
const scopeOptions = [
  { title: 'Dùng chung', value: '' },
  { title: 'Blog', value: 'blog' },
  { title: 'Visual Style', value: 'visual_style' },
  { title: 'YouTube', value: 'youtube' },
  { title: 'Campaign', value: 'campaign' },
]

const blank = () => ({
  name: '',
  scope: '', // '' = dùng chung
  description: '',
  status: 'active',
})

const form = ref(blank())

const headers = [
  { title: 'Tên thẻ', key: 'name' },
  { title: 'Phân vùng', key: 'scope', sortable: false },
  { title: 'Slug', key: 'slug', sortable: false },
  { title: 'Trạng thái', key: 'status', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]

async function load() {
  loading.value = true
  try {
    const params = {
      search: search.value,
      page: options.value.page,
      itemsPerPage: options.value.itemsPerPage,
    }

    if (scope.value)
      params.scope = scope.value // 'shared' = lọc scope NULL; '' = không lọc

    const res = await taxonomyApi.tags(params)

    items.value = res.items ?? []
    itemsLength.value = res.itemsLength ?? 0
  } catch (e) {
    notify(firstErrorMessage(e), 'error')
  } finally {
    loading.value = false
  }
}

function notify(text, color = 'success') {
  snack.value = { show: true, text, color }
}

function openCreate() {
  editing.value = null
  form.value = blank()
  errors.value = {}
  dialog.value = true
}

function openEdit(item) {
  editing.value = item
  form.value = {
    name: item.name,
    scope: item.scope ?? '',
    description: item.description,
    status: item.status,
  }
  errors.value = {}
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const payload = { ...form.value, scope: form.value.scope || null }

    if (editing.value)
      await taxonomyApi.updateTag(editing.value.id, payload)
    else
      await taxonomyApi.createTag(payload)

    dialog.value = false
    notify(editing.value ? 'Đã cập nhật' : 'Đã tạo thẻ')
    load()
  } catch (e) {
    errors.value = fieldErrors(e)
    notify(firstErrorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  if (!confirm('Xóa thẻ ' + item.name + '?')) return
  try {
    await taxonomyApi.deleteTag(item.id)
    notify('Đã xóa thẻ')
    load()
  } catch (e) {
    notify(firstErrorMessage(e), 'error')
  }
}

watch(options, load, { deep: true })
let searchTimer
watch([search, scope], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 350)
})
onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Thẻ
        </h4>
        <div class="text-body-1">
          Gắn nhãn nội dung, có thể dùng chung hoặc theo từng scope
        </div>
      </div>
      <div class="d-flex gap-4 align-center flex-wrap">
        <AppSelect
          v-model="scope"
          :items="filterOptions"
          label="Phân vùng"
          style="min-inline-size: 170px"
        />
        <AppTextField
          v-model="search"
          placeholder="Tìm theo tên..."
          prepend-inner-icon="tabler-search"
          style="min-inline-size: 240px"
          clearable
        />
        <VBtn
          variant="flat"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Thêm thẻ
        </VBtn>
      </div>
    </div>

    <VCard>
      <VDataTableServer
        v-model:page="options.page"
        v-model:items-per-page="options.itemsPerPage"
        :headers="headers"
        :items="items"
        :items-length="itemsLength"
        :loading="loading"
        class="text-no-wrap"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3">
            <VAvatar
              color="info"
              variant="tonal"
              size="34"
            >
              <VIcon icon="tabler-tag" />
            </VAvatar>
            <div class="text-body-1 font-weight-medium">
              {{ item.name }}
            </div>
          </div>
        </template>

        <template #item.scope="{ item }">
          <VChip
            size="small"
            variant="tonal"
            :color="item.scope ? 'info' : 'secondary'"
          >
            {{ item.scope || 'dùng chung' }}
          </VChip>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="item.status === 'active' ? 'success' : 'secondary'"
            label
          >
            {{ item.status === 'active' ? 'Hoạt động' : 'Tắt' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <IconBtn
            size="small"
            @click="openEdit(item)"
          >
            <VIcon icon="tabler-edit" />
          </IconBtn>
          <IconBtn
            size="small"
            color="error"
            @click="remove(item)"
          >
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>
      </VDataTableServer>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="620"
      scrollable
      :persistent="saving"
    >
      <AppDialogLayout
        :title="editing ? 'Sửa thẻ' : 'Thêm thẻ'"
        :close-disabled="saving"
        @close="dialog = false"
      >
        <VCardText>
          <VForm @submit.prevent="save">
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="form.name"
                  label="Tên thẻ"
                  placeholder="VD: trí tuệ nhân tạo"
                  :error-messages="errors.name"
                />
              </VCol>
              <VCol cols="12">
                <AppSelect
                  v-model="form.scope"
                  :items="scopeOptions"
                  label="Phân vùng (scope)"
                  :error-messages="errors.scope"
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  v-model="form.description"
                  label="Mô tả"
                  rows="3"
                  :error-messages="errors.description"
                />
              </VCol>
              <VCol cols="12">
                <AppSelect
                  v-model="form.status"
                  :items="[
                    { title: 'Hoạt động', value: 'active' },
                    { title: 'Tắt', value: 'inactive' },
                  ]"
                  label="Trạng thái"
                  :error-messages="errors.status"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <template #footer>
          <VCardActions class="justify-end">
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="saving"
              @click="dialog = false"
            >
              Hủy
            </VBtn>
            <VBtn
              variant="flat"
              color="primary"
              :loading="saving"
              @click="save"
            >
              {{ editing ? 'Lưu thay đổi' : 'Tạo thẻ' }}
            </VBtn>
          </VCardActions>
        </template>
      </AppDialogLayout>
    </VDialog>

    <VSnackbar
      v-model="snack.show"
      :color="snack.color"
      location="top end"
    >
      {{ snack.text }}
    </VSnackbar>
  </div>
</template>
