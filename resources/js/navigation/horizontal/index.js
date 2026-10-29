/*
 * =====================================================================
 * CHỨC NĂNG FILE: Gom toàn bộ mục menu Sidebar ngang (App Shell + Modules).
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: default export -> mảng nav item đã hợp nhất.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : các file navigation cục bộ + Modules/<Name>/resources/js/navigation.js.
 * - OUTPUT: array nav item dùng cho <HorizontalNavLayout :nav-items>.
 * SIDE EFFECT: đọc glob tĩnh lúc build; sắp theo `order` export trong module.
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

const localNav = [
  ...dashboard,
  ...apps,
  ...pages,
  ...uiElements,
  ...forms,
  ...tables,
  ...charts,
  ...misc,
]

const moduleNavModules = import.meta.glob(
  '../../../../Modules/*/resources/js/navigation.js',
  { eager: true },
)

const moduleNav = Object.entries(moduleNavModules)
  .map(([file, mod]) => ({
    file,
    order: mod.order ?? 100,
    items: mod.default ?? [],
  }))
  .sort((a, b) => a.order - b.order)
  .flatMap(entry => entry.items)

export default [...localNav, ...moduleNav]
