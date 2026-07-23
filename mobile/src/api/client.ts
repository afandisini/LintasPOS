import { secureToken } from '../services/secureStorage'
import type { ApiFailure, ApiSuccess } from '../types/api'

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
  const url = `${baseUrl}${path}`
  console.log(path === '/auth/login' ? 'LOGIN URL:' : 'API URL:', url)
  try {
    const response = await fetch(url, { ...options, headers })
    console.log('STATUS:', response.status)
    const rawBody = await response.text()
    console.log('BODY:', rawBody)
    let body: ApiSuccess<T> | ApiFailure | null = null
    try {
      body = rawBody ? JSON.parse(rawBody) as ApiSuccess<T> | ApiFailure : null
    } catch {
      throw new Error(`Server mengembalikan HTTP ${response.status}: ${rawBody.slice(0, 160)}`)
    }
    if (!response.ok || !body || !('success' in body) || !body.success) {
      throw new ApiError(response.status, body?.success === false ? body : null)
    }
    return body as ApiSuccess<T>
  } catch (error) {
    if (error instanceof ApiError) throw error
    console.error('FETCH ERROR:', error)
    throw error
  }
}
