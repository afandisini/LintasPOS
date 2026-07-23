import { SecureStorage } from '@aparajita/capacitor-secure-storage'

const TOKEN_KEY = 'lintaspos_access_token'

export const secureToken = {
  async get(): Promise<string | null> {
    try {
      const value = await SecureStorage.get(TOKEN_KEY)
      return typeof value === 'string' ? value : null
    } catch {
      // Browser-only development fallback. Never use this fallback in a production native build.
      return sessionStorage.getItem(TOKEN_KEY)
    }
  },
  async set(token: string): Promise<void> {
    try {
      await SecureStorage.set(TOKEN_KEY, token)
    } catch {
      sessionStorage.setItem(TOKEN_KEY, token)
    }
  },
  async clear(): Promise<void> {
    try {
      await SecureStorage.remove(TOKEN_KEY)
    } finally {
      sessionStorage.removeItem(TOKEN_KEY)
    }
  },
}
