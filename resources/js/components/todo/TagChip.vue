<script setup lang="ts">
import type { Tag } from '@/types';

defineProps<{ tag: Tag }>();
</script>

<template>
    <span
        class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
        >({{ tag.name }})</span
    >
</template>
