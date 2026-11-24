<!--
  =====================================================================
  CHỨC NĂNG FILE: Giao diện chính trang danh sách Visual Style (DNA ảnh).
  =====================================================================

  Chuẩn hóa giao diện theo ngôn ngữ thiết kế Vuexy của dự án (tương đồng
  với nhóm DNA Content — Writing Profiles) sử dụng bộ dữ liệu mẫu tĩnh
  (chưa gọi API backend):
  - Page Header chuẩn kèm 2 nút CTA (Phân tích từ ảnh / Tạo Visual Style).
  - Hàng 4 thẻ KPI tổng quan tính động từ dữ liệu tĩnh.
  - Thẻ bộ lọc gồm VTabs trạng thái, ô tìm kiếm, lọc tỷ lệ khung hình,
    nhóm ánh sáng và tùy chọn sắp xếp.
  - Lưới thẻ VisualDnaCard hiển thị Color Swatches, 5 phân vùng DNA và
    độ tin cậy AI, kèm TablePagination và hộp thoại xem nhanh chi tiết DNA.

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - statusCounts(): Đếm số lượng hồ sơ theo từng nhóm trạng thái.
  - kpiCards(): Tạo dữ liệu 4 thẻ KPI thống kê tổng quan.
  - filteredStyles(): Lọc danh sách theo tab trạng thái, từ khóa, tỷ lệ và ánh sáng.
  - sortedStyles(): Sắp xếp danh sách đã lọc theo tiêu chí đang chọn.
  - paginatedStyles(): Cắt trang hiện tại theo page và itemsPerPage.
  - resetFilters(): Đưa toàn bộ bộ lọc và trang hiện tại về mặc định.
  - openPreview(): Mở hộp thoại xem chi tiết 5 phân vùng DNA và prompt 7 lớp.
  - handleDuplicate(): Nhân bản 1 hồ sơ mẫu vào danh sách tĩnh cục bộ.
  - handleToggleArchive(): Chuyển đổi trạng thái lưu trữ / hoạt động trên dữ liệu tĩnh.
  - handleDelete(): Xóa hồ sơ mẫu khỏi danh sách tĩnh cục bộ.
  - goToCreate(): Điều hướng sang trang tạo mới `admin-visual-style-visual-styles-create`.
  - handlePreviewAction(): Hiển thị thông báo snackbar cho thao tác sẽ nối API ở giai đoạn sau.

  INPUT/OUTPUT CỦA COMPONENT (tổng thể):
  - INPUT : Không nhận prop bắt buộc; khởi tạo từ bộ dữ liệu DNA mẫu tĩnh.
  - OUTPUT: Render hoàn chỉnh trang quản lý Visual Styles theo chuẩn Vuexy.
  =====================================================================
-->
<script setup>
import thumbColorBlock from '@images/banner/banner-11.jpg'
import thumbEditorialFloral from '@images/banner/banner-14.jpg'
import thumbAnalog from '@images/banner/banner-16.jpg'
import thumbLifestyle from '@images/banner/banner-20.jpg'
import thumbArchitectural from '@images/banner/banner-21.jpg'
import thumbPortrait from '@images/banner/banner-29.jpg'
import thumbPopPastel from '@images/banner/banner-3.jpg'
import thumbNoirTech from '@images/banner/banner-30.jpg'
import thumbCyberpunk from '@images/banner/banner-34.jpg'
import thumbScandinavian from '@images/banner/banner-4.jpg'
import thumbBakeryPastel from '@images/banner/banner-5.jpg'
import thumbPackshot from '@images/banner/banner-6.jpg'
import thumbFlatlay from '@images/banner/banner-7.jpg'
import thumbSports from '@images/pages/puma-shoes.jpeg'
import { useSnackbar } from '@/composables/useSnackbar'
import VisualDnaCard from '../components/VisualDnaCard.vue'

const router = useRouter()
const snackbar = useSnackbar()

