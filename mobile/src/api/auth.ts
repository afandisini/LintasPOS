import { apiRequest } from './client'
import type { AuthData, User } from '../types/api'

export function login(payload: { identity: string; password: string; device_name: string; device_uuid: string; platform: string; app_version: string }) {
  return apiRequest<AuthData>('/auth/login', { method: 'POST', body: JSON.stringify(payload) })
}

export function currentUser() {
  return apiRequest<User>('/auth/me')
}

export function logout() {
  return apiRequest<null>('/auth/logout', { method: 'POST' })
}
