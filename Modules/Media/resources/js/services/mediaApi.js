/*
 * =====================================================================
 * CHỨC NĂNG FILE: Service gọi API module Media.
 * =====================================================================
 * Bọc $api (ofetch, tự unwrap BaseResponse.data) cho endpoint:
 * - GET    /v1/admin/media/assets          -> assets() dataTable + filter
 * - GET    /v1/admin/media/assets/options  -> assetOptions() dropdown nhẹ
 * - GET    /v1/admin/media/assets/folders  -> folders()
 * - GET    /v1/admin/media/assets/{id}     -> asset()
 * - POST   /v1/admin/media/assets          -> upload(file(s)) multipart
 * - PUT    /v1/admin/media/assets/{id}     -> updateAsset()
 * - DELETE /v1/admin/media/assets/{id}     -> deleteAsset()
 * - GET    /v1/admin/media/assets/{id}/stream -> streamUrl() (url thô)
 * - GET    /v1/admin/media/slots           -> slots() metadata registry
 * - GET    /v1/admin/media/usages          -> usages() của model+slot
 * - POST   /v1/admin/media/usages          -> attach() gán media
 * - DELETE /v1/admin/media/usages          -> detach() gỡ media
 *
 * upload() tự dựng FormData khi có File|Blob — còn lại dùng JSON body.
 * =====================================================================
 */
import { $api } from '@/utils/api'

const BASE = '/v1/admin/media'

export const mediaApi = {
  /* Danh sách media phân trang + filter {kind,visibility,folder,channel_group_id,search,itemsPerPage,page,sortBy,orderBy}. */
  assets(params = {}) {
    return $api(`${BASE}/assets`, { params })
  },

  /* Options nhẹ cho dropdown — {kind,folder,channel_group_id,search}. */
  assetOptions(params = {}) {
    return $api(`${BASE}/assets/options`, { params })
  },

  /* Danh sách folder distinct đang dùng. */
  folders() {
    return $api(`${BASE}/assets/folders`)
  },

  /* Chi tiết 1 asset (kèm usages + creator/channel_group). */
  asset(id) {
    return $api(`${BASE}/assets/${id}`)
  },

  /*
   * CHỨC NĂNG: Upload 1 file -> MediaAsset.
   * INPUT: File|Blob, {folder,visibility,channel_group_id,title,alt_text,allowed_kinds,dedupe}.
   * OUTPUT: asset vừa tạo (hoặc cũ khi dedupe).
   */
  upload(file, options = {}) {
    const form = new FormData()

    form.append('file', file, options.file_name || file.name || 'file')
    for (const [k, v] of Object.entries(options)) {
      if (v === undefined || v === null) { continue }
      if (Array.isArray(v)) {
        // Laravel FormRequest đọc array qua key[] — gửi từng phần tử.
        v.forEach(item => form.append(`${k}[]`, item))
      } else {
        form.append(k, v)
      }
    }

    return $api(`${BASE}/assets`, { method: 'POST', body: form })
  },

  /*
   * CHỨC NĂNG: Upload nhiều file (files[]) -> mảng MediaAsset.
   * INPUT: File[]|Blob[], options.
   */
  uploadMany(files, options = {}) {
    const form = new FormData()
    for (const f of files) {
      form.append('files[]', f, f.name || 'file')
    }
    for (const [k, v] of Object.entries(options)) {
      if (v === undefined || v === null) { continue }
      if (Array.isArray(v)) {
        // Laravel FormRequest đọc array qua key[] — gửi từng phần tử.
        v.forEach(item => form.append(`${k}[]`, item))
      } else {
        form.append(k, v)
      }
    }

    return $api(`${BASE}/assets`, { method: 'POST', body: form })
  },

  /* Cập nhật metadata asset (title/alt/folder/visibility/channel_group_id). */
  updateAsset(id, payload) {
    return $api(`${BASE}/assets/${id}`, { method: 'PUT', body: payload })
  },

  /* Soft delete asset. */
  deleteAsset(id) {
    return $api(`${BASE}/assets/${id}`, { method: 'DELETE' })
  },

  /* URL stream file vật lý (cho video private / preview). */
  streamUrl(id) {
    return `${BASE}/assets/${id}/stream`
  },

  /* Metadata slot registry (kind/maxItems/visibility/label). */
  slots() {
    return $api(`${BASE}/slots`)
  },

  /* Usages của model+slot — {linkable_type,linkable_id,slot}. */
  usages(params) {
    return $api(`${BASE}/usages`, { params })
  },

  /* Gán media vào model — mode 'attach'|'replace'|'sync'. */
  attach(payload) {
    return $api(`${BASE}/usages`, { method: 'POST', body: payload })
  },

  /* Gỡ media khỏi model — {linkable_type,linkable_id,slot,media_asset_ids?,detach_all?}. */
  detach(payload) {
    return $api(`${BASE}/usages`, { method: 'DELETE', body: payload })
  },
}
