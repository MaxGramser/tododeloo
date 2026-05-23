<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    BookOpen,
    CalendarDays,
    Inbox,
    ListChecks,
    Plus,
    Sun,
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { SidebarLists } from '@/types';

const page = usePage<{ sidebarLists: SidebarLists | null }>();
const sidebarLists = computed(() => page.props.sidebarLists);

const { isCurrentUrl } = useCurrentUrl();

const creating = ref(false);
const newListName = ref('');

function submitCreateList() {
    if (!newListName.value.trim()) {
        creating.value = false;
        return;
    }
    router.post(
        '/lists',
        { name: newListName.value.trim() },
        {
            preserveScroll: true,
            onFinish: () => {
                newListName.value = '';
                creating.value = false;
            },
        },
    );
}

function formatDate(iso: string): string {
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('nl-NL', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/today" class="!h-auto">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel class="font-mono text-[10px] tracking-widest uppercase"
                    >focus</SidebarGroupLabel
                >
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                as-child
                                :is-active="isCurrentUrl('/today')"
                                tooltip="Vandaag"
                            >
                                <Link href="/today">
                                    <Sun />
                                    <span>Vandaag</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                as-child
                                :is-active="isCurrentUrl('/master')"
                                tooltip="Master"
                            >
                                <Link href="/master">
                                    <Inbox />
                                    <span>Master</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup
                v-if="sidebarLists?.upcomingDailies?.length"
                class="px-2 py-0"
            >
                <SidebarGroupLabel
                    class="font-mono text-[10px] tracking-widest uppercase"
                    >upcoming</SidebarGroupLabel
                >
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="d in sidebarLists.upcomingDailies"
                            :key="d.id"
                        >
                            <SidebarMenuButton
                                as-child
                                :is-active="isCurrentUrl(d.href)"
                                :tooltip="formatDate(d.date)"
                            >
                                <Link :href="d.href">
                                    <CalendarDays />
                                    <span>{{ formatDate(d.date) }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel
                    class="flex items-center justify-between font-mono text-[10px] tracking-widest uppercase"
                >
                    <span>lijsten</span>
                    <button
                        v-if="!creating"
                        type="button"
                        class="rounded p-0.5 text-muted-foreground hover:bg-sidebar-accent hover:text-foreground"
                        aria-label="Nieuwe lijst"
                        @click="creating = true"
                    >
                        <Plus class="size-3.5" />
                    </button>
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="c in sidebarLists?.customs ?? []"
                            :key="c.id"
                        >
                            <SidebarMenuButton
                                as-child
                                :is-active="isCurrentUrl(c.href)"
                                :tooltip="c.name"
                            >
                                <Link :href="c.href">
                                    <ListChecks />
                                    <span>{{ c.name }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem v-if="creating">
                            <form
                                class="px-2 py-1"
                                @submit.prevent="submitCreateList"
                            >
                                <input
                                    v-model="newListName"
                                    type="text"
                                    autofocus
                                    placeholder="Lijst naam..."
                                    class="w-full rounded-md border border-input bg-background px-2 py-1 text-sm outline-none ring-ring focus:ring-2"
                                    @blur="submitCreateList"
                                    @keydown.escape="
                                        () => {
                                            newListName = '';
                                            creating = false;
                                        }
                                    "
                                />
                            </form>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel
                    class="font-mono text-[10px] tracking-widest uppercase"
                    >ondersteuning</SidebarGroupLabel
                >
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                as-child
                                :is-active="isCurrentUrl('/help')"
                                tooltip="Handleiding"
                            >
                                <Link href="/help">
                                    <BookOpen />
                                    <span>Handleiding</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
