/*
 * =====================================================================
 * CHỨC NĂNG FILE: Gom toàn bộ mục menu Sidebar dọc (App Shell + Modules).
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: default export -> mảng nav item đã hợp nhất.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : các file navigation cục bộ + Modules/<Name>/resources/js/navigation.js
 *           (lấy qua import.meta.glob, sắp theo module.priority khai trong file).
 * - OUTPUT: array nav item dùng cho <VerticalNavLayout :nav-items>.
 * SIDE EFFECT: đọc glob tĩnh lúc build; mỗi navigation.js có thể export `order`.
 * =====================================================================
 */
import dashboard from './dashboard'
import appsAndPages from './apps-and-pages'
import uiElements from './ui-elements'
import forms from './forms'
import charts from './charts'
import others from './others'

// Thu nav cục bộ (App Shell). Giữ nguyên thứ tự mặc định Vuexy.
const localNav = [
  ...dashboard,
  ...appsAndPages,
  ...uiElements,
  ...forms,
  ...charts,
  ...others,
]

// Thu navigation.js của từng module. Mỗi file export default mảng nav item,
// kèm export const order (số) để sắp xen kẽ; mặc định order = 100.
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

// Module items nối sau nav cục bộ; mỗi module tự quy định section title của nó.
export default [...localNav, ...moduleNav]
