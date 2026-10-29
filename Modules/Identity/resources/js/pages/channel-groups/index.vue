<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang quản lý Nhóm kênh (ChannelGroup) — list + CRUD dialog.
  =====================================================================
-->
<script setup>
import { onMounted, ref, watch } from 'vue'
import AppDialogLayout from '@/components/dialogs/AppDialogLayout.vue'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'
import AppTextarea from '@core/components/app-form-elements/AppTextarea.vue'
import AppSelect from '@core/components/app-form-elements/AppSelect.vue'
import { identityApi } from '../../services/identityApi'
import { fieldErrors, firstErrorMessage } from '@/utils/api'

definePage({ meta: { layout: 'default' } })

const items = ref([])
const itemsLength = ref(0)
const loading = ref(false)
const search = ref('')
const options = ref({ page: 1, itemsPerPage: 10 })
const errors = ref({})
const snack = ref({ show: false, text: '', color: 'success' })

const dialog = ref(false)
const saving = ref(false)
const editing = ref(null)

const blank = () => ({
  name: '',
  code: '',
  color: 'primary',
  description: '',
  is_active: true,
})
const form = ref(blank())

const headers = [
  { title: 'Tên nhóm', key: 'name' },
  { title: 'Mã', key: 'code' },
  { title: 'Thành viên', key: 'members_count', sortable: false },
  { title: 'Trạng thái', key: 'is_active', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]

const colorOptions = ['primary', 'secondary', 'success', 'info', 'warning', 'error']

async function load() {
  loading.value = true
  try {
    const res = await identityApi.channelGroups({
      search: search.value,
      page: options.value.page,
      itemsPerPage: options.value.itemsPerPage,
    })
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
    code: item.code,
    color: item.color,
    description: item.description,
    is_active: item.is_active,
  }
  errors.value = {}
  dialog.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    if (editing.value)
      await identityApi.updateChannelGroup(editing.value.id, form.value)
    else
      await identityApi.createChannelGroup(form.value)

    dialog.value = false
    notify(editing.value ? 'Đã cập nhật' : 'Đã tạo nhóm')
    load()
  } catch (e) {
    errors.value = fieldErrors(e)
    notify(firstErrorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  if (!confirm('Xóa nhóm kênh ' + item.name + '?')) return
  try {
    await identityApi.deleteChannelGroup(item.id)
    notify('Đã xóa nhóm kênh')
    load()
  } catch (e) {
    notify(firstErrorMessage(e), 'error')
  }
}

watch(options, load, { deep: true })
let searchTimer
watch(search, () => {
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
          Nhóm kênh
        </h4>
        <div class="text-body-1">
          Gom cụm Fanpage / YouTube / TikTok theo ngách nội dung
        </div>
      </div>
      <div class="d-flex gap-4 align-center flex-wrap">
        <AppTextField
          v-model="search"
          placeholder="Tìm theo tên / mã..."
          prepend-inner-icon="tabler-search"
          style="min-inline-size: 260px"
          clearable
        />
        <VBtn
          variant="flat"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          Thêm nhóm kênh
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
              :color="item.color"
              variant="tonal"
              size="34"
            >
              <VIcon icon="tabler-affiliate" />
            </VAvatar>
            <div>
              <div class="text-body-1 font-weight-medium">
                {{ item.name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.code }}
              </div>
            </div>
          </div>
        </template>

        <template #item.members_count="{ item }">
          <VChip
            size="small"
            variant="tonal"
          >
            {{ item.members_count ?? 0 }} người
          </VChip>
        </template>

        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            label
          >
            {{ item.is_active ? 'Hoạt động' : 'Tắt' }}
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
        :title="editing ? 'Sửa nhóm kênh' : 'Thêm nhóm kênh'"
        :close-disabled="saving"
        @close="dialog = false"
      >
        <VCardText>
          <VForm @submit.prevent="save">
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="form.name"
                  label="Tên nhóm kênh"
                  placeholder="VD: Cụm Kênh Công Nghệ"
                  :error-messages="errors.name"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="form.code"
                  label="Mã (slug)"
                  placeholder="auto từ tên"
                  :error-messages="errors.code"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="form.color"
                  :items="colorOptions"
                  label="Màu nhận diện"
                  :error-messages="errors.color"
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
                <VSwitch
                  v-model="form.is_active"
                  label="Đang hoạt động"
                  color="primary"
                  hide-details
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
              {{ editing ? 'Lưu thay đổi' : 'Tạo nhóm' }}
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
