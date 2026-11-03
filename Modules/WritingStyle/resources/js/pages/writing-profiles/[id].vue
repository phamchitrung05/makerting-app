<!--
  =====================================================================
  CHỨC NĂNG FILE: Trang Chỉnh sửa Writing Profile (route edit).
  =====================================================================
  Route: /admin/writing-style/writing-profiles/:id
  Tự nạp qua file-based routing — tên 'admin-writing-style-writing-profiles-id'.

  Chuẩn hoá theo docs/architecture/VUEXY_LARAVEL_ARCHITECTURE_GUIDE.md §3.5
  (trang Add/Edit theo mẫu product/add): PascalCase V*/App*, icon tabler-*,
  màu theme, VCard mặc định (không border/elevation-0/rounded-xl), field
  App* + label prop, spacing logical me/ms, không inline font-size.

  CÁC HÀM/METHOD TRONG FILE:
  - saveProfile()   : lưu Writing Profile (demo — nối API sau).
  - prev/nextQuote  : điều hướng carousel ví dụ văn phong.
  - addSampleQuote  : thêm 1 mẫu trích dẫn mới.
  INPUT/OUTPUT: route.params.id -> profile đang sửa (nạp sau).
  SIDE EFFECT: chưa gọi API (demo tĩnh).
  =====================================================================
-->
<script setup>
import { reactive, ref } from 'vue'

definePage({ meta: { layout: 'default' } })

/* Tab mặc định mở sẵn là "Đặc điểm văn phong" giống ảnh mẫu. */
const activeTab = ref('style-traits')

/* Thông tin trên Banner Profile. */
const profileData = reactive({
  name: 'Brand Voice – Mộc Cà Phê',
  version: 'v1.4',
  isActive: true,
  shortDesc: 'Văn phong gần gũi, ấm áp, truyền cảm hứng về cà phê và nhịp sống chậm.',
})

/* 8 Tabs điều hướng ngang. */
const profileTabs = [
  { value: 'overview', label: 'Tổng quan', icon: 'tabler-file-text' },
  { value: 'style-traits', label: 'Đặc điểm văn phong', icon: 'tabler-adjustments-horizontal' },
  { value: 'content-structure', label: 'Cấu trúc nội dung', icon: 'tabler-layout-grid' },
  { value: 'language-rules', label: 'Quy tắc ngôn ngữ', icon: 'tabler-message-2' },
  { value: 'content-samples', label: 'Mẫu nội dung', icon: 'tabler-files' },
  { value: 'vocab-cta', label: 'Từ vựng & CTA', icon: 'tabler-heart' },
  { value: 'advanced', label: 'Cài đặt nâng cao', icon: 'tabler-settings' },
  { value: 'history', label: 'Lịch sử', icon: 'tabler-history' },
]

/* ---------------- DỮ LIỆU TAB 2: ĐẶC ĐIỂM VĂN PHONG ---------------- */
const traitSummaryList = [
  { title: 'Giọng điệu', value: 'Thân thiện, ấm áp', icon: 'tabler-microphone', color: 'warning' },
  { title: 'Cảm xúc', value: 'Tích cực, truyền cảm hứng', icon: 'tabler-heart', color: 'error' },
  { title: 'Tính chuyên nghiệp', value: 'Chuyên nghiệp vừa phải', icon: 'tabler-briefcase', color: 'primary' },
  { title: 'Cá tính thương hiệu', value: 'Gần gũi, chân thành', icon: 'tabler-leaf', color: 'success' },
  { title: 'Mức độ hài hước', value: 'Vừa phải', icon: 'tabler-mood-smile', color: 'warning' },
]

const traitDetailText = ref(
  'Văn phong của Mộc Cà Phê mang hơi thở của sự ấm áp, gần gũi và tích cực.\nChúng tôi không chỉ bán cà phê, mà còn chia sẻ những khoảnh khắc đẹp trong cuộc sống, truyền cảm hứng về lối sống chậm và tận hưởng hiện tại.',
)

const expressionLevel = ref(85)

/* Carousel Ví dụ văn phong. */
const currentQuoteIdx = ref(0)

const sampleQuotes = ref([
  {
    text: 'Một ly cà phê, một chút bình yên... Đôi khi, hạnh phúc chỉ đơn giản là được thưởng thức những điều nhỏ bé trong cuộc sống.',
    image: 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=300',
    tags: ['Truyền cảm hứng', 'Gần gũi', 'Tích cực'],
  },
  {
    text: 'Giữa phố thị vội vã, mời bạn ghé hiên nhà Mộc ngồi lại đôi chút, nghe một bản nhạc dịu êm và nhấp ngụm cà phê rang mộc thơm nồng.',
    image: 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=300',
    tags: ['Sống chậm', 'Ấm áp', 'Thư giãn'],
  },
  {
    text: 'Mỗi hạt cà phê đều mang trong mình câu chuyện của nắng gió cao nguyên và bàn tay chăm chút của người nông dân Việt.',
    image: 'https://images.unsplash.com/photo-1447933601403-0c6688de566e?w=300',
    tags: ['Chân thành', 'Kể chuyện', 'Cà phê Việt'],
  },
  {
    text: 'Sáng đầu tuần sẽ dịu dàng hơn thật nhiều khi trên bàn làm việc có thêm hương thơm quen thuộc từ ly Latte nóng hổi.',
    image: 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=300',
    tags: ['Năng lượng sáng', 'Gần gũi', 'Tích cực'],
  },
])

