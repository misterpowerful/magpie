<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter, type RouteLocationRaw } from 'vue-router'
import Card from '@/shared/components/Card.vue'
import RangeToggle from '@/shared/components/RangeToggle.vue'
import { tileRanges } from '../constants'
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
const ranges = tileRanges.map((r) => r.value)
const direction = ref<'left' | 'right'>('left')
const clickable = computed(() => !props.disabled && props.to !== undefined)

let justSwiped = false

function setRange(next: StatsRange) {
    if (next === range.value) return
    direction.value = ranges.indexOf(next) > ranges.indexOf(range.value) ? 'left' : 'right'
    range.value = next
}

// The pills set the range through setRange too, so they slide the same way a swipe does.
const toggled = computed({ get: () => range.value, set: setRange })

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
    <Card
        class="overflow-hidden transition-colors select-none"
        :class="[clickable ? 'cursor-pointer hover:border-white/20 focus-visible:border-teal-500 focus-visible:outline-none' : '', disabled ? 'opacity-50' : '']"
        :role="clickable ? 'link' : undefined"
        :tabindex="clickable ? 0 : undefined"
        @click="open"
        @keydown.enter="open"
        @keydown.left.prevent="!disabled && step(-1)"
        @keydown.right.prevent="!disabled && step(1)"
    >
        <template #header>
            <span class="text-sm font-medium text-zinc-400">{{ title }}</span>
            <q-icon :name="icon" size="18px" class="text-zinc-500" />
        </template>

        <div v-touch-swipe.mouse.horizontal="onSwipe" class="relative min-h-0 flex-1">
            <Transition :name="`tile-slide-${direction}`" mode="out-in">
                <div :key="range">
                    <slot :range="range" />
                </div>
            </Transition>
        </div>

        <RangeToggle v-if="!disabled" v-model="toggled" :options="tileRanges" size="sm" />
    </Card>
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
