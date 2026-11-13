/*
 * =====================================================================
 * CHỨC NĂNG FILE: Pinia store quản lý thư viện Media cho UI chọn/lọc.
 * =====================================================================
 * - items      : danh sách asset hiện tại (dataTable).
 * - total      : tổng số (itemsLength) cho phân trang.
 * - filters    : {kind,visibility,folder,channel_group_id,search}.
 * - folders    : danh sách folder distinct (cho dropdown).
 * - slots      : metadata slot registry (kind/maxItems/visibility/label).
 * - loading    : cờ đang fetch.
 * - pagination : {page,itemsPerPage,sortBy,orderBy}.
 *
 * Các action:
 * - refresh()           : gọi GET /assets với filters + pagination.
 * - setFilter(key,val)  : đổi filter -> reset page=1 -> refresh().
 * - setPage(n)          : đổi trang -> refresh().
 * - upload(file,opts)   : upload 1 file -> prepend vào items (hoặc dedupe trả cũ).
 * - uploadMany(files)   : upload nhiều file -> prepend vào items.
 * - remove(id)          : xóa asset khỏi items (sau khi DELETE thành công).
 * - loadFolders()       : nạp distinct folder.
 * - loadSlots()         : nạp metadata slot.
 * - attach/detach       : gán/gỡ media vào model đích (dùng trong MediaSlotField).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : không (Pinia store global 'mediaAsset').
 * - OUTPUT: state + actions cho component MediaPickerDialog/MediaSlotField/
 *           MediaGalleryField/trang media/index.
 * =====================================================================
 */
import { defineStore } from 'pinia'
import { mediaApi } from '../services/mediaApi'

