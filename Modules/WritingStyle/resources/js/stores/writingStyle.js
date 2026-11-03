/*
 * =====================================================================
 * CHỨC NĂNG FILE: Pinia store DNA văn phong (WritingStyle profiles).
 * =====================================================================
 * Load-once pattern aiConfig: profiles + options (tiers/rules) nạp 1 lần,
 * mọi component dùng chung; thêm/sửa/xóa cập nhật mảng in-place (không refetch).
 * =====================================================================
 */
/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { writingStyleApi } from '@modules/WritingStyle/resources/js/services/writingStyleApi'

export const useWritingStyleStore = defineStore('writingStyle', {
  state: () => ({
    profiles: [],            // WritingProfileResource[]
    options: { tiers: [], rules: [] },
    profilesLoaded: false,
    optionsLoaded: false,
    loadingProfiles: false,
    saving: false,
    analyzing: false,
    sandboxing: false,
    lastAnalysis: null,      // {analysis_id, task_run_id} của lần analyze gần nhất
    sandboxResult: null,     // SandboxResultDto gần nhất
  }),

  getters: {
    tiers: state => state.options.tiers ?? [],
    ruleKeys: state => state.options.rules ?? [],
    enabledProfiles: state => state.profiles.filter(p => p.is_enabled),
  },

  actions: {
    /* Nạp profiles 1 lần (force=true để refetch). */
    async fetchProfiles({ force = false } = {}) {
      if (this.profilesLoaded && !force) return this.profiles
      this.loadingProfiles = true
      try {
        const res = await writingStyleApi.profiles({ itemsPerPage: 100 })

        this.profiles = res?.items ?? (Array.isArray(res) ? res : [])
        this.profilesLoaded = true
        
        return this.profiles
      } finally {
        this.loadingProfiles = false
      }
    },

    /* Nạp options (tiers + rule keys) 1 lần. */
    async fetchOptions({ force = false } = {}) {
      if (this.optionsLoaded && !force) return this.options
      const res = await writingStyleApi.options()

      this.options = res ?? { tiers: [], rules: [] }
      this.optionsLoaded = true
      
      return this.options
    },

    ensureLoaded(opts = {}) {
      return Promise.all([this.fetchProfiles(opts), this.fetchOptions(opts)])
    },

    /* Tạo profile -> unshift vào list. */
    async createProfile(payload) {
      this.saving = true
      try {
        const res = await writingStyleApi.createProfile(payload)

        this.profiles.unshift(res)
        
        return res
      } finally {
        this.saving = false
      }
    },

    /* Sửa profile -> thay trong list. */
    async updateProfile(id, payload) {
      this.saving = true
      try {
        const res = await writingStyleApi.updateProfile(id, payload)
        const i = this.profiles.findIndex(p => p.id === id)
        if (i >= 0) this.profiles[i] = res
        
        return res
      } finally {
        this.saving = false
      }
    },

    /* Xóa profile -> bỏ khỏi list. */
    async deleteProfile(id) {
      await writingStyleApi.deleteProfile(id)
      this.profiles = this.profiles.filter(p => p.id !== id)
    },

    /* Giải mã DNA -> 202 {analysis_id, task_run_id}. */
    async analyze(payload) {
      this.analyzing = true
      try {
        const res = await writingStyleApi.analyze(payload)

        this.lastAnalysis = res
        
        return res
      } finally {
        this.analyzing = false
      }
    },

    /* DNA Sandbox -> {text,...}. */
    async sandbox(id, payload) {
      this.sandboxing = true
      try {
        const res = await writingStyleApi.sandbox(id, payload)

        this.sandboxResult = res
        
        return res
      } finally {
        this.sandboxing = false
      }
    },
  },
})
