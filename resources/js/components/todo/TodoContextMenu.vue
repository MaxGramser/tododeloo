<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    CalendarDays,
    CalendarPlus,
    Check,
    Copy,
    Inbox,
    Pencil,
    Plus,
    Sun,
    Tag as TagIcon,
    Trash2,
    X,
} from 'lucide-vue-next';
import {
    ContextMenu,
    ContextMenuCheckboxItem,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuLabel,
    ContextMenuSeparator,
    ContextMenuShortcut,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import type { Priority, SidebarLists, Tag, Todo, TodoList } from '@/types';

const props = defineProps<{
    todo: Todo;
    list: TodoList;
}>();

const emit = defineEmits<{
    (e: 'edit'): void;
    (e: 'open-tags'): void;
    (e: 'pick-date'): void;
}>();

const page = usePage<{
    sidebarLists: SidebarLists | null;
    userTags: Tag[];
    today: string;
}>();

const todayISO = computed(() => page.props.today);
const completed = computed(() => props.todo.completed_at !== null);
const isMaster = computed(() => props.list.type === 'master');
const isDaily = computed(() => props.list.type === 'daily');
const isTodayList = computed(
    () => isDaily.value && props.list.date === todayISO.value,
);

const customLists = computed(() => page.props.sidebarLists?.customs ?? []);
const tags = computed(() => page.props.userTags ?? []);
const selectedTagIds = computed(
    () => new Set((props.todo.tags ?? []).map((t) => t.id)),
);

const priorityOptions: { value: Priority; label: string; dot: string }[] = [
    { value: 'high', label: 'Hoog', dot: 'bg-accent' },
    { value: 'normal', label: 'Normaal', dot: 'bg-muted-foreground/40' },
    { value: 'low', label: 'Laag', dot: 'bg-muted-foreground/15' },
];

const currentPriorityDot = computed(() => {
    return (
        priorityOptions.find((o) => o.value === props.todo.priority)?.dot ??
        'bg-muted-foreground/40'
    );
});

function post(url: string, data: Record<string, unknown> = {}) {
    router.post(url, data, { preserveScroll: true, preserveState: true });
}

function patch(url: string, data: Record<string, unknown> = {}) {
    router.patch(url, data, { preserveScroll: true, preserveState: true });
}

function del(url: string) {
    router.delete(url, { preserveScroll: true, preserveState: true });
}

function toggleCompleted() {
    post(
        completed.value
            ? `/todos/${props.todo.id}/uncomplete`
            : `/todos/${props.todo.id}/complete`,
    );
}

function setPriority(p: Priority) {
    patch(`/todos/${props.todo.id}`, { priority: p });
}

function toggleTag(tag: Tag, checked: boolean) {
    const next = new Set(selectedTagIds.value);
    if (checked) next.add(tag.id);
    else next.delete(tag.id);
    patch(`/todos/${props.todo.id}/tags`, { tag_ids: [...next] });
}

function moveTo(dateISO: string) {
    post(`/todos/${props.todo.id}/move-to-date`, {
        date: dateISO,
        from_list_id: isDaily.value ? props.list.id : null,
    });
}

function addToList(targetListId: number) {
    post(`/todos/${props.todo.id}/lists/${targetListId}`);
}

function addToToday() {
    post(`/todos/${props.todo.id}/add-to-today`);
}

function removeFromCurrentList() {
    del(`/todos/${props.todo.id}/lists/${props.list.id}`);
}

function duplicate() {
    post(`/todos/${props.todo.id}/duplicate`, {
        list_id: isMaster.value ? null : props.list.id,
    });
}

function softDelete() {
    del(`/todos/${props.todo.id}`);
}

function isoOffset(days: number): string {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

function nextWorkdayISO(): string {
    const d = new Date();
    const day = d.getDay();
    let offset = 1;
    if (day === 5) offset = 3;
    if (day === 6) offset = 2;
    d.setDate(d.getDate() + offset);
    return d.toISOString().slice(0, 10);
}
</script>

<template>
    <ContextMenu>
        <ContextMenuTrigger as-child>
            <slot />
        </ContextMenuTrigger>
        <ContextMenuContent>
            <ContextMenuItem @click="toggleCompleted">
                <Check />
                <span>{{
                    completed ? 'Markeer als open' : 'Markeer als done'
                }}</span>
                <ContextMenuShortcut>⌘⏎</ContextMenuShortcut>
            </ContextMenuItem>

            <ContextMenuItem @click="emit('edit')">
                <Pencil />
                <span>Bewerk titel</span>
                <ContextMenuShortcut>⏎</ContextMenuShortcut>
            </ContextMenuItem>

            <ContextMenuSub>
                <ContextMenuSubTrigger>
                    <span
                        class="inline-flex size-3.5 items-center justify-center"
                    >
                        <span
                            :class="['size-2 rounded-full', currentPriorityDot]"
                        />
                    </span>
                    <span>Prioriteit</span>
                </ContextMenuSubTrigger>
                <ContextMenuSubContent>
                    <ContextMenuItem
                        v-for="opt in priorityOptions"
                        :key="opt.value"
                        @click="setPriority(opt.value)"
                    >
                        <span
                            class="inline-flex size-3.5 items-center justify-center"
                        >
                            <span
                                :class="['size-2 rounded-full', opt.dot]"
                            />
                        </span>
                        <span>{{ opt.label }}</span>
                        <ContextMenuShortcut v-if="todo.priority === opt.value"
                            >huidig</ContextMenuShortcut
                        >
                    </ContextMenuItem>
                </ContextMenuSubContent>
            </ContextMenuSub>

            <ContextMenuSub>
                <ContextMenuSubTrigger>
                    <TagIcon />
                    <span>Tags</span>
                </ContextMenuSubTrigger>
                <ContextMenuSubContent>
                    <ContextMenuLabel v-if="tags.length === 0">
                        nog geen tags
                    </ContextMenuLabel>
                    <ContextMenuCheckboxItem
                        v-for="tag in tags"
                        :key="tag.id"
                        :checked="selectedTagIds.has(tag.id)"
                        @update:checked="(v) => toggleTag(tag, v)"
                        @select.prevent
                    >
                        {{ tag.name }}
                    </ContextMenuCheckboxItem>
                    <ContextMenuSeparator v-if="tags.length > 0" />
                    <ContextMenuItem @click="emit('open-tags')">
                        <Plus />
                        <span>Nieuwe tag…</span>
                    </ContextMenuItem>
                </ContextMenuSubContent>
            </ContextMenuSub>

            <ContextMenuSub>
                <ContextMenuSubTrigger>
                    <CalendarDays />
                    <span>Verplaats naar dag</span>
                </ContextMenuSubTrigger>
                <ContextMenuSubContent>
                    <ContextMenuItem @click="moveTo(isoOffset(0))">
                        <Sun />
                        <span>Vandaag</span>
                    </ContextMenuItem>
                    <ContextMenuItem @click="moveTo(isoOffset(1))">
                        <CalendarDays />
                        <span>Morgen</span>
                    </ContextMenuItem>
                    <ContextMenuItem @click="moveTo(nextWorkdayISO())">
                        <CalendarDays />
                        <span>Volgende werkdag</span>
                    </ContextMenuItem>
                    <ContextMenuSeparator />
                    <ContextMenuItem @click="emit('pick-date')">
                        <CalendarPlus />
                        <span>Specifieke datum…</span>
                    </ContextMenuItem>
                </ContextMenuSubContent>
            </ContextMenuSub>

            <ContextMenuSub v-if="customLists.length > 0">
                <ContextMenuSubTrigger>
                    <Inbox />
                    <span>Zet op lijst</span>
                </ContextMenuSubTrigger>
                <ContextMenuSubContent>
                    <ContextMenuItem
                        v-for="c in customLists"
                        :key="c.id"
                        :disabled="c.id === list.id"
                        @click="addToList(c.id)"
                    >
                        <Inbox />
                        <span>{{ c.name }}</span>
                    </ContextMenuItem>
                </ContextMenuSubContent>
            </ContextMenuSub>

            <ContextMenuSeparator />

            <ContextMenuItem v-if="!isTodayList" @click="addToToday">
                <Sun />
                <span>Zet in vandaag</span>
            </ContextMenuItem>

            <ContextMenuItem
                v-if="isTodayList"
                @click="removeFromCurrentList"
            >
                <X />
                <span>Verwijder van vandaag</span>
                <ContextMenuShortcut>blijft master</ContextMenuShortcut>
            </ContextMenuItem>

            <ContextMenuItem
                v-else-if="!isMaster"
                @click="removeFromCurrentList"
            >
                <X />
                <span>Verwijder van deze lijst</span>
                <ContextMenuShortcut>blijft master</ContextMenuShortcut>
            </ContextMenuItem>

            <ContextMenuItem @click="duplicate">
                <Copy />
                <span>Dupliceer</span>
            </ContextMenuItem>

            <ContextMenuSeparator />

            <ContextMenuItem variant="destructive" @click="softDelete">
                <Trash2 />
                <span>Verwijder volledig</span>
                <ContextMenuShortcut>⌫</ContextMenuShortcut>
            </ContextMenuItem>
        </ContextMenuContent>
    </ContextMenu>
</template>
