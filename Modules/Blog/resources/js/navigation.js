/*
 * =====================================================================
 * CHỨC NĂNG FILE: Khai báo mục menu Sidebar cho module Blog.
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: export order (thứ tự gom) + default mảng nav item.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : không.
 * - OUTPUT: mảng nav item { title, to/icon/children } cho VerticalNav/HorizontalNav.
 * SIDE EFFECT: được navigation/vertical|horizontal/index.js gom lúc build.
 * =====================================================================
 */

// Thứ tự gom trong sidebar (module càng nhỏ càng lên trên).
export const order = 60

export default [
  {
    title: 'Blog',
    icon: { icon: 'tabler-article' },
    children: [
      {
        title: 'Bài viết',
        to: 'admin-blog-posts',
      },
      {
        title: 'Viết bằng AI',
        to: 'admin-blog-ai-writer',
      },
    ],
  },
]
