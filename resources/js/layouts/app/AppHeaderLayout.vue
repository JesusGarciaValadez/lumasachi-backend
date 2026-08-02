<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell class="flex-col" variant="header">
        <AppHeader :breadcrumbs="breadcrumbs" :show-mobile-navigation="false" />
        <AppContent>
            <slot />
        </AppContent>
    </AppShell>
</template>
