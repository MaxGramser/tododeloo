<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import { Plus, X } from 'lucide-vue-next';
import type { SubTodo, Todo } from '@/types';

const props = defineProps<{
    todo: Todo;
    autofocus?: boolean;
}>();

const newTitle = ref('');
const submitting = ref(false);
const addInputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.autofocus,
    (v) => {
        if (v) {
            nextTick(() => addInputRef.value?.focus());
        }
    },
    { immediate: true },
);

function toggle(sub: SubTodo) {
    router.post(
        `/sub-todos/${sub.id}/toggle`,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function submitNew() {
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
                nextTick(() => addInputRef.value?.focus());
            },
        },
    );
}

function commitTitleEdit(sub: SubTodo, e: FocusEvent | KeyboardEvent) {
    const next = (e.target as HTMLInputElement).value.trim();
    if (!next || next === sub.title) return;
    router.patch(
        `/sub-todos/${sub.id}`,
        { title: next },
        { preserveScroll: true, preserveState: true },
    );
}

function deleteSub(sub: SubTodo) {
    router.delete(`/sub-todos/${sub.id}`, {
        preserveScroll: true,
        preserveState: true,
    });
}

function focusAdd() {
    addInputRef.value?.focus();
}
</script>

<template>
    <div class="relative ml-[14px] pb-2 pl-7">
        <span
            aria-hidden="true"
            class="absolute top-0 bottom-4 left-0 w-px bg-border/70"
        />

        <div class="flex flex-col gap-0">
            <div
                v-for="sub in todo.sub_todos ?? []"
                :key="sub.id"
                class="group relative flex items-center gap-2.5 rounded-md py-1.5 pr-1 pl-2 transition-colors hover:bg-secondary/40"
            >
                <span
                    aria-hidden="true"
                    class="absolute top-1/2 -left-7 h-px w-5 bg-border/70"
                />

                <button
                    type="button"
                    class="grid size-3.5 shrink-0 place-items-center rounded-full border transition-colors"
                    :class="
                        sub.completed_at
                            ? 'border-accent bg-accent text-accent-foreground'
                            : 'border-input hover:border-accent'
                    "
                    :aria-label="
                        sub.completed_at
                            ? 'Markeer als open'
                            : 'Markeer als done'
                    "
                    @click="toggle(sub)"
                >
                    <svg
                        v-if="sub.completed_at"
                        viewBox="0 0 16 16"
                        fill="currentColor"
                        class="size-2"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.131.094l-3-3a.75.75 0 1 1 1.06-1.06l2.37 2.37 4.453-6.678a.75.75 0 0 1 1.04-.266Z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>
                <input
                    :value="sub.title"
                    type="text"
                    class="flex-1 bg-transparent text-[13px] leading-none outline-none"
                    :class="
                        sub.completed_at &&
                        'text-muted-foreground line-through decoration-muted-foreground/50'
                    "
                    @blur="commitTitleEdit(sub, $event)"
                    @keydown.enter.prevent="commitTitleEdit(sub, $event)"
                />
                <button
                    type="button"
                    class="rounded p-0.5 text-muted-foreground/70 opacity-0 transition-opacity hover:bg-secondary hover:text-foreground group-hover:opacity-100"
                    aria-label="Verwijder subtaak"
                    @click="deleteSub(sub)"
                >
                    <X class="size-3" />
                </button>
            </div>

            <form
                class="group/add relative flex items-center gap-2.5 rounded-md py-1.5 pr-1 pl-2 transition-colors hover:bg-secondary/30"
                @submit.prevent="submitNew"
                @click="focusAdd"
            >
                <span
                    aria-hidden="true"
                    class="absolute top-1/2 -left-7 h-px w-5 bg-border/70"
                />

                <span
                    class="grid size-3.5 shrink-0 place-items-center text-muted-foreground/50 transition-all group-focus-within/add:rotate-90 group-focus-within/add:text-accent"
                >
                    <Plus class="size-3" />
                </span>
                <input
                    ref="addInputRef"
                    v-model="newTitle"
                    type="text"
                    placeholder="Nog een subtaak…"
                    class="flex-1 bg-transparent text-[13px] leading-none outline-none placeholder:text-muted-foreground/55"
                />
                <span
                    v-if="newTitle.trim()"
                    class="font-mono text-[9px] tracking-[0.2em] text-muted-foreground/60 uppercase"
                    >⏎</span
                >
            </form>
        </div>
    </div>
</template>
