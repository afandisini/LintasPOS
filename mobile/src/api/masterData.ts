import { apiRequest } from './client'

export interface LookupItem { value: string | number; label: string }

export function lookup(resource: 'barang' | 'jasa' | 'pelanggan' | 'supplier', search = '') {
  const query = new URLSearchParams({ limit: '20' })
  if (search) query.set('search', search)
  return apiRequest<LookupItem[]>(`/lookups/${resource}?${query}`)
}
