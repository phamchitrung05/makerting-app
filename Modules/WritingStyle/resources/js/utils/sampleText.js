/*
  =====================================================================
  CHỨC NĂNG FILE: Chuyển văn bản mẫu và ghép các rule được chỉnh trong form.
  =====================================================================

  CÁC HÀM/COMPUTED/WATCHER TRONG FILE:
  - htmlToText(): Chuyển HTML editor thành văn bản, giữ đoạn và dấu bullet để phân tích.
  - textToHtml(): Escape văn bản trước khi dựng HTML cho Tiptap.
  - mergeRuleEdits(): Ghép các ô đã thay đổi vào DNA, giữ nguyên khóa người dùng chưa sửa.
  - Computed: Không có.
  - Watcher/lifecycle: Không có.

  INPUT/OUTPUT CỦA FILE (tổng thể):
  - INPUT : Chuỗi HTML/text hoặc các object original, initial, current.
  - OUTPUT: Chuỗi text/HTML hoặc bản sao rules đã ghép thay đổi; không mutate đầu vào.
  =====================================================================
*/
const entities = { nbsp: ' ', amp: '&', lt: '<', gt: '>', quot: '"', apos: "'" }

/**
 * =====================================================================
 * CHỨC NĂNG: Chuyển HTML editor thành văn bản, giữ đoạn và dấu bullet để phân tích
 * =====================================================================
 *
 * INPUT:
 * - html
 *
 * OUTPUT:
 * - string văn bản đã giải mã entity và chuẩn hóa dòng
 *
 * SIDE EFFECT:
 * - Chỉ xử lý chuỗi.
 *
 * EXCEPTION:
 * - Lỗi kiểu dữ liệu không được ném chủ động; chuyển đầu vào qua String().
 * =====================================================================
 */
export function htmlToText(html) {
  return String(html ?? '')
    .replace(/<li\b[^>]*>\s*(?:<p\b[^>]*>)?/gi, '\n\n• ')
    .replace(/<(?:br|hr)\b[^>]*>/gi, '\n')
    .replace(/<\/?(?:p|li|h[1-6]|div|blockquote|pre|tr|table|ul|ol)\b[^>]*>/gi, '\n\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&(?:([a-z]+)|#(\d+)|#x([\da-f]+));/gi, (entity, name, decimal, hex) => {
      if (name) return entities[name.toLowerCase()] ?? entity
      const codePoint = Number.parseInt(hex ?? decimal, hex ? 16 : 10)

      return codePoint <= 0x10FFFF ? String.fromCodePoint(codePoint) : entity
    })
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

/**
 * =====================================================================
 * CHỨC NĂNG: Escape văn bản trước khi dựng HTML cho Tiptap
 * =====================================================================
 *
 * INPUT:
 * - text
 *
 * OUTPUT:
 * - string HTML gồm p/br, giữ xuống dòng
 *
 * SIDE EFFECT:
 * - Chỉ xử lý chuỗi.
 *
 * EXCEPTION:
 * - Không gọi API hoặc ghi DOM trực tiếp.
 * =====================================================================
 */
export function textToHtml(text) {
  return String(text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .split(/\r?\n\s*\n/)
    .map(p => `<p>${p.replace(/\r?\n/g, '<br>')}</p>`)
    .join('')
}

/**
 * =====================================================================
 * CHỨC NĂNG: Ghép các ô đã thay đổi vào DNA, giữ nguyên khóa người dùng chưa sửa
 * =====================================================================
 *
 * INPUT:
 * - original, initial, current
 *
 * OUTPUT:
 * - Object rules mới; xóa khóa khi giá trị mới rỗng, cta ánh xạ sang ending
 *
 * SIDE EFFECT:
 * - Tạo bản sao, không mutate original/initial/current.
 *
 * EXCEPTION:
 * - Không bắt lỗi dữ liệu không đúng hình dạng; caller truyền các object từ form.
 * =====================================================================
 */
export function mergeRuleEdits(original, initial, current) {
  const rules = { ...original }
  for (const [field, value] of Object.entries(current)) {
    if (value === initial[field]) continue
    const key = field === 'cta' ? 'ending' : field
    if (value) rules[key] = value
    else delete rules[key]
  }

  return rules
}
