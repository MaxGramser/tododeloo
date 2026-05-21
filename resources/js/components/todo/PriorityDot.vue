<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { Priority } from '@/types';

const props = defineProps<{
    priority: Priority;
    todoId?: number;
    interactive?: boolean;
}>();

const map: Record<Priority, { dot: string; label: string }> = {
    high: { dot: 'bg-accent', label: 'hoog' },
    normal: { dot: 'bg-muted-foreground/40', label: 'normaal' },
    low: { dot: 'bg-muted-foreground/15', label: 'laag' },
};

const cycle: Record<Priority, Priority> = {
    low: 'normal',
    normal: 'high',
    high: 'low',
};

function bump() {
    if (!props.interactive || !props.todoId) return;
    const next = cycle[props.priority];
    router.patch(
        `/todos/${props.todoId}`,
        { priority: next },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <button
        v-if="interactive"
        type="button"
        :title="`Prioriteit: ${map[priority].label} (klik om te wisselen)`"
        :class="['size-2.5 shrink-0 rounded-full', map[priority].dot]"
        @click.stop="bump"
    />
    <span
        v-else
        :title="`Prioriteit: ${map[priority].label}`"
        :class="['inline-block size-2 rounded-full', map[priority].dot]"
    />
</template>
