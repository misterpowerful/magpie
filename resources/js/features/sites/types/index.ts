export type SiteDriver = 'email' | 'rss' | 'api' | 'html' | 'browser'

export type SiteAdapter = 'we_work_remotely' | 'larajobs' | 'remotive' | 'remote_ok'

export type SiteStatus = 'ok' | 'needs_login' | 'recipe_broken' | 'error'

/** XPath selectors for html sites (see app/Intake/HtmlIntakeDriver.php). */
export interface SiteSelectors {
    list_item: string
    link: string
    title: string
    company: string
    location: string
}

export interface SiteConfig {
    adapter?: SiteAdapter | 'html' | null
    selectors?: Partial<SiteSelectors>
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
    selectors: SiteSelectors
    /** The site's saved config, kept as-is for drivers the form doesn't edit (html selectors). */
    config: SiteConfig | null
}

export type SiteProbeVerdict = 'feed' | 'api' | 'readable' | 'needs_browser' | 'unreachable'

/** POST /api/admin/sites/probe — which driver can read a URL from the server. */
export interface SiteProbeResult {
    verdict: SiteProbeVerdict
    suggested_driver: SiteDriver | null
    suggested_adapter: SiteAdapter | null
    reason: string
    feed_url: string | null
    http_status: number | null
}
