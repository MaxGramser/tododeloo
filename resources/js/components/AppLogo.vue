<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex w-full items-center gap-3">
        <AppLogoIcon class="size-9 shrink-0 text-black dark:text-white" />
        <div class="grid flex-1 text-left">
            <span
                class="brand-dot truncate text-lg leading-none font-bold tracking-tight"
                >Tododeloo</span
            >
            <span
                class="mt-1 font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                >focus · today</span
            >
        </div>
    </div>
</template>
