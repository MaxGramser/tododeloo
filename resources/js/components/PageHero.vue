<script setup lang="ts">
defineProps<{
    eyebrow?: string;
    title: string;
    sub?: string;
    accent?: boolean;
    counter?: { value: number | string; label: string };
}>();
</script>

<template>
    <section
        class="flex flex-col gap-4 border-b border-border/70 px-6 py-8 sm:px-10 sm:py-12"
    >
        <div class="flex items-end justify-between gap-6">
            <div class="flex flex-col gap-2">
                <span
                    v-if="eyebrow"
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                    >{{ eyebrow }}</span
                >
                <h1
                    class="text-4xl leading-none font-bold tracking-tight sm:text-5xl"
                    :class="accent && 'brand-dot'"
                >
                    {{ title }}
                </h1>
                <p v-if="sub" class="text-sm text-muted-foreground">{{ sub }}</p>
            </div>
            <div
                v-if="counter"
                class="hidden flex-col items-end gap-1 sm:flex"
            >
                <span
                    class="text-4xl leading-none font-bold tracking-tight text-accent"
                    >{{ counter.value }}</span
                >
                <span
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                    >{{ counter.label }}</span
                >
            </div>
        </div>
        <slot />
    </section>
</template>
