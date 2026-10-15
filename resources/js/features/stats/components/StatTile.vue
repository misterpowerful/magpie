<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter, type RouteLocationRaw } from 'vue-router'
import { rangeLabels } from '../constants'
import type { StatsRange } from '../types'

/**
 * shadcn-style stat card: muted label + icon on top, the figure in the
 * middle, and a Last run / 7 days toggle at the bottom. The body swipes
 * (touch or mouse drag) between the two ranges; the toggle pills do the
 * same for anyone who'd never think to drag a card. Tapping the card
 * itself opens `to`, and a swipe never counts as that tap.
 */
const props = defineProps<{
    title: string
    icon: string
    to?: RouteLocationRaw
    /** Feature not built yet: dimmed, no toggle, not clickable. */
    disabled?: boolean
}>()

const range = defineModel<StatsRange>('range', { default: 'last_run' })

defineSlots<{
    default(props: { range: StatsRange }): unknown
}>()

const router = useRouter()
const ranges = Object.keys(rangeLabels) as StatsRange[]
const direction = ref<'left' | 'right'>('left')
const clickable = computed(() => !props.disabled && props.to !== undefined)

let justSwiped = false

function setRange(next: StatsRange) {
    if (next === range.value) return
    direction.value = ranges.indexOf(next) > ranges.indexOf(range.value) ? 'left' : 'right'
    range.value = next
}

function step(delta: 1 | -1) {
    const next = ranges[ranges.indexOf(range.value) + delta]
    if (next) setRange(next)
}

function onSwipe({ direction: swiped }: { direction?: string }) {
    if (props.disabled) return
    justSwiped = true
    // The click that a mouse-drag swipe ends with fires right after this.
    setTimeout(() => (justSwiped = false), 0)
    step(swiped === 'left' ? 1 : -1)
}

function open() {
    if (justSwiped || !clickable.value || !props.to) return
    router.push(props.to)
}
</script>

<template>
    <div
        class="flex flex-col gap-3 overflow-hidden rounded-xl border border-white/8 bg-[var(--q-dark)] p-4 transition-colors select-none"
        :class="[clickable ? 'cursor-pointer hover:border-white/20 focus-visible:border-teal-500 focus-visible:outline-none' : '', disabled ? 'opacity-50' : '']"
        :role="clickable ? 'link' : undefined"
        :tabindex="clickable ? 0 : undefined"
        @click="open"
        @keydown.enter="open"
        @keydown.left.prevent="!disabled && step(-1)"
        @keydown.right.prevent="!disabled && step(1)"
    >
        <div class="flex flex-nowrap! items-center justify-between">
            <span class="text-sm font-medium text-zinc-400">{{ title }}</span>
            <q-icon :name="icon" size="18px" class="text-zinc-500" />
        </div>

        <div v-touch-swipe.mouse.horizontal="onSwipe" class="relative min-h-0 flex-1">
            <Transition :name="`tile-slide-${direction}`" mode="out-in">
                <div :key="range">
                    <slot :range="range" />
                </div>
            </Transition>
        </div>

        <div v-if="!disabled" class="flex flex-nowrap! gap-1" role="tablist" aria-label="Range">
            <button
                v-for="r in ranges"
                :key="r"
                type="button"
                role="tab"
                :aria-selected="r === range"
                class="cursor-pointer rounded-full border-0 px-2 py-0.5 font-[inherit] text-xs transition-colors"
                :class="r === range ? 'bg-teal-500/15 text-teal-400' : 'bg-transparent text-zinc-500 hover:text-zinc-300'"
                @click.stop="setRange(r)"
            >
                {{ rangeLabels[r] }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.tile-slide-left-enter-active,
.tile-slide-left-leave-active,
.tile-slide-right-enter-active,
.tile-slide-right-leave-active {
    transition:
        transform 150ms ease,
        opacity 150ms ease;
}

.tile-slide-left-enter-from,
.tile-slide-right-leave-to {
    transform: translateX(24px);
    opacity: 0;
}

.tile-slide-left-leave-to,
.tile-slide-right-enter-from {
    transform: translateX(-24px);
    opacity: 0;
}
</style>