const INITIAL_VISUAL_STYLES = [
  {
    id: 1,
    name: 'Cinematic Neo-Noir Tech',
    description: 'Ánh sáng tương phản cao kết hợp tông xanh đêm và điểm nhấn hổ phách, phù hợp chủ đề công nghệ và video phân tích chuyên sâu.',
    thumbnailUrl: thumbNoirTech,
    version: 3,
    status: 'active',
    confidence: 94,
    shotsCount: 5,
    aspectRatios: ['16:9', '9:16', '1:1'],
    taxonomyTags: ['Công nghệ', 'YouTube'],
    accentColor: 'primary',
    icon: 'tabler-aperture',
    updatedAt: 'Hôm nay, 14:20',
    negativePrompt: 'chữ chìm, watermark, da nhựa quá mịn, cháy sáng neon, bố cục lệch nặng',
    compiledPromptPreview: 'Chụp cận trung ngang tầm mắt bằng ống kính 85mm f/1.4 trên bối cảnh studio đêm công nghệ, ánh sáng điện ảnh trầm tương phản mạnh với viền sáng xanh lạnh và điểm nhấn vàng hổ phách, bảng màu #0F172A #1E3A8A #38BDF8 #F59E0B.',
    dna: {
      composition: { label: 'Một phần ba, tiền cảnh sâu' },
      camera: { lensLabel: '85mm f/1.4', angleLabel: 'Ngang tầm mắt' },
      lighting: { toneKey: 'moody_cinematic', toneLabel: 'Điện ảnh trầm' },
      environment: { settingLabel: 'Studio công nghệ ánh đêm' },
      colorMood: {
        moodLabel: 'Sắc lạnh chuyên sâu, điểm nhấn ấm',
        paletteHex: ['#0F172A', '#1E3A8A', '#38BDF8', '#F59E0B', '#E2E8F0'],
      },
    },
  },
  {
    id: 2,
    name: 'Tối Giản Bắc Âu (Scandinavian)',
    description: 'Bố cục thoáng sạch trên nền sáng trung tính, độ chuyển khối dịu nhẹ tôn trọn chất liệu sản phẩm và trang biên tập.',
    thumbnailUrl: thumbScandinavian,
    version: 2,
    status: 'active',
    confidence: 91,
    shotsCount: 4,
    aspectRatios: ['1:1', '4:5', '16:9'],
    taxonomyTags: ['Thương hiệu', 'Editorial'],
    accentColor: 'info',
    icon: 'tabler-layout-grid',
    updatedAt: 'Hôm qua, 17:05',
    negativePrompt: 'bối cảnh rối, bóng gắt đen kịt, màu bão hòa gắt, chi tiết thừa',
    compiledPromptPreview: 'Khung hình tối giản cân đối chụp bằng ống kính 50mm f/2.0 trong studio ánh sáng khuếch tán mềm đều, chất liệu tự nhiên sạch sẽ trên nền trắng xám ấm, bảng màu #F8FAFC #E2E8F0 #CBD5E1 #94A3B8.',
    dna: {
      composition: { label: 'Đối xứng thoáng, khoảng thở lớn' },
      camera: { lensLabel: '50mm f/2.0', angleLabel: 'Góc 3/4 cao nhẹ' },
      lighting: { toneKey: 'studio_clean', toneLabel: 'Studio sắc nét' },
      environment: { settingLabel: 'Nền xám sáng vật liệu tự nhiên' },
      colorMood: {
        moodLabel: 'Thanh lịch, trung tính, tinh gọn',
        paletteHex: ['#F8FAFC', '#E2E8F0', '#CBD5E1', '#94A3B8', '#334155'],
      },
    },
  },
  {
    id: 3,
    name: 'Ánh Nắng Vàng Lifestyle',
    description: 'Sắc nắng giờ vàng ấm áp tự nhiên cùng chiều sâu trường ảnh mỏng, tạo cảm giác gần gũi cho nội dung mạng xã hội.',
    thumbnailUrl: thumbLifestyle,
    version: 2,
    status: 'active',
    confidence: 89,
    shotsCount: 4,
    aspectRatios: ['4:5', '9:16', '1:1'],
    taxonomyTags: ['Social', 'Lifestyle'],
    accentColor: 'warning',
    icon: 'tabler-sun',
    updatedAt: '20/11/2026',
    negativePrompt: 'ánh sáng huỳnh quang xanh xám, biểu cảm cứng, bóng đổ lốm đốm gắt',
    compiledPromptPreview: 'Góc chụp đời thường tự nhiên với ống kính 35mm f/1.8 ngược sáng giờ vàng tạo viền tóc ấm áp, không gian ngoại cảnh buổi chiều dịu nhẹ và bảng màu mật ong ấm #FEF3C7 #F59E0B #D97706 #78350F.',
    dna: {
      composition: { label: 'Lệch tâm tự nhiên, bokeh tiền cảnh' },
      camera: { lensLabel: '35mm f/1.8', angleLabel: 'Ngang tầm mắt tự nhiên' },
      lighting: { toneKey: 'warm_golden', toneLabel: 'Ánh sáng ấm' },
      environment: { settingLabel: 'Ngoại cảnh quán cà phê & phố nắng' },
      colorMood: {
        moodLabel: 'Ấm áp, tràn đầy năng lượng sống',
        paletteHex: ['#FEF3C7', '#F59E0B', '#D97706', '#78350F', '#FFFBEB'],
      },
    },
  },
  {
    id: 4,
    name: 'Cyberpunk Neon Futurism',
    description: 'Bề mặt phản chiếu ướt kết hợp dải đèn tím cyan nổi bật, tối ưu cho ảnh bìa AI concept và video ngắn tốc độ nhanh.',
    thumbnailUrl: thumbCyberpunk,
    version: 1,
    status: 'analyzed',
    confidence: 87,
    shotsCount: 3,
    aspectRatios: ['16:9', '9:16'],
    taxonomyTags: ['AI Concept', 'Shorts'],
    accentColor: 'secondary',
    icon: 'tabler-sparkles',
    updatedAt: '19/11/2026',
    negativePrompt: 'ánh sáng phẳng thiếu chiều sâu, biến dạng chữ biển hiệu, nhiễu hạt bệt màu',
    compiledPromptPreview: 'Góc máy rộng 24mm hơi hướng từ dưới lên tạo cảm giác kịch tính trong không gian tương lai phản chiếu ánh tím và xanh ngọc neon, độ tương phản rực nét với bảng màu #1E1B4B #7C3AED #EC4899 #06B6D4.',
    dna: {
      composition: { label: 'Đường dẫn chéo kịch tính' },
      camera: { lensLabel: '24mm Wide', angleLabel: 'Góc thấp hướng lên' },
      lighting: { toneKey: 'neon_cyberpunk', toneLabel: 'Neon / Công nghệ' },
      environment: { settingLabel: 'Phòng lab tương lai & phản chiếu ướt' },
      colorMood: {
        moodLabel: 'Tương lai rực rỡ, năng lượng cao',
        paletteHex: ['#1E1B4B', '#7C3AED', '#EC4899', '#06B6D4', '#F8FAFC'],
      },
    },
  },
  {
    id: 5,
    name: 'Chân Dung Tạp Chí High-Key',
    description: 'Tái tạo tông da trung thực với nguồn sáng softbox lớn và phông vô cực sạch sẽ, chuẩn bài viết chân dung chuyên gia.',
    thumbnailUrl: thumbPortrait,
    version: 4,
    status: 'active',
    confidence: 96,
    shotsCount: 3,
    aspectRatios: ['4:5', '1:1'],
    taxonomyTags: ['Chân dung', 'Tạp chí'],
    accentColor: 'success',
    icon: 'tabler-mood-smile',
    updatedAt: '18/11/2026',
    negativePrompt: 'da bóng dầu, bóng mũi gắt, lệch màu tông da, méo phối cảnh góc rộng',
    compiledPromptPreview: 'Chân dung trung cận bằng ống kính 85mm Prime trên phông nền vô cực sáng tinh khiết, ánh sáng studio hai nguồn tách khối nhẹ nhàng và giữ vân da tự nhiên, bảng màu #FFFFFF #F1F5F9 #E2C2A4 #8D5524.',
    dna: {
      composition: { label: 'Trung tâm cân bằng tỷ lệ vàng' },
      camera: { lensLabel: '85mm Prime', angleLabel: 'Ngang mặt trực diện' },
      lighting: { toneKey: 'studio_clean', toneLabel: 'Studio sắc nét' },
      environment: { settingLabel: 'Phông vô cực sáng tạp chí' },
      colorMood: {
        moodLabel: 'Sang trọng, tin cậy, sắc nét',
        paletteHex: ['#FFFFFF', '#F1F5F9', '#E2C2A4', '#8D5524', '#1E293B'],
      },
    },
  },
  {
    id: 6,
    name: 'Café Thủ Công Ấm Áp (Flatlay)',
    description: 'Bố cục chụp phẳng từ trên xuống kết hợp chất liệu gỗ mộc và gốm thủ công, phù hợp chiến dịch F&B và sản phẩm bàn ăn.',
    thumbnailUrl: thumbFlatlay,
    version: 1,
    status: 'analyzed',
    confidence: 84,
    shotsCount: 2,
    aspectRatios: ['1:1', '4:5'],
    taxonomyTags: ['F&B', 'Sản phẩm'],
    accentColor: 'warning',
    icon: 'tabler-palette',
    updatedAt: '17/11/2026',
    negativePrompt: 'đèn flash trực diện chói lóa, màu thực phẩm ngả xanh, góc nghiêng méo bàn',
    compiledPromptPreview: 'Góc chụp vuông góc 90 độ từ trên xuống với ống kính 50mm Macro trên mặt bàn gỗ sồi mộc, ánh sáng cửa sổ bên xiên dịu tạo bóng mềm tự nhiên cùng bảng màu cà phê rang #451A03 #92400E #D97706 #FDE68A.',
    dna: {
      composition: { label: 'Flatlay sắp đặt theo lưới tự nhiên' },
      camera: { lensLabel: '50mm Macro', angleLabel: 'Thẳng đứng 90 độ' },
      lighting: { toneKey: 'warm_golden', toneLabel: 'Ánh sáng ấm' },
      environment: { settingLabel: 'Bàn gỗ mộc & đạo cụ gốm' },
      colorMood: {
        moodLabel: 'Thủ công mộc mạc, đậm đà vị giác',
        paletteHex: ['#451A03', '#92400E', '#D97706', '#FDE68A', '#FAF5FF'],
      },
    },
  },
  {
    id: 7,
    name: 'Thể Thao Tốc Độ Động',
    description: 'Bắt chuyển động tốc độ cao kèm vệt ánh sáng năng lượng mạnh và gam đỏ cam giàu kịch tính cho thumbnail thể thao.',
    thumbnailUrl: thumbSports,
    version: 1,
    status: 'analyzing',
    confidence: 62,
    shotsCount: 2,
    aspectRatios: ['16:9', '9:16'],
    taxonomyTags: ['Thể thao', 'Thumbnail'],
    accentColor: 'error',
    icon: 'tabler-wand',
    updatedAt: '16/11/2026',
    negativePrompt: 'nhòe chủ thể chính, ánh sáng yếu xám xịt, tư thế tĩnh thiếu chuyển động',
    compiledPromptPreview: 'Ống kính tele 70-200mm khóa nét chủ thể giữa đường chạy đêm, ánh sáng rim light đỏ cam mạnh tách khỏi nền tối sâu tạo lực chuyển động rõ rệt.',
    dna: {
      composition: { label: 'Đường chéo động chừa khoảng hướng chạy' },
      camera: { lensLabel: '70-200mm Tele', angleLabel: 'Ngang hông bám chuyển động' },
      lighting: { toneKey: 'moody_cinematic', toneLabel: 'Điện ảnh trầm' },
      environment: { settingLabel: 'Đường chạy đêm ánh đèn sân' },
      colorMood: {
        moodLabel: 'Quyết liệt, bứt tốc, tương phản gắt',
        paletteHex: ['#18181B', '#EF4444', '#F97316', '#52525B', '#FAFAFA'],
      },
    },
  },
  {
    id: 8,
    name: 'Di Sản Film Analog 35mm',
    description: 'Hạt phim mịn hoài niệm với độ tương phản dịu và sắc trầm tự nhiên, đang hoàn thiện bộ góc chụp kể chuyện thương hiệu.',
    thumbnailUrl: thumbAnalog,
    version: 1,
    status: 'draft',
    confidence: 45,
    shotsCount: 1,
    aspectRatios: ['4:5', '16:9'],
    taxonomyTags: ['Analog', 'Storytelling'],
    accentColor: 'secondary',
    icon: 'tabler-camera',
    updatedAt: '15/11/2026',
    negativePrompt: 'nét sắc kỹ thuật số quá gắt, HDR giả tạo, bão hòa màu neon',
    compiledPromptPreview: 'Chụp film 35mm trong ánh chiều tự nhiên trên nền kiến trúc phố cổ, dải chuyển vùng tối dịu nhẹ mang sắc trầm cổ điển.',
    dna: {
      composition: { label: 'Khung lồng khung tự nhiên' },
      camera: { lensLabel: '35mm Film', angleLabel: 'Góc quan sát tự nhiên' },
      lighting: { toneKey: 'natural_minimal', toneLabel: 'Tự nhiên tối giản' },
      environment: { settingLabel: 'Phố cổ chiều muộn' },
      colorMood: {
        moodLabel: 'Hoài niệm, trầm lắng, chân thực',
        paletteHex: ['#292524', '#78716C', '#A8A29E', '#E7E5E4', '#D6D3D1'],
      },
    },
  },
  {
    id: 9,
    name: 'Thương Mại Điện Tử Packshot',
    description: 'Hồ sơ ánh sáng hộp chụp sản phẩm nền trắng chuẩn sàn TMĐT, giữ chính xác biên cạnh và màu sắc bao bì gốc.',
    thumbnailUrl: thumbPackshot,
    version: 2,
    status: 'archived',
    confidence: 88,
    shotsCount: 3,
    aspectRatios: ['1:1', '4:5'],
    taxonomyTags: ['E-Commerce', 'Packshot'],
    accentColor: 'info',
    icon: 'tabler-stack',
    updatedAt: '10/11/2026',
    negativePrompt: 'nền xám bẩn, đổ bóng kéo dài che sản phẩm, phản chiếu người chụp',
    compiledPromptPreview: 'Chụp macro 100mm thẳng góc sản phẩm đặt trong hộp sáng đồng đều 5500K, nền trắng tinh khiết và viền bao bì sắc nét.',
    dna: {
      composition: { label: 'Chính giữa chiếm 75% khung hình' },
      camera: { lensLabel: '100mm Macro', angleLabel: 'Chính diện 15 độ' },
      lighting: { toneKey: 'studio_clean', toneLabel: 'Studio sắc nét' },
      environment: { settingLabel: 'Hộp sáng thương mại 5500K' },
      colorMood: {
        moodLabel: 'Trung thực màu sắc, chuẩn bán hàng',
        paletteHex: ['#FFFFFF', '#E5E7EB', '#9CA3AF', '#2563EB', '#111827'],
      },
    },
  },
  {
    id: 10,
    name: 'Kiến Trúc Kính Hiện Đại',
    description: 'Đường nét hình học dứt khoát trên nền trời xanh trong, phù hợp chủ đề bất động sản cao cấp và doanh nghiệp B2B.',
    thumbnailUrl: thumbArchitectural,
    version: 2,
    status: 'active',
    confidence: 92,
    shotsCount: 4,
    aspectRatios: ['16:9', '4:5', '1:1'],
    taxonomyTags: ['B2B', 'Kiến trúc'],
    accentColor: 'primary',
    icon: 'tabler-frame',
    updatedAt: '09/11/2026',
    negativePrompt: 'méo đường thẳng, dây điện vướng trời, màu ám xám u ám',
    compiledPromptPreview: 'Chụp góc nghiêng 24mm shift giữ thẳng đường đứng tòa nhà kính xanh dương hiện đại dưới bầu trời quang đãng ban trưa.',
    dna: {
      composition: { label: 'Khối hình học bất đối xứng mạnh' },
      camera: { lensLabel: '24mm Tilt-Shift', angleLabel: 'Góc ngước kiến trúc' },
      lighting: { toneKey: 'studio_clean', toneLabel: 'Studio sắc nét' },
      environment: { settingLabel: 'Mặt dựng kính đô thị ban ngày' },
      colorMood: {
        moodLabel: 'Minh bạch, hiện đại, chuẩn mực',
        paletteHex: ['#0284C7', '#38BDF8', '#E2E8F0', '#F8FAFC', '#0F172A'],
      },
    },
  },
  {
    id: 11,
    name: 'Hoa Anh Đào Tối Giản Studio',
    description: 'Ánh sáng tán xạ cực mềm trên nền trắng tinh khôi cùng điểm nhấn cánh hoa pastel, hợp ngành làm đẹp và quà tặng.',
    thumbnailUrl: thumbEditorialFloral,
    version: 1,
    status: 'active',
    confidence: 90,
    shotsCount: 3,
    aspectRatios: ['4:5', '1:1'],
    taxonomyTags: ['Beauty', 'Minimal'],
    accentColor: 'info',
    icon: 'tabler-sparkles',
    updatedAt: '08/11/2026',
    negativePrompt: 'bóng đen cứng, màu hoa gắt rực, nền loang xám không đều',
    compiledPromptPreview: 'Bố cục tĩnh vật tối giản chụp bằng ống kính 50mm f/2.8 trên phông trắng đồng nhất, ánh sáng softbox tán xạ tạo độ mềm tinh tế.',
    dna: {
      composition: { label: 'Tĩnh vật cân bằng khoảng âm rộng' },
      camera: { lensLabel: '50mm f/2.8', angleLabel: 'Ngang mặt bàn' },
      lighting: { toneKey: 'studio_clean', toneLabel: 'Studio sắc nét' },
      environment: { settingLabel: 'Studio phông trắng ánh sáng mềm' },
      colorMood: {
        moodLabel: 'Tinh khiết, thanh thoát, cao cấp',
        paletteHex: ['#F8FAFC', '#FCE7F3', '#F9A8D4', '#A5F3FC', '#64748B'],
      },
    },
  },
  {
    id: 12,
    name: 'Color Block Sắc Độ Tương Phản',
    description: 'Cặp mảng màu bổ túc vàng rực và xanh ngọc tách khối mạnh, giúp thumbnail khóa ánh nhìn ngay giây đầu tiên.',
    thumbnailUrl: thumbColorBlock,
    version: 2,
    status: 'analyzed',
    confidence: 86,
    shotsCount: 3,
    aspectRatios: ['16:9', '1:1'],
    taxonomyTags: ['ColorBlock', 'Ads'],
    accentColor: 'warning',
    icon: 'tabler-palette',
    updatedAt: '07/11/2026',
    negativePrompt: 'màu bùn đục, chi tiết nền rườm rà, thiếu tách lớp tiền hậu',
    compiledPromptPreview: 'Khung hình chia hai mảng màu vàng và xanh ngọc bão hòa cao chụp chính diện, độ sắc nét bề mặt vật liệu rõ ràng.',
    dna: {
      composition: { label: 'Chia đôi mảng màu mạnh lệch 1/3' },
      camera: { lensLabel: '35mm f/4', angleLabel: 'Chính diện phẳng' },
      lighting: { toneKey: 'warm_golden', toneLabel: 'Ánh sáng ấm' },
      environment: { settingLabel: 'Mảng tường kiến trúc sơn màu' },
      colorMood: {
        moodLabel: 'Năng lượng, sáng tạo, dễ nhớ',
        paletteHex: ['#FACC15', '#06B6D4', '#0F766E', '#F8FAFC', '#1E293B'],
      },
    },
  },
  {
    id: 13,
    name: 'Pop Art Studio Bóng Đổ Sắc',
    description: 'Đèn spotlight tạo bóng đổ viền cứng theo phong cách Pop Art hiện đại, tối ưu banner sản phẩm trẻ trung.',
    thumbnailUrl: thumbPopPastel,
    version: 1,
    status: 'active',
    confidence: 89,
    shotsCount: 3,
    aspectRatios: ['1:1', '9:16'],
    taxonomyTags: ['PopArt', 'Campaign'],
    accentColor: 'error',
    icon: 'tabler-brush',
    updatedAt: '05/11/2026',
    negativePrompt: 'ánh sáng dịu mờ nhạt, bóng tán nhòe, tông màu xỉn tối',
    compiledPromptPreview: 'Chụp studio đèn điểm đơn trực tiếp trên nền hồng pastel tạo bóng đổ viền sắc nét tương phản tông vàng rực.',
    dna: {
      composition: { label: 'Chủ thể đơn độc giữ tâm khung' },
      camera: { lensLabel: '50mm Studio', angleLabel: 'Chéo 45 độ từ trên' },
      lighting: { toneKey: 'studio_clean', toneLabel: 'Studio sắc nét' },
      environment: { settingLabel: 'Nền màu studio ánh đèn cứng' },
      colorMood: {
        moodLabel: 'Táo bạo, trẻ trung, nổi bật feed',
        paletteHex: ['#FBCFE8', '#FDE047', '#4C1D95', '#EC4899', '#1E1B4B'],
      },
    },
  },
  {
    id: 14,
    name: 'Pastel Ngọt Ngào Flatlay',
    description: 'Bố cục tối giản theo mép khung với tông pastel nhẹ nhàng, tạo khoảng trống tự nhiên để chèn tiêu đề quảng cáo.',
    thumbnailUrl: thumbBakeryPastel,
    version: 1,
    status: 'draft',
    confidence: 54,
    shotsCount: 2,
    aspectRatios: ['1:1', '4:5'],
    taxonomyTags: ['Pastel', 'Banner'],
    accentColor: 'secondary',
    icon: 'tabler-copy',
    updatedAt: '03/11/2026',
    negativePrompt: 'chủ thể chen giữa gây rối chữ, bóng tối nặng, màu gắt chói',
    compiledPromptPreview: 'Góc chụp từ trên xuống chừa khoảng trống lớn ở tâm khung trên nền hồng pastel mềm mại cho banner chèn tiêu đề.',
    dna: {
      composition: { label: 'Mép khung viền, tâm thoáng' },
      camera: { lensLabel: '35mm Macro', angleLabel: 'Thẳng đứng từ trên xuống' },
      lighting: { toneKey: 'natural_minimal', toneLabel: 'Tự nhiên tối giản' },
      environment: { settingLabel: 'Mặt phẳng nền pastel khuếch tán' },
      colorMood: {
        moodLabel: 'Nhẹ nhàng, ngọt ngào, dễ đọc chữ',
        paletteHex: ['#FBCFE8', '#F472B6', '#D9F99D', '#FEF08A', '#831843'],
      },
    },
  },
]

