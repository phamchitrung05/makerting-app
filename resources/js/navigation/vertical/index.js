/*
 * =====================================================================
 * CHỨC NĂNG FILE: Gom toàn bộ mục menu Sidebar dọc (App Shell + Modules).
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: default export -> mảng nav item đã hợp nhất.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : các file navigation cục bộ + Modules/<Name>/resources/js/navigation.js
 *           (lấy qua import.meta.glob, sắp theo module.priority khai trong file).
 * - OUTPUT: array nav item dùng cho <VerticalNavLayout :nav-items>.
 * SIDE EFFECT: đọc glob tĩnh lúc build; mỗi navigation.js có thể export `order`
 *              (thứ tự gom, mặc định 100) và `position` ('top' = ghim lên đầu
 *              sidebar trước Apps & Pages, 'bottom'/bỏ trống = nối sau nav cục bộ).
 * =====================================================================
 */
import dashboard from './dashboard'
import appsAndPages from './apps-and-pages'
import uiElements from './ui-elements'
import forms from './forms'
import charts from './charts'
import others from './others'

// Thu navigation.js của từng module. Mỗi file export default mảng nav item,
// kèm export const order (số) để sắp xen kẽ; mặc định order = 100.
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

// Module ghim lên đầu sidebar (trước nhóm Apps & Pages của Vuexy).
const topModuleNav = sortedModuleNav
  .filter(entry => entry.position === 'top')
  .flatMap(entry => entry.items)

// Module mặc định: nối sau nav cục bộ, mỗi module tự quy định section title.
const bottomModuleNav = sortedModuleNav
  .filter(entry => entry.position !== 'top')
  .flatMap(entry => entry.items)

// Thu nav cục bộ (App Shell). Dashboard nằm đầu, sau đó mới đến các nhóm còn lại
// để module position 'top' vẫn đứng trên 'Apps & Pages' nhưng dưới Dashboards.
const localNav = [
  ...dashboard,
  ...topModuleNav,
  ...appsAndPages,
  ...uiElements,
  ...forms,
  ...charts,
  ...others,
]

export default [...localNav, ...bottomModuleNav]