export const useMediaAssetStore = defineStore('mediaAsset', {
  state: () => ({
    items: [],
    total: 0,
    folders: [],
    slots: [],
    loading: false,
    filters: {
      kind: '',
      visibility: '',
      folder: '',
      'channel_group_id': '', // eslint-disable-line camelcase,
      search: '',
      quick: '',      // bộ lọc nhanh cột trái: 'recent'|'shared'|'ai' ('' = tất cả)
      trashed: '',    // '1' = chỉ asset đã xóa (nhóm Thùng rác)
    },
    pagination: {
      page: 1,
      itemsPerPage: 24,
      sortBy: 'id',
      orderBy: 'desc',
    },
  }),

  getters: {
    /* Số asset đang hiển thị trên trang. */
    itemCount: state => state.items.length,

    /* Có filter nào đang áp dụng không. */
    hasActiveFilters: state =>
      Object.values(state.filters).some(v => v !== '' && v !== null),

    /* Tổng số asset hiện có (không tính thùng rác) — chip "Tất cả tệp tin". */
    totalAssets: state => {
      const keep = state.items.length
      
      return state.filters.trashed ? keep : state.total
    },

    /* Cây thư mục cho cột trái trang media — dựng từ API folders [{name,count}]
     * (phân cấp theo dấu '/' đầu tiên, ví dụ 'products/images' → con của 'products'). */
    folderTree: state => {
      const flat = state.folders || []
      const map = new Map()
      const roots = []

      // Duyệt 2 lần: pass 1 dựng node, pass 2 gắn con vào cha theo prefix.
      for (const f of flat) {
        map.set(f.name, { id: f.name, name: f.name.split('/').pop(), count: f.count, expanded: false })
      }
      for (const f of flat) {
        const node = map.get(f.name)
        const slash = f.name.lastIndexOf('/')
        if (slash > 0) {
          const parentName = f.name.slice(0, slash)
          const parent = map.get(parentName)
          if (parent) {
            ;(parent.children ||= []).push(node)
            continue
          }
        }
        roots.push(node)
      }
      
      return roots
    },

    /* Tổng dung lượng các item trên trang hiện tại (bytes) — chip đã chọn. */
    selectedSizeBytes: () => (items = []) =>
      items.reduce((sum, a) => sum + (Number(a.size) || 0), 0),

    /* Slot metadata theo tên (helper cho MediaSlotField). */
    slotInfo: state => slot =>
      state.slots.find(s => s.slot === slot) || null,
  },

  actions: {
    /* Nạp danh sách asset theo filters + pagination hiện tại. */
    async refresh() {
      this.loading = true
      try {
        const params = {
          ...Object.fromEntries(
            Object.entries(this.filters).filter(([, v]) => v !== '' && v !== null),
          ),
          ...this.pagination,
        }

        const res = await mediaApi.assets(params)

        // $api unwrap data -> data.items + itemsLength ở cùng cấp.
        this.items = res.items || []
        this.total = res.itemsLength || 0
      } finally {
        this.loading = false
      }
    },

    /* Đổi 1 filter (reset page=1) rồi refresh. */
    setFilter(key, value) {
      this.filters[key] = value
      this.pagination.page = 1
      this.refresh()
    },

    /* Xóa toàn bộ filter. */
    clearFilters() {
      this.filters = { kind: '', visibility: '', folder: '', 'channel_group_id': '', search: '', quick: '', trashed: '' } // eslint-disable-line camelcase
      this.pagination.page = 1
      this.refresh()
    },

    /* Chọn bộ lọc nhanh cột trái — 'trash' đi qua trashed riêng, còn lại qua quick. */
    setQuickFilter(id) {
      this.filters.quick = (id === 'all' || id === 'trash') ? '' : id
      this.filters.trashed = id === 'trash' ? '1' : ''
      this.pagination.page = 1
      this.refresh()
    },

    /* Đổi trang. */
    setPage(page) {
      this.pagination.page = page
      this.refresh()
    },

    /* Đổi itemsPerPage. */
    setPerPage(n) {
      this.pagination.itemsPerPage = n
      this.pagination.page = 1
      this.refresh()
    },

    /* Upload 1 file; nếu dedupe trả asset cũ (id trùng) -> update item thay vì prepend. */
    async upload(file, options = {}) {
      const asset = await mediaApi.upload(file, options)
      const idx = this.items.findIndex(i => i.id === asset.id)

      if (idx >= 0) {
        this.items.splice(idx, 1, asset)
      } else {
        this.items.unshift(asset)
        this.total++
      }

      return asset
    },

    /* Upload nhiều file -> prepend mảng. */
    async uploadMany(files, options = {}) {
      const assets = await mediaApi.uploadMany(files, options)

      for (const asset of assets) {
        const idx = this.items.findIndex(i => i.id === asset.id)

        if (idx >= 0) {
          this.items.splice(idx, 1, asset)
        } else {
          this.items.unshift(asset)
          this.total++
        }
      }

      return assets
    },

    /* Xóa asset khỏi items sau DELETE. */
    remove(id) {
      const idx = this.items.findIndex(i => i.id === id)

      if (idx >= 0) {
        this.items.splice(idx, 1)
        this.total = Math.max(0, this.total - 1)
      }
    },

    /* Nạp danh sách folder distinct kèm số asset — API trả [{name,count}]. */
    async loadFolders() {
      this.folders = await mediaApi.folders()
    },

    /* Xóa mềm asset trên server rồi gỡ khỏi items (trừ khi đang xem thùng rác,
     * thì giữ lại để phản ánh đúng trạng thái đã xóa). */
    async deleteAsset(id) {
      await mediaApi.deleteAsset(id)
      if (this.filters.trashed) {
        this.refresh() // đang ở nhóm thùng rác → refetch thay vì gỡ tay
      } else {
        this.remove(id)
      }
    },

    /* Cập nhật metadata asset (title/alt_text/folder/visibility) rồi sửa in-place. */
    async updateAsset(id, payload) {
      const asset = await mediaApi.updateAsset(id, payload)
      const idx = this.items.findIndex(i => i.id === asset.id)
      if (idx >= 0) {
        this.items.splice(idx, 1, asset)
      }
      
      return asset
    },

    /* Nạp metadata slot registry. */
    async loadSlots() {
      this.slots = await mediaApi.slots()
    },

    /* Gán media vào model. */
    async attach(payload) {
      return await mediaApi.attach(payload)
    },

    /* Gỡ media khỏi model. */
    async detach(payload) {
      return await mediaApi.detach(payload)
    },
  },
})
