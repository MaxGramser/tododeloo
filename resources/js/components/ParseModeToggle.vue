<script setup lang="ts">
defineProps<{ modelValue: boolean }>();
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void }>();
</script>

<template>
    <button
        type="button"
        class="font-mono text-[10px] tracking-widest uppercase transition-colors"
        :class="
            modelValue
                ? 'text-muted-foreground/50 hover:text-muted-foreground'
                : 'text-accent'
        "
        :title="
            modelValue
                ? 'Datum en herhaling worden automatisch herkend — klik om letterlijk op te slaan'
                : 'Wordt letterlijk opgeslagen — klik om herkenning weer aan te zetten'
        "
        @click="emit('update:modelValue', !modelValue)"
    >
        {{ modelValue ? 'auto' : 'letterlijk' }}
    </button>
</template>
