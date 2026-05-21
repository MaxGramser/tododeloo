<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    done: number;
    total: number;
    size?: number;
    withCheck?: boolean;
}>();

const size = computed(() => props.size ?? 12);
const stroke = computed(() => (size.value >= 18 ? 1.5 : 2));
const radius = computed(() => (size.value - stroke.value) / 2);
const center = computed(() => size.value / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const progress = computed(() =>
    props.total === 0 ? 0 : props.done / props.total,
);
const dashOffset = computed(
    () => circumference.value * (1 - progress.value),
);
const isComplete = computed(
    () => props.done > 0 && props.done === props.total,
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 ${size} ${size}`"
        class="shrink-0"
        aria-hidden="true"
    >
        <circle
            :cx="center"
            :cy="center"
            :r="radius"
            fill="none"
            stroke="currentColor"
            :stroke-width="stroke"
            class="text-muted-foreground/30"
        />
        <circle
            v-if="isComplete"
            :cx="center"
            :cy="center"
            :r="radius"
            class="fill-accent"
        />
        <circle
            v-if="progress > 0 && !isComplete"
            :cx="center"
            :cy="center"
            :r="radius"
            fill="none"
            stroke="currentColor"
            :stroke-width="stroke"
            stroke-linecap="round"
            :stroke-dasharray="circumference"
            :stroke-dashoffset="dashOffset"
            :transform="`rotate(-90 ${center} ${center})`"
            class="text-accent transition-[stroke-dashoffset] duration-300"
        />
        <path
            v-if="withCheck && isComplete"
            :transform="`translate(${center - 4} ${center - 4}) scale(${size / 20})`"
            d="M7.4 0.4a0.6 0.6 0 0 1 0.2 0.8L4 6.8a0.6 0.6 0 0 1 -0.9 0.1l-2.4 -2.4a0.6 0.6 0 1 1 0.85 -0.85l1.9 1.9 3.55 -5.35a0.6 0.6 0 0 1 0.85 -0.15Z"
            class="fill-accent-foreground"
        />
    </svg>
</template>
