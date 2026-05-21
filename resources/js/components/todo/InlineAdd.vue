<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { TodoList } from '@/types';

const props = defineProps<{ list: TodoList }>();

const title = ref('');
const submitting = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

function submit() {
    const value = title.value.trim();
    if (!value || submitting.value) {
        return;
    }
    submitting.value = true;
    const payload =
        props.list.type === 'master'
            ? { title: value }
            : { title: value, list_id: props.list.id };
    router.post('/todos', payload, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            title.value = '';
            submitting.value = false;
            inputRef.value?.focus();
        },
    });
}

function blur(e: FocusEvent) {
    if (!title.value.trim()) {
        (e.target as HTMLInputElement)?.blur();
    } else {
        submit();
    }
}
</script>

<template>
    <form class="flex items-center gap-3 py-2.5" @submit.prevent="submit">
        <span class="grid size-5 shrink-0 place-items-center text-muted-foreground/60">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 16 16"
                fill="currentColor"
                class="size-3"
            >
                <path
                    d="M8.75 3.75a.75.75 0 0 0-1.5 0v3.5h-3.5a.75.75 0 0 0 0 1.5h3.5v3.5a.75.75 0 0 0 1.5 0v-3.5h3.5a.75.75 0 0 0 0-1.5h-3.5v-3.5Z"
                />
            </svg>
        </span>
        <input
            ref="inputRef"
            v-model="title"
            type="text"
            placeholder="Type om toe te voegen…"
            class="w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground/60"
            @blur="blur"
            @keydown.enter.prevent="submit"
        />
    </form>
</template>
