<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Priority, Tag } from '@/types';

const props = defineProps<{
    priority: Priority | null;
    tagIds: number[];
}>();

const emit = defineEmits<{
    (e: 'update:priority', value: Priority | null): void;
    (e: 'update:tagIds', value: number[]): void;
}>();

const page = usePage<{ userTags: Tag[] }>();
const allTags = computed(() => page.props.userTags ?? []);

const priorityOptions: { value: Priority | null; label: string }[] = [
    { value: null, label: 'alle' },
    { value: 'high', label: 'hoog' },
    { value: 'normal', label: 'normaal' },
    { value: 'low', label: 'laag' },
];

function setPriority(p: Priority | null) {
    emit('update:priority', p);
}

function toggleTag(tag: Tag) {
    const next = new Set(props.tagIds);
    if (next.has(tag.id)) next.delete(tag.id);
    else next.add(tag.id);
    emit('update:tagIds', [...next]);
}

const hasAnyFilter = computed(
    () => props.priority !== null || props.tagIds.length > 0,
);

function reset() {
    emit('update:priority', null);
    emit('update:tagIds', []);
}
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-border/60 py-3 font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
    >
        <div class="flex items-center gap-2">
            <span>prioriteit</span>
            <button
                v-for="opt in priorityOptions"
                :key="opt.label"
                type="button"
                class="rounded-full px-2 py-0.5 text-[10px] tracking-wider transition-colors"
                :class="
                    priority === opt.value
                        ? 'bg-accent text-accent-foreground'
                        : 'bg-secondary text-foreground/70 hover:bg-secondary/80'
                "
                @click="setPriority(opt.value)"
            >
                {{ opt.label }}
            </button>
        </div>

        <div v-if="allTags.length" class="flex items-center gap-2">
            <span>tags</span>
            <button
                v-for="tag in allTags"
                :key="tag.id"
                type="button"
                class="rounded-full px-2 py-0.5 text-[10px] tracking-wider transition-colors"
                :class="
                    tagIds.includes(tag.id)
                        ? 'bg-accent text-accent-foreground'
                        : 'bg-secondary text-foreground/70 hover:bg-secondary/80'
                "
                @click="toggleTag(tag)"
            >
                ({{ tag.name }})
            </button>
        </div>

        <button
            v-if="hasAnyFilter"
            type="button"
            class="ml-auto text-foreground/70 underline hover:text-foreground"
            @click="reset"
        >
            reset
        </button>
    </div>
</template>
