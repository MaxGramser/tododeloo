<script setup lang="ts">
import type { ContextMenuItemProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { ContextMenuItem, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<
        ContextMenuItemProps & {
            class?: HTMLAttributes['class'];
            inset?: boolean;
            variant?: 'default' | 'destructive';
        }
    >(),
    { variant: 'default' },
);

const delegatedProps = reactiveOmit(props, 'inset', 'variant', 'class');
const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <ContextMenuItem
        data-slot="context-menu-item"
        :data-inset="inset ? '' : undefined"
        :data-variant="variant"
        v-bind="forwardedProps"
        :class="
            cn(
                'relative flex cursor-default items-center gap-2.5 rounded-md px-2 py-1.5 text-[13px] leading-none tracking-tight outline-hidden select-none transition-colors focus:bg-secondary/70 focus:text-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 data-[variant=destructive]:focus:text-destructive data-[disabled]:pointer-events-none data-[disabled]:opacity-40 data-[inset]:pl-9 [&>svg]:pointer-events-none [&>svg]:shrink-0 [&>svg]:!size-3.5 [&>svg:not([class*=\'text-\'])]:text-muted-foreground/80',
                props.class,
            )
        "
    >
        <slot />
    </ContextMenuItem>
</template>
