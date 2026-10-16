<script setup lang="ts">
import Card from '@/shared/components/Card.vue'

/** Plain stat tile: label, value, optional note. No plot, no hover layer. */
defineProps<{ label: string; value: string; note?: string }>()
</script>

<template>
    <Card gap="sm">
        <div class="text-sm font-medium text-zinc-400">{{ label }}</div>
        <div class="text-2xl font-semibold text-zinc-100">{{ value }}</div>
        <div v-if="note" class="text-xs text-zinc-500">{{ note }}</div>
    </Card>
</template>
