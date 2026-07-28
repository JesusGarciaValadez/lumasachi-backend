<script lang="ts" setup>
import { Head } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const breadcrumbItems = computed<BreadcrumbItem[]>(() => [{ title: t('settings.language_title'), href: '/settings/language' }]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.language_title')" />

        <SettingsLayout>
            <div class="space-y-8">
                <HeadingSmall :description="t('settings.language_description')" :title="t('settings.language_title')" />

                <section class="rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border">
                    <div class="space-y-2">
                        <h4 class="text-base font-medium">{{ t('settings.language_preference') }}</h4>
                        <p class="text-sm text-muted-foreground">{{ t('settings.language_preference_description') }}</p>
                    </div>

                    <div class="mt-6 max-w-md">
                        <LocaleSwitcher show-flags variant="settings" />
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
