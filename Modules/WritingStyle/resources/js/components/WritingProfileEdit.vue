<!--
  =====================================================================
  CHỨC NĂNG FILE: Drawer Sửa DNA Văn phong — DEMO TĨNH.
  =====================================================================
  Sửa name/tier/status/enabled/style_instructions + rules_json theo tier;
  evidence_json collapsed chỉ đọc. Emit 'save' payload cho parent xử lý.
  Không gọi store/API.
  =====================================================================
-->
<script setup>
/* eslint-disable camelcase */
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  profile: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'save'])

const RULE_KEYS = ['tone', 'pronouns', 'emotion', 'opening', 'sentence_rhythm', 'paragraph_rhythm', 'transitions', 'vocabulary', 'technical_terms', 'structure_patterns', 'headings', 'bullets', 'examples', 'ending', 'avoid', 'uncertainties']
const LONG_ONLY = ['headings', 'bullets', 'paragraph_rhythm', 'structure_patterns']

const form = reactive({ name: '', dominant_tier: 'medium', status: 'draft', is_enabled: true, style_instructions: '', rules_json: {} })
const error = ref('')

const show = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})

const displayedRules = computed(() => {
  const t = form.dominant_tier
  if (t === 'micro') return RULE_KEYS.filter(k => !LONG_ONLY.includes(k))
  if (t === 'short') return RULE_KEYS.filter(k => !['headings', 'bullets', 'structure_patterns'].includes(k))
  
  return RULE_KEYS
})

const evidence = computed(() => props.profile?.evidence_json ?? [])

watch(() => props.profile, p => {
  if (!p) return
  form.name = p.name ?? ''
  form.dominant_tier = p.dominant_tier ?? 'medium'
  form.status = p.status ?? 'draft'
  form.is_enabled = p.is_enabled !== false
  form.style_instructions = p.style_instructions ?? ''
  form.rules_json = { ...(p.rules_json ?? {}) }
}, { immediate: true })

function save() {
  emit('save', { id: props.profile.id, ...form })
  show.value = false
}
</script>

<template>
  <VDialog
    v-model="show"
    max-width="900"
    scrollable
  >
    <VCard>
      <VCardItem>
        <template #title>
          Sửa DNA — {{ props.profile?.name }}
        </template>
      </VCardItem>
      <VCardText>
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ error }}
        </VAlert>
        <VRow dense>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.name"
              label="Tên văn phong"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="form.dominant_tier"
              :items="[{title:'1–3 câu',value:'micro'},{title:'4–10 câu',value:'short'},{title:'11–30 câu',value:'medium'},{title:'Bài dài',value:'long'}]"
              label="Cấp độ"
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
              label="Hướng dẫn hành văn bổ sung"
              rows="3"
            />
          </VCol>
          <VCol
            cols="12"
            class="d-flex align-center"
          >
            <VSwitch
              v-model="form.is_enabled"
              label="Đang bật"
              color="primary"
              hide-details
              inset
            />
          </VCol>
        </VRow>

        <VDivider class="my-4" />
        <div class="text-subtitle-1 font-weight-medium mb-3">
          Khóa DNA (theo cấp độ {{ form.dominant_tier }})
        </div>
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

        <VExpansionPanels
          v-if="evidence.length"
          class="mt-4"
          variant="accordion"
        >
          <VExpansionPanel>
            <VExpansionPanelTitle class="text-body-2">
              Bằng chứng trích dẫn ({{ evidence.length }}) — chỉ đọc
            </VExpansionPanelTitle>
            <VExpansionPanelText>
              <div
                v-for="(e, i) in evidence"
                :key="i"
                class="mb-3 pa-3 border rounded"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ e.rule_key }}
                </div>
                <div class="text-body-2 font-italic">
                  "{{ e.excerpt }}"
                </div>
                <div
                  v-if="e.note"
                  class="text-caption mt-1"
                >
                  {{ e.note }}
                </div>
              </div>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          @click="show = false"
        >
          Hủy
        </VBtn>
        <VBtn
          color="primary"
          @click="save"
        >
          Lưu DNA
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
