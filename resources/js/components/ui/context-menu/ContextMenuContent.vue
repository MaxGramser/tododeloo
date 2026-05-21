<script setup lang="ts">
import type {
    ContextMenuContentEmits,
    ContextMenuContentProps,
} from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import {
    ContextMenuContent,
    ContextMenuPortal,
    useForwardPropsEmits,
} from 'reka-ui';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<
    ContextMenuContentProps & { class?: HTMLAttributes['class'] }
>();
const emits = defineEmits<ContextMenuContentEmits>();

const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <ContextMenuPortal>
        <ContextMenuContent
            data-slot="context-menu-content"
            v-bind="{ ...$attrs, ...forwarded }"
            :class="
                cn(
                    'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 z-50 min-w-[14rem] origin-(--reka-context-menu-content-transform-origin) overflow-hidden rounded-xl border border-border/70 bg-card p-1.5 shadow-2xl shadow-black/10',
                    props.class,
                )
            "
        >
            <slot />
        </ContextMenuContent>
    </ContextMenuPortal>
</template>
