<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang Tạo DNA Văn phong thủ công (origin=manual).
  =====================================================================
  Form đầy đủ: name + dominant_tier + status + is_enabled + style_instructions
  + 16 RULE_KEYS theo cấp độ chọn + gợi ý mở dialog giải mã từ bài mẫu.
  Hiện GIAO DIỆN TĨNH — save chỉ console.log + điều hướng về list.

  CHUẨN UI (docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §2–3):
  - Header trang text-h4 + nút quay lại (§3.5 trang Add/Edit).
  - VCard/VBtn/App* PascalCase; AppTextField/AppSelect/AppTextarea + label.
  - Icon Tabler (tabler-*), màu theme Vuetify — không hex cứng, không mdi.
  - Spacing logical me/ms; không inline font-size / !important.

  CÁC HÀM/METHOD TRONG FILE:
  - save(): demo tĩnh — log payload + snackbar + điều hướng về list.
  INPUT/OUTPUT: route /admin/writing-style/writing-profiles/create.
  SIDE EFFECT : console.log + router.push về list (demo).
  =====================================================================
-->
<script setup>
/* eslint-disable camelcase */
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

definePage({ meta: { layout: 'default' } })

const router = useRouter()

const RULE_KEYS = ['tone', 'pronouns', 'emotion', 'opening', 'sentence_rhythm', 'paragraph_rhythm', 'transitions', 'vocabulary', 'technical_terms', 'structure_patterns', 'headings', 'bullets', 'examples', 'ending', 'avoid', 'uncertainties']
const LONG_ONLY = ['headings', 'bullets', 'paragraph_rhythm', 'structure_patterns']

const form = reactive({
  name: '',
  dominant_tier: 'medium',
  status: 'draft',
  is_enabled: true,
  style_instructions: '',
  rules_json: Object.fromEntries(RULE_KEYS.map(k => [k, ''])),
})

const displayedRules = computed(() => {
  const t = form.dominant_tier
  if (t === 'micro') return RULE_KEYS.filter(k => !LONG_ONLY.includes(k))
  if (t === 'short') return RULE_KEYS.filter(k => !['headings', 'bullets', 'structure_patterns'].includes(k))

  return RULE_KEYS
})

const TIER_ITEMS = [
  { title: '1–3 câu (micro)', value: 'micro' },
  { title: '4–10 câu (short)', value: 'short' },
  { title: '11–30 câu (medium)', value: 'medium' },
  { title: 'Bài dài (long)', value: 'long' },
]

const snackbar = ref(false)
const snackMsg = ref('')

function save() {
  // DEMO tĩnh — chưa nối API; log payload rồi quay về list.
  console.log('create profile', JSON.parse(JSON.stringify(form)))
  snackMsg.value = 'Đã lưu DNA (demo) — quay về danh sách'
  snackbar.value = true
  setTimeout(() => router.push({ name: 'admin-writing-style-writing-profiles-list' }), 800)
}
</script>

<template>
  <div>
    <!-- ================= HEADER TRANG ================= -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 align-center mb-6">
      <div class="d-flex align-center gap-3">
        <IconBtn
          variant="tonal"
          color="secondary"
          :to="{ name: 'admin-writing-style-writing-profiles-list' }"
        >
          <VIcon icon="tabler-arrow-left" />
        </IconBtn>
        <div class="d-flex flex-column justify-center">
          <h4 class="text-h4 font-weight-medium mb-1">
            Tạo DNA Văn phong mới
          </h4>
          <div class="text-body-2 text-medium-emphasis">
            Điền thủ công 16 khía cạnh văn phong, hoặc "Giải mã từ bài mẫu" để AI tự bóc tách.
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 align-center">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-wand"
        >
          Giải mã từ bài mẫu
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :disabled="!form.name.trim()"
          @click="save"
        >
          Tạo DNA
        </VBtn>
      </div>
    </div>

    <!-- ================= CARD THÔNG TIN CƠ BẢN ================= -->
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle>Thông tin cơ bản</VCardTitle>
        <VCardSubtitle>Tên, cấp độ độ dài và trạng thái của DNA văn phong</VCardSubtitle>
      </VCardItem>
      <VCardText>
        <VRow dense>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.name"
              label="Tên văn phong"
              placeholder="vd: Giọng Chuyên gia SEO"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="form.dominant_tier"
              :items="TIER_ITEMS"
              label="Cấp độ độ dài"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="form.status"
              :items="[{title:'Nháp',value:'draft'},{title:'Hoạt động',value:'active'}]"
              label="Trạng thái"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.style_instructions"
              label="Hướng dẫn hành văn bổ sung (tùy chọn)"
              placeholder="vd: luôn mở bằng câu hỏi, tránh từ nối cứng..."
              rows="3"
            />
          </VCol>
          <VCol
            cols="12"
            class="d-flex align-center"
          >
            <VSwitch
              v-model="form.is_enabled"
              label="Bật ngay sau khi tạo"
              color="primary"
              hide-details
              inset
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- ================= CARD 16 KHÓA DNA ================= -->
    <VCard>
      <VCardItem>
        <VCardTitle>Khóa DNA ({{ displayedRules.length }}/16)</VCardTitle>
        <VCardSubtitle>Các khía cạnh áp dụng theo cấp độ độ dài "{{ form.dominant_tier }}"</VCardSubtitle>
        <template #append>
          <VChip
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ form.dominant_tier }}
          </VChip>
        </template>
      </VCardItem>
      <VCardText>
        <VRow dense>
          <VCol
            v-for="k in displayedRules"
            :key="k"
            cols="12"
            md="6"
          >
            <AppTextarea
              v-model="form.rules_json[k]"
              :label="k"
              rows="2"
              density="compact"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VBtn
          variant="tonal"
          color="secondary"
          :to="{ name: 'admin-writing-style-writing-profiles-list' }"
        >
          Hủy
        </VBtn>
        <VSpacer />
        <VBtn
          color="primary"
          :disabled="!form.name.trim()"
          @click="save"
        >
          Tạo DNA
        </VBtn>
      </VCardActions>
    </VCard>

    <VSnackbar
      v-model="snackbar"
      :timeout="3000"
      color="success"
      location="bottom end"
    >
      {{ snackMsg }}
    </VSnackbar>
  </div>
</template>
