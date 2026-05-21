<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <span
        data-slot="context-menu-shortcut"
        :class="
            cn(
                'ml-auto font-mono text-[10px] tracking-widest text-muted-foreground uppercase',
                props.class,
            )
        "
    >
        <slot />
    </span>
</template>
