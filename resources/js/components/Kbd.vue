<script setup lang="ts">
defineProps<{ keys: string }>();
</script>

<template>
    <kbd
        class="inline-flex items-center rounded border border-border bg-card px-1.5 py-0.5 font-mono text-[11px] leading-none text-foreground shadow-sm"
        >{{ keys }}</kbd
    >
</template>
