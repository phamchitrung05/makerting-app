/*
 * =====================================================================
 * CHỨC NĂNG FILE: Composable tính trạng thái loading của một card/panel.
 * =====================================================================
 * Dùng cặp với <AppCardLoader>. Nhận 1+ nguồn "đang tải" (ref/computed/
 * getter trả boolean) + nguồn dữ liệu (mảng/object có thể rỗng), trả cờ:
 * - loading   : đang tải (bất kỳ nguồn nào true).
 * - hasData   : đã có dữ liệu (nguồn data không rỗng).
 * - firstLoad : đang tải VÀ chưa có data (lần đầu -> skeleton).
 * - ready     : không tải VÀ có data (nội dung thật).
 *
 * INPUT/OUTPUT:
 * - useCardLoading(sources..., dataSource)
 *   sources   : 1..N ref/computed/function trả boolean (loading flags).
 *   dataSource: ref/computed/function trả collection/object (mảng|đếm).
 * - OUTPUT: { loading, hasData, firstLoad, ready } — computed.
 * SIDE EFFECT: chỉ đọc — không gọi API.
 * =====================================================================
 */
import { computed, unref } from 'vue'

const val = x => (typeof x === 'function' ? x() : unref(x))

export function useCardLoading(...args) {
  const dataSource = args.length ? args[args.length - 1] : null
  const loadings = args.slice(0, -1)

  const loading = computed(() => loadings.some(s => !!val(s)))

  const hasData = computed(() => {
    const d = val(dataSource)
    if (d == null) return false
    if (Array.isArray(d)) return d.length > 0
    if (typeof d === 'object') {
      // hỗ trợ object {items:[]} hoặc có key 'length'/'count'.
      if (Array.isArray(d.items)) return d.items.length > 0
      
      return Object.keys(d).length > 0
    }
    
    return !!d
  })

  const firstLoad = computed(() => loading.value && !hasData.value)
  const ready = computed(() => !loading.value && hasData.value)

  return { loading, hasData, firstLoad, ready }
}