const prevQuote = () => {
  currentQuoteIdx.value =
    (currentQuoteIdx.value - 1 + sampleQuotes.value.length) % sampleQuotes.value.length
}

const nextQuote = () => {
  currentQuoteIdx.value = (currentQuoteIdx.value + 1) % sampleQuotes.value.length
}

const addSampleQuote = () => {
  sampleQuotes.value.push({
    text: 'Chiều mưa lành lạnh, chẳng gì tuyệt hơn một góc quán quen và tách Cappuccino ấm sực lòng.',
    image: 'https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?w=300',
    tags: ['Ấm áp', 'Chiều mưa', 'Mộc Cà Phê'],
  })
  currentQuoteIdx.value = sampleQuotes.value.length - 1
}

/* Lưới 8 Kênh Tùy chọn hiển thị. */
const displayChannels = ref([
  { name: 'Facebook', icon: 'tabler-brand-facebook', color: '#1877F2', enabled: true },
  { name: 'YouTube', icon: 'tabler-brand-youtube', color: '#FF0000', enabled: true },
  { name: 'TikTok', icon: 'tabler-brand-tiktok', color: '#18181b', enabled: true },
  { name: 'Blog', icon: 'tabler-pencil', color: '#7367F0', enabled: true },
  { name: 'Instagram', icon: 'tabler-brand-instagram', color: '#E4405F', enabled: true },
  { name: 'Website', icon: 'tabler-world', color: '#6366f1', enabled: true },
  { name: 'Email', icon: 'tabler-mail', color: '#6366f1', enabled: false },
  { name: 'Khác', icon: 'tabler-dots', color: '#4b5563', enabled: false },
])

/* Tab 1: Tổng quan. */
const overviewForm = reactive({
  industry: 'F&B – Chuỗi Cà phê & Đồ uống',
  primaryGoal: 'Kết nối cảm xúc & Xây dựng cộng đồng',
  tags: ['Cà phê', 'Lifestyle', 'Truyền cảm hứng', 'Sống chậm', 'Mộc Cà Phê'],
  targetAudience:
    'Nhân viên văn phòng, freelancer và người trẻ (22–35 tuổi) yêu thích không gian cà phê yên tĩnh, trân trọng giá trị nguyên bản và tìm kiếm sự cân bằng trong cuộc sống.',
  brandPillars: [
    { title: 'Sự Ấm Áp & Hiếu Khách', desc: 'Luôn chào đón người đọc như một người bạn thân ghé chơi nhà.', icon: 'tabler-coffee', color: 'warning' },
    { title: 'Nhịp Sống Chậm (Slow Living)', desc: 'Khuyến khích tận hưởng từng khoảnh khắc hiện tại.', icon: 'tabler-leaf', color: 'success' },
    { title: 'Chất Lượng Nguyên Bản', desc: 'Tôn vinh hương vị tự nhiên của hạt cà phê rang mộc.', icon: 'tabler-wand', color: 'primary' },
  ],
})

/* Tab 3: Cấu trúc nội dung. */
const structureSections = [
  {
    title: 'Mở bài: Khơi gợi khung cảnh & Cảm xúc',
    weight: '20% bài viết',
    sentences: '1 – 2 câu ngắn',
    guideline: 'Bắt đầu bằng hình ảnh thời tiết, âm thanh hoặc hương thơm cà phê quen thuộc để thu hút sự đồng cảm.',
    sample: 'Sáng nay trời trở gió se lạnh, còn gì tuyệt hơn được ủ ấm đôi bàn tay bên tách cà phê vừa pha nóng hổi...',
  },
  {
    title: 'Thân bài: Câu chuyện nhỏ & Giá trị sản phẩm',
    weight: '60% bài viết',
    sentences: '3 – 5 câu vừa phải',
    guideline: 'Lồng ghép khéo léo hương vị đồ uống vào câu chuyện đời sống. Chia đoạn thoáng mắt.',
    sample: 'Vị đắng dịu nhẹ hòa quyện cùng lớp kem sữa béo ngậy của Latte hạt dẻ giúp đánh thức mọi giác quan một cách êm dịu nhất.',
  },
  {
    title: 'Kết bài: Lời mời ghé quán thân tình (Soft CTA)',
    weight: '20% bài viết',
    sentences: '1 – 2 câu kết',
    guideline: 'Không thúc ép mua hàng; luôn kết bằng lời chúc ngày mới tốt lành hoặc lời hẹn gặp tại quán.',
    sample: 'Nếu hôm nay cần một góc nhỏ bình yên để chạy nốt công việc, Mộc luôn dành sẵn bàn cạnh cửa sổ chờ bạn ghé qua nhé! ☕🌿',
  },
]

/* Tab 4: Quy tắc ngôn ngữ. */
const languageConfig = reactive({
  brandPronoun: 'Mình / Tụi mình / Mộc',
  customerPronoun: 'Bạn / Cả nhà / Người thương',
  emojiDensity: 'Vừa phải (2–3 emoji ấm áp mỗi đoạn: ☕🌿✨)',
  editorialRules: [
    { text: 'Ưu tiên các tính từ gợi cảm giác ấm cúng, mộc mạc, dịu dàng', allowed: true },
    { text: 'Ngắt dòng sau mỗi 2–3 câu để dễ đọc trên thiết bị di động', allowed: true },
    { text: 'Sử dụng ngôn từ giật gân, viết hoa toàn bộ câu để ép mua hàng', allowed: false },
    { text: 'Dùng từ lóng thiếu chuẩn mực hoặc chê bai đối thủ cạnh tranh', allowed: false },
  ],
})