const styleItems = ref(INITIAL_VISUAL_STYLES.map(item => ({ ...item })))
const selectedStatus = ref('all')
const searchQuery = ref('')
const selectedRatio = ref(null)
const selectedLighting = ref(null)
const sortBy = ref('newest')
const page = ref(1)
const itemsPerPage = ref(12)

const previewDialog = ref(false)
const activePreviewItem = ref(null)

const aspectRatioOptions = [
  { title: 'Ngang 16:9 (Thumbnail / Hero)', value: '16:9' },
  { title: 'Dọc 9:16 (Shorts / Reels)', value: '9:16' },
  { title: 'Vuông 1:1 (Feed / Avatar)', value: '1:1' },
  { title: 'Dọc 4:5 (Social Feed)', value: '4:5' },
]

const lightingOptions = [
  { title: 'Điện ảnh trầm', value: 'moody_cinematic' },
  { title: 'Studio sắc nét', value: 'studio_clean' },
  { title: 'Ánh sáng ấm', value: 'warm_golden' },
  { title: 'Neon / Công nghệ', value: 'neon_cyberpunk' },
  { title: 'Tự nhiên tối giản', value: 'natural_minimal' },
]

const sortOptions = [
  { title: 'Mới nhất', value: 'newest' },
  { title: 'Độ tin cậy cao nhất', value: 'confidence' },
  { title: 'Tên A–Z', value: 'name' },
]

