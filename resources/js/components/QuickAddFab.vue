<script setup lang="ts">
import { Plus } from 'lucide-vue-next';

const emit = defineEmits<{ (e: 'open'): void }>();
</script>

<template>
    <button
        type="button"
        class="fixed right-5 bottom-5 z-40 flex size-14 items-center justify-center rounded-full bg-accent text-accent-foreground shadow-lg transition-transform active:scale-95 md:hidden"
        aria-label="Snel toevoegen"
        @click="emit('open')"
    >
        <Plus class="size-6" />
    </button>
</template>
