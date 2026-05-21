<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHero from '@/components/PageHero.vue';
import TodoListView from '@/components/todo/TodoListView.vue';
import type { Todo, TodoList } from '@/types';

const props = defineProps<{
    list: TodoList & { todos: Todo[] };
}>();

const openCount = computed(
    () => props.list.todos.filter((t) => !t.completed_at).length,
);
</script>

<template>
    <Head title="Master" />
    <PageHero
        eyebrow="alle todos"
        title="Master"
        accent
        sub="Alles wat je ooit nog wil doen, op één plek. Voeg toe, archiveer, of versleep naar een daglijst."
        :counter="{ value: openCount, label: 'open items' }"
    />
    <div class="px-6 pb-32 sm:px-10">
        <TodoListView :list="list" :todos="list.todos" />
    </div>
</template>
