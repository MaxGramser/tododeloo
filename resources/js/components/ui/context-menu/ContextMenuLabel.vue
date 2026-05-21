<script setup lang="ts">
import type { ContextMenuLabelProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { ContextMenuLabel, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<
    ContextMenuLabelProps & {
        class?: HTMLAttributes['class'];
        inset?: boolean;
    }
>();

const delegatedProps = reactiveOmit(props, 'class', 'inset');
const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <ContextMenuLabel
        data-slot="context-menu-label"
        :data-inset="inset ? '' : undefined"
        v-bind="forwardedProps"
        :class="
            cn(
                'px-2 pt-1 pb-1.5 font-mono text-[10px] leading-none tracking-[0.18em] text-muted-foreground/70 uppercase data-[inset]:pl-8',
                props.class,
            )
        "
    >
        <slot />
    </ContextMenuLabel>
</template>