/*
 * CHỨC NĂNG: Tính tổng số lượng hồ sơ theo từng tab trạng thái.
 * INPUT: styleItems.value.
 * OUTPUT: Object { all, active, analyzed, pending, archived }.
 */
const statusCounts = computed(() => {
  const list = styleItems.value

  return {
    all: list.length,
    active: list.filter(i => i.status === 'active').length,
    analyzed: list.filter(i => i.status === 'analyzed').length,
    pending: list.filter(i => ['draft', 'analyzing'].includes(i.status)).length,
    archived: list.filter(i => i.status === 'archived').length,
  }
})

/*
 * CHỨC NĂNG: Dữ liệu 4 thẻ KPI tổng quan phía trên danh sách.
 * INPUT: statusCounts.value.
 * OUTPUT: Mảng 4 object KPI { label, value, color, icon }.
 */
const kpiCards = computed(() => [
  {
    label: 'Tổng hồ sơ DNA',
    value: statusCounts.value.all,
    color: 'primary',
    icon: 'tabler-photo-spark',
  },
  {
    label: 'Đang hoạt động',
    value: statusCounts.value.active,
    color: 'success',
    icon: 'tabler-circle-check',
  },
  {
    label: 'Chờ duyệt & Nháp',
    value: statusCounts.value.analyzed + statusCounts.value.pending,
    color: 'warning',
    icon: 'tabler-clock',
  },
  {
    label: 'Đã lưu trữ',
    value: statusCounts.value.archived,
    color: 'secondary',
    icon: 'tabler-archive',
  },
])

