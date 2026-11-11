<!--
  =====================================================================
  CHỨC NĂNG FILE: Form nhập brief cho bài viết trong sandbox V3.
  =====================================================================

  Form trong Phòng thử DNA V3; trang edit chuyển dữ liệu phẳng từ form
  thành WritingBrief trước khi gửi API. Chưa validate nghiệp vụ hoặc lưu ở đây.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - Không có hàm nghiệp vụ cục bộ.
  - Computed: Không có.
  - Watcher/lifecycle: Không có.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : modelValue: Object bắt buộc, chứa fields brief dạng phẳng.
  - OUTPUT: Sửa các trường trên object v-model của cha; defineModel khai báo update:modelValue. Không gọi API.
  =====================================================================
-->
<script setup>
const brief = defineModel({ type: Object, required: true })

const types = [
  { value: 'sales_post', title: 'Bài bán hàng' },
  { value: 'announcement', title: 'Thông báo' },
  { value: 'storytelling', title: 'Kể chuyện' },
  { value: 'technical_article', title: 'Bài kỹ thuật' },
  { value: 'blog', title: 'Blog' },
]
const formatOptions = [
  { title: 'Theo hồ sơ', value: null },
  { title: 'Cho phép', value: true },
  { title: 'Không dùng', value: false },
]
</script>

<template>
  <VExpansionPanels class="mb-4">
    <VExpansionPanel title="Yêu cầu bài viết (Brief)">
      <VExpansionPanelText>
        <VRow>
          <VCol cols="12" md="6">
            <AppSelect v-model="brief.contentType" :items="types" label="Loại bài" clearable />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField v-model="brief.platform" label="Kênh đăng" placeholder="Facebook, blog…" />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField v-model="brief.audience" label="Người đọc" />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField v-model="brief.objective" label="Mục tiêu bài viết" />
          </VCol>
          <VCol cols="12">
            <AppTextarea v-model="brief.facts" label="Dữ kiện được cung cấp" placeholder="Mỗi dòng một dữ kiện, ví dụ giá, địa chỉ, chất liệu" rows="3" />
          </VCol>
          <VCol cols="12">
            <AppTextarea v-model="brief.requiredDetails" label="Chi tiết bắt buộc xuất hiện" placeholder="Mỗi dòng một chi tiết có trong chủ đề, dữ kiện, ưu đãi hoặc CTA" rows="2" />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField v-model="brief.offer" label="Ưu đãi và điều kiện" />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField v-model="brief.cta" label="Lời kêu gọi hành động (CTA)" />
          </VCol>
          <VCol cols="12" md="6">
            <AppSelect v-model="brief.headings" :items="formatOptions" label="Tiêu đề mục" />
          </VCol>
          <VCol cols="12" md="6">
            <AppSelect v-model="brief.bullets" :items="formatOptions" label="Danh sách gạch đầu dòng" />
          </VCol>
          <VCol cols="12" md="4">
            <AppSelect v-model="brief.lengthUnit" :items="[{ title: 'Từ', value: 'words' }, { title: 'Câu', value: 'sentences' }]" label="Độ dài tùy chỉnh" clearable />
          </VCol>
          <VCol v-if="brief.lengthUnit" cols="6" md="4">
            <AppTextField v-model="brief.lengthMin" label="Tối thiểu" type="number" min="1" />
          </VCol>
          <VCol v-if="brief.lengthUnit" cols="6" md="4">
            <AppTextField v-model="brief.lengthMax" label="Tối đa" type="number" min="1" />
          </VCol>
          <VCol cols="12">
            <AppTextarea v-model="brief.constraints" label="Yêu cầu thêm" placeholder="Mỗi dòng một yêu cầu" rows="2" />
          </VCol>
        </VRow>
      </VExpansionPanelText>
    </VExpansionPanel>
  </VExpansionPanels>
</template>
