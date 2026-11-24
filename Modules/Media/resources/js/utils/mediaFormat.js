/*
 * =====================================================================
 * CHỨC NĂNG FILE: Helper định dạng dữ liệu hiển thị cho module Media.
 * =====================================================================
 * CÁC HÀM TRONG FILE:
 * - thumbOf(file)                 : Lấy URL thumbnail ưu tiên cho lưới.
 * - previewOf(file)               : Lấy URL xem trước chất lượng cao cho sidebar.
 * - fmtSize(bytes)                : Định dạng dung lượng (B/KB/MB/GB).
 * - fmtDate(iso)                  : Định dạng ngày vi-VN.
 * - fmtDateTime(iso)              : Định dạng ngày + giờ vi-VN.
 * - fmtDuration(ms)               : Định dạng thời lượng mm:ss từ milliseconds.
 * - extOf(file)                   : Trích nhãn phần mở rộng ngắn từ mime type.
 * - deriveAspectRatio(w, h)       : Tính tỷ lệ khung hình tối giản (vd 16:9, 3:2).
 *
 * INPUT/OUTPUT (tổng thể):
 * - INPUT : Bản ghi MediaAsset từ API hoặc số liệu kích thước/thời gian.
 * - OUTPUT: Chuỗi hiển thị chuẩn cho trang Media và MediaDetailSidebar.
 * =====================================================================
 */

/*
 * CHỨC NĂNG: Lấy URL thumbnail tối ưu để render card trong lưới media.
 * INPUT : file (Object) — MediaAssetResource.
 * OUTPUT: String URL hoặc null (khi asset private / thiếu file).
 */
export function thumbOf(file) {
  return file?.conversions?.thumb || file?.conversions?.preview || file?.url || null
}

/*
 * CHỨC NĂNG: Lấy URL xem trước kích thước lớn cho khung chi tiết media.
 * INPUT : file (Object) — MediaAssetResource.
 * OUTPUT: String URL hoặc null (khi asset private / chưa có URL công khai).
 */
export function previewOf(file) {
  return file?.conversions?.featured || file?.conversions?.thumb || file?.url || null
}

/*
 * CHỨC NĂNG: Chuyển số bytes thành chuỗi dung lượng dễ đọc (B/KB/MB/GB).
 * INPUT : bytes (Number|String).
 * OUTPUT: String (vd '2.4 MB').
 */
export function fmtSize(bytes) {
  const b = Number(bytes) || 0

  if (b >= 1073741824)
    return `${(b / 1073741824).toFixed(1)} GB`
  if (b >= 1048576)
    return `${(b / 1048576).toFixed(1)} MB`
  if (b >= 1024)
    return `${(b / 1024).toFixed(1)} KB`

  return `${b} B`
}

/*
 * CHỨC NĂNG: Định dạng chuỗi ISO datetime thành ngày dd/mm/yyyy theo chuẩn vi-VN.
 * INPUT : iso (String|null).
 * OUTPUT: String ngày hoặc '' nếu không hợp lệ.
 */
export function fmtDate(iso) {
  if (!iso)
    return ''

  const d = new Date(iso)
  if (Number.isNaN(d.getTime()))
    return ''

  return d.toLocaleDateString('vi-VN')
}

/*
 * CHỨC NĂNG: Định dạng chuỗi ISO datetime thành ngày + giờ (dd/mm/yyyy HH:mm).
 * INPUT : iso (String|null).
 * OUTPUT: String ngày giờ hoặc '' nếu không hợp lệ.
 */
export function fmtDateTime(iso) {
  if (!iso)
    return ''

  const d = new Date(iso)
  if (Number.isNaN(d.getTime()))
    return ''

  const datePart = d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' })
  const timePart = d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', hour12: false })

  return `${datePart} ${timePart}`
}

/*
 * CHỨC NĂNG: Định dạng thời lượng milliseconds thành chuỗi mm:ss.
 * INPUT : ms (Number|null).
 * OUTPUT: String mm:ss hoặc null.
 */
export function fmtDuration(ms) {
  if (!ms)
    return null

  const s = Math.round(Number(ms) / 1000)

  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}

/*
 * CHỨC NĂNG: Rút gọn phần loại từ mime type (tối đa 4 ký tự in hoa).
 * INPUT : file (Object) — có trường mime.
 * OUTPUT: String (vd 'PNG', 'MP4', 'PDF') hoặc null.
 */
export function extOf(file) {
  const m = (file?.mime || '').split('/')[1]

  return m ? m.toUpperCase().slice(0, 4) : null
}

/*
 * CHỨC NĂNG: Tính tỷ lệ khung hình tối giản từ chiều rộng và chiều cao ảnh/video.
 * INPUT : width (Number), height (Number).
 * OUTPUT: String dạng 'W:H' hoặc null khi thiếu kích thước hợp lệ.
 */
export function deriveAspectRatio(width, height) {
  const w = Number(width)
  const h = Number(height)

  if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0)
    return null

  const gcd = (a, b) => (b === 0 ? a : gcd(b, a % b))
  const divisor = gcd(Math.round(w), Math.round(h))

  return `${Math.round(w) / divisor}:${Math.round(h) / divisor}`
}

export const KIND_LABELS = {
  image: 'Hình ảnh',
  video: 'Video',
  audio: 'Âm thanh',
  document: 'Tài liệu',
  archive: 'File nén',
}

export const KIND_ICONS = {
  image: 'tabler-photo-off',
  video: 'tabler-video',
  audio: 'tabler-music',
  document: 'tabler-file-text',
  archive: 'tabler-archive',
}

/*
 * CHỨC NĂNG: Trả metadata hiển thị đồng nhất (icon, bgClass, color, label) theo kind.
 */
export function kindMeta(kind) {
  const map = {
    image: { icon: 'tabler-photo-off', bgClass: '', color: 'secondary', label: KIND_LABELS.image },
    video: { icon: KIND_ICONS.video, bgClass: 'bg-primary', color: 'primary', label: KIND_LABELS.video },
    audio: { icon: KIND_ICONS.audio, bgClass: 'bg-info', color: 'info', label: KIND_LABELS.audio },
    document: { icon: KIND_ICONS.document, bgClass: 'bg-error', color: 'error', label: KIND_LABELS.document },
    archive: { icon: KIND_ICONS.archive, bgClass: 'bg-warning', color: 'warning', label: KIND_LABELS.archive },
  }

  return map[kind] || { icon: KIND_ICONS.document, bgClass: 'bg-error', color: 'secondary', label: kind || 'Tệp' }
}
