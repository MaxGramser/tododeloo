<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowRight, Inbox, Plus } from 'lucide-vue-next';
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
    if (total === 0) return 0;
    return Math.round((doneCount.value / total) * 100);
});

function toggleCarry(id: number) {
    if (selectedCarry.value.has(id)) selectedCarry.value.delete(id);
    else selectedCarry.value.add(id);
}

function toggleMaster(id: number) {
    if (selectedFromMaster.value.has(id)) selectedFromMaster.value.delete(id);
    else selectedFromMaster.value.add(id);
}

function addNewTodo() {
    const title = newTodoTitle.value.trim();
    if (!title) return;
    newTodos.value.push(title);
    newTodoTitle.value = '';
}

function removeNewTodo(idx: number) {
    newTodos.value.splice(idx, 1);
}

function startDay() {
    if (adding.value) return;
    adding.value = true;

    const allCarry = [
        ...selectedCarry.value,
        ...selectedFromMaster.value,
    ];

    const finish = async () => {
        // create new todos sequentially so they all land on today's daily list
        for (const title of newTodos.value) {
            await new Promise<void>((resolve) => {
                router.post(
                    '/todos',
                    { title },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onFinish: () => resolve(),
                    },
                );
            });
        }
        // refresh the page so the carry-over + new items appear
        router.reload({ preserveScroll: true });
        adding.value = false;
    };

    if (allCarry.length > 0) {
        router.post(
            `/day/${props.date}/carry-over`,
            { todo_ids: allCarry },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (newTodos.value.length === 0) {
                        router.reload({ preserveScroll: true });
                        adding.value = false;
                    } else {
                        void finish();
                    }
                },
            },
        );
    } else if (newTodos.value.length > 0) {
        void finish();
    } else {
        // user wants an empty daily list — create it by posting an empty carry-over
        router.post(
            `/day/${props.date}/carry-over`,
            { todo_ids: [] },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    router.reload({ preserveScroll: true });
                    adding.value = false;
                },
            },
        );
    }
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
            list
                ? { value: `${completionPercent}%`, label: 'klaar' }
                : undefined
        "
    />

    <div v-if="needsRitual" class="flex flex-col gap-10 px-6 pb-32 sm:px-10">
        <section class="pt-8">
            <header class="mb-3 flex items-center justify-between">
                <h2
                    class="flex items-center gap-2 text-xl font-bold tracking-tight"
                >
                    <Plus class="size-5" /> Nieuw vandaag
                </h2>
                <span
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                >
                    stap 1 · intentie
                </span>
            </header>
            <p class="mb-4 max-w-prose text-sm text-muted-foreground">
                Waar gaat vandaag eigenlijk over? Type wat nieuw is — wordt
                ook in master gezet.
            </p>
            <form
                class="flex items-center gap-2 border-y border-border/60 py-2"
                @submit.prevent="addNewTodo"
            >
                <Plus class="size-4 text-muted-foreground" />
                <input
                    v-model="newTodoTitle"
                    type="text"
                    placeholder="Iets nieuws voor vandaag…"
                    class="flex-1 bg-transparent text-sm outline-none"
                />
                <button
                    type="submit"
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase hover:text-foreground"
                >
                    enter · toevoegen
                </button>
            </form>
            <ul v-if="newTodos.length" class="mt-3 divide-y divide-border/60">
                <li
                    v-for="(t, i) in newTodos"
                    :key="i"
                    class="flex items-center gap-3 py-2"
                >
                    <span class="text-sm">{{ t }}</span>
                    <button
                        type="button"
                        class="ml-auto font-mono text-[10px] tracking-widest text-muted-foreground uppercase hover:text-destructive"
                        @click="removeNewTodo(i)"
                    >
                        verwijder
                    </button>
                </li>
            </ul>
        </section>

        <section>
            <header class="mb-3 flex items-center justify-between">
                <h2 class="text-xl font-bold tracking-tight">
                    Van {{ previousLabel }}
                </h2>
                <span
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                >
                    stap 2 · meenemen
                </span>
            </header>
            <p class="mb-4 max-w-prose text-sm text-muted-foreground">
                Niet-afgemaakte todos van vorige werkdag. Vink aan wat je
                vandaag wil oppakken.
            </p>
            <div
                v-if="carryOverCandidates.length === 0"
                class="rounded-xl border border-dashed border-border/70 bg-card/60 p-6 text-sm text-muted-foreground"
            >
                Niets om mee te nemen — vorige werkdag was schoon afgesloten.
            </div>
            <ul
                v-else
                class="divide-y divide-border/60 border-y border-border/60"
            >
                <li
                    v-for="todo in carryOverCandidates"
                    :key="`carry-${todo.id}`"
                    class="flex items-center gap-3 py-2.5"
                >
                    <input
                        :id="`carry-${todo.id}`"
                        type="checkbox"
                        class="size-4 cursor-pointer rounded border-input accent-accent"
                        :checked="selectedCarry.has(todo.id)"
                        @change="toggleCarry(todo.id)"
                    />
                    <label
                        :for="`carry-${todo.id}`"
                        class="flex-1 cursor-pointer text-sm"
                    >
                        {{ todo.title }}
                    </label>
                </li>
            </ul>
        </section>

        <section>
            <header class="mb-3 flex items-center justify-between">
                <h2
                    class="flex items-center gap-2 text-xl font-bold tracking-tight"
                >
                    <Inbox class="size-5" /> Uit master
                </h2>
                <span
                    class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                >
                    stap 3 · aanvullen
                </span>
            </header>
            <p class="mb-4 max-w-prose text-sm text-muted-foreground">
                Wil je nog iets uit je master oppakken vandaag?
            </p>
            <div
                v-if="masterOpenTodos.length === 0"
                class="rounded-xl border border-dashed border-border/70 bg-card/60 p-6 text-sm text-muted-foreground"
            >
                Master is leeg.
            </div>
            <ul
                v-else
                class="divide-y divide-border/60 border-y border-border/60"
            >
                <li
                    v-for="todo in masterOpenTodos"
                    :key="`master-${todo.id}`"
                    class="flex items-center gap-3 py-2.5"
                >
                    <input
                        :id="`master-${todo.id}`"
                        type="checkbox"
                        class="size-4 cursor-pointer rounded border-input accent-accent"
                        :checked="selectedFromMaster.has(todo.id)"
                        @change="toggleMaster(todo.id)"
                    />
                    <label
                        :for="`master-${todo.id}`"
                        class="flex-1 cursor-pointer text-sm"
                    >
                        {{ todo.title }}
                    </label>
                </li>
            </ul>
        </section>

        <div class="flex items-center justify-end pt-4">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-accent px-5 py-2.5 text-accent-foreground transition-colors hover:bg-accent/90 disabled:opacity-50"
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
