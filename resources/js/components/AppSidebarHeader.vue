<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { AppPageProps, BreadcrumbItemType } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const page = usePage<AppPageProps>();
const isPlatformSupport = computed(() => page.props.is_platform_support === true);
const { t } = useI18n();

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <Link
            v-if="isPlatformSupport"
            :href="route('tenant.central-entry.exit')"
            as="button"
            class="ml-auto rounded-md border border-border px-3 py-1.5 text-sm font-medium text-foreground hover:bg-accent"
            dusk="central-support-exit"
            method="post"
        >
            {{ t('common.exit_platform_support') }}
        </Link>
    </header>
</template>