/* Tab 5: Mẫu nội dung. */
const fullPostSamples = [
  {
    channel: 'Facebook Fanpage',
    date: '30/10/2026',
    title: 'Góc nhỏ bình yên cho ngày mưa đầu mùa',
    content: `Ngoài hiên mưa cứ rơi rả rích, còn bên trong Mộc lại ngập tràn mùi hương cà phê mới xay ấm sực. ☕🌧️\n\nĐôi khi giữa một tuần làm việc bận rộn, điều chúng mình cần chỉ là một khoảng lặng nhỏ để thở sâu và lắng nghe chính mình.\n\nGhé Mộc trú mưa và thưởng thức ly Cacao nóng bạn nhé!`,
  },
  {
    channel: 'Instagram Post',
    date: '28/10/2026',
    title: 'Hương vị mùa thu trong tách Trà Đào Cam Sả',
    content: `Chút thanh mát của cam vàng, vị ngọt dịu từ đào ngâm giòn sần sật hòa cùng hương sả ấm nồng. 🍑🍊\n\nMột thức uống vừa đủ dịu dàng để chiều lòng những tâm hồn đang tìm kiếm chút nắng ấm ngày thu.`,
  },
  {
    channel: 'TikTok / Reels Script',
    date: '25/10/2026',
    title: 'Kịch bản: 30 giây pha chế Cold Brew Cam Vàng',
    content: `[0-5s]: Cận cảnh đá viên rơi vào ly thủy tinh, tiếng ASMR sống động.\n[5-18s]: Rót dòng Cold Brew ủ lạnh 18 tiếng hòa quyện cùng nước cam tươi.\n[18-30s]: Đặt ly nước bên khung cửa sổ ngập nắng + Voiceover ấm áp: "Nạp chút tươi mới cho buổi sáng tỉnh táo cùng Mộc nhé!"`,
  },
]

/* Tab 6: Từ vựng & CTA. */
const vocabConfig = reactive({
  powerWords: ['bình yên', 'ấm áp', 'rang mộc', 'khoảnh khắc', 'tận hưởng', 'dịu dàng', 'thơm nồng', 'nhịp sống chậm', 'hiên nhà'],
  negativeWords: ['xả kho', 'rẻ vô địch', 'mua ngay kẻo hết', 'cam kết 100%', 'hàng loạt'],
  ctaList: [
    { type: 'CTA Tương tác nhẹ nhàng', text: 'Sáng nay của bạn bắt đầu bằng thức uống gì thế? Kể cho Mộc nghe với nhé!' },
    { type: 'CTA Mời ghé trải nghiệm tại quán', text: 'Mộc vẫn mở cửa từ 7h sáng với nhạc nhẹ và bánh thơm, hẹn gặp bạn chiều nay nha!' },
    { type: 'CTA Đặt hàng giao tận nơi', text: 'Nhắn nhẹ cho Mộc để tụi mình gửi ngay một ly cà phê ấm nóng tới bàn làm việc của bạn nhé!' },
  ],
})

/* Tab 7: Cài đặt nâng cao. */
const advancedConfig = reactive({
  systemPrompt: `BẠN LÀ NGƯỜI KỂ CHUYỆN CỦA THƯƠNG HIỆU "MỘC CÀ PHÊ".
- Giọng điệu: Thân thiện (95%), Tích cực (90%), Gần gũi (89%), Truyền cảm hứng (88%).
- Xưng hô: Xưng "Mộc" hoặc "chúng mình", gọi người đọc là "bạn".
- Phong cách: Mang hơi thở ấm áp, chia sẻ khoảnh khắc đẹp và truyền cảm hứng về lối sống chậm.`,
  preferredModel: 'Claude 3.5 Sonnet (Khuyên dùng cho văn chương)',
  temperature: 0.75,
  autoScoreCheck: true,
})

/* Tab 8: Lịch sử phiên bản. */
const versionHistory = [
  { version: 'v1.4', summary: 'Cập nhật biểu đồ DNA 6 trục & thêm kênh TikTok, Blog', date: '30/10/2026 14:20', author: 'Phạm Chí Trung', current: true },
  { version: 'v1.3', summary: 'Tinh chỉnh mức độ thể hiện lên 85% và bổ sung 4 ví dụ văn phong', date: '22/10/2026 09:15', author: 'Phạm Chí Trung', current: false },
  { version: 'v1.2', summary: 'Bổ sung từ điển từ vựng khuyến khích & mẫu câu CTA mềm mại', date: '10/10/2026 16:40', author: 'Phạm Chí Trung', current: false },
  { version: 'v1.0', summary: 'Khởi tạo hồ sơ Brand Voice – Mộc Cà Phê lần đầu', date: '01/10/2026 08:00', author: 'Phạm Chí Trung', current: false },
]

const saveProfile = () => {
  console.log('Đã lưu Writing Profile:', profileData)
}
</script>

