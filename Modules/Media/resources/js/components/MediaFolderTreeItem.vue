<!--
  =====================================================================
  CHỨC NĂNG FILE: Item đệ quy cho cây thư mục cột trái trang Media (hỗ trợ nhiều cấp).
  =====================================================================
  - Render từng nút thư mục `{id,name,count,children?}` theo độ sâu `depth`.
  - Bấm chevron để mở/thu nhánh con; bấm dòng để lọc/bỏ lọc thư mục.
  =====================================================================
-->
<script setup>
defineProps({
  node: {
    type: Object,
    required: true,
  },
  depth: {
    type: Number,
    default: 0,
  },
  activeFolder: {
    type: String,
    default: '',
  },
  expandedFolders: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['select', 'toggle'])

defineOptions({ name: 'MediaFolderTreeItem' })
</script>

<template>
  <div class="d-flex flex-column">
    <div
      class="nav-item rounded px-2 py-1 d-flex align-center justify-space-between gap-2 cursor-pointer"
      :class="{
        'nav-item--parent': depth === 0 && activeFolder === node.id,
        'nav-item--sub': depth > 0 && activeFolder === node.id,
      }"
      @click="emit('select', node)"
    >
      <div class="d-flex align-center flex-grow-1 media-truncate-wrap">
        <VIcon
          v-if="node.children?.length"
          :icon="expandedFolders[node.id] ? 'tabler-chevron-down' : 'tabler-chevron-right'"
          :size="depth === 0 ? 15 : 14"
          class="me-1 flex-shrink-0"
          @click.stop="emit('toggle', node)"
        />
        <VIcon
          :icon="activeFolder === node.id ? 'tabler-folder-open' : 'tabler-folder'"
          :size="depth === 0 ? 17 : 15"
          color="primary"
          class="me-2 flex-shrink-0"
        />
        <span
          class="text-body-1 text-truncate"
          :class="[
            depth === 0 ? 'text-high-emphasis font-weight-medium' : '',
            depth > 0 && activeFolder === node.id ? 'text-primary font-weight-medium' : '',
            depth > 0 && activeFolder !== node.id ? 'text-medium-emphasis' : '',
          ]"
        >
          {{ node.name }}
        </span>
      </div>
      <span class="text-caption text-medium-emphasis flex-shrink-0">
        {{ node.count }}
      </span>
    </div>

    <div
      v-if="node.children?.length && expandedFolders[node.id]"
      class="d-flex flex-column ms-5 gap-2 my-1"
    >
      <MediaFolderTreeItem
        v-for="sub in node.children"
        :key="sub.id"
        :node="sub"
        :depth="depth + 1"
        :active-folder="activeFolder"
        :expanded-folders="expandedFolders"
        @select="emit('select', $event)"
        @toggle="emit('toggle', $event)"
      />
    </div>
  </div>
</template>
