<script setup lang="ts">
import type { ContextMenuTriggerProps } from 'reka-ui';
import { ContextMenuTrigger, useForwardProps } from 'reka-ui';

const props = defineProps<ContextMenuTriggerProps>();
const forwarded = useForwardProps(props);
</script>

<template>
    <ContextMenuTrigger data-slot="context-menu-trigger" v-bind="forwarded">
        <slot />
    </ContextMenuTrigger>
</template>
