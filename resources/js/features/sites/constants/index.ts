import type { QTableColumn } from 'quasar'
import type { Site, SiteAdapter, SiteDriver, SiteProbeVerdict } from '../types'

// Mirrors Site::DRIVERS / IntakeDriverRegistry::knownAdapters() on the
// backend (app/Models/Site.php, app/Intake/IntakeDriverRegistry.php) —
// keep in sync by hand, there's no shared source of truth across PHP/TS.
export const siteDrivers: SiteDriver[] = ['email', 'rss', 'api', 'html', 'browser']

export const siteAdapters: SiteAdapter[] = ['we_work_remotely', 'larajobs', 'remotive', 'remote_ok']

export const siteColumns: QTableColumn<Site>[] = [
    { name: 'name', label: 'Name', field: 'name', align: 'left' },
    { name: 'driver', label: 'Driver', field: 'driver', align: 'left' },
    { name: 'enabled', label: 'Enabled', field: 'enabled', align: 'left' },
    { name: 'last_status', label: 'Last status', field: 'last_status', align: 'left' },
    { name: 'last_run_at', label: 'Last run', field: 'last_run_at', align: 'left' },
    { name: 'actions', label: '', field: 'id', align: 'right' },
]

/** How each URL-check verdict is shown: an icon plus a label, never colour alone. */
export const probeVerdicts: Record<SiteProbeVerdict, { label: string; icon: string; iconClass: string }> = {
    feed: { label: 'Feed found', icon: 'rss_feed', iconClass: 'text-teal-300' },
    api: { label: 'API found', icon: 'data_object', iconClass: 'text-teal-300' },
    readable: { label: 'Readable by the server', icon: 'check_circle', iconClass: 'text-teal-300' },
    needs_browser: { label: 'Needs a browser', icon: 'language', iconClass: 'text-amber-400' },
    unreachable: { label: "Couldn't read it", icon: 'error_outline', iconClass: 'text-red-400' },
}
