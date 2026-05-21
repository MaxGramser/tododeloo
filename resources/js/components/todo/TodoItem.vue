<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { GripVertical, Tag as TagIcon, Trash2, X } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import PriorityDot from '@/components/todo/PriorityDot.vue';
import TagChip from '@/components/todo/TagChip.vue';
import TagPicker from '@/components/todo/TagPicker.vue';
import type { Todo, TodoList } from '@/types';

const props = defineProps<{
    todo: Todo;
    list: TodoList;
    draggable?: boolean;
}>();

const completed = computed(() => props.todo.completed_at !== null);
const isMaster = computed(() => props.list.type === 'master');

const editing = ref(false);
const editTitle = ref(props.todo.title);
const tagsOpen = ref(false);

function toggleCompleted() {
    const url = completed.value
        ? `/todos/${props.todo.id}/uncomplete`
        : `/todos/${props.todo.id}/complete`;
    router.post(
        url,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function startEdit() {
    editTitle.value = props.todo.title;
    editing.value = true;
}

function commitEdit() {
    const next = editTitle.value.trim();
    if (!next || next === props.todo.title) {
        editing.value = false;
        return;
    }
    router.patch(
        `/todos/${props.todo.id}`,
        { title: next },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                editing.value = false;
            },
        },
    );
}

function removeFromList() {
    router.delete(`/todos/${props.todo.id}/lists/${props.list.id}`, {
        preserveScroll: true,
        preserveState: true,
    });
}

function softDelete() {
    const id = props.todo.id;
    router.delete(`/todos/${id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast('Todo verwijderd', {
                action: {
                    label: 'Herstel',
                    onClick: () => {
                        router.post(
                            `/todos/${id}/restore`,
                            {},
                            { preserveScroll: true },
                        );
                    },
                },
            });
        },
    });
}
</script>

<template>
    <div
        class="group flex items-center gap-2 border-b border-border/60 py-2.5 transition-colors hover:bg-card/40"
        :class="[completed && 'opacity-60', draggable && 'cursor-grab active:cursor-grabbing']"
    >
        <span
            v-if="draggable"
            class="text-muted-foreground/30 transition-colors group-hover:text-muted-foreground/70"
            aria-hidden="true"
        >
            <GripVertical class="size-4" />
        </span>
        <button
            type="button"
            class="relative grid size-5 shrink-0 place-items-center rounded-full border border-input transition-colors"
            :class="
                completed
                    ? 'border-accent bg-accent text-accent-foreground'
                    : 'hover:border-accent'
            "
            :aria-label="completed ? 'Markeer als open' : 'Markeer als done'"
            @click="toggleCompleted"
        >
            <svg
                v-if="completed"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 16 16"
                fill="currentColor"
                class="size-3"
            >
                <path
                    fill-rule="evenodd"
                    d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.131.094l-3-3a.75.75 0 1 1 1.06-1.06l2.37 2.37 4.453-6.678a.75.75 0 0 1 1.04-.266Z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <PriorityDot
            :priority="todo.priority"
            :todo-id="todo.id"
            interactive
        />

        <div class="min-w-0 flex-1">
            <input
                v-if="editing"
                v-model="editTitle"
                type="text"
                class="w-full border-b border-input bg-transparent text-sm outline-none"
                autofocus
                @blur="commitEdit"
                @keydown.enter.prevent="commitEdit"
                @keydown.escape="
                    () => {
                        editing = false;
                        editTitle = props.todo.title;
                    }
                "
            />
            <button
                v-else
                type="button"
                class="block w-full text-left text-sm leading-snug truncate"
                :class="completed && 'line-through decoration-muted-foreground/60'"
                @click="startEdit"
            >
                {{ todo.title }}
            </button>
        </div>

        <div
            v-if="todo.tags?.length"
            class="hidden items-center gap-1 sm:flex"
        >
            <TagChip v-for="tag in todo.tags" :key="tag.id" :tag="tag" />
        </div>

        <div
            class="relative ml-auto flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
        >
            <button
                type="button"
                class="rounded p-1 text-muted-foreground hover:bg-secondary hover:text-foreground"
                aria-label="Tags bewerken"
                @click.stop="tagsOpen = !tagsOpen"
            >
                <TagIcon class="size-3.5" />
            </button>
            <button
                v-if="!isMaster"
                type="button"
                class="rounded p-1 text-muted-foreground hover:bg-secondary hover:text-foreground"
                aria-label="Van lijst halen"
                @click="removeFromList"
            >
                <X class="size-3.5" />
            </button>
            <button
                type="button"
                class="rounded p-1 text-muted-foreground hover:bg-secondary hover:text-foreground"
                aria-label="Verwijderen"
                @click="softDelete"
            >
                <Trash2 class="size-3.5" />
            </button>
            <TagPicker
                v-if="tagsOpen"
                :todo="todo"
                :open="tagsOpen"
                @update:open="tagsOpen = $event"
            />
        </div>
    </div>
</template>
