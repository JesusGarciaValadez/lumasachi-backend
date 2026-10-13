<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const appName = (import.meta.env.VITE_APP_NAME as string) || 'Laravel';
const page = usePage<AppPageProps>();
const companyName = computed(() => page.props.current_company_name);
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ appName }}</span>
        <span v-if="companyName" class="truncate text-xs leading-tight text-muted-foreground">{{ companyName }}</span>
    </div>
</template>
