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
      this.filters = { kind: '', visibility: '', folder: '', 'channel_group_id': '', search: '' } // eslint-disable-line camelcase
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

    /* Nạp folder distinct. */
    async loadFolders() {
      this.folders = await mediaApi.folders()
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
