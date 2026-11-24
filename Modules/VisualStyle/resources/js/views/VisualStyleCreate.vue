<!--
  =====================================================================
  CHỨC NĂNG FILE: Giao diện tạo mới / giải mã DNA Visual Style (tĩnh).
  =====================================================================

  Cung cấp biểu mẫu chuẩn Vuexy để tạo hồ sơ DNA phong cách thị giác mới
  với dữ liệu mẫu tĩnh (chưa nối API backend):
  - Page Header chuẩn kèm nút quay về danh sách & lưu hồ sơ.
  - Cột trái (8/12): Thông tin cơ bản, nguồn ảnh tham chiếu (upload cục bộ
    hoặc chọn ảnh mẫu nhanh để mô phỏng AI Vision), cấu hình 5 phân vùng
    DNA thị giác (`composition`, `camera`, `lighting`, `environment`,
    `colorMood`) và tỷ lệ khung hình / preset góc chụp.
  - Cột phải (4/12): Thẻ xem trước trực tiếp `VisualDnaCard`, chuỗi Prompt
    7 lớp tự động biên dịch từ form, Negative Prompt và nút xuất bản.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - selectedSample(): Trả về ảnh mẫu hiện tại đang gắn với form.
  - compiledPromptPreview(): Ghép mô tả Prompt 7 lớp xem trước từ 5 phân vùng DNA.
  - previewCardItem(): Tạo object tương thích `VisualDnaCard` để xem trước realtime.
  - toSlugFallback(): Tạo chuỗi slug thuần từ tiếng Việt khi chạy tĩnh offline.
  - runSlugSuggest(): Gọi Core slug-suggest kiểm tra/gợi ý slug khả dụng (fallback local khi offline).
  - onNameBlur(): Tự động gợi ý slug từ tên hồ sơ khi người dùng rời ô tên.
  - onSlugBlur(): Kiểm tra và chuẩn hóa slug khi người dùng rời ô slug.
  - goBackToList(): Điều hướng về trang danh sách `admin-visual-style-visual-styles-list`.
  - triggerUpload(): Mở hộp thoại chọn file ảnh cục bộ.
  - handleImageUpload(): Đọc ảnh cục bộ bằng Object URL để xem trước tức thì.
  - applySamplePreset(): Nạp sẵn ảnh mẫu và DNA gợi ý vào biểu mẫu.
  - runStaticVisionAnalyze(): Mô phỏng giải mã AI Vision từ ảnh tham chiếu hiện tại.
  - toggleAspectRatio(): Bật hoặc tắt một tỷ lệ khung hình trong danh sách chọn.
  - addPaletteHex(): Thêm mã màu hex hợp lệ vào bảng màu `paletteHex`.
  - removePaletteHex(): Gỡ bỏ một mã màu khỏi bảng màu `paletteHex`.
  - handleSaveStyle(): Kiểm tra tên hồ sơ, thông báo snackbar và quay về danh sách.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : `route.query.mode` ('analyze' | 'manual') để chọn tab khởi tạo.
  - OUTPUT: Render UI tạo hồ sơ và điều hướng giữa create/list qua Vue Router.
  =====================================================================
-->
<script setup>
import thumbPortrait from '@images/banner/banner-29.jpg'
import thumbNoirTech from '@images/banner/banner-30.jpg'
import thumbCyberpunk from '@images/banner/banner-34.jpg'
import thumbScandinavian from '@images/banner/banner-4.jpg'
import thumbLifestyle from '@images/banner/banner-20.jpg'
import SlugField from '@modules/Core/resources/js/components/SlugField.vue'
import { slugApi } from '@modules/Core/resources/js/services/slugApi'
import { useSnackbar } from '@/composables/useSnackbar'
import VisualDnaCard from '../components/VisualDnaCard.vue'

const route = useRoute()
const router = useRouter()
const snackbar = useSnackbar()

