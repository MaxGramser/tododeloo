<script setup lang="ts">
import type { ContextMenuSubTriggerProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { ChevronRight } from 'lucide-vue-next';
import { ContextMenuSubTrigger, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<
    ContextMenuSubTriggerProps & {
        class?: HTMLAttributes['class'];
        inset?: boolean;
    }
>();

const delegatedProps = reactiveOmit(props, 'class', 'inset');
const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <ContextMenuSubTrigger
        data-slot="context-menu-sub-trigger"
        v-bind="forwardedProps"
        :class="
            cn(
                'flex cursor-default items-center gap-2.5 rounded-md px-2 py-1.5 text-[13px] leading-none tracking-tight outline-hidden select-none transition-colors focus:bg-secondary/70 focus:text-foreground data-[state=open]:bg-secondary/70 data-[inset]:pl-9 [&>svg]:pointer-events-none [&>svg]:shrink-0 [&>svg]:!size-3.5 [&>svg:not([class*=\'text-\'])]:text-muted-foreground/80',
                props.class,
            )
        "
    >
        <slot />
        <ChevronRight class="ml-auto !size-3 !text-muted-foreground/50" />
    </ContextMenuSubTrigger>
</template>
