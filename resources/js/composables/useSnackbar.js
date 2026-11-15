/*
 * =====================================================================
 * CHỨC NĂNG FILE: Composable điều khiển Global Snackbar toàn hệ thống.
 * =====================================================================
 * Kết nối với component <AppSnackbar /> gắn ở App.vue để hiển thị thông báo
 * nổi thống nhất (success / error / warning / info) từ mọi page, dialog,
 * Pinia store hoặc API service mà không cần đặt <VSnackbar> cục bộ.
 *
 * INPUT/OUTPUT:
 * - useSnackbar() -> { state, show, success, error, warning, info, fromError, hide, close }
 * - appSnackbar   -> singleton điều khiển trực tiếp ngoài Vue setup()
 *
 * SIDE EFFECT:
 * - Cập nhật singleton reactive `state` để render <AppSnackbar /> toàn cục.
 * =====================================================================
 */
import { nextTick, readonly, ref } from 'vue'
import { firstErrorMessage } from '@/utils/api'

const DEFAULT_ICONS = {
  success: 'tabler-circle-check',
  error: 'tabler-alert-circle',
  warning: 'tabler-alert-triangle',
  info: 'tabler-info-circle',
}

const DEFAULT_TIMEOUTS = {
  success: 4000,
  error: 7000,
  warning: 5000,
  info: 4000,
}

const DEFAULT_STATE = {
  show: false,
  text: '',
  color: 'info',
  icon: DEFAULT_ICONS.info,
  timeout: DEFAULT_TIMEOUTS.info,
  location: 'top end',
  variant: 'flat',
  closable: true,
  zIndex: 10000,
  action: null,
}

const state = ref({ ...DEFAULT_STATE })
let snackbarSeq = 0

function resolveIcon(color, customIcon) {
  if (customIcon === false || customIcon === '')
    return null
  if (typeof customIcon === 'string' && customIcon.trim() !== '')
    return customIcon.trim()

  return DEFAULT_ICONS[color] || DEFAULT_ICONS.info
}

function resolveTimeout(color, customTimeout) {
  if (typeof customTimeout === 'number' && Number.isFinite(customTimeout))
    return customTimeout

  return DEFAULT_TIMEOUTS[color] ?? DEFAULT_TIMEOUTS.info
}

function normalizeOptions(textOrOptions, extraOptions = {}) {
  const opts = typeof textOrOptions === 'object' && textOrOptions !== null
    ? { ...textOrOptions, ...extraOptions }
    : { ...extraOptions, text: textOrOptions }

  const cleanText = String(opts.text ?? opts.message ?? '').trim()
  const color = String(opts.color || opts.type || 'info').trim() || 'info'

  const action = opts.action && typeof opts.action === 'object' && String(opts.action.text ?? '').trim()
    ? {
      text: String(opts.action.text).trim(),
      color: opts.action.color || '',
      onClick: typeof opts.action.onClick === 'function' ? opts.action.onClick : null,
    }
    : null

  return {
    show: true,
    text: cleanText,
    color,
    icon: resolveIcon(color, opts.icon),
    timeout: resolveTimeout(color, opts.timeout),
    location: String(opts.location || DEFAULT_STATE.location),
    variant: String(opts.variant || DEFAULT_STATE.variant),
    closable: opts.closable !== false,
    zIndex: Number.isFinite(Number(opts.zIndex)) ? Number(opts.zIndex) : DEFAULT_STATE.zIndex,
    action,
  }
}

/*
 * CHỨC NĂNG: Hiển thị snackbar mới (reset timer sạch nếu gọi liên tiếp).
 * INPUT: textOrOptions (string|Object), extraOptions (Object).
 * OUTPUT: Promise<void>.
 */
async function show(textOrOptions, extraOptions = {}) {
  const nextState = normalizeOptions(textOrOptions, extraOptions)
  if (!nextState.text)
    return

  const seq = ++snackbarSeq

  // Đóng qua 1 tick để Vuetify reset countdown và phát lại transition khi gọi nối tiếp.
  if (state.value.show) {
    state.value = {
      ...state.value,
      show: false,
    }
    await nextTick()
  }

  if (seq !== snackbarSeq)
    return

  state.value = nextState
}

/*
 * CHỨC NĂNG: Ẩn snackbar hiện tại và hủy các lượt hiển thị chờ trước đó.
 */
function hide() {
  snackbarSeq += 1
  if (!state.value.show)
    return

  state.value = {
    ...state.value,
    show: false,
  }
}

function showWithColor(color) {
  return (textOrOptions, options = {}) => {
    const merged = typeof textOrOptions === 'object' && textOrOptions !== null
      ? { ...textOrOptions, ...options, color }
      : { ...options, text: textOrOptions, color }

    return show(merged)
  }
}

/*
 * CHỨC NĂNG: Hiển thị nhanh thông báo lỗi chuẩn từ ApiError / exception của $api.
 */
function fromError(err, fallback = 'Có lỗi xảy ra', options = {}) {
  const msg = firstErrorMessage(err)
  const text = msg && msg !== 'Có lỗi xảy ra' ? msg : (fallback || 'Có lỗi xảy ra')

  return show(text, { ...options, color: 'error' })
}

export const appSnackbar = {
  state: readonly(state),
  show,
  hide,
  close: hide,
  success: showWithColor('success'),
  error: showWithColor('error'),
  warning: showWithColor('warning'),
  info: showWithColor('info'),
  fromError,
}

export function useSnackbar() {
  return appSnackbar
}
