<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ParseModeToggle from '@/components/ParseModeToggle.vue';
import ParsePreview from '@/components/ParsePreview.vue';
import { useQuickAddTarget } from '@/composables/useQuickAddTarget';

const title = ref('');
const parseEnabled = ref(true);
const submitting = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);
const { target } = useQuickAddTarget();

defineExpose({ focus: () => inputRef.value?.focus() });

function submit() {
    if (!title.value.trim() || submitting.value) {
        return;
    }
    submitting.value = true;
    router.post(
        '/quick-add',
        {
            title: title.value.trim(),
            list_id: target.value.listId,
            parse: parseEnabled.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                title.value = '';
                parseEnabled.value = true;
                submitting.value = false;
                inputRef.value?.focus();
            },
        },
    );
}
</script>

<template>
    <form
        class="hidden flex-1 items-center gap-2 md:flex"
        @submit.prevent="submit"
    >
        <div class="relative w-full max-w-2xl">
            <div
                class="flex items-center gap-2 rounded-lg border border-input bg-card px-3 py-1.5 focus-within:ring-2 focus-within:ring-ring"
            >
                <span
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                    >+</span
                >
                <input
                    ref="inputRef"
                    v-model="title"
                    type="text"
                    :placeholder="`Snel toevoegen (⌘K)`"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                />
                <ParseModeToggle v-model="parseEnabled" />
                <span
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                >
                    → {{ target.label }}
                </span>
            </div>
            <ParsePreview :title="title" :parse="parseEnabled" variant="panel" />
        </div>
    </form>
</template>
