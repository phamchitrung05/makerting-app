<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang quản lý Vai trò & Phân quyền (Role + ma trận permission).
  =====================================================================
  Hiển thị danh sách role (VDataTableServer) + dialog sửa role với ma trận
  checkbox quyền được nhóm theo module từ permission matrix API.
  =====================================================================
-->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AppDialogLayout from '@/components/dialogs/AppDialogLayout.vue'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'
import { identityApi } from '../../services/identityApi'
import { fieldErrors, firstErrorMessage } from '@/utils/api'

definePage({ meta: { layout: 'default' } })

const roles = ref([])
const rolesLength = ref(0)
const loading = ref(false)
const search = ref('')
const options = ref({ page: 1, itemsPerPage: 10 })
const errors = ref({})
const snack = ref({ show: false, text: '', color: 'success' })

const matrix = ref({})
const dialog = ref(false)
const saving = ref(false)
const editing = ref(null)
const form = ref({ name: '', permissions: [] })

const headers = [
  { title: 'Vai trò', key: 'name' },
  { title: 'Số quyền', key: 'permissions', sortable: false },
  { title: 'Người dùng', key: 'users_count', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]

const moduleList = computed(() =>
  Object.entries(matrix.value).map(([key, mod]) => ({
    key,
    label: mod.label,
    permissions: Object.entries(mod.permissions || {}).map(([name, label]) => ({ name, label })),
  })),
)

async function loadRoles() {
  loading.value = true
  try {
    const res = await identityApi.roles({
      search: search.value,
      page: options.value.page,
      itemsPerPage: options.value.itemsPerPage,
    })

    roles.value = res.items ?? []
    rolesLength.value = res.itemsLength ?? 0
  } catch (e) {
    notify(firstErrorMessage(e), 'error')
  } finally {
    loading.value = false
  }
}

async function loadMatrix() {
  try {
    const res = await identityApi.permissionMatrix()

    matrix.value = res.modules ?? {}
  } catch (e) {
    notify(firstErrorMessage(e), 'error')
  }
}

function notify(text, color = 'success') {
  snack.value = { show: true, text, color }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', permissions: [] }
  errors.value = {}
  dialog.value = true
}

function openEdit(role) {
  editing.value = role
  form.value = { name: role.name, permissions: [...(role.permissions ?? [])] }
  errors.value = {}
  dialog.value = true
}

function toggle(perm) {
  const i = form.value.permissions.indexOf(perm)
  if (i === -1) form.value.permissions.push(perm)
  else form.value.permissions.splice(i, 1)
}

function checked(perm) {
  return form.value.permissions.includes(perm)
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const payload = { name: form.value.name, permissions: form.value.permissions }
    if (editing.value)
      await identityApi.updateRole(editing.value.id, payload)
    else
      await identityApi.createRole(payload)

    dialog.value = false
    notify('Đã lưu vai trò')
    loadRoles()
  } catch (e) {
    errors.value = fieldErrors(e)
    notify(firstErrorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function remove(role) {
  if (!confirm('Xóa vai trò ' + role.name + '?')) return
  try {
    await identityApi.deleteRole(role.id)
    notify('Đã xóa vai trò')
    loadRoles()
  } catch (e) {
    notify(firstErrorMessage(e), 'error')
  }
}

watch(options, loadRoles, { deep: true })
let searchTimer
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(loadRoles, 350)
})
onMounted(() => {
  loadRoles()
  loadMatrix()
})
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Vai trò &amp; Phân quyền
        </h4>
        <div class="text-body-1">
          Gán nhóm quyền cho nhân sự nội bộ
        </div>
      </div>
      <div class="d-flex gap-4 align-center flex-wrap">
        <AppTextField
          v-model="search"
          placeholder="Tìm vai trò..."
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
          Thêm vai trò
        </VBtn>
      </div>
    </div>

    <VCard>
      <VDataTableServer
        v-model:page="options.page"
        v-model:items-per-page="options.itemsPerPage"
        :headers="headers"
        :items="roles"
        :items-length="rolesLength"
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
              <VIcon icon="tabler-shield-lock" />
            </VAvatar>
            <span class="text-body-1 font-weight-medium">{{ item.name }}</span>
          </div>
        </template>

        <template #item.permissions="{ item }">
          <VChip
            size="small"
            variant="tonal"
          >
            {{ (item.permissions || []).length }} quyền
          </VChip>
        </template>

        <template #item.users_count="{ item }">
          {{ item.users_count ?? 0 }}
        </template>

        <template #item.actions="{ item }">
          <IconBtn
            size="small"
            @click="openEdit(item)"
          >
            <VIcon icon="tabler-edit" />
          </IconBtn>
          <IconBtn
            v-if="item.name !== 'super-admin'"
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
      max-width="720"
      scrollable
      :persistent="saving"
    >
      <AppDialogLayout
        :title="editing ? 'Sửa vai trò' : 'Thêm vai trò'"
        :close-disabled="saving"
        @close="dialog = false"
      >
        <VCardText>
          <AppTextField
            v-model="form.name"
            label="Tên vai trò"
            placeholder="vd: editor, reviewer"
            :error-messages="errors.name"
            class="mb-6"
          />

          <div class="text-subtitle-1 font-weight-medium mb-3">
            Quyền hạn
          </div>
          <div
            v-for="mod in moduleList"
            :key="mod.key"
            class="mb-5"
          >
            <div class="text-body-1 font-weight-medium mb-2">
              {{ mod.label }}
            </div>
            <VRow dense>
              <VCol
                v-for="perm in mod.permissions"
                :key="perm.name"
                cols="12"
                sm="6"
                md="4"
              >
                <VCheckbox
                  :model-value="checked(perm.name)"
                  :label="perm.label"
                  density="compact"
                  hide-details
                  @update:model-value="toggle(perm.name)"
                />
              </VCol>
            </VRow>
            <VDivider class="mt-3" />
          </div>
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
              {{ editing ? 'Lưu thay đổi' : 'Tạo vai trò' }}
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