const statusTabs = computed(() => [
  { value: 'all', title: 'Tất cả', count: statusCounts.value.all, color: 'primary' },
  { value: 'active', title: 'Hoạt động', count: statusCounts.value.active, color: 'success' },
  { value: 'analyzed', title: 'Đã giải mã', count: statusCounts.value.analyzed, color: 'info' },
  { value: 'pending', title: 'Nháp & Đang xử lý', count: statusCounts.value.pending, color: 'warning' },
  { value: 'archived', title: 'Lưu trữ', count: statusCounts.value.archived, color: 'secondary' },
])

/*
 * CHỨC NĂNG: Lọc danh sách hồ sơ theo tab trạng thái, từ khóa, tỷ lệ và ánh sáng.
 * INPUT: styleItems, selectedStatus, searchQuery, selectedRatio, selectedLighting.
 * OUTPUT: Mảng hồ sơ phù hợp điều kiện lọc hiện tại.
 */
const filteredStyles = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase()

  return styleItems.value.filter(item => {
    if (selectedStatus.value === 'active' && item.status !== 'active')
      return false
    if (selectedStatus.value === 'analyzed' && item.status !== 'analyzed')
      return false
    if (selectedStatus.value === 'pending' && !['draft', 'analyzing'].includes(item.status))
      return false
    if (selectedStatus.value === 'archived' && item.status !== 'archived')
      return false

    if (selectedRatio.value && !item.aspectRatios.includes(selectedRatio.value))
      return false

    if (selectedLighting.value && item.dna.lighting.toneKey !== selectedLighting.value)
      return false

    if (keyword) {
      const searchable = [
        item.name,
        item.description,
        item.dna.lighting.toneLabel,
        item.dna.camera.lensLabel,
        ...item.taxonomyTags,
      ]
        .join(' ')
        .toLowerCase()

      if (!searchable.includes(keyword))
        return false
    }

    return true
  })
})

