<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import ParsePreview from '@/components/ParsePreview.vue';
import { useQuickAddTarget } from '@/composables/useQuickAddTarget';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const title = ref('');
const submitting = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);
const { target } = useQuickAddTarget();

watch(
    () => props.open,
    (open) => {
        if (open) {
            title.value = '';
            nextTick(() => inputRef.value?.focus());
        }
    },
);

function close() {
    emit('update:open', false);
}

function submit() {
    if (!title.value.trim() || submitting.value) {
        return;
    }
    submitting.value = true;
    router.post(
        '/quick-add',
        { title: title.value.trim(), list_id: target.value.listId },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                submitting.value = false;
                close();
            },
        },
    );
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-start justify-center bg-black/30 pt-32"
                @click.self="close"
            >
                <form
                    class="w-full max-w-lg rounded-2xl border border-border bg-card p-4 shadow-2xl"
                    @submit.prevent="submit"
                    @keydown.escape="close"
                >
                    <div
                        class="flex items-center justify-between pb-3 font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                    >
                        <span>Quick add</span>
                        <span>→ {{ target.label }}</span>
                    </div>
                    <input
                        ref="inputRef"
                        v-model="title"
                        type="text"
                        placeholder="Wat moet er gebeuren?"
                        class="w-full border-b border-input bg-transparent pb-2 text-xl font-semibold outline-none placeholder:font-normal placeholder:text-muted-foreground"
                    />
                    <div class="min-h-[1.25rem] pt-3">
                        <ParsePreview :title="title" />
                    </div>
                    <div
                        class="flex items-center justify-between pt-3 font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                    >
                        <span>esc · sluit</span>
                        <span>enter · opslaan</span>
                    </div>
                </form>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.12s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
