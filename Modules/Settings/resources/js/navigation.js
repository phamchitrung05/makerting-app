/*
 * =====================================================================
 * CHỨC NĂNG FILE: Khai báo mục menu Sidebar cho module Settings.
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: export order + default mảng nav item.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : không.
 * - OUTPUT: mảng nav item cho VerticalNav/HorizontalNav.
 * SIDE EFFECT: được navigation/vertical|horizontal/index.js gom lúc build.
 * =====================================================================
 */
export const order = 65

// Ghim lên đầu sidebar (cùng nhóm top với Blog) để nằm ngay dưới heading BLOG.
export const position = 'top'

export default [
  // Header nhóm các mục cấu hình/vận hành.
  { heading: 'SYSTEM' },
  {
    title: 'Cài đặt',
    icon: { icon: 'tabler-settings' },
    children: [
      {
        title: 'Chung',
        to: 'admin-settings-general',
      },
    ],
  },
]
