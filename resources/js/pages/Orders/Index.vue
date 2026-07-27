<script setup lang="ts">
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { OrderApiError, useOrderApi } from '@/composables/useOrderApi';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import type { OrderSummary } from '@/types/orders';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
    can_create_order: boolean;
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: t('common.orders'), href: route('web.orders.index') }]);

const loading = ref(true);
const orders = ref<OrderSummary[]>([]);
const error = ref<OrderApiError | null>(null);
const orderApi = useOrderApi();

onMounted(async () => {
    try {
        orders.value = await orderApi.index();
    } catch (caughtError: unknown) {
        error.value = caughtError instanceof OrderApiError ? caughtError : null;
        orders.value = [];
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head :title="t('common.orders')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <Card>
                <div class="px-6 py-2">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h1 class="text-xl font-semibold">{{ t('common.orders') }}</h1>
                        <Button v-if="can_create_order" as-child size="sm"
                            ><Link :href="route('web.orders.create')">{{ t('orders.create') }}</Link></Button
                        >
                    </div>
                    <div
                        v-if="loading"
                        aria-busy="true"
                        aria-live="polite"
                        class="relative min-h-[40vh] rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <PlaceholderPattern />
                    </div>
                    <div v-else>
                        <div
                            v-if="error"
                            class="mb-4 rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                            role="alert"
                        >
                            {{ error.message }}
                        </div>
                        <div v-if="orders.length" class="divide-y">
                            <div v-for="o in orders" :key="o.uuid" class="flex items-center justify-between gap-4 py-3">
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
