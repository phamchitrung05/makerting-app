/*
 * =====================================================================
 * CHỨC NĂNG FILE: Khai báo mục menu Sidebar cho module VisualStyle.
 * =====================================================================
 * Đặt ngay sau WritingStyle (order = 62, position = 'top') để mục
 * 'Visual Styles' hiển thị chung dưới nhóm header 'DNA Content'.
 *
 * CÁC HÀM/METHOD TRONG FILE: export order, position và default mảng nav item.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : không.
 * - OUTPUT: mảng nav item cho VerticalNav/HorizontalNav.
 * SIDE EFFECT: được navigation/vertical|horizontal/index.js gom lúc build.
 * =====================================================================
 */
export const order = 63

// Ghim lên đầu sidebar cùng nhóm top 'DNA Content' của WritingStyle (order = 62).
export const position = 'top'

export default [
  {
    title: 'Visual Styles',
    icon: { icon: 'tabler-photo-spark' },
    to: 'admin-visual-style-visual-styles-list',
  },
]