/*
 * CHỨC NĂNG: Sắp xếp danh sách đã lọc theo tiêu chí đang chọn mà không đột biến mảng gốc.
 * INPUT: filteredStyles.value, sortBy.value.
 * OUTPUT: Mảng hồ sơ đã sắp xếp.
 */
const sortedStyles = computed(() => {
  const list = [...filteredStyles.value]

  if (sortBy.value === 'confidence')
    return list.sort((a, b) => b.confidence - a.confidence)

  if (sortBy.value === 'name')
    return list.sort((a, b) => a.name.localeCompare(b.name, 'vi'))

  return list.sort((a, b) => a.id - b.id)
})

/*
 * CHỨC NĂNG: Cắt lát danh sách hồ sơ theo trang hiện tại cho lưới thẻ và TablePagination.
 * INPUT: sortedStyles.value, page.value, itemsPerPage.value.
 * OUTPUT: Mảng tối đa itemsPerPage thẻ.
 */
const paginatedStyles = computed(() => {
  const start = (page.value - 1) * itemsPerPage.value

  return sortedStyles.value.slice(start, start + itemsPerPage.value)
})

watch([selectedStatus, searchQuery, selectedRatio, selectedLighting, sortBy], () => {
  page.value = 1
})

/*
 * CHỨC NĂNG: Đặt lại toàn bộ bộ lọc tìm kiếm về trạng thái mặc định.
 * INPUT: không có tham số.
 * OUTPUT: void — reset filter refs và quay về trang 1.
 */
function resetFilters() {
  selectedStatus.value = 'all'
  searchQuery.value = ''
  selectedRatio.value = null
  selectedLighting.value = null
  sortBy.value = 'newest'
  page.value = 1
}

/*
 * CHỨC NĂNG: Mở hộp thoại xem trước chi tiết 5 phân vùng DNA và prompt 7 lớp của thẻ được chọn.
 * INPUT: item (Object hồ sơ Visual Style).
 * OUTPUT: void — cập nhật activePreviewItem và bật previewDialog.
 */
function openPreview(item) {
  activePreviewItem.value = item
  previewDialog.value = true
}

/*
 * CHỨC NĂNG: Nhân bản một hồ sơ Visual Style vào danh sách dữ liệu tĩnh cục bộ.
 * INPUT: item (Object hồ sơ nguồn).
 * OUTPUT: void — thêm bản sao ở đầu danh sách ở trạng thái Nháp và hiện snackbar.
 */
function handleDuplicate(item) {
  const nextId = styleItems.value.reduce((max, row) => Math.max(max, row.id), 0) + 1
  const cloned = {
    ...item,
    id: nextId,
    name: `${item.name} (Bản sao)`,
    version: 1,
    status: 'draft',
    updatedAt: 'Vừa tạo',
  }

  styleItems.value = [cloned, ...styleItems.value]
  snackbar.success(`Đã nhân bản hồ sơ "${item.name}" ở trạng thái Nháp.`)
}

/*
 * CHỨC NĂNG: Chuyển đổi trạng thái giữa Lưu trữ và Hoạt động trên danh sách mẫu tĩnh.
 * INPUT: item (Object hồ sơ cần chuyển trạng thái).
 * OUTPUT: void — cập nhật trạng thái trong state và hiện snackbar phản hồi.
 */
