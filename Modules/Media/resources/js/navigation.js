/*
 * =====================================================================
 * CHỨC NĂNG FILE: Khai báo mục menu Sidebar cho module Media.
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: export order + default mảng nav item.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : không.
 * - OUTPUT: mảng nav item cho VerticalNav/HorizontalNav.
 * SIDE EFFECT: được navigation/vertical|horizontal/index.js gom lúc build.
 * =====================================================================
 */
export const order = 70

// Ghim lên đầu sidebar (cùng nhóm top với Blog/Taxonomy).
export const position = 'top'

export default [
  {
    title: 'Thư viện Media',
    icon: { icon: 'tabler-photo' },
    to: 'admin-media-media',
  },
]
