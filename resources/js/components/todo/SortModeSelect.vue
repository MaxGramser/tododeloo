<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SortMode, TodoList } from '@/types';

const props = defineProps<{
    list: TodoList;
    visibleTodoIds: number[];
}>();

const options: { value: SortMode; label: string }[] = [
    { value: 'manual', label: 'Handmatig' },
    { value: 'created_at', label: 'Datum toegevoegd' },
    { value: 'alphabetical', label: 'Alfabetisch' },
    { value: 'priority', label: 'Prioriteit' },
];

const value = computed({
    get: () => props.list.sort_mode,
    set: (next: SortMode) => {
        router.post(
            `/lists/${props.list.id}/sort-mode`,
            {
                sort_mode: next,
                visible_todo_ids: next === 'manual' ? props.visibleTodoIds : null,
            },
            { preserveScroll: true, preserveState: true },
        );
    },
});
</script>

<template>
    <label
        class="flex items-center gap-2 font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
    >
        <span>sort</span>
        <select
            v-model="value"
            class="border-b border-input bg-transparent pb-0.5 font-mono text-[11px] tracking-wider text-foreground uppercase outline-none"
        >
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
    </label>
</template>
