export interface Service {
  code: string
  slug: string | null
  name: string
  tagline: string
  description: string
  bullets: string[] | null
  icon: string
  order: number
}

export interface Project {
  id: number
  title: string
  client: string | null
  sector: string | null
  summary: string
  tags: string[] | null
  image_url: string | null
  year: string | null
}

export interface Visual {
  key: string
  slot: string
  url: string
  credit: string | null
  alt: string
  source: string
}

export interface SiteMeta {
  name: string
  tagline: string
  address: string
  phone: string
  email_contact: string
  email_support: string
  hours: string
  years: number
  projects: number
}

export interface SiteData {
  meta: SiteMeta
  services: Service[]
  projects: Project[]
  visuals: Record<string, Visual[]>
}

export interface ChatLeadState {
  score: number
  status: string
}

export interface ChatResponse {
  session_uuid: string
  reply: string
  quick_replies: string[]
  intent: string
  escalated: boolean
  lead: ChatLeadState
}
