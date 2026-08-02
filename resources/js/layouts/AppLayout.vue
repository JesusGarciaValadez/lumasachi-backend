<script setup lang="ts">
import HeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import SidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const canViewSidebar = computed(() => page.props.can_view_sidebar === true);
</script>

<template>
    <SidebarLayout v-if="canViewSidebar" :breadcrumbs="breadcrumbs">
        <slot />
    </SidebarLayout>
    <HeaderLayout v-else :breadcrumbs="breadcrumbs">
        <slot />
    </HeaderLayout>
</template>
