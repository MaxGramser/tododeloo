<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Trash2 } from 'lucide-vue-next';
import PageHero from '@/components/PageHero.vue';
import TodoListView from '@/components/todo/TodoListView.vue';
import type { Todo, TodoList } from '@/types';

const props = defineProps<{
    list: TodoList & { todos: Todo[] };
}>();

const openCount = computed(
    () => props.list.todos.filter((t) => !t.completed_at).length,
);

const editingName = ref(false);
const editName = ref(props.list.name ?? '');

function commitName() {
    const next = editName.value.trim();
    if (!next || next === props.list.name) {
        editingName.value = false;
        return;
    }
    router.patch(
        `/lists/${props.list.id}`,
        { name: next },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                editingName.value = false;
            },
        },
    );
}

function deleteList() {
    if (!confirm(`Lijst "${props.list.name}" verwijderen?`)) {
        return;
    }
    router.delete(`/lists/${props.list.id}`);
}
</script>

<template>
    <Head :title="list.name ?? 'Lijst'" />
    <PageHero
        eyebrow="lijst"
        :title="list.name ?? 'Naamloos'"
        accent
        :counter="{ value: openCount, label: 'open items' }"
    >
        <div class="flex gap-2">
            <button
                type="button"
                class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase hover:text-foreground"
                @click="
                    () => {
                        editName = list.name ?? '';
                        editingName = true;
                    }
                "
            >
                hernoemen
            </button>
            <span class="text-muted-foreground/40">·</span>
            <button
                type="button"
                class="inline-flex items-center gap-1 font-mono text-[10px] tracking-widest text-muted-foreground uppercase hover:text-destructive"
                @click="deleteList"
            >
                <Trash2 class="size-3" />
                verwijder lijst
            </button>
        </div>
        <input
            v-if="editingName"
            v-model="editName"
            type="text"
            autofocus
            class="mt-2 w-full max-w-md rounded-md border border-input bg-card px-3 py-1.5 text-lg outline-none ring-ring focus:ring-2"
            @blur="commitName"
            @keydown.enter.prevent="commitName"
            @keydown.escape="
                () => {
                    editingName = false;
                    editName = list.name ?? '';
                }
            "
        />
    </PageHero>
    <div class="px-6 pb-32 sm:px-10">
        <TodoListView :list="list" :todos="list.todos" />
    </div>
</template>
