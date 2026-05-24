<script setup lang="ts">
import { computed, toRef } from 'vue';
import {
    useParsePreview,
    type ParseSegment,
} from '@/composables/useParsePreview';

const props = withDefaults(
    defineProps<{ title: string; variant?: 'strip' | 'panel'; parse?: boolean }>(),
    { variant: 'strip', parse: true },
);

const { preview } = useParsePreview(toRef(props, 'title'), toRef(props, 'parse'));

const containerClass = computed(() =>
    props.variant === 'panel'
        ? 'absolute inset-x-0 top-full z-40 mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1 rounded-lg border border-border bg-card px-3 py-2 shadow-lg'
        : 'flex flex-wrap items-baseline gap-x-2 gap-y-1',
);

function segClass(seg: ParseSegment): string {
    switch (seg.type) {
        case 'date':
        case 'recurrence':
            return 'rounded-[3px] bg-accent/15 px-0.5 font-medium text-accent';
        case 'title':
            return 'text-foreground';
        default:
            return 'text-muted-foreground/45'; // ignored filler
    }
}

const resolvedLabel = computed(() => {
    if (!preview.value) {
        return null;
    }
    if (preview.value.date) {
        return preview.value.date.label;
    }
    if (preview.value.recurrence) {
        const r = preview.value.recurrence;
        return `${r.summary} · vanaf ${r.anchor_label}`;
    }
    return null;
});
</script>

<template>
    <Transition name="parse-fade">
        <div v-if="preview" :class="containerClass">
            <span class="font-mono text-[10px] tracking-widest text-muted-foreground/60 uppercase">{{ props.parse ? 'leest als' : 'letterlijk' }}</span>
            <span class="text-sm whitespace-pre-wrap">
                <span
                    v-for="(seg, i) in preview.segments"
                    :key="i"
                    :class="segClass(seg)"
                    :title="seg.resolved || undefined"
                >{{ seg.text }}</span>
            </span>
            <span
                v-if="resolvedLabel"
                class="rounded bg-accent/15 px-1.5 py-0.5 font-mono text-[10px] tracking-wide text-accent"
                >→ {{ resolvedLabel }}</span
            >
        </div>
    </Transition>
</template>

<style scoped>
.parse-fade-enter-active,
.parse-fade-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}
.parse-fade-enter-from,
.parse-fade-leave-to {
    opacity: 0;
    transform: translateY(-2px);
}
</style>
