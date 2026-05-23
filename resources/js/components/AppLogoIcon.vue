<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="16 7 72 72"
        :class="className"
        v-bind="$attrs"
    >
        <path
            d="M26 46 L46 65 L70 32"
            fill="none"
            stroke="currentColor"
            stroke-width="11.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <circle cx="77.3" cy="22.7" r="6.8" fill="#f26b26" />
    </svg>
</template>
