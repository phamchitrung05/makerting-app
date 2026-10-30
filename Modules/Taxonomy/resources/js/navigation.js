/*
 * =====================================================================
 * CHỨC NĂNG FILE: Khai báo mục menu Sidebar cho module Taxonomy.
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: export order + default mảng nav item.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : không.
 * - OUTPUT: mảng nav item cho VerticalNav/HorizontalNav.
 * SIDE EFFECT: được navigation/vertical|horizontal/index.js gom lúc build.
 * =====================================================================
 */
export const order = 65

// Ghim lên đầu sidebar (cùng nhóm top với Blog).
export const position = 'top'

export default [
  {
    title: 'Phân loại',
    icon: { icon: 'tabler-tags' },
    children: [
      { title: 'Danh mục', to: 'admin-taxonomy-categories' },
      { title: 'Thẻ', to: 'admin-taxonomy-tags' },
    ],
  },
]
