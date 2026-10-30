<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang quản lý Danh mục (Category) — cây cha–con + CRUD.
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
const parentOptions = ref([])

// Bộ lọc danh sách (gồm "Tất cả"=không lọc + "Dùng chung"=scope NULL).
const filterOptions = [
  { title: 'Tất cả', value: '' },
  { title: 'Dùng chung', value: 'shared' },
  { title: 'Blog', value: 'blog' },
  { title: 'Visual Style', value: 'visual_style' },
  { title: 'YouTube', value: 'youtube' },
  { title: 'Campaign', value: 'campaign' },
]

// Options trong Dialog — '' = dùng chung (scope NULL); KHÔNG có 'Tất cả'
// và KHÔNG có 'shared' (hai lựa chọn đó chỉ là sentinel lọc, không phải giá trị).
const scopeOptions = [
  { title: 'Dùng chung', value: '' },
  { title: 'Blog', value: 'blog' },
  { title: 'Visual Style', value: 'visual_style' },
  { title: 'YouTube', value: 'youtube' },
  { title: 'Campaign', value: 'campaign' },
]

const blank = () => ({
  name: '',
  'parent_id': null, // eslint-disable-line camelcase
  scope: '', // '' = dùng chung
  description: '',
  status: 'active',
  'sort_order': 0, // eslint-disable-line camelcase
  'show_on_menu': false, // eslint-disable-line camelcase
})

const form = ref(blank())

const headers = [
  { title: 'Tên danh mục', key: 'name' },
  { title: 'Phân vùng', key: 'scope', sortable: false },
  { title: 'Cha', key: 'parent', sortable: false },
  { title: 'Thứ tự', key: 'sort_order', sortable: false },
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

    const res = await taxonomyApi.categories(params)

    items.value = res.items ?? []
    itemsLength.value = res.itemsLength ?? 0
  } catch (e) {
    notify(firstErrorMessage(e), 'error')
  } finally {
    loading.value = false
  }
}

async function loadParents() {
  try {
    parentOptions.value = await taxonomyApi.categoryOptions({})
  } catch (e) {
    /* bỏ qua */
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
    'parent_id': item.parent_id, // eslint-disable-line camelcase
    scope: item.scope ?? '',
    description: item.description,
    status: item.status,
    'sort_order': item.sort_order ?? 0, // eslint-disable-line camelcase
    'show_on_menu': item.show_on_menu ?? false, // eslint-disable-line camelcase
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
      await taxonomyApi.updateCategory(editing.value.id, payload)
    else
      await taxonomyApi.createCategory(payload)

    dialog.value = false
    notify(editing.value ? 'Đã cập nhật' : 'Đã tạo danh mục')
    load()
    loadParents()
  } catch (e) {
    errors.value = fieldErrors(e)
    notify(firstErrorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  if (!confirm('Xóa danh mục ' + item.name + '?')) return
  try {
    await taxonomyApi.deleteCategory(item.id)
    notify('Đã xóa danh mục')
    load()
    loadParents()
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
onMounted(() => {
  load()
  loadParents()
})
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Danh mục
        </h4>
        <div class="text-body-1">
          Cây cha–con + phân vùng (scope) dùng chung cho mọi module
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
          Thêm danh mục
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
              color="primary"
              variant="tonal"
              size="34"
            >
              <VIcon icon="tabler-category" />
            </VAvatar>
            <div>
              <div class="text-body-1 font-weight-medium">
                {{ item.name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.slug }}
              </div>
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

        <template #item.parent="{ item }">
          {{ item.parent?.name ?? '—' }}
        </template>

        <template #item.sort_order="{ item }">
          {{ item.sort_order }}
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
      max-width="640"
      scrollable
      :persistent="saving"
    >
      <AppDialogLayout
        :title="editing ? 'Sửa danh mục' : 'Thêm danh mục'"
        :close-disabled="saving"
        @close="dialog = false"
      >
        <VCardText>
          <VForm @submit.prevent="save">
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="form.name"
                  label="Tên danh mục"
                  placeholder="VD: Tin công nghệ"
                  :error-messages="errors.name"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.parent_id"
                  :items="[{ id: null, name: '— Gốc —' }, ...parentOptions.filter(p => p.id !== editing?.id)]"
                  item-title="name"
                  item-value="id"
                  label="Danh mục cha"
                  :error-messages="errors.parent_id"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
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
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model.number="form.sort_order"
                  type="number"
                  label="Thứ tự"
                  :error-messages="errors.sort_order"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VSwitch
                  v-model="form.show_on_menu"
                  label="Hiện trên menu"
                  color="primary"
                  hide-details
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
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
              {{ editing ? 'Lưu thay đổi' : 'Tạo danh mục' }}
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
