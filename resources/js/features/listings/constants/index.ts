import type { QTableColumn } from 'quasar'
import type { Listing } from '../types'

export const listingColumns: QTableColumn<Listing>[] = [
    { name: 'company', label: 'Company', field: 'company', align: 'left' },
    { name: 'title', label: 'Title', field: 'title', align: 'left' },
    { name: 'location_text', label: 'Location', field: 'location_text', align: 'left' },
    { name: 'remote_type', label: 'Remote', field: 'remote_type', align: 'left' },
    { name: 'state', label: 'Pipeline state', field: 'state', align: 'left' },
    { name: 'status', label: 'Status', field: 'status', align: 'left' },
    { name: 'actions', label: '', field: 'id', align: 'right' },
]
