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
                'flex cursor-default items-center gap-2 rounded-md px-2 py-1.5 text-sm outline-hidden select-none transition-colors focus:bg-secondary data-[state=open]:bg-secondary data-[inset]:pl-8 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-4 [&_svg:not([class*=\'text-\'])]:text-muted-foreground',
                props.class,
            )
        "
    >
        <slot />
        <ChevronRight class="ml-auto !size-3.5 !text-muted-foreground" />
    </ContextMenuSubTrigger>
</template>