const QUICK_SAMPLE_PRESETS = [
  {
    key: 'noir_tech',
    label: 'Cinematic Neo-Noir Tech',
    thumbnailUrl: thumbNoirTech,
    accentColor: 'primary',
    icon: 'tabler-aperture',
    description: 'Ánh sáng tương phản cao kết hợp tông xanh đêm và điểm nhấn hổ phách cho nội dung công nghệ.',
    tags: ['Công nghệ', 'YouTube'],
    composition: { framing: 'Bố cục 1/3 có tiền cảnh', layoutRule: 'Chừa khoảng âm chữ tiêu đề', negativeSpace: 'Cân bằng 35%' },
    camera: { lensLabel: '85mm f/1.4', angleLabel: 'Ngang tầm mắt', depthOfField: 'Xóa phông mượt (f/1.4)' },
    lighting: { toneKey: 'moody_cinematic', toneLabel: 'Điện ảnh trầm', direction: 'Ánh sáng viền chéo 45 độ', contrast: 'Tương phản cao' },
    environment: { settingLabel: 'Studio công nghệ ánh đêm', surface: 'Kim loại mờ & kính phản chiếu', atmosphere: 'Trầm lắng chuyên sâu' },
    colorMood: { moodLabel: 'Sắc lạnh chuyên sâu, điểm nhấn ấm', paletteHex: ['#0F172A', '#1E3A8A', '#38BDF8', '#F59E0B', '#E2E8F0'] },
    aspectRatios: ['16:9', '9:16', '1:1'],
  },
  {
    key: 'scandinavian',
    label: 'Tối Giản Bắc Âu',
    thumbnailUrl: thumbScandinavian,
    accentColor: 'info',
    icon: 'tabler-layout-grid',
    description: 'Bố cục thoáng sạch trên nền sáng trung tính, độ chuyển khối dịu tôn trọn chất liệu sản phẩm.',
    tags: ['Thương hiệu', 'Editorial'],
    composition: { framing: 'Đối xứng thoáng', layoutRule: 'Khoảng thở rộng hai bên', negativeSpace: 'Rộng 50%' },
    camera: { lensLabel: '50mm f/2.0', angleLabel: 'Góc 3/4 cao nhẹ', depthOfField: 'Rõ chủ thể vừa phải (f/2.8)' },
    lighting: { toneKey: 'studio_clean', toneLabel: 'Studio sắc nét', direction: 'Ánh sáng cửa sổ khuếch tán', contrast: 'Dịu trung bình' },
    environment: { settingLabel: 'Nền xám sáng vật liệu tự nhiên', surface: 'Thạch cao mờ & gỗ sồi', atmosphere: 'Sạch sẽ tinh gọn' },
    colorMood: { moodLabel: 'Thanh lịch, trung tính, tinh gọn', paletteHex: ['#F8FAFC', '#E2E8F0', '#CBD5E1', '#94A3B8', '#334155'] },
    aspectRatios: ['1:1', '4:5', '16:9'],
  },
  {
    key: 'golden_hour',
    label: 'Ánh Nắng Vàng Lifestyle',
    thumbnailUrl: thumbLifestyle,
    accentColor: 'warning',
    icon: 'tabler-sun',
    description: 'Sắc nắng giờ vàng ấm áp tự nhiên cùng chiều sâu trường ảnh mỏng, phù hợp mạng xã hội.',
    tags: ['Social', 'Lifestyle'],
    composition: { framing: 'Lệch tâm tự nhiên', layoutRule: 'Bokeh tiền cảnh dẫn mắt', negativeSpace: 'Tự nhiên 30%' },
    camera: { lensLabel: '35mm f/1.8', angleLabel: 'Ngang tầm mắt tự nhiên', depthOfField: 'Mỏng ấm áp (f/1.8)' },
    lighting: { toneKey: 'warm_golden', toneLabel: 'Ánh sáng ấm', direction: 'Ngược nắng giờ vàng', contrast: 'Ấm mềm' },
    environment: { settingLabel: 'Ngoại cảnh quán cà phê & phố nắng', surface: 'Gỗ mộc & lá cây', atmosphere: 'Gần gũi sống động' },
    colorMood: { moodLabel: 'Ấm áp, tràn đầy năng lượng sống', paletteHex: ['#FEF3C7', '#F59E0B', '#D97706', '#78350F', '#FFFBEB'] },
    aspectRatios: ['4:5', '9:16', '1:1'],
  },
  {
    key: 'cyberpunk',
    label: 'Cyberpunk Neon Futurism',
    thumbnailUrl: thumbCyberpunk,
    accentColor: 'secondary',
    icon: 'tabler-sparkles',
    description: 'Bề mặt phản chiếu ướt kết hợp dải đèn tím cyan nổi bật, tối ưu ảnh bìa concept và shorts.',
    tags: ['AI Concept', 'Shorts'],
    composition: { framing: 'Đường dẫn chéo kịch tính', layoutRule: 'Góc nghiêng tạo lực chuyển động', negativeSpace: 'Chặt 20%' },
    camera: { lensLabel: '24mm Wide', angleLabel: 'Góc thấp hướng lên', depthOfField: 'Rộng lấy trọn bối cảnh (f/4)' },
    lighting: { toneKey: 'neon_cyberpunk', toneLabel: 'Neon / Công nghệ', direction: 'Hai nguồn sáng đối lập tím-cyan', contrast: 'Siêu tương phản' },
    environment: { settingLabel: 'Phòng lab tương lai & phản chiếu ướt', surface: 'Nhựa bóng & kim loại phát quang', atmosphere: 'Kịch tính vị lai' },
    colorMood: { moodLabel: 'Tương lai rực rỡ, năng lượng cao', paletteHex: ['#1E1B4B', '#7C3AED', '#EC4899', '#06B6D4', '#F8FAFC'] },
    aspectRatios: ['16:9', '9:16'],
  },
  {
    key: 'editorial_portrait',
    label: 'Tạp Chí High-Key',
    thumbnailUrl: thumbPortrait,
    accentColor: 'success',
    icon: 'tabler-mood-smile',
    description: 'Ánh sáng softbox lớn và phông sạch giữ trung thực tông da cho ảnh bìa tạp chí.',
    tags: ['Chân dung', 'Tạp chí'],
    composition: { framing: 'Trung tâm cân bằng tỷ lệ vàng', layoutRule: 'Cận cảnh tôn biểu cảm', negativeSpace: 'Sạch 40%' },
    camera: { lensLabel: '85mm Prime', angleLabel: 'Ngang mặt trực diện', depthOfField: 'Nét căng mắt (f/2.2)' },
    lighting: { toneKey: 'studio_clean', toneLabel: 'Studio sắc nét', direction: 'Softbox đôi đều mặt', contrast: 'Sạch trung tính' },
    environment: { settingLabel: 'Phông vô cực sáng tạp chí', surface: 'Giấy phông mịn', atmosphere: 'Sang trọng hiện đại' },
    colorMood: { moodLabel: 'Sang trọng, tin cậy, sắc nét', paletteHex: ['#FFFFFF', '#F1F5F9', '#E2C2A4', '#8D5524', '#1E293B'] },
    aspectRatios: ['4:5', '1:1'],
  },
]

