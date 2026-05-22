<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { Todo } from '@/types';

const props = defineProps<{
    todo: Todo;
    open: boolean;
    anchorIso: string;
}>();

const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

type Frequency = 'daily' | 'weekly' | 'monthly' | 'yearly';

const WEEKDAYS = [
    { code: 'MO', label: 'Ma' },
    { code: 'TU', label: 'Di' },
    { code: 'WE', label: 'Wo' },
    { code: 'TH', label: 'Do' },
    { code: 'FR', label: 'Vr' },
    { code: 'SA', label: 'Za' },
    { code: 'SU', label: 'Zo' },
];
const WEEKDAY_NAMES = [
    'zondag',
    'maandag',
    'dinsdag',
    'woensdag',
    'donderdag',
    'vrijdag',
    'zaterdag',
];
const ORDINALS = ['', '1e', '2e', '3e', '4e'];

const frequency = ref<Frequency>('weekly');
const interval = ref(1);
const days = ref<Set<string>>(new Set());
const monthlyMode = ref<'day' | 'weekday'>('weekday');
const submitting = ref(false);

const anchor = computed(() => new Date(props.anchorIso + 'T00:00:00'));
const anchorDayCode = computed(
    () => WEEKDAYS[(anchor.value.getDay() + 6) % 7].code,
);
const anchorDayOfMonth = computed(() => anchor.value.getDate());
const anchorNth = computed(() => {
    const n = Math.ceil(anchorDayOfMonth.value / 7);

    return n >= 5 ? -1 : n;
});
const anchorWeekdayName = computed(() => WEEKDAY_NAMES[anchor.value.getDay()]);

watch(
    () => props.open,
    (open) => {
        if (open) {
            frequency.value = 'weekly';
            interval.value = 1;
            days.value = new Set([anchorDayCode.value]);
            monthlyMode.value = 'weekday';
        }
    },
);

function toggleDay(code: string) {
    if (days.value.has(code)) {
        days.value.delete(code);
    } else {
        days.value.add(code);
    }
}

const rrule = computed(() => {
    const freq = {
        daily: 'DAILY',
        weekly: 'WEEKLY',
        monthly: 'MONTHLY',
        yearly: 'YEARLY',
    }[frequency.value];
    const parts = [`FREQ=${freq}`];

    if (interval.value > 1) {
        parts.push(`INTERVAL=${interval.value}`);
    }

    if (frequency.value === 'weekly') {
        const ordered = WEEKDAYS.filter((d) => days.value.has(d.code)).map(
            (d) => d.code,
        );
        parts.push(
            `BYDAY=${(ordered.length ? ordered : [anchorDayCode.value]).join(',')}`,
        );
    }

    if (frequency.value === 'monthly') {
        if (monthlyMode.value === 'weekday') {
            parts.push(`BYDAY=${anchorNth.value}${anchorDayCode.value}`);
        } else {
            parts.push(`BYMONTHDAY=${anchorDayOfMonth.value}`);
        }
    }

    return parts.join(';');
});

const nthLabel = computed(() =>
    anchorNth.value === -1 ? 'laatste' : ORDINALS[anchorNth.value],
);

function close() {
    emit('update:open', false);
}

function save() {
    if (submitting.value) {
        return;
    }

    submitting.value = true;
    router.post(
        `/todos/${props.todo.id}/recurrence`,
        { rrule: rrule.value, anchor_date: props.anchorIso },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: close,
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-start justify-center bg-black/30 pt-24"
                @click.self="close"
                @keydown.escape="close"
            >
                <div
                    class="w-full max-w-md rounded-2xl border border-border bg-card p-5 shadow-2xl"
                    @click.stop
                >
                    <header class="mb-5 flex items-start justify-between gap-4">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span
                                class="font-mono text-[10px] tracking-[0.25em] text-muted-foreground/70 uppercase"
                                >herhaal · aangepast</span
                            >
                            <h2
                                class="truncate text-xl font-bold tracking-tight"
                            >
                                {{ todo.title }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="rounded-md p-1 text-muted-foreground hover:bg-secondary hover:text-foreground"
                            aria-label="Sluit"
                            @click="close"
                        >
                            <X class="size-4" />
                        </button>
                    </header>

                    <div class="flex flex-col gap-5">
                        <label class="flex items-center gap-3 text-sm">
                            <span class="text-muted-foreground">Elke</span>
                            <input
                                v-model.number="interval"
                                type="number"
                                min="1"
                                max="99"
                                class="w-16 rounded-md border border-input bg-background px-2 py-1 text-sm outline-none focus:ring-2 focus:ring-ring"
                            />
                            <select
                                v-model="frequency"
                                class="flex-1 rounded-md border border-input bg-background px-2 py-1 text-sm outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="daily">
                                    {{ interval > 1 ? 'dagen' : 'dag' }}
                                </option>
                                <option value="weekly">
                                    {{ interval > 1 ? 'weken' : 'week' }}
                                </option>
                                <option value="monthly">
                                    {{ interval > 1 ? 'maanden' : 'maand' }}
                                </option>
                                <option value="yearly">
                                    {{ interval > 1 ? 'jaren' : 'jaar' }}
                                </option>
                            </select>
                        </label>

                        <div v-if="frequency === 'weekly'" class="flex gap-1.5">
                            <button
                                v-for="d in WEEKDAYS"
                                :key="d.code"
                                type="button"
                                class="grid size-9 place-items-center rounded-full border text-xs font-medium transition-colors"
                                :class="
                                    days.has(d.code)
                                        ? 'border-accent bg-accent text-accent-foreground'
                                        : 'border-input text-muted-foreground hover:border-accent'
                                "
                                @click="toggleDay(d.code)"
                            >
                                {{ d.label }}
                            </button>
                        </div>

                        <div
                            v-if="frequency === 'monthly'"
                            class="flex flex-col gap-2"
                        >
                            <button
                                type="button"
                                class="flex items-center gap-3 rounded-lg border px-3 py-2 text-left text-sm transition-colors"
                                :class="
                                    monthlyMode === 'weekday'
                                        ? 'border-accent bg-accent/5'
                                        : 'border-input hover:border-accent/60'
                                "
                                @click="monthlyMode = 'weekday'"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :class="
                                        monthlyMode === 'weekday'
                                            ? 'bg-accent'
                                            : 'bg-muted-foreground/30'
                                    "
                                />
                                Op de {{ nthLabel }} {{ anchorWeekdayName }}
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-3 rounded-lg border px-3 py-2 text-left text-sm transition-colors"
                                :class="
                                    monthlyMode === 'day'
                                        ? 'border-accent bg-accent/5'
                                        : 'border-input hover:border-accent/60'
                                "
                                @click="monthlyMode = 'day'"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :class="
                                        monthlyMode === 'day'
                                            ? 'bg-accent'
                                            : 'bg-muted-foreground/30'
                                    "
                                />
                                Op dag {{ anchorDayOfMonth }} van de maand
                            </button>
                        </div>

                        <p
                            class="font-mono text-[10px] tracking-wider text-muted-foreground/50"
                        >
                            {{ rrule }}
                        </p>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm text-muted-foreground hover:text-foreground"
                            @click="close"
                        >
                            Annuleer
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 bg-foreground px-5 py-2 text-sm font-medium text-background transition-opacity hover:opacity-90 disabled:opacity-50"
                            :disabled="submitting"
                            @click="save"
                        >
                            Bewaar herhaling
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.12s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
