/*
 * =====================================================================
 * CHỨC NĂNG FILE: Khai báo mục menu Sidebar cho module WritingStyle.
 * =====================================================================
 * Nhóm header 'DNA Content' gom các công cụ "bộ gen" (văn phong, sau này
 * motif/visual DNA) — hiện chỉ có Writing Profiles.
 * CÁC HÀM/METHOD TRONG FILE: export order + default mảng nav item.
 * INPUT/OUTPUT: mảng nav item cho VerticalNav/HorizontalNav.
 * SIDE EFFECT: được navigation/vertical|horizontal/index.js gom lúc build.
 * =====================================================================
 */
export const order = 62

// Ghim lên đầu sidebar (cùng nhóm top với Blog/Media/Taxonomy).
export const position = 'top'

export default [
  // Header title phân nhóm.
  { heading: 'DNA Content' },
  {
    title: 'Writing Profiles',
    icon: { icon: 'tabler-dna' },
    to: 'admin-writing-style-writing-profiles-list',
  },
]
