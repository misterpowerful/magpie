import { computed, onUnmounted, ref, watch, type Ref } from 'vue'
import api from '@/shared/lib/api'
import type { Run } from '../types'

export const pipelineStages = [
    { key: 'intake', label: 'Pulling email and sites' },
    { key: 'stub_filter', label: 'Filtering new listings' },
    { key: 'enrichment', label: 'Fetching job pages' },
    { key: 'hard_filter', label: 'Applying hard filters' },
    { key: 'scoring', label: 'Scoring with Claude' },
    { key: 'confirmation_check', label: 'Checking application confirmations' },
] as const

const POLL_MS = 3000

interface RunSummary {
    latestRun: Ref<Run | null>
    pending: Ref<boolean>
    running: Ref<boolean>
    load: () => Promise<void>
}

export interface PipelineOutcome {
    tone: 'positive' | 'warning' | 'negative'
    headline: string
    details: string[]
}

type StageStats = Record<string, Record<string, unknown> | undefined>

function count(n: unknown, noun: string): string {
    const value = typeof n === 'number' ? n : 0

    return `${value} ${noun}${value === 1 ? '' : 's'}`
}

function describeOutcome(run: Run): PipelineOutcome {
    const stats = (run.stage_stats ?? {}) as StageStats
    const details: string[] = []

    const email = stats.intake?.email as Record<string, unknown> | undefined
    if (email?.error) {
        details.push(`Email pull failed: ${String(email.error)}`)
    } else if (email) {
        details.push(`${count(email.processed, 'email')} read, ${count(email.stubs, 'new listing')}`)
    }

    const scoring = stats.scoring
    if (scoring) {
        const strong = typeof scoring.strong === 'number' && scoring.strong > 0 ? ` (${scoring.strong} Strong)` : ''
        details.push(`${count(scoring.scored, 'listing')} scored${strong}`)
        if (typeof scoring.failed === 'number' && scoring.failed > 0) details.push(`${scoring.failed} failed to score`)
    }

    if (run.status === 'done') return { tone: 'positive', headline: 'Run finished.', details }
    if (run.status === 'paused_rate_limited') {
        return { tone: 'warning', headline: 'Run paused: Claude rate limit hit. The next run picks up the rest.', details }
    }
    if (run.status === 'paused_time') {
        return { tone: 'warning', headline: 'Run paused: time cap reached. The next run picks up the rest.', details }
    }

    return { tone: 'negative', headline: 'Run failed. See the Laravel log for details.', details }
}

/**
 * Drives the dashboard's "Run pipeline" button. The web process can't run
 * the claude CLI, so a click only files a request; the scheduler starts the
 * run within a minute. Polls /api/runs while a request or run is active
 * (this also picks up scheduled runs that are in progress on page load).
 */
export function usePipelineRun(summary: RunSummary, onFinished: () => void) {
    const requesting = ref(false)
    const outcome = ref<PipelineOutcome | null>(null)
    const now = ref(Date.now())
    let pollTimer: ReturnType<typeof setInterval> | undefined
    let tickTimer: ReturnType<typeof setInterval> | undefined
    let sawRunning = false
    let baselineRunId: number | null | undefined

    const active = computed(() => summary.pending.value || summary.running.value)
    const busy = computed(() => requesting.value || active.value)

    const completedStages = computed(() => {
        const stats = summary.latestRun.value?.stage_stats ?? {}

        return pipelineStages.filter((stage) => stage.key in stats).length
    })

    const currentStage = computed(() => {
        if (!summary.running.value) return null
        const stats = summary.latestRun.value?.stage_stats ?? {}

        return pipelineStages.find((stage) => !(stage.key in stats)) ?? null
    })

    const elapsedSeconds = computed(() => {
        const run = summary.latestRun.value

        if (!summary.running.value || !run) return 0

        return Math.max(0, Math.floor((now.value - new Date(run.started_at).getTime()) / 1000))
    })

    function stopPolling() {
        clearInterval(pollTimer)
        clearInterval(tickTimer)
        pollTimer = undefined
        tickTimer = undefined
    }

    watch(
        active,
        (isActive, wasActive) => {
            if (isActive) {
                outcome.value = null
                now.value = Date.now()
                tickTimer ??= setInterval(() => (now.value = Date.now()), 1000)
                pollTimer ??= setInterval(() => summary.load().catch(() => undefined), POLL_MS)

                return
            }

            stopPolling()

            if (!wasActive) return

            onFinished()

            const run = summary.latestRun.value
            // A quick run can start and finish between two polls, so a new run id counts as "started" too.
            const started = run !== null && (sawRunning || run.id !== baselineRunId)
            outcome.value =
                started && run
                    ? describeOutcome(run)
                    : { tone: 'warning', headline: 'The run request expired before it started. Is the scheduler running?', details: [] }
            sawRunning = false
        },
        { immediate: true },
    )

    watch(summary.running, (isRunning) => {
        if (isRunning) sawRunning = true
    })

    async function start() {
        requesting.value = true
        outcome.value = null
        sawRunning = false
        baselineRunId = summary.latestRun.value?.id ?? null

        try {
            const { data } = await api.post<{ data: { pending: boolean; running: boolean } }>('/api/admin/runs')
            summary.pending.value = data.data.pending
            summary.running.value = data.data.running
            await summary.load()
        } finally {
            requesting.value = false
        }
    }

    function dismiss() {
        outcome.value = null
    }

    onUnmounted(() => {
        stopPolling()
    })

    return { busy, active, outcome, currentStage, completedStages, elapsedSeconds, start, dismiss }
}
