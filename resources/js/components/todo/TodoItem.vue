<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, GripVertical, Repeat, Trash2, X } from 'lucide-vue-next';
import { computed, inject, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { toast } from 'vue-sonner';
import PriorityDot from '@/components/todo/PriorityDot.vue';
import RecurrenceModal from '@/components/todo/RecurrenceModal.vue';
import SubProgressRing from '@/components/todo/SubProgressRing.vue';
import SubTodoList from '@/components/todo/SubTodoList.vue';
import SubTodoModal from '@/components/todo/SubTodoModal.vue';
import TagChip from '@/components/todo/TagChip.vue';
import TagPicker from '@/components/todo/TagPicker.vue';
import TodoContextMenu from '@/components/todo/TodoContextMenu.vue';
import type { Todo, TodoList } from '@/types';

const props = defineProps<{
    todo: Todo;
    list: TodoList;
    draggable?: boolean;
}>();

const completed = computed(() => props.todo.completed_at !== null);
const isMaster = computed(() => props.list.type === 'master');

const subs = computed(() => props.todo.sub_todos ?? []);
const hasSubs = computed(() => subs.value.length > 0);
const subDone = computed(() => subs.value.filter((s) => s.completed_at).length);

const editing = ref(false);
const editTitle = ref(props.todo.title);
const tagsOpen = ref(false);
const subModalOpen = ref(false);
const recurrenceModalOpen = ref(false);

const recurrenceAnchorISO = computed(() =>
    props.list.type === 'daily' && props.list.date
        ? props.list.date
        : new Date().toLocaleDateString('en-CA'),
);

const subsGlobal = inject<Ref<'all' | 'none' | null>>('subsGlobal', ref(null));
const expanded = ref(subsGlobal.value === 'all' && hasSubs.value);
watch(subsGlobal, (v) => {
    if (v === 'all' && hasSubs.value) {
        expanded.value = true;
    }

    if (v === 'none') {
        expanded.value = false;
    }
});

const otherMemberships = computed(() =>
    (props.todo.list_memberships ?? []).filter((m) => m.id !== props.list.id),
);

function checkboxClick() {
    if (hasSubs.value) {
        expanded.value = !expanded.value;

        return;
    }

    toggleCompleted();
}

function toggleCompleted() {
    const url = completed.value
        ? `/todos/${props.todo.id}/uncomplete`
        : `/todos/${props.todo.id}/complete`;
    router.post(url, {}, { preserveScroll: true, preserveState: true });
}

function toggleExpand() {
    expanded.value = !expanded.value;
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
    <div class="border-b border-border/60" :data-todo-id="todo.id">
        <TodoContextMenu
            :todo="todo"
            :list="list"
            @edit="startEdit"
            @open-tags="tagsOpen = true"
            @add-subtodo="subModalOpen = true"
            @custom-recurrence="recurrenceModalOpen = true"
        >
            <div
                class="group flex items-center gap-2 py-2.5 transition-colors hover:bg-card/40"
                :class="[completed && !hasSubs && 'opacity-60']"
            >
                <button
                    v-if="draggable"
                    type="button"
                    tabindex="-1"
                    class="drag-handle -ml-1 cursor-grab rounded p-0.5 text-muted-foreground/30 opacity-0 transition-all group-hover:opacity-100 hover:bg-secondary hover:text-foreground active:cursor-grabbing"
                    aria-label="Sleep om te verplaatsen"
                >
                    <GripVertical class="size-4" />
                </button>

                <button
                    v-if="hasSubs"
                    type="button"
                    class="grid size-5 shrink-0 place-items-center rounded-full transition-transform hover:scale-110"
                    :aria-label="
                        expanded ? 'Verberg subtaken' : 'Toon subtaken'
                    "
                    @click="checkboxClick"
                >
                    <SubProgressRing
                        :done="subDone"
                        :total="subs.length"
                        :size="18"
                        with-check
                    />
                </button>
                <button
                    v-else
                    type="button"
                    class="relative grid size-5 shrink-0 place-items-center rounded-full border border-input transition-colors"
                    :class="
                        completed
                            ? 'border-accent bg-accent text-accent-foreground'
                            : 'hover:border-accent'
                    "
                    :aria-label="
                        completed ? 'Markeer als open' : 'Markeer als done'
                    "
                    @click="checkboxClick"
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
                        class="block w-full truncate text-left text-sm leading-snug"
                        :class="
                            completed &&
                            'line-through decoration-muted-foreground/60'
                        "
                        @click="startEdit"
                    >
                        {{ todo.title }}
                    </button>
                </div>

                <button
                    v-if="hasSubs"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 transition-colors hover:bg-secondary"
                    :aria-label="
                        expanded ? 'Verberg subtaken' : 'Toon subtaken'
                    "
                    @click="toggleExpand"
                >
                    <span
                        class="font-mono text-[10px] leading-none tracking-wider tabular-nums"
                    >
                        <span
                            :class="
                                subDone > 0
                                    ? 'font-semibold text-accent'
                                    : 'text-muted-foreground'
                            "
                            >{{ subDone }}</span
                        ><span class="text-muted-foreground/50"
                            >/{{ subs.length }}</span
                        >
                    </span>
                    <ChevronDown
                        class="size-3 text-muted-foreground/60 transition-transform"
                        :class="expanded && 'rotate-180'"
                    />
                </button>

                <span
                    v-if="todo.recurrence_id"
                    class="hidden items-center gap-1 text-muted-foreground/60 sm:flex"
                    :title="todo.recurrence?.summary ?? 'Herhalende taak'"
                    :aria-label="todo.recurrence?.summary ?? 'Herhalende taak'"
                >
                    <Repeat class="size-3 shrink-0" />
                    <span
                        v-if="todo.recurrence?.summary"
                        class="font-mono text-[10px] tracking-widest whitespace-nowrap uppercase"
                        >{{ todo.recurrence.summary }}</span
                    >
                </span>

                <div
                    v-if="otherMemberships.length"
                    class="hidden items-center gap-2 font-mono text-[10px] tracking-widest text-muted-foreground/70 uppercase sm:flex"
                >
                    <span
                        v-for="m in otherMemberships"
                        :key="m.id"
                        :class="m.type === 'daily' && 'text-accent/80'"
                        >→ {{ m.label }}</span
                    >
                </div>

                <div
                    v-if="todo.tags?.length"
                    class="hidden items-center gap-1 sm:flex"
                >
                    <TagChip
                        v-for="tag in todo.tags"
                        :key="tag.id"
                        :tag="tag"
                    />
                </div>

                <div
                    class="relative ml-auto flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                >
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

            <SubTodoList v-if="expanded" :todo="todo" />
        </TodoContextMenu>

        <SubTodoModal
            :todo="todo"
            :open="subModalOpen"
            @update:open="subModalOpen = $event"
        />

        <RecurrenceModal
            :todo="todo"
            :open="recurrenceModalOpen"
            :anchor-iso="recurrenceAnchorISO"
            @update:open="recurrenceModalOpen = $event"
        />
    </div>
</template>