const ASPECT_RATIO_OPTIONS = ['16:9', '9:16', '1:1', '4:5']
const TAXONOMY_SUGGESTIONS = ['Công nghệ', 'Thương hiệu', 'Editorial', 'Social', 'Sản phẩm', 'Chân dung', 'F&B', 'AI Concept']
const LENS_OPTIONS = ['24mm Wide', '35mm f/1.8', '50mm f/2.0', '50mm Macro', '85mm f/1.4', '85mm Prime', '70-200mm Tele', '100mm Macro']
const CAMERA_ANGLE_OPTIONS = ['Ngang tầm mắt', 'Góc thấp hướng lên', 'Góc 3/4 cao nhẹ', 'Thẳng đứng 90 độ (Flatlay)', 'Chính diện 15 độ']
const LIGHTING_TONE_OPTIONS = [
  { title: 'Điện ảnh trầm', value: 'moody_cinematic' },
  { title: 'Studio sắc nét', value: 'studio_clean' },
  { title: 'Ánh sáng ấm', value: 'warm_golden' },
  { title: 'Neon / Công nghệ', value: 'neon_cyberpunk' },
  { title: 'Tự nhiên tối giản', value: 'natural_minimal' },
]

const COLOR_HEX_SUGGESTIONS = ['#0F172A', '#1E3A8A', '#38BDF8', '#F59E0B', '#EC4899', '#10B981', '#F8FAFC']

const fileInputRef = ref(null)
const uploadedPreviewUrl = ref('')
const inputMode = ref(route.query.mode === 'manual' ? 'manual' : 'analyze')
const dnaSectionTab = ref('composition')
const activeSampleKey = ref('noir_tech')
const newHexInput = ref('')
const slugSuggesting = ref(false)
const slugAvailable = ref(null)
const slugTakenBy = ref(null)
let slugRequestSeq = 0

const form = reactive({
  name: 'Cinematic Neo-Noir Tech',
  slug: 'cinematic-neo-noir-tech',
  description: 'Ánh sáng tương phản cao kết hợp tông xanh đêm và điểm nhấn hổ phách cho nội dung công nghệ.',
  taxonomyTags: ['Công nghệ', 'YouTube'],
  accentColor: 'primary',
  icon: 'tabler-aperture',
  thumbnailUrl: thumbNoirTech,
  confidence: 92,
  isActive: true,
  aspectRatios: ['16:9', '9:16', '1:1'],
  negativePrompt: 'chữ chìm, watermark, da nhựa quá mịn, cháy sáng neon, bố cục lệch nặng',
  dna: {
    composition: {
      framing: 'Bố cục 1/3 có tiền cảnh',
      layoutRule: 'Chừa khoảng âm chữ tiêu đề',
      negativeSpace: 'Cân bằng 35%',
    },
    camera: {
      lensLabel: '85mm f/1.4',
      angleLabel: 'Ngang tầm mắt',
      depthOfField: 'Xóa phông mượt (f/1.4)',
    },
    lighting: {
      toneKey: 'moody_cinematic',
      toneLabel: 'Điện ảnh trầm',
      direction: 'Ánh sáng viền chéo 45 độ',
      contrast: 'Tương phản cao',
    },
    environment: {
      settingLabel: 'Studio công nghệ ánh đêm',
      surface: 'Kim loại mờ & kính phản chiếu',
      atmosphere: 'Trầm lắng chuyên sâu',
    },
    colorMood: {
      moodLabel: 'Sắc lạnh chuyên sâu, điểm nhấn ấm',
      paletteHex: ['#0F172A', '#1E3A8A', '#38BDF8', '#F59E0B', '#E2E8F0'],
    },
  },
  shotPresets: [
    { key: 'hero', label: 'Hero Banner', ratio: '16:9', enabled: true, note: 'Ảnh đầu bài viết / landing section' },
    { key: 'thumbnail', label: 'Thumbnail YouTube', ratio: '16:9', enabled: true, note: 'Giữ khoảng trống tiêu đề rõ nét' },
    { key: 'social_feed', label: 'Social Feed', ratio: '4:5', enabled: true, note: 'Tối ưu Facebook / Instagram feed' },
    { key: 'story', label: 'Story Vertical', ratio: '9:16', enabled: false, note: 'Video dọc Shorts / Reels / TikTok' },
    { key: 'b_roll', label: 'Cảnh phụ B-Roll', ratio: '16:9', enabled: false, note: 'Chèn minh họa bối cảnh chi tiết' },
  ],
})

