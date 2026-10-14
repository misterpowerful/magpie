export type SiteDriver = 'email' | 'rss' | 'api' | 'html' | 'browser'

export type SiteAdapter = 'we_work_remotely' | 'larajobs' | 'remotive' | 'remote_ok'

export type SiteStatus = 'ok' | 'needs_login' | 'recipe_broken' | 'error'

export interface SiteConfig {
    adapter?: SiteAdapter | null
    [key: string]: unknown
}

export interface Site {
    id: number
    name: string
    url: string
    driver: SiteDriver
    config: SiteConfig | null
    enabled: boolean
    last_run_at: string | null
    last_status: SiteStatus | null
}

export interface SiteForm {
    name: string
    url: string
    driver: SiteDriver
    adapter: SiteAdapter | null
    enabled: boolean
}
