<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, nextTick, ref, watch } from 'vue';
import type { Tag, Todo } from '@/types';

const props = defineProps<{ todo: Todo; open: boolean }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const page = usePage<{ userTags: Tag[] }>();
const allTags = computed(() => page.props.userTags ?? []);
const selectedIds = ref<Set<number>>(new Set(props.todo.tags.map((t) => t.id)));
const newTagName = ref('');
const newTagInput = ref<HTMLInputElement | null>(null);
const root = ref<HTMLElement | null>(null);

onClickOutside(root, () => close());

watch(
    () => props.todo.tags,
    (tags) => {
        selectedIds.value = new Set(tags.map((t) => t.id));
    },
);

watch(
    () => props.open,
    (open) => {
        if (open) {
            nextTick(() => newTagInput.value?.focus());
        }
    },
);

function toggle(tag: Tag) {
    if (selectedIds.value.has(tag.id)) {
        selectedIds.value.delete(tag.id);
    } else {
        selectedIds.value.add(tag.id);
    }
    sync();
}

function sync() {
    router.patch(
        `/todos/${props.todo.id}/tags`,
        { tag_ids: [...selectedIds.value] },
        { preserveScroll: true, preserveState: true },
    );
}

function createTag() {
    const name = newTagName.value.trim();
    if (!name) return;
    router.post(
        '/tags',
        { name },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                newTagName.value = '';
                nextTick(() => newTagInput.value?.focus());
            },
        },
    );
}

function close() {
    emit('update:open', false);
}
</script>

<template>
    <div
        v-if="open"
        ref="root"
        class="absolute right-0 z-30 mt-2 w-64 rounded-xl border border-border bg-card p-3 shadow-lg"
        @click.stop
    >
        <header class="mb-2 flex items-center justify-between">
            <span
                class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase"
                >tags</span
            >
            <button
                type="button"
                class="font-mono text-[10px] tracking-widest text-muted-foreground uppercase hover:text-foreground"
                @click="close"
            >
                sluit
            </button>
        </header>
        <ul v-if="allTags.length" class="mb-3 max-h-48 overflow-y-auto">
            <li v-for="tag in allTags" :key="tag.id">
                <label
                    class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-sm hover:bg-secondary"
                >
                    <input
                        type="checkbox"
                        :checked="selectedIds.has(tag.id)"
                        class="size-3.5 rounded border-input accent-accent"
                        @change="toggle(tag)"
                    />
                    <span>{{ tag.name }}</span>
                </label>
            </li>
        </ul>
        <form class="flex gap-1" @submit.prevent="createTag">
            <input
                ref="newTagInput"
                v-model="newTagName"
                type="text"
                placeholder="Nieuwe tag…"
                class="flex-1 rounded-md border border-input bg-background px-2 py-1 text-sm outline-none focus:ring-2 focus:ring-ring"
            />
            <button
                type="submit"
                class="rounded-md bg-accent px-2 py-1 text-xs text-accent-foreground hover:bg-accent/90"
            >
                +
            </button>
        </form>
    </div>
</template>
