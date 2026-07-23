import { defineStore } from 'pinia'
import { currentUser, login, logout } from '../api/auth'
import { secureToken } from '../services/secureStorage'
import type { User } from '../types/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null as User | null, permissions: [] as string[], ready: false, busy: false }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
    isAdmin: (state) => ['administrator', 'admin', 'owner'].includes((state.user?.role || '').toLowerCase()),
    can: (state) => (permission: string) =>
      ['administrator', 'admin', 'owner'].includes((state.user?.role || '').toLowerCase()) ||
      state.permissions.includes(permission),
  },
  actions: {
    async restore() {
      if (!(await secureToken.get())) { this.ready = true; return }
      try {
        const result = await currentUser()
        this.user = result.data
      } catch { await this.clear() }
      this.ready = true
    },
    async signIn(payload: Parameters<typeof login>[0]) {
      this.busy = true
      try {
        const result = await login(payload)
        await secureToken.set(result.data.access_token)
        this.user = result.data.user
        this.permissions = result.data.permissions || []
      } finally { this.busy = false }
    },
    async signOut() {
      try { if (await secureToken.get()) await logout() } finally { await this.clear() }
    },
    async clear() { await secureToken.clear(); this.user = null; this.permissions = [] },
  },
})
