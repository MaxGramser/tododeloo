<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import { Plus, X } from 'lucide-vue-next';
import type { SubTodo, Todo } from '@/types';

const props = defineProps<{
    todo: Todo;
    open: boolean;
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const newTitle = ref('');
const submitting = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            newTitle.value = '';
            nextTick(() => inputRef.value?.focus());
        }
    },
);

function close() {
    emit('update:open', false);
}

function submit() {
    const value = newTitle.value.trim();
    if (!value || submitting.value) return;
    submitting.value = true;
    router.post(
        `/todos/${props.todo.id}/sub-todos`,
        { title: value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                newTitle.value = '';
                submitting.value = false;
                nextTick(() => inputRef.value?.focus());
            },
        },
    );
}

function toggleSub(sub: SubTodo) {
    router.post(
        `/sub-todos/${sub.id}/toggle`,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function deleteSub(sub: SubTodo) {
    router.delete(`/sub-todos/${sub.id}`, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-start justify-center bg-black/30 pt-24"
                @click.self="close"
                @keydown.escape="close"
            >
                <div
                    class="w-full max-w-lg rounded-2xl border border-border bg-card p-5 shadow-2xl"
                    @click.stop
                >
                    <header
                        class="mb-4 flex items-start justify-between gap-4"
                    >
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span
                                class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground/70 uppercase"
                                >subtaken van</span
                            >
                            <h2
                                class="truncate text-xl font-bold tracking-tight"
                            >
                                {{ todo.title }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="rounded-md p-1 text-muted-foreground hover:bg-secondary hover:text-foreground"
                            aria-label="Sluit"
                            @click="close"
                        >
                            <X class="size-4" />
                        </button>
                    </header>

                    <ul
                        v-if="(todo.sub_todos ?? []).length"
                        class="mb-3 flex max-h-64 flex-col gap-0.5 overflow-y-auto"
                    >
                        <li
                            v-for="sub in todo.sub_todos"
                            :key="sub.id"
                            class="group flex items-center gap-3 rounded-md px-1 py-1.5 transition-colors hover:bg-secondary/50"
                        >
                            <button
                                type="button"
                                class="grid size-4 shrink-0 place-items-center rounded-full border transition-colors"
                                :class="
                                    sub.completed_at
                                        ? 'border-accent bg-accent text-accent-foreground'
                                        : 'border-input hover:border-accent'
                                "
                                @click="toggleSub(sub)"
                            >
                                <svg
                                    v-if="sub.completed_at"
                                    viewBox="0 0 16 16"
                                    fill="currentColor"
                                    class="size-2.5"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.131.094l-3-3a.75.75 0 1 1 1.06-1.06l2.37 2.37 4.453-6.678a.75.75 0 0 1 1.04-.266Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                            <span
                                class="flex-1 text-sm"
                                :class="
                                    sub.completed_at &&
                                    'text-muted-foreground line-through decoration-muted-foreground/60'
                                "
                                >{{ sub.title }}</span
                            >
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground opacity-0 hover:bg-secondary hover:text-foreground group-hover:opacity-100"
                                aria-label="Verwijder subtaak"
                                @click="deleteSub(sub)"
                            >
                                <X class="size-3" />
                            </button>
                        </li>
                    </ul>

                    <form
                        class="flex items-center gap-3 rounded-lg border border-input bg-background px-3 py-2 focus-within:ring-2 focus-within:ring-ring"
                        @submit.prevent="submit"
                    >
                        <Plus class="size-4 text-muted-foreground" />
                        <input
                            ref="inputRef"
                            v-model="newTitle"
                            type="text"
                            placeholder="Type subtaak + ⏎ voor de volgende…"
                            class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60"
                        />
                    </form>

                    <div
                        class="mt-3 flex items-center justify-between font-mono text-[10px] tracking-[0.25em] text-muted-foreground/70 uppercase"
                    >
                        <span>esc · sluit</span>
                        <span>⏎ · toevoegen</span>
                    </div>
                </div>
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