const lightingLabelByKey = computed(() => {
  const found = LIGHTING_TONE_OPTIONS.find(opt => opt.value === form.dna.lighting.toneKey)

  return found ? found.title : 'Điện ảnh trầm'
})

/*
 * CHỨC NĂNG: Đồng bộ nhãn ánh sáng khi người dùng thay đổi mã nhóm ánh sáng.
 * INPUT: form.dna.lighting.toneKey.
 * OUTPUT: void — cập nhật form.dna.lighting.toneLabel.
 */
watch(() => form.dna.lighting.toneKey, () => {
  form.dna.lighting.toneLabel = lightingLabelByKey.value
})

/*
 * CHỨC NĂNG: Đọc lại tab chế độ nhập nếu query `?mode` trên URL thay đổi.
 * INPUT: route.query.mode.
 * OUTPUT: void — cập nhật inputMode.
 */
watch(() => route.query.mode, mode => {
  if (mode === 'manual' || mode === 'analyze')
    inputMode.value = mode
})

/*
 * CHỨC NĂNG: Ghép chuỗi Prompt 7 lớp xem trước theo thời gian thực từ 5 phân vùng DNA.
 * INPUT: toàn bộ giá trị form.dna hiện tại.
 * OUTPUT: chuỗi prompt tiếng Việt tóm tắt 7 lớp.
 */
const compiledPromptPreview = computed(() => {
  const paletteText = form.dna.colorMood.paletteHex.join(' ') || 'tự nhiên'

  return [
    `${form.dna.composition.framing} (${form.dna.composition.layoutRule})`,
    `chụp ${form.dna.camera.angleLabel.toLowerCase()} với ống kính ${form.dna.camera.lensLabel}`,
    `nguồn sáng ${form.dna.lighting.toneLabel.toLowerCase()} (${form.dna.lighting.direction})`,
    `bối cảnh ${form.dna.environment.settingLabel} trên chất liệu ${form.dna.environment.surface}`,
    `tâm trạng ${form.dna.colorMood.moodLabel} cùng bảng màu ${paletteText}.`,
  ].join(', ')
})

/*
 * CHỨC NĂNG: Chuyển đổi dữ liệu form hiện tại thành object prop cho `VisualDnaCard`.
 * INPUT: reactive form.
 * OUTPUT: Object styleItem chuẩn khớp `VisualDnaCard`.
 */
const previewCardItem = computed(() => ({
  id: 0,
  name: form.name.trim() || 'Hồ sơ Visual Style mới',
  description: form.description.trim() || 'Chưa nhập mô tả tổng quan phong cách.',
  thumbnailUrl: form.thumbnailUrl,
  version: 1,
  status: form.isActive ? 'active' : 'draft',
  confidence: form.confidence,
  shotsCount: form.shotPresets.filter(s => s.enabled).length,
  aspectRatios: form.aspectRatios.length ? form.aspectRatios : ['16:9'],
  taxonomyTags: form.taxonomyTags.slice(0, 2),
  accentColor: form.accentColor,
  icon: form.icon,
  updatedAt: 'Bản nháp xem trước',
  dna: form.dna,
}))

/*
 * CHỨC NĂNG: Điều hướng quay lại trang danh sách Visual Styles.
 * INPUT: không có tham số.
 * OUTPUT: Promise điều hướng router.
 */
function goBackToList() {
  router.push({ name: 'admin-visual-style-visual-styles-list' })
}

/*
 * CHỨC NĂNG: Kích hoạt input ẩn để người dùng chọn ảnh tham chiếu từ máy.
 * INPUT: không có tham số.
 * OUTPUT: void.
 */
function triggerUpload() {
  fileInputRef.value?.click()
}

/*
 * CHỨC NĂNG: Tạo URL xem trước cục bộ cho ảnh upload từ máy và thu hồi URL cũ.
 * INPUT: event (InputEvent từ input type="file").
 * OUTPUT: void.
 */
function handleImageUpload(event) {
  const file = event.target?.files?.[0]
  if (!file)
    return

  if (uploadedPreviewUrl.value)
    URL.revokeObjectURL(uploadedPreviewUrl.value)

  uploadedPreviewUrl.value = URL.createObjectURL(file)
  form.thumbnailUrl = uploadedPreviewUrl.value
  activeSampleKey.value = ''
  snackbar.info(`Đã nạp ảnh "${file.name}" để xem trước và mô phỏng giải mã DNA.`)
}

/*
 * CHỨC NĂNG: Nạp nhanh một mẫu phong cách có sẵn vào toàn bộ form để trải nghiệm giao diện tĩnh.
 * INPUT: preset (Object trong QUICK_SAMPLE_PRESETS).
 * OUTPUT: void.
 */
