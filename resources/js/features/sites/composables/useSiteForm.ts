import { computed, ref, watch } from 'vue'
import api from '@/shared/lib/api'
import { validationErrors } from '@/shared/lib/apiError'
import type { Site, SiteConfig, SiteForm, SiteProbeResult, SiteSelectors } from '../types'

function blankSelectors(): SiteSelectors {
    return { list_item: '', link: '', title: '', company: '', location: '' }
}

function blankForm(): SiteForm {
    return { name: '', url: '', driver: 'rss', adapter: null, enabled: true, selectors: blankSelectors(), config: null }
}

/** The html driver's config: the saved config, with the form's non-empty selectors on top. */
function htmlConfig(form: SiteForm): SiteConfig {
    const selectors = Object.fromEntries(Object.entries(form.selectors).filter(([, value]) => value.trim() !== ''))

    return { ...(form.config ?? {}), adapter: 'html', selectors }
}

export function useSiteForm(onSaved: () => void | Promise<void>) {
    const dialogOpen = ref(false)
    const editingId = ref<number | null>(null)
    const form = ref<SiteForm>(blankForm())
    const errors = ref<Record<string, string[]>>({})

    const needsAdapter = computed(() => ['rss', 'api'].includes(form.value.driver))

    const probing = ref(false)
    const probeResult = ref<SiteProbeResult | null>(null)

    // A result describes the URL it was run against; editing the URL
    // makes it stale.
    watch(
        () => form.value.url,
        () => (probeResult.value = null),
    )

    /**
     * Ask the server which driver can read the URL. On a new site the
     * suggestion is applied straight away (still editable); on an
     * existing one it's only shown, so a check never silently changes
     * a working site.
     */
    async function checkUrl() {
        errors.value = {}
        probing.value = true
        try {
            const { data } = await api.post<{ data: SiteProbeResult }>('/api/admin/sites/probe', { url: form.value.url })
            probeResult.value = data.data
            if (!editingId.value) applySuggestion()
        } catch (e) {
            errors.value = validationErrors(e)
        } finally {
            probing.value = false
        }
    }

    function applySuggestion() {
        const result = probeResult.value
        if (!result?.suggested_driver) return
        form.value.driver = result.suggested_driver
        form.value.adapter = result.suggested_adapter
    }

    /** Switch to the feed the page advertises, then check that instead. */
    async function useFeedUrl() {
        if (!probeResult.value?.feed_url) return
        form.value.url = probeResult.value.feed_url
        await checkUrl()
    }

    function openCreate() {
        editingId.value = null
        form.value = blankForm()
        errors.value = {}
        probeResult.value = null
        dialogOpen.value = true
    }

    function openEdit(site: Site) {
        editingId.value = site.id
        form.value = {
            name: site.name,
            url: site.url,
            driver: site.driver,
            // 'html' is the html driver's implicit adapter, not one to pick.
            adapter: site.config?.adapter === 'html' ? null : (site.config?.adapter ?? null),
            enabled: site.enabled,
            selectors: { ...blankSelectors(), ...(site.config?.selectors ?? {}) },
            config: site.config,
        }
        errors.value = {}
        probeResult.value = null
        dialogOpen.value = true
    }

    async function save() {
        errors.value = {}

        const payload = {
            name: form.value.name,
            url: form.value.url,
            driver: form.value.driver,
            // rss/api: the adapter is all there is. html: its selectors.
            // Anything else isn't edited here, so send back what was saved
            // rather than wiping it.
            config: needsAdapter.value
                ? { adapter: form.value.adapter }
                : form.value.driver === 'html'
                  ? htmlConfig(form.value)
                  : (form.value.config ?? {}),
            enabled: form.value.enabled,
        }

        try {
            if (editingId.value) {
                await api.put(`/api/admin/sites/${editingId.value}`, payload)
            } else {
                await api.post('/api/admin/sites', payload)
            }

            dialogOpen.value = false
            await onSaved()
        } catch (e) {
            errors.value = validationErrors(e)
        }
    }

    return {
        dialogOpen,
        editingId,
        form,
        errors,
        needsAdapter,
        probing,
        probeResult,
        checkUrl,
        applySuggestion,
        useFeedUrl,
        openCreate,
        openEdit,
        save,
    }
}
