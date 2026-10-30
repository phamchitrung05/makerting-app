/*
 * =====================================================================
 * CHỨC NĂNG FILE: Khai báo mục menu Sidebar cho module Blog.
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: export order (thứ tự gom) + default mảng nav item.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : không.
 * - OUTPUT: mảng nav item { heading | title, to/icon/children } cho
 *           VerticalNav/HorizontalNav.
 * SIDE EFFECT: được navigation/vertical|horizontal/index.js gom lúc build;
 *              position 'top' ghim cả khối lên đầu sidebar (trước Apps & Pages).
 * =====================================================================
 */

// Thứ tự gom trong sidebar (module càng nhỏ càng lên trên).
export const order = 60

// Ghim lên đầu sidebar, trước nhóm 'Apps & Pages' của Vuexy.
export const position = 'top'

export default [
  // Head title phân nhóm hiển thị phía trên các mục của module.
  { heading: 'Blog' },
  {
    title: 'Bài viết',
    icon: { icon: 'tabler-article' },
    to: 'admin-blog-posts',
  },
  {
    title: 'Viết bằng AI',
    icon: { icon: 'tabler-wand' },
    to: 'admin-blog-ai-writer',
  },
]
