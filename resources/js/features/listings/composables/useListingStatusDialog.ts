import { ref } from 'vue'
import api from '@/shared/lib/api'
import type { Listing, ListingStatus } from '../types'

interface PendingAction {
    listing: Listing | null
    status: ListingStatus | null
    reason: string
}

export function useListingStatusDialog(onUpdated: (listing: Listing) => void) {
    const dialogOpen = ref(false)
    const pendingAction = ref<PendingAction>({ listing: null, status: null, reason: '' })

    function open(listing: Listing, status: ListingStatus) {
        pendingAction.value = { listing, status, reason: '' }
        dialogOpen.value = true
    }

    async function confirm() {
        const { listing, status, reason } = pendingAction.value

        if (!listing || !status) {
            return
        }

        const { data } = await api.patch<{ data: Listing }>(`/api/listings/${listing.id}/status`, {
            status,
            reason: reason || null,
        })

        onUpdated(data.data)
        dialogOpen.value = false
    }

    return { dialogOpen, pendingAction, open, confirm }
}