<template>
  <div>
    <!-- ================= 1. HEADER TRANG ================= -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium">
          Chỉnh sửa Writing Profile
        </h4>
        <div class="text-body-1">
          Cập nhật thông tin, đặc điểm và quy tắc văn phong cho thương hiệu của bạn
        </div>
      </div>

      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          color="primary"
          prepend-icon="tabler-check"
          @click="saveProfile"
        >
          Lưu thay đổi
        </VBtn>
      </div>
    </div>

    <!-- ================= 2. BANNER HỒ SƠ + THANH TABS ================= -->
    <VCard class="mb-6">
      <VCardText>
        <VRow class="align-center">
          <VCol
            cols="12"
            class="d-flex align-center gap-x-4"
          >
            <!-- Avatar + nút sửa nhỏ -->
            <div class="position-relative">
              <VAvatar
                size="72"
                rounded
                variant="tonal"
                color="warning"
              >
                <VImg
                  src="https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=200"
                  cover
                />
              </VAvatar>
              <VBtn
                icon="tabler-pencil"
                size="24"
                color="primary"
                class="avatar-edit-btn text-white"
                elevation="2"
              />
            </div>

            <div>
              <div class="d-flex align-center flex-wrap gap-x-2 mb-1">
                <h5 class="text-h5 font-weight-medium mb-0">
                  {{ profileData.name }}
                </h5>
                <VChip
                  size="small"
                  color="primary"
                  variant="tonal"
                  label
                >
                  {{ profileData.version }}
                </VChip>
                <VChip
                  size="small"
                  :color="profileData.isActive ? 'success' : 'secondary'"
                  variant="tonal"
                  label
                >
                  {{ profileData.isActive ? 'Đang sử dụng' : 'Tạm dừng' }}
                </VChip>
                <VSwitch
                  v-model="profileData.isActive"
                  color="primary"
                  density="compact"
                  hide-details
                  inset
                  class="ms-1"
                />
              </div>
              <div class="text-body-2 text-medium-emphasis">
                {{ profileData.shortDesc }}
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VTabs
        v-model="activeTab"
        show-arrows
        class="px-4"
      >
        <VTab
          v-for="tab in profileTabs"
          :key="tab.value"
          :value="tab.value"
        >
          <VIcon
            :icon="tab.icon"
            size="18"
            class="me-2"
          />
          {{ tab.label }}
        </VTab>
      </VTabs>
    </VCard>

    <!-- ================= 3. NỘI DUNG 8 TABS ================= -->
    <VWindow v-model="activeTab">
      <!-- TAB 1: TỔNG QUAN -->
      <VWindowItem value="overview">
        <VRow>
          <VCol
            cols="12"
            lg="7"
          >
            <VCard
              title="Thông tin cơ bản của Writing Profile"
              subtitle="Thiết lập tên gọi, phiên bản, ngành hàng và thông điệp định vị"
            >
              <VCardText>
                <VRow>
                  <VCol
                    cols="12"
                    sm="8"
                  >
                    <AppTextField
                      v-model="profileData.name"
                      label="Tên Writing Profile *"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="4"
                  >
                    <AppTextField
                      v-model="profileData.version"
                      label="Phiên bản"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="overviewForm.industry"
                      :items="['F&B – Chuỗi Cà phê & Đồ uống', 'Du lịch & Nghỉ dưỡng', 'Bán lẻ & Thời trang', 'Công nghệ & SaaS']"
                      label="Ngành hàng / Lĩnh vực"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppSelect
                      v-model="overviewForm.primaryGoal"
                      :items="['Kết nối cảm xúc & Xây dựng cộng đồng', 'Thúc đẩy doanh số & Khuyến mãi', 'Chia sẻ kiến thức chuyên môn']"
                      label="Mục tiêu truyền thông chính"
                    />
                  </VCol>
                  <VCol cols="12">
                    <AppTextarea
                      v-model="profileData.shortDesc"
                      label="Mô tả ngắn gọn"
                      rows="2"
                    />
                  </VCol>
                  <VCol cols="12">
                    <VLabel class="mb-2 text-body-2">
                      Thẻ phân loại (Profile Tags)
                    </VLabel>
                    <div class="d-flex flex-wrap gap-2">
                      <VChip
                        v-for="tag in overviewForm.tags"
                        :key="tag"
                        size="small"
                        color="primary"
                        variant="tonal"
                        closable
                      >
                        {{ tag }}
                      </VChip>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>

          <VCol
            cols="12"
            lg="5"
          >
            <VCard
              title="Chân dung Độc giả & Giá trị cốt lõi"
              subtitle="Đối tượng khách hàng mà văn phong này hướng tới"
            >
              <VCardText>
                <AppTextarea
                  v-model="overviewForm.targetAudience"
                  label="Độc giả mục tiêu (Target Persona)"
                  rows="3"
                  class="mb-4"
                />
                <VLabel class="mb-2 text-body-2">
                  3 Trụ cột cảm xúc thương hiệu
                </VLabel>
                <div class="d-flex flex-column gap-3">
                  <div
                    v-for="pillar in overviewForm.brandPillars"
                    :key="pillar.title"
                    class="pa-3 rounded border d-flex align-center"
                  >
                    <VAvatar
                      :color="pillar.color"
                      variant="tonal"
                      size="36"
                      rounded
                      class="me-3"
                    >
                      <VIcon
                        :icon="pillar.icon"
                        size="20"
                      />
                    </VAvatar>
                    <div>
                      <div class="text-body-2 font-weight-medium text-high-emphasis">
                        {{ pillar.title }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ pillar.desc }}
                      </div>
                    </div>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VWindowItem>

      <!-- TAB 2: ĐẶC ĐIỂM VĂN PHONG -->
      <VWindowItem value="style-traits">
        <VRow>
          <!-- Tổng quan đặc điểm: Radar + summary -->
          <VCol
            cols="12"
            lg="6"
          >
            <VCard
              title="Tổng quan đặc điểm"
              subtitle="DNA văn phong của bạn"
              class="mb-6"
            >
              <VCardText>
                <VRow class="align-center">
                  <VCol
                    cols="12"
                    sm="7"
                    class="d-flex justify-center"
                  >
                    <svg
                      viewBox="0 0 320 265"
                      class="radar-chart"
                    >
                      <polygon
                        points="160,35 247,85 247,185 160,235 73,185 73,85"
                        class="radar-ring radar-ring--outer"
                      />
                      <polygon
                        points="160,68 218,101 218,168 160,202 102,168 102,101"
                        class="radar-ring"
                      />
                      <polygon
                        points="160,101 189,118 189,151 160,168 131,151 131,118"
                        class="radar-ring radar-ring--inner"
                      />
                      <line
                        x1="160"
                        y1="135"
                        x2="160"
                        y2="35"
                        class="radar-axis"
                      />
                      <line
                        x1="160"
                        y1="135"
                        x2="247"
                        y2="85"
                        class="radar-axis"
                      />
                      <line
                        x1="160"
                        y1="135"
                        x2="247"
                        y2="185"
                        class="radar-axis"
                      />
                      <line
                        x1="160"
                        y1="135"
                        x2="160"
                        y2="235"
                        class="radar-axis"
                      />
                      <line
                        x1="160"
                        y1="135"
                        x2="73"
                        y2="185"
                        class="radar-axis"
                      />
                      <line
                        x1="160"
                        y1="135"
                        x2="73"
                        y2="85"
                        class="radar-axis"
                      />
                      <polygon
                        points="160,40 236,91 226,173 160,207 83,179 82,90"
                        class="radar-data"
                      />
                      <circle
                        cx="160"
                        cy="40"
                        r="3.5"
                        class="radar-dot"
                      />
                      <circle
                        cx="236"
                        cy="91"
                        r="3.5"
                        class="radar-dot"
                      />
                      <circle
                        cx="226"
                        cy="173"
                        r="3.5"
                        class="radar-dot"
                      />
                      <circle
                        cx="160"
                        cy="207"
                        r="3.5"
                        class="radar-dot"
                      />
                      <circle
                        cx="83"
                        cy="179"
                        r="3.5"
                        class="radar-dot"
                      />
                      <circle
                        cx="82"
                        cy="90"
                        r="3.5"
                        class="radar-dot"
                      />
                      <text
                        x="160"
                        y="16"
                        text-anchor="middle"
                        class="radar-label-bold"
                      >Thân thiện</text>
                      <text
                        x="160"
                        y="28"
                        text-anchor="middle"
                        class="radar-label-sub"
                      >(95%)</text>
                      <text
                        x="256"
                        y="78"
                        text-anchor="start"
                        class="radar-label-bold"
                      >Truyền cảm hứng</text>
                      <text
                        x="256"
                        y="90"
                        text-anchor="start"
                        class="radar-label-sub"
                      >(88%)</text>
                      <text
                        x="256"
                        y="184"
                        text-anchor="start"
                        class="radar-label-bold"
                      >Chuyên nghiệp</text>
                      <text
                        x="256"
                        y="196"
                        text-anchor="start"
                        class="radar-label-sub"
                      >(76%)</text>
                      <text
                        x="160"
                        y="250"
                        text-anchor="middle"
                        class="radar-label-bold"
                      >Sáng tạo</text>
                      <text
                        x="160"
                        y="262"
                        text-anchor="middle"
                        class="radar-label-sub"
                      >(72%)</text>
                      <text
                        x="64"
                        y="184"
                        text-anchor="end"
                        class="radar-label-bold"
                      >Gần gũi</text>
                      <text
                        x="64"
                        y="196"
                        text-anchor="end"
                        class="radar-label-sub"
                      >(89%)</text>
                      <text
                        x="64"
                        y="78"
                        text-anchor="end"
                        class="radar-label-bold"
                      >Tích cực</text>
                      <text
                        x="64"
                        y="90"
                        text-anchor="end"
                        class="radar-label-sub"
                      >(90%)</text>
                    </svg>
                  </VCol>
                  <VCol
                    cols="12"
                    sm="5"
                  >
                    <div class="d-flex flex-column gap-3">
                      <div
                        v-for="attr in traitSummaryList"
                        :key="attr.title"
                        class="d-flex align-center"
                      >
                        <VAvatar
                          :color="attr.color"
                          variant="tonal"
                          size="34"
                          rounded
                          class="me-3 flex-shrink-0"
                        >
                          <VIcon
                            :icon="attr.icon"
                            size="18"
                          />
                        </VAvatar>
                        <div>
                          <div class="text-body-2 font-weight-medium text-high-emphasis">
                            {{ attr.title }}
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ attr.value }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Chi tiết đặc điểm -->
          <VCol
            cols="12"
            lg="6"
          >
            <VCard
              title="Chi tiết đặc điểm"
              subtitle="Mô tả chi tiết hơn về phong cách viết"
              class="mb-6"
            >
              <VCardText>
                <AppTextarea
                  v-model="traitDetailText"
                  rows="5"
                  counter="2000"
                  class="mb-4"
                />
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-body-2 font-weight-medium">Mức độ thể hiện</span>
                  <span class="text-subtitle-1 font-weight-medium text-primary">{{ expressionLevel }}%</span>
                </div>
                <VSlider
                  v-model="expressionLevel"
                  min="0"
                  max="100"
                  step="1"
                  color="primary"
                  hide-details
                />
              </VCardText>
            </VCard>
          </VCol>

          <!-- Ví dụ văn phong -->
          <VCol
            cols="12"
            lg="6"
          >
            <VCard class="mb-6">
              <VCardItem>
                <template #title>
                  Ví dụ văn phong
                </template>
                <template #subtitle>
                  Các đoạn văn mẫu thể hiện phong cách viết
                </template>
                <template #append>
                  <VBtn
                    variant="tonal"
                    color="primary"
                    size="small"
                    prepend-icon="tabler-plus"
                    @click="addSampleQuote"
                  >
                    Thêm mẫu
                  </VBtn>
                </template>
              </VCardItem>
              <VCardText>
                <div class="quote-showcase-box pa-4 rounded mb-4">
                  <div class="d-flex align-center justify-space-between gap-4">
                    <div class="d-flex align-start flex-grow-1">
                      <VIcon
                        icon="tabler-quote"
                        size="26"
                        color="primary"
                        class="me-2 mt-n1"
                      />
                      <p class="text-body-2 font-italic text-high-emphasis mb-0">
                        “{{ sampleQuotes[currentQuoteIdx].text }}”
                      </p>
                    </div>
                    <VAvatar
                      rounded
                      size="84"
                      class="flex-shrink-0 border"
                    >
                      <VImg
                        :src="sampleQuotes[currentQuoteIdx].image"
                        cover
                      />
                    </VAvatar>
                  </div>
                </div>
                <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                  <div class="d-flex flex-wrap gap-2">
                    <VChip
                      v-for="tag in sampleQuotes[currentQuoteIdx].tags"
                      :key="tag"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                      label
                    >
                      {{ tag }}
                    </VChip>
                  </div>
                  <div class="d-flex align-center gap-1">
                    <span
                      v-for="(_, qIdx) in sampleQuotes"
                      :key="qIdx"
                      class="quote-dot cursor-pointer"
                      :class="{ 'quote-dot--active': currentQuoteIdx === qIdx }"
                      @click="currentQuoteIdx = qIdx"
                    />
                  </div>
                  <div class="d-flex align-center gap-1">
                    <IconBtn
                      size="small"
                      @click="prevQuote"
                    >
                      <VIcon icon="tabler-chevron-left" />
                    </IconBtn>
                    <IconBtn
                      size="small"
                      @click="nextQuote"
                    >
                      <VIcon icon="tabler-chevron-right" />
                    </IconBtn>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Tùy chọn hiển thị -->
          <VCol
            cols="12"
            lg="6"
          >
            <VCard
              title="Tùy chọn hiển thị"
              subtitle="Cách áp dụng và hiển thị phong cách này"
              class="mb-6"
            >
              <VCardText>
                <VRow>
                  <VCol
                    v-for="ch in displayChannels"
                    :key="ch.name"
                    cols="12"
                    sm="6"
                  >
                    <div class="channel-toggle-box px-3 py-2 rounded border d-flex align-center justify-space-between">
                      <div class="d-flex align-center text-truncate me-2">
                        <VAvatar
                          :color="ch.color"
                          size="24"
                          rounded
                          class="me-2 flex-shrink-0"
                        >
                          <VIcon
                            :icon="ch.icon"
                            size="14"
                            color="white"
                          />
                        </VAvatar>
                        <span class="text-body-2 font-weight-medium text-high-emphasis text-truncate">
                          {{ ch.name }}
                        </span>
                      </div>
                      <VSwitch
                        v-model="ch.enabled"
                        color="primary"
                        density="compact"
                        hide-details
                        inset
                      />
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VWindowItem>

      <!-- TAB 3: CẤU TRÚC NỘI DUNG -->
      <VWindowItem value="content-structure">
        <VCard>
          <VCardItem>
            <template #title>
              Khung Cấu trúc Bài viết Chuẩn (Content Skeleton)
            </template>
            <template #subtitle>
              Thiết lập tỷ lệ độ dài, cách dẫn dắt câu chuyện và quy tắc ngắt đoạn cho AI
            </template>
            <template #append>
              <VBtn
                color="primary"
                prepend-icon="tabler-plus"
              >
                Thêm khối cấu trúc
              </VBtn>
            </template>
          </VCardItem>
          <VCardText>
            <VRow>
              <VCol
                v-for="(sec, idx) in structureSections"
                :key="sec.title"
                cols="12"
                md="4"
              >
                <div class="border rounded pa-4 h-100 d-flex flex-column justify-space-between">
                  <div>
                    <div class="d-flex justify-space-between align-center mb-3">
                      <VChip
                        size="small"
                        color="primary"
                        variant="flat"
                        label
                      >
                        Phần {{ idx + 1 }} • {{ sec.weight }}
                      </VChip>
                      <span class="text-caption text-medium-emphasis">{{ sec.sentences }}</span>
                    </div>
                    <div class="text-subtitle-1 font-weight-medium text-high-emphasis mb-2">
                      {{ sec.title }}
                    </div>
                    <div class="text-body-2 text-medium-emphasis mb-3">
                      {{ sec.guideline }}
                    </div>
                  </div>
                  <div class="pa-3 rounded bg-surface border text-body-2 font-italic text-high-emphasis">
                    “{{ sec.sample }}”
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 4: QUY TẮC NGÔN NGỮ -->
      <VWindowItem value="language-rules">
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VCard title="Quy chuẩn Xưng hô & Hình thức câu chữ">
              <VCardText class="d-flex flex-column gap-4">
                <AppSelect
                  v-model="languageConfig.brandPronoun"
                  :items="['Mình / Tụi mình / Mộc', 'Chúng tôi / Mộc Cà Phê', 'Ad / Nhà Mộc']"
                  label="Đại từ xưng hô của Thương hiệu"
                />
                <AppSelect
                  v-model="languageConfig.customerPronoun"
                  :items="['Bạn / Cả nhà / Người thương', 'Quý khách / Anh Chị', 'Các tín đồ cà phê']"
                  label="Cách gọi Khách hàng / Độc giả"
                />
                <AppSelect
                  v-model="languageConfig.emojiDensity"
                  :items="['Vừa phải (2–3 emoji ấm áp mỗi đoạn: ☕🌿✨)', 'Tối giản (Chỉ dùng ở cuối bài)', 'Không sử dụng Emoji']"
                  label="Mật độ sử dụng Emoji"
                />
              </VCardText>
            </VCard>
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <VCard title="Danh sách Kiểm duyệt Ngôn từ (Do's & Don'ts)">
              <VCardText class="d-flex flex-column gap-3">
                <div
                  v-for="rule in languageConfig.editorialRules"
                  :key="rule.text"
                  class="pa-3 rounded border d-flex align-center justify-space-between"
                >
                  <div class="d-flex align-center text-body-2 text-high-emphasis">
                    <VIcon
                      :icon="rule.allowed ? 'tabler-circle-check' : 'tabler-circle-x'"
                      :color="rule.allowed ? 'success' : 'error'"
                      size="18"
                      class="me-2"
                    />
                    <span>{{ rule.text }}</span>
                  </div>
                  <VChip
                    size="x-small"
                    :color="rule.allowed ? 'success' : 'error'"
                    variant="tonal"
                    label
                  >
                    {{ rule.allowed ? 'Khuyến khích' : 'Cấm dùng' }}
                  </VChip>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VWindowItem>

      <!-- TAB 5: MẪU NỘI DUNG -->
      <VWindowItem value="content-samples">
        <VCard>
          <VCardItem>
            <template #title>
              Thư viện Bài viết Mẫu (Brand Voice Samples)
            </template>
            <template #subtitle>
              Các bài đăng mẫu làm tài liệu tham chiếu (Few-Shot Examples) cho AI học theo
            </template>
            <template #append>
              <VBtn
                color="primary"
                prepend-icon="tabler-plus"
              >
                Thêm bài mẫu mới
              </VBtn>
            </template>
          </VCardItem>
          <VCardText>
            <VRow>
              <VCol
                v-for="post in fullPostSamples"
                :key="post.title"
                cols="12"
                md="4"
              >
                <div class="border rounded pa-4 h-100 d-flex flex-column justify-space-between">
                  <div>
                    <div class="d-flex justify-space-between align-center mb-3">
                      <VChip
                        size="small"
                        color="primary"
                        variant="tonal"
                        label
                      >
                        {{ post.channel }}
                      </VChip>
                      <span class="text-caption text-medium-emphasis">{{ post.date }}</span>
                    </div>
                    <div class="text-subtitle-1 font-weight-medium text-high-emphasis mb-2">
                      {{ post.title }}
                    </div>
                    <div
                      class="text-body-2 text-medium-emphasis mb-3"
                      style="white-space: pre-line; line-height: 1.6;"
                    >
                      {{ post.content }}
                    </div>
                  </div>
                  <div class="d-flex justify-end gap-2 pt-3 border-t">
                    <VBtn
                      variant="text"
                      size="small"
                      color="secondary"
                      prepend-icon="tabler-copy"
                    >
                      Sao chép
                    </VBtn>
                    <VBtn
                      variant="tonal"
                      size="small"
                      color="primary"
                      prepend-icon="tabler-pencil"
                    >
                      Chỉnh sửa
                    </VBtn>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 6: TỪ VỰNG & CTA -->
      <VWindowItem value="vocab-cta">
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VCard title="Từ điển Từ vựng Thương hiệu (Brand Lexicon)">
              <VCardText>
                <div class="mb-5">
                  <div class="text-body-2 font-weight-medium text-success mb-2">
                    Từ khóa Đặc trưng Nên dùng (Power Words)
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <VChip
                      v-for="w in vocabConfig.powerWords"
                      :key="w"
                      size="small"
                      color="success"
                      variant="tonal"
                      closable
                    >
                      {{ w }}
                    </VChip>
                  </div>
                </div>
                <div>
                  <div class="text-body-2 font-weight-medium text-error mb-2">
                    Từ khóa Cấm xuất hiện (Negative Keywords)
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <VChip
                      v-for="w in vocabConfig.negativeWords"
                      :key="w"
                      size="small"
                      color="error"
                      variant="tonal"
                      closable
                    >
                      {{ w }}
                    </VChip>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <VCard title="Mẫu câu Kêu gọi Hành động (CTA Templates)">
              <VCardText class="d-flex flex-column gap-3">
                <div
                  v-for="cta in vocabConfig.ctaList"
                  :key="cta.type"
                  class="pa-3 rounded border"
                >
                  <div class="text-body-2 font-weight-medium text-primary mb-1">
                    {{ cta.type }}
                  </div>
                  <div class="text-body-2 text-high-emphasis font-italic">
                    “{{ cta.text }}”
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VWindowItem>

      <!-- TAB 7: CÀI ĐẶT NÂNG CAO -->
      <VWindowItem value="advanced">
        <VCard
          title="Cấu hình System Prompt & Tham số Mô hình AI"
          subtitle="Tinh chỉnh trực tiếp chỉ thị hệ thống và mức độ sáng tạo của AI"
        >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="7"
              >
                <AppTextarea
                  v-model="advancedConfig.systemPrompt"
                  label="System Prompt tự động biên dịch"
                  rows="8"
                  class="font-mono"
                />
              </VCol>
              <VCol
                cols="12"
                md="5"
              >
                <AppSelect
                  v-model="advancedConfig.preferredModel"
                  :items="['Claude 3.5 Sonnet (Khuyên dùng cho văn chương)', 'GPT-4o (Đa năng)', 'Gemini 1.5 Pro']"
                  label="Mô hình AI ưu tiên cho Profile này"
                  class="mb-6"
                />
                <div class="mb-4">
                  <div class="d-flex justify-space-between text-body-2 font-weight-medium mb-1">
                    <span>Độ bay bổng / Sáng tạo (Temperature)</span>
                    <span class="text-primary">{{ advancedConfig.temperature }}</span>
                  </div>
                  <VSlider
                    v-model="advancedConfig.temperature"
                    min="0"
                    max="1"
                    step="0.05"
                    color="primary"
                    hide-details
                  />
                </div>
                <div class="d-flex align-center justify-space-between pt-3 border-t">
                  <span class="text-body-2 font-weight-medium">
                    Tự động chấm điểm độ khớp văn phong trước khi xuất bản
                  </span>
                  <VSwitch
                    v-model="advancedConfig.autoScoreCheck"
                    color="primary"
                    hide-details
                    inset
                  />
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- TAB 8: LỊCH SỬ -->
      <VWindowItem value="history">
        <VCard
          title="Lịch sử Phiên bản & Nhật ký Chỉnh sửa"
          subtitle="Theo dõi các thay đổi qua từng phiên bản và khôi phục khi cần"
        >
          <VCardText class="d-flex flex-column gap-3">
            <div
              v-for="ver in versionHistory"
              :key="ver.version"
              class="pa-4 rounded border d-flex align-center justify-space-between flex-wrap gap-3"
              :class="{ 'ver-item--current': ver.current }"
            >
              <div class="d-flex align-center">
                <VAvatar
                  :color="ver.current ? 'primary' : 'secondary'"
                  :variant="ver.current ? 'flat' : 'tonal'"
                  size="40"
                  rounded
                  class="me-3"
                >
                  <span class="text-body-2 font-weight-medium">{{ ver.version }}</span>
                </VAvatar>
                <div>
                  <div class="d-flex align-center gap-2">
                    <span class="text-subtitle-1 font-weight-medium text-high-emphasis">{{ ver.summary }}</span>
                    <VChip
                      v-if="ver.current"
                      size="x-small"
                      color="success"
                      variant="flat"
                      label
                    >
                      Hiện tại
                    </VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Cập nhật lúc {{ ver.date }} • bởi <strong>{{ ver.author }}</strong>
                  </div>
                </div>
              </div>
              <VBtn
                v-if="!ver.current"
                variant="tonal"
                color="primary"
                size="small"
                prepend-icon="tabler-restore"
              >
                Khôi phục bản này
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VWindowItem>
    </VWindow>
  </div>
