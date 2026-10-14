import type { QTableColumn } from 'quasar'
import type { Site, SiteAdapter, SiteDriver } from '../types'

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
