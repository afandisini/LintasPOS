import { secureToken } from '@/services/secureStorage'
import type { ApiFailure, ApiSuccess } from '@/types/api'

const baseUrl = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '')

export class ApiError extends Error {
  constructor(public readonly status: number, public readonly body: ApiFailure | null) {
    super(body?.message || `API request failed (${status})`)
  }
}

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<ApiSuccess<T>> {
  const token = await secureToken.get()
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (!(options.body instanceof FormData)) headers.set('Content-Type', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)
  const response = await fetch(`${baseUrl}${path}`, { ...options, headers })
  const body = await response.json().catch(() => null)
  if (!response.ok || !body?.success) throw new ApiError(response.status, body)
  return body as ApiSuccess<T>
}
