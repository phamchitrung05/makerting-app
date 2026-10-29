/*
 * =====================================================================
 * CHỨC NĂNG FILE: Điểm khởi động Mock Service Worker (MSW) cho dev offline.
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE: default export — cài handler và start worker.
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : import.meta.env.VITE_USE_MSW ('true' mới bật) + bộ handlers demo.
 * - OUTPUT: không trả về; đăng ký service worker chặn request /api khi bật.
 * SIDE EFFECT: KHI BẬT -> chặn mọi request /api bằng dữ liệu giả (KHÔNG dùng
 *              với backend Laravel thật). Mặc định backend thật => giữ TẮT.
 * =====================================================================
 */

// Chỉ bật MSW khi VITE_USE_MSW=true. Mặc định chạy backend Laravel thật => TẮT.
const ENABLED = import.meta.env.VITE_USE_MSW === 'true'

export default function () {
  if (!ENABLED)
    return

  // Lazy import để không tải handlers + worker khi backend thật.
  return Promise.all([
    import('msw/browser'),
    import('@db/app-bar-search/index'),
    import('@db/apps/academy/index'),
    import('@db/apps/calendar/index'),
    import('@db/apps/chat/index'),
    import('@db/apps/ecommerce/index'),
    import('@db/apps/email/index'),
    import('@db/apps/invoice/index'),
    import('@db/apps/kanban/index'),
    import('@db/apps/logistics/index'),
    import('@db/apps/permission/index'),
    import('@db/apps/users/index'),
    import('@db/auth/index'),
    import('@db/dashboard/index'),
    import('@db/pages/datatable/index'),
    import('@db/pages/faq/index'),
    import('@db/pages/help-center/index'),
    import('@db/pages/profile/index'),
  ]).then(([
    { setupWorker },
    { handlerAppBarSearch },
    { handlerAppsAcademy },
    { handlerAppsCalendar },
    { handlerAppsChat },
    { handlerAppsEcommerce },
    { handlerAppsEmail },
    { handlerAppsInvoice },
    { handlerAppsKanban },
    { handlerAppLogistics },
    { handlerAppsPermission },
    { handlerAppsUsers },
    { handlerAuth },
    { handlerDashboard },
    { handlerPagesDatatable },
    { handlerPagesFaq },
    { handlerPagesHelpCenter },
    { handlerPagesProfile },
  ]) => {
    const worker = setupWorker(
      ...handlerAppsEcommerce,
      ...handlerAppsAcademy,
      ...handlerAppsInvoice,
      ...handlerAppsUsers,
      ...handlerAppsEmail,
      ...handlerAppsCalendar,
      ...handlerAppsChat,
      ...handlerAppsPermission,
      ...handlerPagesHelpCenter,
      ...handlerPagesProfile,
      ...handlerPagesFaq,
      ...handlerPagesDatatable,
      ...handlerAppBarSearch,
      ...handlerAppLogistics,
      ...handlerAuth,
      ...handlerAppsKanban,
      ...handlerDashboard,
    )

    const workerUrl = `${import.meta.env.BASE_URL.replace(/build\/$/g, '') ?? '/'}mockServiceWorker.js`

    worker.start({
      serviceWorker: { url: workerUrl },
      onUnhandledRequest: 'bypass',
    })
  })
}
