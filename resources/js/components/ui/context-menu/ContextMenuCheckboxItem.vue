<script setup lang="ts">
import type {
    ContextMenuCheckboxItemEmits,
    ContextMenuCheckboxItemProps,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { Check } from 'lucide-vue-next';
import {
    ContextMenuCheckboxItem,
    ContextMenuItemIndicator,
    useForwardPropsEmits,
} from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<
    ContextMenuCheckboxItemProps & { class?: HTMLAttributes['class'] }
>();
const emits = defineEmits<ContextMenuCheckboxItemEmits>();

const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <ContextMenuCheckboxItem
        data-slot="context-menu-checkbox-item"
        v-bind="forwarded"
        :class="
            cn(
                'relative flex cursor-default items-center gap-2.5 rounded-md px-2 py-1.5 pl-9 text-[13px] leading-none tracking-tight outline-hidden select-none transition-colors focus:bg-secondary/70 data-[disabled]:pointer-events-none data-[disabled]:opacity-40',
                props.class,
            )
        "
    >
        <span
            class="pointer-events-none absolute left-2.5 inline-flex size-3.5 items-center justify-center"
        >
            <ContextMenuItemIndicator>
                <slot name="indicator-icon">
                    <Check class="size-3 text-accent" />
                </slot>
            </ContextMenuItemIndicator>
        </span>
        <slot />
    </ContextMenuCheckboxItem>
</template>
