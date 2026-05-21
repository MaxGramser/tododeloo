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
</script>

<template>
    <div
        class="flex flex-col gap-0.5 border-l border-border/60 bg-card/30 py-2 pr-2 pl-9"
    >
        <div
            v-for="sub in todo.sub_todos ?? []"
            :key="sub.id"
            class="group flex items-center gap-2.5 rounded-md px-1 py-1 transition-colors hover:bg-card/60"
        >
            <button
                type="button"
                class="grid size-4 shrink-0 place-items-center rounded-full border transition-colors"
                :class="
                    sub.completed_at
                        ? 'border-accent bg-accent text-accent-foreground'
                        : 'border-input hover:border-accent'
                "
                :aria-label="
                    sub.completed_at ? 'Markeer als open' : 'Markeer als done'
                "
                @click="toggle(sub)"
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
            <input
                :value="sub.title"
                type="text"
                class="flex-1 bg-transparent text-[13px] outline-none"
                :class="
                    sub.completed_at &&
                    'text-muted-foreground line-through decoration-muted-foreground/60'
                "
                @blur="commitTitleEdit(sub, $event)"
                @keydown.enter.prevent="commitTitleEdit(sub, $event)"
            />
            <button
                type="button"
                class="rounded p-0.5 text-muted-foreground opacity-0 hover:bg-secondary hover:text-foreground group-hover:opacity-100"
                aria-label="Verwijder subtodo"
                @click="deleteSub(sub)"
            >
                <X class="size-3" />
            </button>
        </div>

        <form
            class="flex items-center gap-2.5 px-1 py-1"
            @submit.prevent="submitNew"
        >
            <span class="grid size-4 shrink-0 place-items-center text-muted-foreground/50">
                <Plus class="size-3" />
            </span>
            <input
                ref="addInputRef"
                v-model="newTitle"
                type="text"
                placeholder="Subtaak toevoegen…"
                class="flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground/60"
            />
        </form>
    </div>
</template>
