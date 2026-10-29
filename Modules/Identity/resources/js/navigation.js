/*
 * =====================================================================
 * CHỨC NĂNG FILE: Khai báo mục điều hướng sidebar cho module Identity.
 * =====================================================================
 * export const order: thứ tự gom trong navigation tổng (nhỏ = lên trên).
 * default export: mảng item theo chuẩn Vuexy nav (title/icon/to/children).
 * =====================================================================
 */
export const order = 10

export default [
  {
    title: 'Nhân sự & Quyền',
    icon: { icon: 'tabler-users' },
    children: [
      { title: 'Nhóm kênh', to: 'admin-identity-channel-groups' },
      { title: 'Vai trò & Quyền', to: 'admin-identity-roles' },
    ],
  },
]
