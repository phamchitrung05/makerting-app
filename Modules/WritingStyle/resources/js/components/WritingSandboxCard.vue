<!--
  =====================================================================
  CHỨC NĂNG FILE: Card "Phòng thử nghiệm DNA" — DEMO TĨNH.
  =====================================================================
  Chọn profile + chủ đề + độ dài -> emit 'run' {profile_id,topic,target_tier}
  cho parent trả kết quả giả. Không gọi API.
  =====================================================================
-->
<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  profiles: { type: Array, default: () => [] },
  initialProfileId: { type: [Number, String], default: null },
  result: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['run'])

const profileId = ref(props.initialProfileId)
const topic = ref('')
const targetTier = ref('medium')

const profileItems = computed(() =>
  props.profiles.filter(p => p.is_enabled).map(p => ({ title: p.name, value: p.id })),
)

const TIER_ITEMS = [
  { title: '1–3 câu (micro)', value: 'micro' },
  { title: '4–10 câu (short)', value: 'short' },
  { title: '11–30 câu (medium)', value: 'medium' },
  { title: 'Bài dài (long)', value: 'long' },
]

function run() {
  emit('run', { profile_id: profileId.value, topic: topic.value, target_tier: targetTier.value }) // eslint-disable-line camelcase
}
</script>

<template>
  <VCard>
    <VCardItem title="Phòng thử nghiệm DNA" />
    <VCardText>
      <div class="d-flex gap-4 mb-4 flex-wrap">
        <AppSelect
          v-model="profileId"
          :items="profileItems"
          label="Văn phong"
          placeholder="Chọn DNA"
          style="min-width:220px"
        />
        <AppSelect
          v-model="targetTier"
          :items="TIER_ITEMS"
          label="Độ dài"
          style="max-width:200px"
        />
      </div>
      <AppTextField
        v-model="topic"
        label="Chủ đề viết thử"
        placeholder="vd: lợi ích của cà phê đen buổi sáng"
        class="mb-4"
        @keyup.enter="run"
      />
      <VBtn
        color="primary"
        :loading="loading"
        :disabled="!profileId || (topic || '').trim().length < 3"
        prepend-icon="tabler-flask"
        @click="run"
      >
        Viết thử
      </VBtn>
      <div
        v-if="result"
        class="mt-6"
      >
        <div class="d-flex align-center gap-3 mb-2">
          <span class="text-h6">Kết quả</span>
          <VChip
            size="small"
            variant="tonal"
          >
            {{ result.target_tier }}
          </VChip>
        </div>
        <VCard
          variant="outlined"
          class="pa-4"
        >
          <pre class="text-body-2 whitespace-pre-wrap">{{ result.text }}</pre>
        </VCard>
      </div>
    </VCardText>
  </VCard>
</template>
