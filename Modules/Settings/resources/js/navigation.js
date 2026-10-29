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
export const order = 95

export default [
  {
    title: 'Cài đặt',
    icon: { icon: 'tabler-settings' },
    children: [
      {
        title: 'Chung',
        to: 'admin-settings-general',
      },
      {
        title: 'Bảo mật',
        to: 'admin-settings-security',
      },
      {
        title: 'AI Providers',
        to: 'admin-settings-ai-providers',
      },
    ],
  },
]
