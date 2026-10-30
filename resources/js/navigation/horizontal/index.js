/*
 * =====================================================================
 * CHỨC NĂNG FILE: Gom toàn bộ mục menu Sidebar ngang (App Shell + Modules).
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: default export -> mảng nav item đã hợp nhất.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : các file navigation cục bộ + Modules/<Name>/resources/js/navigation.js.
 * - OUTPUT: array nav item dùng cho <HorizontalNavLayout :nav-items>.
 * SIDE EFFECT: đọc glob tĩnh lúc build; sắp theo `order` export trong module;
 *              `position` ('top' = ghim lên đầu trước nhóm Apps, 'bottom' = cuối).
 *              Item { heading } chỉ có nghĩa ở vertical nav -> lọc bỏ ở đây.
 * =====================================================================
 */
import dashboard from './dashboard'
import apps from './apps'
import pages from './pages'
import uiElements from './ui-elements'
import forms from './forms'
import tables from './tables'
import charts from './charts'
import misc from './misc'

const moduleNavModules = import.meta.glob(
  '../../../../Modules/*/resources/js/navigation.js',
  { eager: true },
)

const sortedModuleNav = Object.entries(moduleNavModules)
  .map(([file, mod]) => ({
    file,
    order: mod.order ?? 100,
    position: mod.position ?? 'bottom',
    items: mod.default ?? [],
  }))
  .sort((a, b) => a.order - b.order)

// Horizontal nav không có section title -> loại item { heading } khỏi mảng.
const stripHeadings = items => items.filter(item => !('heading' in item))

const topModuleNav = sortedModuleNav
  .filter(entry => entry.position === 'top')
  .flatMap(entry => stripHeadings(entry.items))

const bottomModuleNav = sortedModuleNav
  .filter(entry => entry.position !== 'top')
  .flatMap(entry => stripHeadings(entry.items))

const localNav = [
  ...dashboard,
  ...topModuleNav,
  ...apps,
  ...pages,
  ...uiElements,
  ...forms,
  ...tables,
  ...charts,
  ...misc,
]

export default [...localNav, ...bottomModuleNav]
