export interface ApiMeta {
  request_id?: string
  timestamp?: string
  pagination?: { page: number; per_page: number; total: number; last_page: number }
}

export interface ApiSuccess<T> {
  success: true
  message: string
  data: T
  meta?: ApiMeta
}

export interface ApiFailure {
  success: false
  message: string
  errors?: Record<string, string[]>
  meta?: ApiMeta
}

export interface User {
  id: number
  name: string
  username: string
  email: string
  role: string
  avatar?: number | null
}

export interface AuthData {
  access_token: string
  token_type: 'Bearer'
  expires_in: number
  expires_at: string
  user: User
  permissions: string[]
}
