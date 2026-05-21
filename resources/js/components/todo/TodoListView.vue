<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onMounted, provide, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { ChevronsDownUp, ChevronsUpDown } from 'lucide-vue-next';
import InlineAdd from '@/components/todo/InlineAdd.vue';
import ListFilters from '@/components/todo/ListFilters.vue';
import SortModeSelect from '@/components/todo/SortModeSelect.vue';
import TodoItem from '@/components/todo/TodoItem.vue';
import type { Priority, Todo, TodoList } from '@/types';

const props = defineProps<{
    list: TodoList;
    todos: Todo[];
}>();

const priorityWeight: Record<Priority, number> = {
    high: 0,
    normal: 1,
    low: 2,
};

const freshIds = ref<Set<number>>(new Set());
let lastIdSet: Set<number> | null = null;

watch(
    () => props.todos.map((t) => t.id),
    (next) => {
        const nextSet = new Set(next);
        if (lastIdSet) {
            for (const id of nextSet) {
                if (!lastIdSet.has(id)) freshIds.value.add(id);
            }
        }
        lastIdSet = nextSet;
    },
    { immediate: true },
);

const filterPriority = ref<Priority | null>(null);
const filterTagIds = ref<number[]>([]);

function passesFilter(t: Todo): boolean {
    if (filterPriority.value && t.priority !== filterPriority.value) {
        return false;
    }
    if (filterTagIds.value.length > 0) {
        const tagIds = new Set((t.tags ?? []).map((tag) => tag.id));
        for (const id of filterTagIds.value) {
            if (!tagIds.has(id)) return false;
        }
    }
    return true;
}

const filteredTodos = computed(() => props.todos.filter(passesFilter));
const activeTodos = computed(() =>
    filteredTodos.value.filter((t) => !t.completed_at),
);
const doneTodos = computed(() =>
    [...filteredTodos.value.filter((t) => t.completed_at)].sort((a, b) => {
        return (b.completed_at ?? '').localeCompare(a.completed_at ?? '');
    }),
);

const sortedActive = computed(() => {
    const list = [...activeTodos.value];
    const mode = props.list.sort_mode;
    const sorter = (() => {
        switch (mode) {
            case 'manual':
                return (a: Todo, b: Todo) =>
                    (a.position ?? 0) - (b.position ?? 0);
            case 'alphabetical':
                return (a: Todo, b: Todo) => a.title.localeCompare(b.title);
            case 'priority':
                return (a: Todo, b: Todo) => {
                    const dw =
                        priorityWeight[a.priority] - priorityWeight[b.priority];
                    if (dw !== 0) return dw;
                    return b.created_at.localeCompare(a.created_at);
                };
            case 'created_at':
            default:
                return (a: Todo, b: Todo) =>
                    b.created_at.localeCompare(a.created_at);
        }
    })();

    if (mode === 'manual') {
        return list.sort(sorter);
    }

    const fresh = list.filter((t) => freshIds.value.has(t.id));
    const rest = list.filter((t) => !freshIds.value.has(t.id));
    rest.sort(sorter);
    fresh.sort((a, b) => b.created_at.localeCompare(a.created_at));
    return [...fresh, ...rest];
});

const visibleTodoIds = computed(() => sortedActive.value.map((t) => t.id));
const isManual = computed(() => props.list.sort_mode === 'manual');

const manualOrder = ref<Todo[]>([]);
watch(
    sortedActive,
    (items) => {
        if (isManual.value) manualOrder.value = [...items];
    },
    { immediate: true },
);

function onDragEnd() {
    if (!isManual.value) return;
    const ids = manualOrder.value.map((t) => t.id);
    router.post(
        `/lists/${props.list.id}/reorder`,
        { todo_ids: ids },
        { preserveScroll: true, preserveState: true },
    );
}

// Global expand/collapse state for sub-todos, persisted per list in localStorage
const subsGlobal = ref<'all' | 'none' | null>(null);
const subsKey = computed(() => `tododeloo:subs:${props.list.id}`);
provide('subsGlobal', subsGlobal);

onMounted(() => {
    const stored = localStorage.getItem(subsKey.value);
    if (stored === 'all' || stored === 'none') {
        subsGlobal.value = stored;
    }
});

const anyHasSubs = computed(() =>
    props.todos.some((t) => (t.sub_todos ?? []).length > 0),
);
const allSubsExpanded = computed(() => subsGlobal.value === 'all');

function toggleAllSubs() {
    const next = allSubsExpanded.value ? 'none' : 'all';
    subsGlobal.value = next;
    localStorage.setItem(subsKey.value, next);
}
</script>

<template>
    <div class="flex flex-col">
        <ListFilters
            v-model:priority="filterPriority"
            v-model:tag-ids="filterTagIds"
        />
        <div
            class="flex items-center justify-between border-b border-border/60 pt-3 pb-2 font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
        >
            <span
                >{{ activeTodos.length }} open ·
                {{ doneTodos.length }} done</span
            >
            <div class="flex items-center gap-3">
                <button
                    v-if="anyHasSubs"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-[10px] tracking-widest text-muted-foreground uppercase hover:bg-secondary hover:text-foreground"
                    :aria-label="
                        allSubsExpanded
                            ? 'Klap alle subtaken in'
                            : 'Klap alle subtaken uit'
                    "
                    @click="toggleAllSubs"
                >
                    <component
                        :is="
                            allSubsExpanded ? ChevronsDownUp : ChevronsUpDown
                        "
                        class="size-3"
                    />
                    <span>{{ allSubsExpanded ? 'subs ✕' : 'subs ✚' }}</span>
                </button>
                <SortModeSelect
                    :list="list"
                    :visible-todo-ids="visibleTodoIds"
                />
            </div>
        </div>

        <VueDraggable
            v-if="isManual"
            v-model="manualOrder"
            :animation="180"
            filter="input, a, [contenteditable], label, select"
            handle=".drag-handle"
            :prevent-on-filter="false"
            :force-fallback="true"
            :fallback-on-body="true"
            ghost-class="todo-ghost"
            chosen-class="todo-chosen"
            drag-class="todo-dragging"
            @end="onDragEnd"
        >
            <TodoItem
                v-for="todo in manualOrder"
                :key="todo.id"
                :todo="todo"
                :list="list"
                draggable
            />
        </VueDraggable>
        <template v-else>
            <TodoItem
                v-for="todo in sortedActive"
                :key="todo.id"
                :todo="todo"
                :list="list"
            />
        </template>

        <InlineAdd :list="list" />

        <template v-if="doneTodos.length">
            <TodoItem
                v-for="todo in doneTodos"
                :key="`done-${todo.id}`"
                :todo="todo"
                :list="list"
            />
        </template>
    </div>
</template>