function handleToggleArchive(item) {
  const nextStatus = item.status === 'archived' ? 'active' : 'archived'

  styleItems.value = styleItems.value.map(row => (
    row.id === item.id
      ? { ...row, status: nextStatus, updatedAt: 'Vừa cập nhật' }
      : row
  ))

  snackbar.info(
    nextStatus === 'archived'
      ? `Đã chuyển "${item.name}" vào kho lưu trữ.`
      : `Đã khôi phục "${item.name}" về trạng thái Hoạt động.`,
  )
}

/*
 * CHỨC NĂNG: Xóa hồ sơ mẫu khỏi danh sách tĩnh cục bộ.
 * INPUT: item (Object hồ sơ cần xóa).
 * OUTPUT: void — lọc bỏ bản ghi và cập nhật trang hiện tại nếu cần.
 */
function handleDelete(item) {
  styleItems.value = styleItems.value.filter(row => row.id !== item.id)
  if ((page.value - 1) * itemsPerPage.value >= sortedStyles.value.length && page.value > 1)
    page.value -= 1

  snackbar.warning(`Đã xóa hồ sơ mẫu "${item.name}" khỏi danh sách xem trước.`)
}

/*
 * CHỨC NĂNG: Điều hướng tới trang tạo mới Visual Style kèm chế độ khởi tạo ban đầu.
 * INPUT: mode ('analyze' | 'manual').
 * OUTPUT: Promise điều hướng qua Vue Router.
 */
function goToCreate(mode = 'manual') {
  router.push({
    name: 'admin-visual-style-visual-styles-create',
    query: { mode },
  })
}

/*
 * CHỨC NĂNG: Hiển thị phản hồi cho các nút thao tác tạo/chỉnh sửa ở chế độ giao diện tĩnh.
 * INPUT: actionLabel (string tên hành động người dùng bấm).
 * OUTPUT: void — gọi snackbar info mô tả tính năng sẽ nối API ở giai đoạn kế tiếp.
 */
