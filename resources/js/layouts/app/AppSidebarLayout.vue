<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import QuickAddBar from '@/components/QuickAddBar.vue';
import QuickAddFab from '@/components/QuickAddFab.vue';
import QuickAddModal from '@/components/QuickAddModal.vue';
import { Toaster } from '@/components/ui/sonner';
import { useQuickAddShortcut } from '@/composables/useQuickAddShortcut';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { open } = useQuickAddShortcut();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs">
                <QuickAddBar />
            </AppSidebarHeader>
            <slot />
        </AppContent>
        <QuickAddModal v-model:open="open" />
        <QuickAddFab @open="open = true" />
        <Toaster position="bottom-right" />
    </AppShell>
</template>
