<script setup lang="ts">
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('common.dashboard'),
        href: '/dashboard',
    },
]);

const loading = ref(true);
const orders = ref<any[]>([]);

const recentFive = computed(() => {
    const list = [...orders.value];
    list.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
    return list.slice(0, 5);
});

onMounted(async () => {
    try {
        const res = await fetch('/api/v1/orders', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const json = await res.json().catch(() => null);
        orders.value = Array.isArray(json?.data) ? json.data : Array.isArray(json) ? json : [];
    } catch (error: unknown) {
        console.error('Error fetching orders', error);
        orders.value = [];
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head :title="t('common.dashboard')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <Card>
                <div class="px-6 py-2">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="text-base font-semibold">{{ t('common.recent_orders') }}</h2>
                        <Link :href="route('web.orders.index')" class="text-sm underline">{{ t('common.view_more') }}</Link>
                    </div>
                    <div v-if="loading" class="relative min-h-[30vh] rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern />
                    </div>
                    <div v-else>
                        <div v-if="recentFive.length" class="divide-y">
                            <div v-for="o in recentFive" :key="o.uuid" class="flex items-center justify-between gap-4 py-3">
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ o.title }}</div>
                                    <div class="truncate text-xs text-muted-foreground">
                                        {{ t('orders.status') }}: {{ o.status_label ?? t('orders.status') }} • {{ t('orders.priority') }}:
                                        {{ o.priority_label ?? t('orders.priority') }} • {{ t('orders.created_at') }}:
                                        {{ formatDateTime(o.created_at) }}
                                    </div>
                                </div>
                                <Link :href="route('web.orders.show', o.uuid)" class="shrink-0 text-sm underline">{{ t('common.view') }}</Link>
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">{{ t('common.empty') }}</div>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