function handlePreviewAction(actionLabel) {
  snackbar.info(`${actionLabel}: đang hiển thị dữ liệu tĩnh để chuẩn hóa giao diện, sẽ kết nối API ở task backend kế tiếp.`)
}
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-start justify-sm-space-between gap-y-4 gap-x-6 mb-6">
      <div class="d-flex flex-column justify-center">
        <h4 class="text-h4 font-weight-medium mb-1">
          Visual Styles
        </h4>
        <div class="text-body-1 text-medium-emphasis">
          Quản lý hồ sơ DNA phong cách thị giác 5 phân vùng cho phân tích ảnh và biên dịch prompt 7 lớp
        </div>
      </div>

      <div class="d-flex gap-4 align-center flex-wrap">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-sparkles"
          @click="goToCreate('analyze')"
        >
          Phân tích từ ảnh
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="goToCreate('manual')"
        >
          Tạo Visual Style
        </VBtn>
      </div>
    </div>

    <!-- 4 KPI Cards -->
    <VRow class="mb-6">
      <VCol
        v-for="kpi in kpiCards"
        :key="kpi.label"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard class="h-100">
          <VCardText class="d-flex align-center">
            <VAvatar
              :color="kpi.color"
              variant="tonal"
              size="44"
              rounded
              class="me-3"
            >
              <VIcon
                :icon="kpi.icon"
                size="24"
              />
            </VAvatar>

            <div>
              <div class="text-body-2 text-medium-emphasis">
                {{ kpi.label }}
              </div>
              <div class="text-h4 font-weight-medium">
                {{ kpi.value }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Bộ lọc trạng thái & thanh tìm kiếm -->
    <VCard class="mb-6">
      <VCardText>
        <VTabs
          v-model="selectedStatus"
          show-arrows
        >
          <VTab
            v-for="tab in statusTabs"
            :key="tab.value"
            :value="tab.value"
          >
            {{ tab.title }}
            <VChip
              size="x-small"
              variant="tonal"
              :color="tab.color"
              class="ms-2"
            >
              {{ tab.count }}
            </VChip>
          </VTab>
        </VTabs>

        <VDivider class="my-4" />

        <VRow>
          <VCol
            cols="12"
            md="5"
          >
            <AppTextField
              v-model="searchQuery"
              label="Tìm kiếm hồ sơ"
              placeholder="Tìm theo tên, ánh sáng, ống kính, tag..."
              prepend-inner-icon="tabler-search"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppSelect
              v-model="selectedRatio"
              label="Tỷ lệ khung hình"
              :items="aspectRatioOptions"
              placeholder="Tất cả tỷ lệ"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <AppSelect
              v-model="selectedLighting"
              label="Nhóm ánh sáng"
              :items="lightingOptions"
              placeholder="Tất cả"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <AppSelect
              v-model="sortBy"
              label="Sắp xếp"
              :items="sortOptions"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Lưới thẻ Visual Style -->
    <div
      v-if="paginatedStyles.length"
      class="visual-style-grid"
    >
      <VisualDnaCard
        v-for="item in paginatedStyles"
        :key="item.id"
        :style-item="item"
        @preview="openPreview"
        @edit="handlePreviewAction(`Chỉnh sửa hồ sơ ${$event.name}`)"
        @duplicate="handleDuplicate"
        @toggle-archive="handleToggleArchive"
        @delete="handleDelete"
      />
    </div>

    <!-- Trạng thái không khớp bộ lọc -->
    <VCard v-else>
      <VCardText class="text-center py-12">
        <VAvatar
          color="primary"
          variant="tonal"
          size="64"
          class="mb-4"
        >
          <VIcon
            icon="tabler-photo-spark"
            size="32"
          />
        </VAvatar>

        <div class="text-subtitle-1 font-weight-medium mb-2">
          Không tìm thấy hồ sơ Visual Style phù hợp
        </div>
        <div class="text-body-2 text-medium-emphasis mb-6">
          Hãy thử thay đổi từ khóa tìm kiếm, tab trạng thái hoặc bỏ lọc tỷ lệ khung hình.
        </div>

        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-refresh"
          @click="resetFilters"
        >
          Xóa bộ lọc
        </VBtn>
      </VCardText>
    </VCard>

    <!-- Phân trang -->
    <VCard
      v-if="sortedStyles.length > itemsPerPage"
      class="mt-6"
    >
      <TablePagination
        v-model:page="page"
        :items-per-page="itemsPerPage"
        :total-items="sortedStyles.length"
      />
    </VCard>

    <!-- Hộp thoại xem nhanh chi tiết 5 phân vùng DNA -->
    <VDialog
      v-model="previewDialog"
      max-width="720"
    >
      <VCard v-if="activePreviewItem">
        <VCardItem>
          <template #prepend>
            <VAvatar
              :color="activePreviewItem.accentColor || 'primary'"
              variant="tonal"
              rounded
            >
              <VIcon :icon="activePreviewItem.icon || 'tabler-photo-spark'" />
            </VAvatar>
          </template>

          <VCardTitle class="text-subtitle-1 font-weight-medium">
            {{ activePreviewItem.name }}
          </VCardTitle>
          <VCardSubtitle class="text-body-2">
            Phiên bản v{{ activePreviewItem.version }} • Độ tin cậy {{ activePreviewItem.confidence }}% • {{ activePreviewItem.shotsCount }} góc chụp
          </VCardSubtitle>

          <template #append>
            <IconBtn
              aria-label="Đóng xem trước"
              @click="previewDialog = false"
            >
              <VIcon icon="tabler-x" />
            </IconBtn>
          </template>
        </VCardItem>

        <VDivider />

        <VCardText>
          <div class="d-flex flex-column flex-sm-row gap-4 mb-5">
            <div
              v-if="activePreviewItem.thumbnailUrl"
              class="preview-dialog-thumb flex-shrink-0"
            >
              <VImg
                :src="activePreviewItem.thumbnailUrl"
                :alt="activePreviewItem.name"
                :aspect-ratio="1"
                cover
                class="rounded"
              />
            </div>

            <div class="flex-grow-1">
              <p class="text-body-1 mb-3">
                {{ activePreviewItem.description }}
              </p>

              <div class="d-flex flex-wrap gap-2">
                <VChip
                  v-for="ratio in activePreviewItem.aspectRatios"
                  :key="ratio"
                  size="small"
                  variant="tonal"
                  color="primary"
                >
                  {{ ratio }}
                </VChip>
                <VChip
                  v-for="tag in activePreviewItem.taxonomyTags"
                  :key="tag"
                  size="small"
                  variant="tonal"
                  color="secondary"
                >
                  #{{ tag }}
                </VChip>
              </div>
            </div>
          </div>

          <div class="text-subtitle-1 font-weight-medium mb-3">
            5 Phân vùng DNA thị giác
          </div>

          <VRow class="mb-4">
            <VCol
              cols="12"
              sm="6"
            >
              <div class="border rounded pa-3 h-100">
                <div class="text-caption text-medium-emphasis mb-1">
                  1. Bố cục (Composition)
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ activePreviewItem.dna.composition.label }}
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <div class="border rounded pa-3 h-100">
                <div class="text-caption text-medium-emphasis mb-1">
                  2. Góc máy &amp; Ống kính (Camera)
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ activePreviewItem.dna.camera.lensLabel }} • {{ activePreviewItem.dna.camera.angleLabel }}
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <div class="border rounded pa-3 h-100">
                <div class="text-caption text-medium-emphasis mb-1">
                  3. Ánh sáng (Lighting)
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ activePreviewItem.dna.lighting.toneLabel }}
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <div class="border rounded pa-3 h-100">
                <div class="text-caption text-medium-emphasis mb-1">
                  4. Bối cảnh (Environment)
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ activePreviewItem.dna.environment.settingLabel }}
                </div>
              </div>
            </VCol>

            <VCol cols="12">
              <div class="border rounded pa-3">
                <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-2">
                  <span class="text-caption text-medium-emphasis">
                    5. Màu sắc &amp; Tâm trạng (Color &amp; Mood)
                  </span>
                  <div class="d-flex align-center gap-2">
                    <span
                      v-for="hex in activePreviewItem.dna.colorMood.paletteHex"
                      :key="hex"
                      class="d-inline-flex align-center gap-1 text-caption"
                    >
                      <span
                        class="preview-swatch"
                        :style="{ backgroundColor: hex }"
                      />
                      {{ hex }}
                    </span>
                  </div>
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ activePreviewItem.dna.colorMood.moodLabel }}
                </div>
              </div>
            </VCol>
          </VRow>

          <div class="border rounded pa-3 mb-3">
            <div class="text-caption text-medium-emphasis mb-1">
              Prompt 7 lớp xem trước (Compiled Preview)
            </div>
            <div class="text-body-2">
              {{ activePreviewItem.compiledPromptPreview }}
            </div>
          </div>

          <div class="border rounded pa-3">
            <div class="text-caption text-medium-emphasis mb-1">
              Negative Prompt mặc định
            </div>
            <div class="text-body-2 text-medium-emphasis">
              {{ activePreviewItem.negativePrompt }}
            </div>
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="previewDialog = false"
          >
            Đóng
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-pencil"
            @click="handlePreviewAction(`Chỉnh sửa hồ sơ ${activePreviewItem.name}`)"
          >
            Chỉnh sửa hồ sơ
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.visual-style-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 1199px) {
  .visual-style-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 959px) {
  .visual-style-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .visual-style-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 479px) {
  .visual-style-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

.preview-dialog-thumb {
  inline-size: 160px;
  max-inline-size: 100%;
}

.preview-swatch {
  display: inline-block;
  inline-size: 14px;
  block-size: 14px;
  border-radius: 50%;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
