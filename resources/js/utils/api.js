/*
 * =====================================================================
 * CHỨC NĂNG FILE: Bộ gọi API chuẩn của toàn hệ thống bọc trên ofetch.
 * =====================================================================
 * CÁC HÀM/METHOD TRONG FILE:
 * - $api            : instance ofetch đã cấu hình baseURL /api, gắn Bearer token,
 *                     bóc lớp `data` của BaseResponse và chuẩn hóa lỗi 401/422/409.
 * - isValidationError(e) : true nếu lỗi 422 kèm errors{field: [msg]}.
 * - isConflictError(e)   : true nếu lỗi 409 (optimistic-lock / trùng phiên bản).
 * - firstErrorMessage(e) : lấy thông điệp hiển thị đầu tiên từ payload lỗi.
 * - fieldErrors(e)       : chuẩn hóa errors{field:msg} để gán lên form.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : mọi request HTTP ra Backend Laravel qua $api(method, url, body).
 * - OUTPUT: resolve -> `data` bên trong BaseResponse; reject -> ApiError đã
 *           chuẩn hóa (status, message, errors, data).
 * SIDE EFFECT: khi 401 xóa cookie accessToken+userData và điều hướng /login;
 *              không ghi log, không tự retry.
 * =====================================================================
 */
import { ofetch } from 'ofetch'

const ACCESS_TOKEN_KEY = 'accessToken'
const USER_DATA_KEY = 'userData'

/*
 * CHỨC NĂNG: Đọc Bearer token hiện tại từ cookie.
 * INPUT: không.
 * OUTPUT: string token hoặc null.
 * SIDE EFFECT: đọc cookie (không ghi).
 */
function currentToken() {
  return useCookie(ACCESS_TOKEN_KEY).value
}

/*
 * CHỨC NĂNG: Xóa phiên đăng nhập và đẩy người dùng về trang /login.
 * INPUT: fullPath hiện tại để gắn vào query `to` quay lại sau khi login.
 * OUTPUT: không (điều hướng router).
 * SIDE EFFECT: xóa 2 cookie auth + chuyển route sang { name: 'login' }.
 */
function forceLogout(toPath) {
  useCookie(ACCESS_TOKEN_KEY).value = null
  useCookie(USER_DATA_KEY).value = null

  const router = useRouter()
  if (router && router.currentRoute.value.name !== 'login') {
    router.push({
      name: 'login',
      query: { to: toPath !== '/' ? toPath : undefined },
    })
  }
}

/*
 * CHỨC NĂNG: Chuẩn hóa một lỗi ofetch thành ApiError thống nhất.
 * INPUT: error (FetchError) từ ofetch, context { method, url }.
 * OUTPUT: Error object có .status .message .errors .data .isValidation .isConflict.
 * SIDE EFFECT: đọc error.response (không gọi lại mạng).
 */
function normalizeError(error, context) {
  const res = error?.response
  const status = res?.status ?? error?.status ?? 0
  const payload = res?._data ?? error?.data ?? {}

  const message = payload.message || error.message || 'Có lỗi xảy ra'

  const errors = payload.errors && typeof payload.errors === 'object'
    ? payload.errors
    : {}

  const apiError = new Error(message)

  apiError.name = 'ApiError'
  apiError.status = status
  apiError.errors = errors
  apiError.data = payload.data ?? null
  apiError.meta = payload.meta ?? null
  apiError.method = context.method
  apiError.url = context.url
  apiError.isValidation = status === 422
  apiError.isConflict = status === 409
  apiError.isUnauthenticated = status === 401

  return apiError
}

/*
 * CHỨC NĂNG: Bóc phần `data` ra khỏi envelope BaseResponse.
 * INPUT: response payload (object hoặc giá trị raw).
 * OUTPUT: payload.data nếu payload là envelope { success, data }; ngược lại payload.
 * SIDE EFFECT: không.
 */
