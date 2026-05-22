<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowRight, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PageHero from '@/components/PageHero.vue';
import TodoListView from '@/components/todo/TodoListView.vue';
import type { Todo, TodoList } from '@/types';

const props = defineProps<{
    date: string;
    isToday: boolean;
    list: (TodoList & { todos: Todo[] }) | null;
    needsRitual: boolean;
    previousWorkday: string;
    carryOverCandidates: Todo[];
    masterOpenTodos: Todo[];
    preScheduled: Todo[];
}>();

const selectedCarry = ref<Set<number>>(new Set());
const selectedFromMaster = ref<Set<number>>(new Set());
const newTodos = ref<string[]>([]);
const newTodoTitle = ref('');
const adding = ref(false);

const headerTitle = computed(() => {
    const d = new Date(props.date + 'T00:00:00');

    if (props.isToday) {
        return 'Vandaag';
    }

    return d.toLocaleDateString('nl-NL', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
});

const headerSub = computed(() => {
    const d = new Date(props.date + 'T00:00:00');

    return d.toLocaleDateString('nl-NL', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const openCount = computed(
    () => props.list?.todos.filter((t) => !t.completed_at).length ?? 0,
);
const doneCount = computed(
    () => props.list?.todos.filter((t) => t.completed_at).length ?? 0,
);
const completionPercent = computed(() => {
    const total = openCount.value + doneCount.value;

    if (total === 0) {
return 0;
}

    return Math.round((doneCount.value / total) * 100);
});

function toggleCarry(id: number) {
    if (selectedCarry.value.has(id)) {
selectedCarry.value.delete(id);
} else {
selectedCarry.value.add(id);
}
}

function toggleMaster(id: number) {
    if (selectedFromMaster.value.has(id)) {
selectedFromMaster.value.delete(id);
} else {
selectedFromMaster.value.add(id);
}
}

function addNewTodo() {
    const title = newTodoTitle.value.trim();

    if (!title) {
return;
}

    newTodos.value.push(title);
    newTodoTitle.value = '';
}

function removeNewTodo(idx: number) {
    newTodos.value.splice(idx, 1);
}

function startDay() {
    if (adding.value) {
return;
}

    adding.value = true;

    const carryOverIds = [...selectedCarry.value, ...selectedFromMaster.value];

    router.post(
        `/day/${props.date}/start`,
        {
            carry_over_ids: carryOverIds,
            new_titles: newTodos.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                adding.value = false;
            },
        },
    );
}

const previousLabel = computed(() => {
    const d = new Date(props.previousWorkday + 'T00:00:00');

    return d.toLocaleDateString('nl-NL', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
});
</script>

<template>
    <Head :title="headerTitle" />

    <PageHero
        :eyebrow="isToday ? 'vandaag' : 'dag'"
        :title="headerTitle"
        accent
        :sub="headerSub"
        :counter="
            !needsRitual && list
                ? { value: `${completionPercent}%`, label: 'klaar' }
                : undefined
        "
    />

    <div v-if="needsRitual" class="px-6 pb-40 sm:px-10">
        <form
            class="group flex items-center gap-3 border-b border-border py-4"
            @submit.prevent="addNewTodo"
        >
            <span
                class="grid size-5 shrink-0 place-items-center text-muted-foreground/50"
            >
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
                v-model="newTodoTitle"
                type="text"
                placeholder="Iets nieuws voor vandaag…"
                class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground/50"
            />
        </form>

        <ul v-if="newTodos.length" class="mt-1">
            <li
                v-for="(t, i) in newTodos"
                :key="i"
                class="group flex items-center gap-3 border-b border-border/40 py-2.5"
            >
                <span class="size-1.5 shrink-0 rounded-full bg-accent" />
                <span class="flex-1 text-sm">{{ t }}</span>
                <button
                    type="button"
                    class="text-muted-foreground/40 opacity-0 transition-opacity group-hover:opacity-100 hover:text-foreground"
                    aria-label="Verwijderen"
                    @click="removeNewTodo(i)"
                >
                    <X class="size-3.5" />
                </button>
            </li>
        </ul>

        <section class="pt-12">
            <header class="mb-2 flex items-baseline gap-2">
                <span
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                >
                    meenemen
                </span>
                <span
                    v-if="carryOverCandidates.length"
                    class="font-mono text-[10px] text-muted-foreground/40"
                    >({{ carryOverCandidates.length }})</span
                >
                <span
                    class="ml-auto font-mono text-[10px] tracking-wide text-muted-foreground/60 lowercase"
                    >van {{ previousLabel }}</span
                >
            </header>
            <p
                v-if="carryOverCandidates.length === 0"
                class="py-3 text-sm text-muted-foreground/70"
            >
                Niets blijven liggen.
            </p>
            <ul v-else>
                <li
                    v-for="todo in carryOverCandidates"
                    :key="`carry-${todo.id}`"
                    class="border-b border-border/40"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 py-2.5 text-left"
                        @click="toggleCarry(todo.id)"
                    >
                        <span
                            class="relative grid size-5 shrink-0 place-items-center rounded-full border transition-colors"
                            :class="
                                selectedCarry.has(todo.id)
                                    ? 'border-accent bg-accent text-accent-foreground'
                                    : 'border-input'
                            "
                        >
                            <svg
                                v-if="selectedCarry.has(todo.id)"
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
                        </span>
                        <span
                            class="flex-1 text-sm transition-colors"
                            :class="
                                !selectedCarry.has(todo.id) &&
                                'text-muted-foreground'
                            "
                            >{{ todo.title }}</span
                        >
                    </button>
                </li>
            </ul>
        </section>

        <section class="pt-12">
            <header class="mb-2 flex items-baseline gap-2">
                <span
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                >
                    master
                </span>
                <span
                    v-if="masterOpenTodos.length"
                    class="font-mono text-[10px] text-muted-foreground/40"
                    >({{ masterOpenTodos.length }})</span
                >
            </header>
            <p
                v-if="masterOpenTodos.length === 0"
                class="py-3 text-sm text-muted-foreground/70"
            >
                Master is leeg.
            </p>
            <ul v-else>
                <li
                    v-for="todo in masterOpenTodos"
                    :key="`master-${todo.id}`"
                    class="border-b border-border/40"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 py-2.5 text-left"
                        @click="toggleMaster(todo.id)"
                    >
                        <span
                            class="relative grid size-5 shrink-0 place-items-center rounded-full border transition-colors"
                            :class="
                                selectedFromMaster.has(todo.id)
                                    ? 'border-accent bg-accent text-accent-foreground'
                                    : 'border-input'
                            "
                        >
                            <svg
                                v-if="selectedFromMaster.has(todo.id)"
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
                        </span>
                        <span
                            class="flex-1 text-sm transition-colors"
                            :class="
                                !selectedFromMaster.has(todo.id) &&
                                'text-muted-foreground'
                            "
                            >{{ todo.title }}</span
                        >
                    </button>
                </li>
            </ul>
        </section>

        <section v-if="preScheduled.length" class="pt-12">
            <header class="mb-2 flex items-baseline gap-2">
                <span
                    class="font-mono text-[10px] tracking-widest text-accent uppercase"
                >
                    al gepland
                </span>
                <span class="font-mono text-[10px] text-accent/50"
                    >({{ preScheduled.length }})</span
                >
            </header>
            <ul>
                <li
                    v-for="todo in preScheduled"
                    :key="`pre-${todo.id}`"
                    class="flex items-center gap-3 border-b border-border/40 py-2.5"
                >
                    <span class="size-1.5 shrink-0 rounded-full bg-accent" />
                    <span class="flex-1 text-sm">{{ todo.title }}</span>
                </li>
            </ul>
        </section>

        <div class="flex justify-end pt-12">
            <button
                type="button"
                class="inline-flex items-center gap-2 bg-foreground px-6 py-3 text-sm font-medium text-background transition-opacity hover:opacity-90 disabled:opacity-50"
                :disabled="adding"
                @click="startDay"
            >
                Start de dag
                <ArrowRight class="size-4" />
            </button>
        </div>
    </div>

    <div v-else-if="list" class="px-6 pb-32 sm:px-10">
        <div
            class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-secondary"
        >
            <div
                class="h-full rounded-full bg-accent transition-all"
                :style="{ width: `${completionPercent}%` }"
            />
        </div>
        <div class="pt-8">
            <TodoListView :list="list" :todos="list.todos" />
        </div>
    </div>
</template>
