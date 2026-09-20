import type { ChatResponse, SiteData } from '../types'

const API_BASE_URL = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

export class ApiError extends Error {
  status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
  }
  if (options.body) headers['Content-Type'] = 'application/json'

  const response = await fetch(`${API_BASE_URL}${path}`, { ...options, headers })

  if (!response.ok) {
    let detail = response.statusText
    try {
      const body = (await response.json()) as {
        message?: string
        errors?: Record<string, string[]>
      }
      const first = body.errors
        ? Object.values(body.errors)[0]?.[0]
        : undefined
      detail = body.message ?? first ?? response.statusText
    } catch {
      // corps non-JSON : on garde le statut
    }
    throw new ApiError(detail, response.status)
  }

  return (await response.json()) as T
}

export interface ContactPayload {
  name: string
  email: string
  phone?: string
  company?: string
  message: string
}

export const api = {
  site: () => request<SiteData>('/api/site'),

  chat: (message: string, sessionUuid?: string | null) =>
    request<ChatResponse>('/api/chat', {
      method: 'POST',
      body: JSON.stringify({
        message,
        ...(sessionUuid ? { session_uuid: sessionUuid } : {}),
      }),
    }),

  contact: (payload: ContactPayload) =>
    request<{ message: string }>('/api/contact', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
}