</template>

<style scoped lang="scss">
/* Nút cây bút nhỏ góc avatar. */
.avatar-edit-btn {
  position: absolute;
  inset-block-end: -4px;
  inset-inline-end: -4px;
  border: 2px solid rgb(var(--v-theme-surface));
}

/* Biểu đồ Radar SVG — màu theo biến theme. */
.radar-chart {
  inline-size: 100%;
  max-inline-size: 320px;
  overflow: visible;
}
.radar-ring {
  fill: none;
  stroke: rgba(var(--v-theme-on-surface), 0.12);
}
.radar-ring--outer {
  fill: rgba(var(--v-theme-primary), 0.04);
}
.radar-ring--inner {
  stroke: rgba(var(--v-theme-on-surface), 0.08);
}
.radar-axis {
  stroke: rgba(var(--v-theme-on-surface), 0.12);
}
.radar-data {
  fill: rgba(var(--v-theme-primary), 0.24);
  stroke: rgb(var(--v-theme-primary));
  stroke-width: 2;
}
.radar-dot {
  fill: rgb(var(--v-theme-primary));
}
.radar-label-bold {
  font-size: 9.5px;
  font-weight: 700;
  fill: rgb(var(--v-theme-on-surface));
}
.radar-label-sub {
  font-size: 9px;
  font-weight: 600;
  fill: rgba(var(--v-theme-on-surface), 0.6);
}

/* Hộp trích dẫn ví dụ văn phong — tông primary nhạt. */
.quote-showcase-box {
  background: rgba(var(--v-theme-primary), 0.05);
  border: 1px solid rgba(var(--v-theme-primary), 0.16);
}

/* Chấm chuyển slide. */
.quote-dot {
  inline-size: 6px;
  block-size: 6px;
  border-radius: 50%;
  background: rgba(var(--v-theme-on-surface), 0.2);
  transition: all 0.2s ease;
}
.quote-dot--active {
  inline-size: 8px;
  block-size: 8px;
  background: rgb(var(--v-theme-primary));
}

/* Ô bật/tắt kênh hiển thị. */
.channel-toggle-box {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  transition: border-color 0.15s ease;
}
.channel-toggle-box:hover {
  border-color: rgb(var(--v-theme-primary));
}

/* Item lịch sử đang là bản hiện tại. */
.ver-item--current {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.04);
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
