<script setup lang="ts">
import OrderStatusIndicators from '@/components/orders/OrderStatusIndicators.vue';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import type { OrderLifecycleStatus, OrderSummary, RefundStatus } from '@/types/orders';
import { resolveLifecycleStatus } from '@/types/orders';
import type { UserAdministrationListUser } from '@/types/users';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const { t, tm } = useI18n();

const props = defineProps<{
    can_create_user?: boolean;
    can_view_users?: boolean;
    is_customer?: boolean;
    recent_users?: UserAdministrationListUser[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('common.dashboard'),
        href: '/dashboard',
    },
]);

const loading = ref(true);
const orders = ref<OrderSummary[]>([]);
const recentUsers = computed(() => (props.recent_users ?? []).slice(0, 5));
const canViewUsers = computed(() => props.can_view_users === true);
const canCreateUser = computed(() => props.can_create_user === true);
const isCustomer = computed(() => props.is_customer === true);

const recentFive = computed(() => {
    const list = [...orders.value];
    list.sort((a, b) => new Date(b.created_at ?? 0).getTime() - new Date(a.created_at ?? 0).getTime());
    return list.slice(0, 5);
});

const indicatorLabels = computed(() => ({
    lifecycle: t('orders.lifecycle_status'),
    priority: t('orders.priority'),
    payment: t('orders.payment_status'),
    disposition: t('orders.disposition_status'),
    refund: t('orders.refund_status'),
}));

const refundStatusLabels = computed(() => tm('orders.refund_status_labels') as Record<string, string>);

function lifecycleStatus(order: OrderSummary): OrderLifecycleStatus | null {
    return resolveLifecycleStatus(order.lifecycle_status);
}

function refundStatuses(order: OrderSummary): RefundStatus[] {
    return (order.refunds ?? []).map((refund) => refund.status);
}

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
            <div v-if="!isCustomer" class="grid auto-rows-min gap-4 md:grid-cols-3" data-dashboard-summary dusk="dashboard-summary">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <Card v-if="canViewUsers" :data-can-create-user="canCreateUser" class="min-h-52" data-user-card>
                    <div class="flex h-full flex-col p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="font-semibold">{{ t('users.recent') }}</h2>
                                <p class="mt-1 text-sm text-muted-foreground">{{ t('users.recent_description') }}</p>
                            </div>
                            <Link :href="route('users.index')" class="text-sm underline" data-user-card-link>{{ t('common.view_more') }}</Link>
                        </div>
                        <div v-if="recentUsers.length" class="mt-4 space-y-1 text-sm" data-user-card-users>
                            <Link
                                v-for="user in recentUsers"
                                :key="user.uuid"
                                :data-user-card-user="user.uuid"
                                :dusk="`dashboard-user-${user.uuid}`"
                                :href="route('user.show', user.uuid)"
                                class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-muted/50"
                            >
                                <span class="truncate font-medium">{{ user.first_name }} {{ user.last_name }}</span>
                                <span class="shrink-0 text-xs text-muted-foreground">{{ t(`users.roles.${user.role}`) }}</span>
                            </Link>
                        </div>
                        <p v-else class="mt-4 text-sm text-muted-foreground">{{ t('common.empty') }}</p>
                    </div>
                </Card>
                <div v-else class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <Card data-recent-orders dusk="recent-orders">
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
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <OrderStatusIndicators
                                            :disposition-status="o.disposition_status"
                                            :disposition-status-label="o.disposition_status_label"
                                            :labels="indicatorLabels"
                                            :lifecycle-status="lifecycleStatus(o)"
                                            :lifecycle-status-label="o.lifecycle_status_label"
                                            :payment-status="o.payment_status"
                                            :payment-status-label="o.payment_status_label"
                                            :priority="o.priority"
                                            :priority-label="o.priority_label"
                                            :refund-status-labels="refundStatusLabels"
                                            :refund-statuses="refundStatuses(o)"
                                        />
                                    </div>
                                    <div class="mt-1 truncate text-xs text-muted-foreground">
                                        {{ t('orders.created_at') }}: {{ formatDateTime(o.created_at) }}
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