function applySamplePreset(preset) {
  activeSampleKey.value = preset.key
  form.name = preset.label
  form.slug = toSlugFallback(preset.label)
  form.description = preset.description
  form.taxonomyTags = [...preset.tags]
  form.accentColor = preset.accentColor
  form.icon = preset.icon
  form.thumbnailUrl = preset.thumbnailUrl
  form.aspectRatios = [...preset.aspectRatios]
  form.dna.composition = { ...preset.composition }
  form.dna.camera = { ...preset.camera }
  form.dna.lighting = { ...preset.lighting }
  form.dna.environment = { ...preset.environment }
  form.dna.colorMood = {
    moodLabel: preset.colorMood.moodLabel,
    paletteHex: [...preset.colorMood.paletteHex],
  }
  runSlugSuggest(preset.label)
}

/*
 * CHỨC NĂNG: Chuyển chuỗi tiếng Việt sang dạng kebab-case dự phòng khi chạy giao diện tĩnh.
 * INPUT: source (string tên hoặc slug nhập tay).
 * OUTPUT: string slug hợp lệ.
 */
function toSlugFallback(source) {
  return String(source || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036F]/g, '')
    .replace(/[đĐ]/g, 'd')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

/*
 * CHỨC NĂNG: Xin slug khả dụng từ Core slugApi theo nguồn nhập (fallback local nếu offline).
 * INPUT: source (string tên hoặc slug).
 * OUTPUT: Promise<void> — cập nhật form.slug, slugAvailable và slugTakenBy.
 */
async function runSlugSuggest(source) {
  const src = String(source || '').trim()
  if (!src)
    return

  const seq = ++slugRequestSeq

  slugSuggesting.value = true
  try {
    const payload = {
      source: src,
      'sluggable_type': 'visual_style', // eslint-disable-line camelcase
      'sluggable_id': null, // eslint-disable-line camelcase
    }

    const res = await slugApi.suggest(payload)
    if (seq !== slugRequestSeq || !res)
      return

    if (res?.slug)
      form.slug = res.slug
    slugAvailable.value = res?.available ?? null
    slugTakenBy.value = res?.taken_by ?? null // eslint-disable-line camelcase
  } catch {
    if (seq !== slugRequestSeq)
      return

    form.slug = toSlugFallback(src)
    slugAvailable.value = null
    slugTakenBy.value = null
  } finally {
    if (seq === slugRequestSeq)
      slugSuggesting.value = false
  }
}

/*
 * CHỨC NĂNG: Tự động gợi ý slug từ tên hồ sơ khi rời khỏi ô Tên.
 * INPUT: không có tham số.
 * OUTPUT: void.
 */
function onNameBlur() {
  runSlugSuggest(form.name)
}

/*
 * CHỨC NĂNG: Kiểm tra và gợi ý lại slug khi rời khỏi ô Slug.
 * INPUT: không có tham số.
 * OUTPUT: void.
 */
function onSlugBlur() {
  runSlugSuggest(form.slug || form.name)
}

/*
 * CHỨC NĂNG: Mô phỏng nút chạy AI Vision phân tích ảnh tham chiếu trên dữ liệu tĩnh.
 * INPUT: không có tham số.
 * OUTPUT: void — tăng độ tin cậy lên mức chuẩn và thông báo snackbar.
 */
function runStaticVisionAnalyze() {
  form.confidence = 93
  snackbar.success('Đã trích xuất mô phỏng 5 phân vùng DNA và bảng màu từ ảnh tham chiếu.')
}

/*
 * CHỨC NĂNG: Bật hoặc tắt một tỷ lệ khung hình được chọn.
 * INPUT: ratio (string như '16:9', '9:16').
 * OUTPUT: void.
 */
function toggleAspectRatio(ratio) {
  if (form.aspectRatios.includes(ratio)) {
    if (form.aspectRatios.length === 1)
      return snackbar.warning('Cần giữ tối thiểu 1 tỷ lệ khung hình.')
    form.aspectRatios = form.aspectRatios.filter(item => item !== ratio)
  } else {
    form.aspectRatios = [...form.aspectRatios, ratio]
  }
}

/*
 * CHỨC NĂNG: Thêm mã màu hex hợp lệ vào danh sách `paletteHex` (tối đa 6 màu).
 * INPUT: hexCandidate (string tùy chọn; nếu trống sẽ lấy từ newHexInput).
 * OUTPUT: void.
 */
