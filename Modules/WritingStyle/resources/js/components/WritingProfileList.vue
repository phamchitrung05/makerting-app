<!--
  =====================================================================
  CHỨC NĂNG FILE: Bảng danh sách DNA Văn phong — GIAO DIỆN TĨNH.
  =====================================================================
  Nhận profiles qua props; emit edit/delete/sandbox/reverse cho parent.
  Không gọi store/API — dùng cho preview tĩnh Task 10.
  =====================================================================
-->
<script setup>
defineProps({
  profiles: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['edit', 'delete', 'sandbox', 'reverse'])

const TIER_LABEL = { micro: '1–3 câu', short: '4–10 câu', medium: '11–30 câu', long: 'Bài dài' }
const tierLabel = t => TIER_LABEL[t] ?? t
</script>

<template>
  <VCard>
    <VCardItem>
      <template #title>
        <div class="d-flex align-center justify-space-between flex-wrap gap-3">
          <span>DNA Văn phong ({{ profiles.length }})</span>
          <VBtn
            color="primary"
            prepend-icon="tabler-scan"
            @click="emit('reverse')"
          >
            Giải mã từ bài mẫu
          </VBtn>
        </div>
      </template>
    </VCardItem>
    <VCardText class="pa-0">
      <VTable v-if="profiles.length">
        <thead>
          <tr>
            <th>Tên văn phong</th>
            <th>Cấp độ</th>
            <th>Nguồn</th>
            <th>Trạng thái</th>
            <th>Bật</th>
            <th class="text-end">
              Thao tác
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="p in profiles"
            :key="p.id"
          >
            <td class="font-weight-medium">
              {{ p.name }}
            </td>
            <td>
              <VChip
                size="small"
                variant="tonal"
              >
                {{ tierLabel(p.dominant_tier) }}
              </VChip>
            </td>
            <td>
              <VChip
                size="small"
                :color="p.origin === 'reference' ? 'info' : 'secondary'"
                variant="tonal"
              >
                {{ p.origin === 'reference' ? 'Giải mã' : 'Thủ công' }}
              </VChip>
            </td>
            <td>
              <VChip
                size="small"
                :color="p.status === 'active' ? 'success' : 'warning'"
                variant="tonal"
              >
                {{ p.status }}
              </VChip>
            </td>
            <td>
              <VIcon
                :icon="p.is_enabled ? 'tabler-check' : 'tabler-x'"
                :color="p.is_enabled ? 'success' : 'error'"
                size="20"
              />
            </td>
            <td class="text-end">
              <VBtn
                icon="tabler-flask"
                size="small"
                variant="text"
                title="Viết thử DNA"
                @click="emit('sandbox', p)"
              />
              <VBtn
                icon="tabler-edit"
                size="small"
                variant="text"
                title="Sửa"
                @click="emit('edit', p)"
              />
              <VBtn
                icon="tabler-trash"
                size="small"
                variant="text"
                color="error"
                title="Xóa"
                @click="emit('delete', p)"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
      <div
        v-else
        class="pa-8 text-center text-medium-emphasis"
      >
        Chưa có văn phong nào. Bấm "Giải mã từ bài mẫu" để bắt đầu.
      </div>
    </VCardText>
  </VCard>
</template>
