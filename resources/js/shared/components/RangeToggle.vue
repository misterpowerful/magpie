<script setup lang="ts" generic="T extends string">
/**
 * Pick one of a few ranges ("Last run / 7 days / 30 days"). `sm` is the
 * pill row inside a stat tile; `md` is the boxed control above a page.
 * Clicks don't bubble, so a toggle inside a clickable card never also
 * opens it.
 */
withDefaults(
    defineProps<{
        options: { value: T; label: string }[]
        size?: 'sm' | 'md'
        label?: string
    }>(),
    { size: 'md', label: 'Range' },
)

const model = defineModel<T>({ required: true })
</script>

<template>
    <div
        class="flex flex-nowrap! gap-1"
        :class="size === 'md' ? 'w-fit rounded-lg border border-white/8 bg-[var(--q-dark)] p-1' : ''"
        role="radiogroup"
        :aria-label="label"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            class="cursor-pointer border-0 font-[inherit] transition-colors"
            :class="[
                size === 'sm' ? 'rounded-full px-2 py-0.5 text-xs' : 'rounded-md px-3 py-1 text-sm',
                model === option.value
                    ? 'bg-teal-500/15 text-teal-300'
                    : size === 'sm'
                      ? 'bg-transparent text-zinc-500 hover:text-zinc-300'
                      : 'bg-transparent text-zinc-400 hover:text-zinc-200',
            ]"
            @click.stop="model = option.value"
        >
            {{ option.label }}
        </button>
    </div>
</template>
