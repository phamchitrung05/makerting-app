/*
 * =====================================================================
 * CHỨC NĂNG FILE: Khai báo mục menu Sidebar cho module TaskCenter.
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: export order + default mảng nav item.
 * INPUT/OUTPUT: mảng nav item cho VerticalNav/HorizontalNav.
 * =====================================================================
 */
export const order = 20

export default [
  {
    title: 'Trung tâm tác vụ',
    icon: { icon: 'tabler-player-play' },
    children: [
      { title: 'Tiến trình nền', to: 'admin-task-center-tasks' },
      { title: 'Cảnh báo', to: 'admin-task-center-alerts' },
    ],
  },
]