function unwrapData(payload) {
  if (
    payload
    && typeof payload === 'object'
    && Object.prototype.hasOwnProperty.call(payload, 'data')
    && (Object.prototype.hasOwnProperty.call(payload, 'success')
      || Object.prototype.hasOwnProperty.call(payload, 'message'))
  ) {
    return payload.data
  }

  return payload
}

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',

  /*
   * CHỨC NĂNG: Gắn Bearer token + Accept JSON vào mọi request trước khi gửi.
   * INPUT: { options } của ofetch.
   * OUTPUT: không (mutate options.headers).
   * SIDE EFFECT: đọc cookie token; ghi header Authorization/Accept.
   */
  async onRequest({ options }) {
    const token = currentToken()
    const headers = new Headers(options.headers)

    headers.set('Accept', 'application/json')
    headers.set('X-Requested-With', 'XMLHttpRequest')

    if (token)
      headers.set('Authorization', `Bearer ${token}`)

    options.headers = headers
  },

  /*
   * CHỨC NĂNG: Unwrap BaseResponse -> trả data ra ngoài cho caller.
   * INPUT: { response } ofetch sau khi parse JSON.
   * OUTPUT: gán response._data = data lõi.
   * SIDE EFFECT: mutate response._data.
   */
  async onResponse({ response }) {
    if (response.ok)
      response._data = unwrapData(response._data)
  },

  /*
   * CHỨC NĂNG: Chuẩn hóa mọi lỗi HTTP thành ApiError + xử lý 401.
   * INPUT: { request, options, response, error } của ofetch.
   * OUTPUT: throw ApiError đã chuẩn hóa (reject promise).
   * SIDE EFFECT: 401 -> forceLogout(); ghi console.error cho lỗi server.
   */
  async onResponseError({ request, options, response, error }) {
    const apiError = normalizeError(error ?? { response }, {
      method: options?.method || 'GET',
      url: typeof request === 'string' ? request : request?.url || '',
    })

    if (apiError.isUnauthenticated) {
      forceLogout(useRoute()?.fullPath ?? '/')
    } else if (apiError.status >= 500) {
      // eslint-disable-next-line no-console
      console.error(`[API] ${apiError.status} ${apiError.method} ${apiError.url}`, apiError.message)
    }

    throw apiError
  },
})

/*
 * CHỨC NĂNG: Kiểm tra lỗi là 422 Validation (dùng trong catch của form).
 * INPUT: error bất kỳ từ $api.
 * OUTPUT: boolean.
 * SIDE EFFECT: không.
 */
export function isValidationError(error) {
  return !!error && (error.isValidation === true || error.status === 422)
}

/*
 * CHỨC NĂNG: Kiểm tra lỗi 409 Conflict (xung đột phiên bản/optimistic-lock).
 * INPUT: error bất kỳ từ $api.
 * OUTPUT: boolean.
 * SIDE EFFECT: không.
 */
export function isConflictError(error) {
  return !!error && (error.isConflict === true || error.status === 409)
}

/*
 * CHỨC NĂNG: Lấy bản đồ lỗi theo trường để gán lên form {field: 'msg'}.
 * INPUT: error 422 từ $api (errors dạng {field: [msg,...]}).
 * OUTPUT: object { field: firstMessage }.
 * SIDE EFFECT: không.
 */
export function fieldErrors(error) {
  const raw = error?.errors || {}
  const out = {}
  for (const key of Object.keys(raw)) {
    const v = raw[key]

    out[key] = Array.isArray(v) ? v[0] : String(v)
  }

  return out
}

/*
 * CHỨC NĂNG: Lấy thông điệp hiển thị đầu tiên cho snackbar/alert.
 * INPUT: error bất kỳ từ $api.
 * OUTPUT: string message (ưu tiên lỗi field đầu tiên, rồi message chung).
 * SIDE EFFECT: không.
 */
export function firstErrorMessage(error) {
  if (!error) return 'Có lỗi xảy ra'
  if (isValidationError(error)) {
    const f = fieldErrors(error)
    const first = Object.values(f)[0]
    if (first) return first
  }

  return error.message || 'Có lỗi xảy ra'
}