function addPaletteHex(hexCandidate) {
  const raw = String(hexCandidate || newHexInput.value || '').trim().toUpperCase()
  const formatted = raw.startsWith('#') ? raw : `#${raw}`

  if (!/^#[0-9A-F]{6}$/.test(formatted))
    return snackbar.warning('Mã màu cần đúng định dạng #RRGGBB (ví dụ: #38BDF8).')

  if (form.dna.colorMood.paletteHex.includes(formatted))
    return snackbar.info(`Mã màu ${formatted} đã có trong bảng màu.`)

  if (form.dna.colorMood.paletteHex.length >= 6)
    return snackbar.warning('Bảng màu DNA hỗ trợ tối đa 6 màu.')

  form.dna.colorMood.paletteHex = [...form.dna.colorMood.paletteHex, formatted]
  newHexInput.value = ''
}

/*
 * CHỨC NĂNG: Gỡ một mã màu khỏi bảng màu DNA (giữ tối thiểu 1 màu).
 * INPUT: hex (string mã màu cần xóa).
 * OUTPUT: void.
 */
function removePaletteHex(hex) {
  if (form.dna.colorMood.paletteHex.length <= 1)
    return snackbar.warning('Bảng màu cần giữ ít nhất 1 mã màu đại diện.')

  form.dna.colorMood.paletteHex = form.dna.colorMood.paletteHex.filter(item => item !== hex)
}

/*
 * CHỨC NĂNG: Kiểm tra biểu mẫu và mô phỏng lưu hồ sơ Visual Style ở chế độ giao diện tĩnh.
 * INPUT: không có tham số.
 * OUTPUT: void — thông báo kết quả qua snackbar và điều hướng về danh sách.
 */
function handleSaveStyle() {
  if (!form.name.trim())
    return snackbar.warning('Vui lòng nhập tên hồ sơ Visual Style trước khi lưu.')

  if (!form.slug.trim())
    form.slug = toSlugFallback(form.name)

  snackbar.success(`Đã chuẩn bị xong hồ sơ "${form.name.trim()}" (slug: ${form.slug}, giao diện tĩnh).`)
  goBackToList()
}

onBeforeUnmount(() => {
  if (uploadedPreviewUrl.value)
    URL.revokeObjectURL(uploadedPreviewUrl.value)
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium mb-1">
          Tạo Visual Style
        </h4>
        <div class="text-body-1 text-medium-emphasis">
          Giải mã DNA từ ảnh tham chiếu hoặc thiết lập thủ công 5 phân vùng thị giác
        </div>
      </div>

      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-arrow-left"
          @click="goBackToList"
        >
          Quay lại danh sách
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-device-floppy"
          @click="handleSaveStyle"
        >
          Lưu hồ sơ
        </VBtn>
      </div>
    </div>

    <VRow>
      <!-- Cột trái: Thông tin, Nguồn ảnh, 5 Phân vùng DNA & Góc chụp -->
      <VCol
        cols="12"
        lg="8"
      >
        <!-- Card 1: Thông tin hồ sơ -->
        <VCard class="mb-6">
          <VCardItem>
            <VCardTitle class="text-subtitle-1 font-weight-medium">
              Thông tin hồ sơ
            </VCardTitle>
            <VCardSubtitle class="text-body-2">
              Định danh tên, mô tả phong cách và nhóm phân loại sử dụng chung toàn hệ thống
            </VCardSubtitle>
          </VCardItem>

          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="7"
              >
                <AppTextField
                  v-model="form.name"
                  label="Tên hồ sơ Visual Style *"
                  placeholder="Ví dụ: Cinematic Neo-Noir Tech"
                  :rules="[v => !!v?.trim() || 'Tên hồ sơ Visual Style bắt buộc nhập']"
                  required
                  @blur="onNameBlur"
                />
              </VCol>

              <VCol
                cols="12"
                md="5"
              >
                <AppSelect
                  v-model="form.taxonomyTags"
                  label="Nhóm chủ đề / Tag"
                  :items="TAXONOMY_SUGGESTIONS"
                  multiple
                  chips
                  closable-chips
                  placeholder="Chọn nhãn phân loại"
                />
              </VCol>

              <VCol cols="12">
                <SlugField
                  v-model="form.slug"
                  label="Slug định danh *"
                  placeholder="cinematic-neo-noir-tech"
                  hint="Blur ô tên hoặc ô slug — hệ thống gợi ý slug khả dụng và cập nhật lại ô."
                  persistent-hint
                  :suggesting="slugSuggesting"
                  :available="slugAvailable"
                  :taken-by="slugTakenBy"
                  sluggable-type="visual_style"
                  prefix="localhost:8000/"
                  @request-suggest="onSlugBlur"
                />
              </VCol>

              <VCol cols="12">
                <AppTextarea
                  v-model="form.description"
                  label="Mô tả tổng quan phong cách"
                  placeholder="Mô tả ngắn gọn tinh thần hình ảnh, bối cảnh phù hợp..."
                  rows="2"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Card 2: Ảnh tham chiếu & chế độ khởi tạo -->
        <VCard class="mb-6">
          <VCardItem>
            <VCardTitle class="text-subtitle-1 font-weight-medium">
              Ảnh tham chiếu &amp; Nguồn DNA
            </VCardTitle>
            <VCardSubtitle class="text-body-2">
              Tải ảnh mẫu để AI Vision giải mã hoặc chọn nhanh mẫu có sẵn để tinh chỉnh
            </VCardSubtitle>
          </VCardItem>

          <VCardText>
            <VTabs
              v-model="inputMode"
              show-arrows
            >
              <VTab value="analyze">
                <VIcon
                  icon="tabler-sparkles"
                  class="me-2"
                />
                Giải mã từ ảnh mẫu
              </VTab>
              <VTab value="manual">
                <VIcon
                  icon="tabler-adjustments"
                  class="me-2"
                />
                Thiết lập DNA thủ công
              </VTab>
            </VTabs>

            <VDivider class="my-4" />

            <div class="d-flex flex-wrap gap-4 align-start">
              <div class="create-thumb-preview flex-shrink-0">
                <VImg
                  :src="form.thumbnailUrl"
                  :alt="form.name"
                  height="168"
                  cover
                  class="rounded"
                />
              </div>

              <div class="flex-grow-1 min-width-0">
                <input
                  ref="fileInputRef"
                  type="file"
                  accept="image/*"
                  class="d-none"
                  @change="handleImageUpload"
                >

                <div class="d-flex flex-wrap gap-3 mb-4">
                  <VBtn
                    variant="tonal"
                    color="secondary"
                    prepend-icon="tabler-upload"
                    @click="triggerUpload"
                  >
                    Tải ảnh từ máy
                  </VBtn>
                  <VBtn
                    v-if="inputMode === 'analyze'"
                    color="primary"
                    variant="tonal"
                    prepend-icon="tabler-sparkles"
                    @click="runStaticVisionAnalyze"
                  >
                    Giải mã DNA từ ảnh này
                  </VBtn>
                </div>

                <div class="text-caption text-medium-emphasis mb-2">
                  Hoặc chọn nhanh phong cách mẫu tham khảo:
                </div>

                <div class="d-flex flex-wrap gap-2">
                  <VChip
                    v-for="preset in QUICK_SAMPLE_PRESETS"
                    :key="preset.key"
                    size="small"
                    :color="activeSampleKey === preset.key ? 'primary' : 'secondary'"
                    :variant="activeSampleKey === preset.key ? 'flat' : 'tonal'"
                    class="cursor-pointer"
                    @click="applySamplePreset(preset)"
                  >
                    {{ preset.label }}
                  </VChip>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- Card 3: 5 Phân vùng DNA thị giác -->
        <VCard class="mb-6">
          <VCardItem>
            <VCardTitle class="text-subtitle-1 font-weight-medium">
              Cấu hình 5 Phân vùng DNA thị giác
            </VCardTitle>
            <VCardSubtitle class="text-body-2">
              Điều chỉnh chi tiết từng thông số theo schema chuẩn của module VisualStyle
            </VCardSubtitle>
          </VCardItem>

          <VCardText>
            <VTabs
              v-model="dnaSectionTab"
              show-arrows
            >
              <VTab value="composition">
                1. Bố cục
              </VTab>
              <VTab value="camera">
                2. Góc máy
              </VTab>
              <VTab value="lighting">
                3. Ánh sáng
              </VTab>
              <VTab value="environment">
                4. Bối cảnh
              </VTab>
              <VTab value="colorMood">
                5. Màu sắc
              </VTab>
            </VTabs>

            <VDivider class="my-4" />

            <VWindow v-model="dnaSectionTab">
              <VWindowItem value="composition">
                <VRow>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="form.dna.composition.framing"
                      label="Quy tắc khung hình"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="form.dna.composition.layoutRule"
                      label="Sắp đặt trọng tâm"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="form.dna.composition.negativeSpace"
                      label="Tỷ lệ khoảng âm"
                    />
                  </VCol>
                </VRow>
              </VWindowItem>

              <VWindowItem value="camera">
                <VRow>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppSelect
                      v-model="form.dna.camera.lensLabel"
                      label="Ống kính / Tiêu cự"
                      :items="LENS_OPTIONS"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppSelect
                      v-model="form.dna.camera.angleLabel"
                      label="Góc đặt máy"
                      :items="CAMERA_ANGLE_OPTIONS"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="form.dna.camera.depthOfField"
                      label="Độ sâu trường ảnh"
                    />
                  </VCol>
                </VRow>
              </VWindowItem>

              <VWindowItem value="lighting">
                <VRow>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppSelect
                      v-model="form.dna.lighting.toneKey"
                      label="Nhóm tông ánh sáng"
                      :items="LIGHTING_TONE_OPTIONS"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="form.dna.lighting.direction"
                      label="Hướng nguồn sáng"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="form.dna.lighting.contrast"
                      label="Độ tương phản"
                    />
                  </VCol>
                </VRow>
              </VWindowItem>

              <VWindowItem value="environment">
                <VRow>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="form.dna.environment.settingLabel"
                      label="Không gian bối cảnh"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="form.dna.environment.surface"
                      label="Bề mặt &amp; Chất liệu"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="form.dna.environment.atmosphere"
                      label="Không khí tổng thể"
                    />
                  </VCol>
                </VRow>
              </VWindowItem>

              <VWindowItem value="colorMood">
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="form.dna.colorMood.moodLabel"
                      label="Cảm xúc &amp; Sắc thái chủ đạo"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <div class="d-flex align-end gap-3">
                      <AppTextField
                        v-model="newHexInput"
                        label="Thêm mã màu DNA (#RRGGBB)"
                        placeholder="#38BDF8"
                        class="flex-grow-1"
                        @keyup.enter="addPaletteHex()"
                      />
                      <VBtn
                        variant="tonal"
                        color="primary"
                        prepend-icon="tabler-plus"
                        @click="addPaletteHex()"
                      >
                        Thêm
                      </VBtn>
                    </div>
                  </VCol>

                  <VCol cols="12">
                    <div class="text-caption text-medium-emphasis mb-2">
                      Bảng màu hiện tại ({{ form.dna.colorMood.paletteHex.length }}/6 — bấm để xóa):
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                      <VChip
                        v-for="hex in form.dna.colorMood.paletteHex"
                        :key="hex"
                        size="small"
                        variant="tonal"
                        color="secondary"
                        closable
                        @click:close="removePaletteHex(hex)"
                      >
                        <span
                          class="hex-dot me-2"
                          :style="{ backgroundColor: hex }"
                        />
                        {{ hex }}
                      </VChip>
                    </div>

                    <div class="text-caption text-medium-emphasis mb-2">
                      Gợi ý mã màu nhanh:
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                      <VChip
                        v-for="hex in COLOR_HEX_SUGGESTIONS"
                        :key="hex"
                        size="x-small"
                        variant="tonal"
                        color="primary"
                        class="cursor-pointer"
                        @click="addPaletteHex(hex)"
                      >
                        <span
                          class="hex-dot me-1"
                          :style="{ backgroundColor: hex }"
                        />
                        + {{ hex }}
                      </VChip>
                    </div>
                  </VCol>
                </VRow>
              </VWindowItem>
            </VWindow>
          </VCardText>
        </VCard>

        <!-- Card 4: Khung hình & Góc chụp (Shot Presets) -->
        <VCard>
          <VCardItem>
            <VCardTitle class="text-subtitle-1 font-weight-medium">
              Tỷ lệ khung hình &amp; Bộ góc chụp (Shot Presets)
            </VCardTitle>
            <VCardSubtitle class="text-body-2">
              Chọn các tỷ lệ khung hình hỗ trợ và góc chụp dựng sẵn đi kèm hồ sơ
            </VCardSubtitle>
          </VCardItem>

          <VCardText>
            <div class="text-caption text-medium-emphasis mb-2">
              Tỷ lệ khung hình áp dụng:
            </div>
            <div class="d-flex flex-wrap gap-2 mb-5">
              <VChip
                v-for="ratio in ASPECT_RATIO_OPTIONS"
                :key="ratio"
                :color="form.aspectRatios.includes(ratio) ? 'primary' : 'secondary'"
                :variant="form.aspectRatios.includes(ratio) ? 'flat' : 'tonal'"
                class="cursor-pointer"
                @click="toggleAspectRatio(ratio)"
              >
                <VIcon
                  v-if="form.aspectRatios.includes(ratio)"
                  icon="tabler-check"
                  size="16"
                  class="me-1"
                />
                {{ ratio }}
              </VChip>
            </div>

            <VRow>
              <VCol
                v-for="shot in form.shotPresets"
                :key="shot.key"
                cols="12"
                md="6"
              >
                <div class="border rounded pa-3 d-flex align-center justify-space-between gap-3 h-100">
                  <div>
                    <div class="d-flex align-center gap-2 mb-1">
                      <span class="text-body-2 font-weight-medium">
                        {{ shot.label }}
                      </span>
                      <VChip
                        size="x-small"
                        variant="tonal"
                        color="primary"
                      >
                        {{ shot.ratio }}
                      </VChip>
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ shot.note }}
                    </div>
                  </div>

                  <VSwitch
                    v-model="shot.enabled"
                    color="primary"
                    inset
                  />
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Cột phải: Thẻ xem trước trực tiếp & Xuất bản -->
      <VCol
        cols="12"
        lg="4"
      >
        <div class="text-subtitle-1 font-weight-medium mb-3">
          Xem trước thẻ hiển thị
        </div>

        <VisualDnaCard
          :style-item="previewCardItem"
          preview-only
        />

        <VCard class="mt-6">
          <VCardItem>
            <VCardTitle class="text-subtitle-1 font-weight-medium">
              Prompt 7 lớp &amp; Xuất bản
            </VCardTitle>
          </VCardItem>

          <VCardText>
            <div class="border rounded pa-3 mb-4">
              <div class="text-caption text-medium-emphasis mb-1">
                Preview chuỗi Prompt 7 lớp tự động:
              </div>
              <div class="text-body-2">
                {{ compiledPromptPreview }}
              </div>
            </div>

            <AppTextarea
              v-model="form.negativePrompt"
              label="Negative Prompt mặc định"
              rows="3"
              class="mb-4"
            />

            <VSwitch
              v-model="form.isActive"
              color="primary"
              label="Kích hoạt sẵn sàng sinh ảnh (Active)"
              class="mb-4"
            />

            <VBtn
              color="primary"
              block
              prepend-icon="tabler-device-floppy"
              @click="handleSaveStyle"
            >
              Lưu hồ sơ Visual Style
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.create-thumb-preview {
  inline-size: 260px;
  max-inline-size: 100%;
}

.hex-dot {
  display: inline-block;
  inline-size: 12px;
  block-size: 12px;
  border-radius: 50%;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
