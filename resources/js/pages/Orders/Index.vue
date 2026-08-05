<script lang="ts" setup>
import OrderStatusIndicators from '@/components/orders/OrderStatusIndicators.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/i18n';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import type {
    OrderAdministrationOptions,
    OrderIndexFilters,
    OrderLifecycleStatus,
    OrderListUser,
    OrderSummary,
    PaginatedOrders,
    RefundStatus,
} from '@/types/orders';
import { resolveLifecycleStatus } from '@/types/orders';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    can_create_order: boolean;
    orders: PaginatedOrders;
    filters: OrderIndexFilters;
    options: OrderAdministrationOptions;
}>();

const { t, tm } = useI18n();
const page = usePage<AppPageProps>();
const filters = ref<OrderIndexFilters>({ ...props.filters });
const filtersOpen = ref(false);
const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const flash = computed(() => ({ ...page.props.flash, ...page.flash }));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: t('common.orders'), href: route('web.orders.index') }]);

const indicatorLabels = computed(() => ({
    lifecycle: t('orders.lifecycle_status'),
    priority: t('orders.priority'),
    payment: t('orders.payment_status'),
    unpaid: t('orders.unpaid'),
    disposition: t('orders.disposition_status'),
    refund: t('orders.refund_status'),
}));

const refundStatusLabels = computed(() => tm('orders.refund_status_labels') as Record<string, string>);

watch(
    () => props.filters,
    (next) => {
        filters.value = { ...next };
    },
    { deep: true },
);

function lifecycleStatus(order: OrderSummary): OrderLifecycleStatus | null {
    return resolveLifecycleStatus(order.lifecycle_status);
}

function refundStatuses(order: OrderSummary): RefundStatus[] {
    return (order.refunds ?? []).map((refund) => refund.status);
}

function userName(user: OrderListUser | null | undefined): string {
    if (!user) {
        return t('orders.unassigned');
    }

    if (user.full_name) {
        return user.full_name;
    }

    return [user.first_name, user.last_name].filter(Boolean).join(' ') || t('orders.unassigned');
}

function statusLabel(order: OrderSummary, field: 'lifecycle' | 'payment' | 'disposition' | 'priority'): string {
    if (field === 'payment' && order.payment_status === 'Partially Paid') {
        return t('orders.unpaid');
    }

    const label =
        field === 'lifecycle'
            ? order.lifecycle_status_label
            : field === 'payment'
              ? order.payment_status_label
              : field === 'disposition'
                ? order.disposition_status_label
                : order.priority_label;

    if (label) {
        return label;
    }

    const value =
        field === 'lifecycle'
            ? order.lifecycle_status
            : field === 'payment'
              ? order.payment_status
              : field === 'disposition'
                ? order.disposition_status
                : order.priority;

    const labelsKey =
        field === 'lifecycle' || field === 'disposition' ? 'status_labels' : field === 'priority' ? 'priority_labels' : `${field}_status_labels`;

    return value ? t(`orders.${labelsKey}.${value}`) : '—';
}

function queryParams(): Record<string, string | number> {
    const params: Record<string, string | number> = {
        per_page: filters.value.per_page || 10,
    };

    for (const key of [
        'title',
        'company_id',
        'assigned_to',
        'priority',
        'lifecycle_status',
        'payment_status',
        'disposition_status',
        'created_from',
        'created_to',
    ] as const) {
        const value = filters.value[key];

        if (value !== '') {
            params[key] = value;
        }
    }

    return params;
}

function visitOrders(): void {
    router.get(route('web.orders.index'), queryParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function scheduleTitleFilter(): void {
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }

    debounceTimer.value = setTimeout(visitOrders, 300);
}

function clearFilters(): void {
    filters.value = {
        title: '',
        company_id: '',
        assigned_to: '',
        priority: '',
        lifecycle_status: '',
        payment_status: '',
        disposition_status: '',
        created_from: '',
        created_to: '',
        per_page: props.filters.per_page || 10,
    };
    visitOrders();
}

onBeforeUnmount(() => {
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }
});
</script>

<template>
    <Head :title="t('common.orders')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4" dusk="orders-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">{{ t('common.orders') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('orders.list_description') }}</p>
                </div>
                <Button v-if="can_create_order" as-child size="sm">
                    <Link :href="route('web.orders.create')">{{ t('orders.create') }}</Link>
                </Button>
            </div>

            <div
                v-if="flash.success || flash.error"
                :class="flash.error ? 'border-destructive/50 text-destructive' : 'border-emerald-500/50 text-emerald-700 dark:text-emerald-300'"
                class="rounded-md border p-3 text-sm"
                dusk="orders-flash"
                role="status"
            >
                {{ flash.error ?? flash.success }}
            </div>

            <Card>
                <div class="border-b border-border">
                    <div class="flex items-center justify-between gap-4 px-6 py-4">
                        <span class="text-sm text-muted-foreground"> {{ orders.from ?? 0 }}–{{ orders.to ?? 0 }} / {{ orders.total }} </span>
                        <button
                            :aria-expanded="filtersOpen"
                            aria-controls="orders-filters-panel"
                            class="inline-flex items-center gap-1 text-sm underline underline-offset-2"
                            dusk="orders-filters-trigger"
                            type="button"
                            @click="filtersOpen = !filtersOpen"
                        >
                            <span :class="filtersOpen ? 'rotate-180' : ''" aria-hidden="true" class="transition-transform duration-200">▼</span>
                            {{ t('orders.filters') }}
                        </button>
                    </div>
                    <Transition
                        enter-active-class="overflow-hidden transition-all duration-200 ease-out"
                        enter-from-class="max-h-0 opacity-0"
                        enter-to-class="max-h-[40rem] opacity-100"
                        leave-active-class="overflow-hidden transition-all duration-150 ease-in"
                        leave-from-class="max-h-[40rem] opacity-100"
                        leave-to-class="max-h-0 opacity-0"
                    >
                        <div
                            v-if="filtersOpen"
                            id="orders-filters-panel"
                            class="grid gap-4 border-t border-border px-6 py-4 sm:grid-cols-2 lg:grid-cols-3"
                            dusk="orders-filters-panel"
                        >
                            <div class="grid gap-2 lg:col-span-2">
                                <label class="text-sm font-medium" for="orders-title">{{ t('orders.title') }}</label>
                                <input
                                    id="orders-title"
                                    v-model="filters.title"
                                    :placeholder="t('orders.search_title')"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @input="scheduleTitleFilter"
                                />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="orders-company">{{ t('orders.company') }}</label>
                                <select
                                    id="orders-company"
                                    v-model="filters.company_id"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @change="visitOrders"
                                >
                                    <option value="">{{ t('orders.all_companies') }}</option>
                                    <option v-for="company in options.companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="orders-assignee">{{ t('orders.assigned_to') }}</label>
                                <select
                                    id="orders-assignee"
                                    v-model="filters.assigned_to"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @change="visitOrders"
                                >
                                    <option value="">{{ t('orders.all_assignees') }}</option>
                                    <option v-for="assignee in options.assignees" :key="assignee.id" :value="assignee.id">
                                        {{ userName(assignee) }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="orders-priority">{{ t('orders.priority') }}</label>
                                <select
                                    id="orders-priority"
                                    v-model="filters.priority"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @change="visitOrders"
                                >
                                    <option value="">{{ t('orders.all_priorities') }}</option>
                                    <option v-for="priority in options.priorities" :key="priority" :value="priority">
                                        {{ t(`orders.priority_labels.${priority}`) }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="orders-lifecycle">{{ t('orders.lifecycle_status') }}</label>
                                <select
                                    id="orders-lifecycle"
                                    v-model="filters.lifecycle_status"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @change="visitOrders"
                                >
                                    <option value="">{{ t('orders.all_lifecycle_statuses') }}</option>
                                    <option v-for="status in options.lifecycle_statuses" :key="status" :value="status">
                                        {{ t(`orders.status_labels.${status}`) }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="orders-payment">{{ t('orders.payment_status') }}</label>
                                <select
                                    id="orders-payment"
                                    v-model="filters.payment_status"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @change="visitOrders"
                                >
                                    <option value="">{{ t('orders.all_payment_statuses') }}</option>
                                    <option v-for="status in options.payment_statuses" :key="status" :value="status">
                                        {{ t(`orders.payment_status_labels.${status}`) }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="orders-disposition">{{ t('orders.disposition_status') }}</label>
                                <select
                                    id="orders-disposition"
                                    v-model="filters.disposition_status"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @change="visitOrders"
                                >
                                    <option value="">{{ t('orders.all_disposition_statuses') }}</option>
                                    <option v-for="status in options.disposition_statuses" :key="status" :value="status">
                                        {{ t(`orders.status_labels.${status}`) }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="orders-created-from">{{ t('orders.created_from') }}</label>
                                <input
                                    id="orders-created-from"
                                    v-model="filters.created_from"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    type="date"
                                    @change="visitOrders"
                                />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="orders-created-to">{{ t('orders.created_to') }}</label>
                                <input
                                    id="orders-created-to"
                                    v-model="filters.created_to"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    type="date"
                                    @change="visitOrders"
                                />
                            </div>
                            <div class="flex items-end">
                                <button class="text-sm underline underline-offset-2" type="button" @click="clearFilters">
                                    {{ t('orders.clear_filters') }}
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-border text-xs text-muted-foreground uppercase">
                            <tr>
                                <th class="px-6 py-3 font-medium">{{ t('orders.title') }}</th>
                                <th class="px-6 py-3 font-medium">{{ t('orders.customer') }}</th>
                                <th class="px-6 py-3 font-medium">{{ t('orders.assigned_to') }}</th>
                                <th class="px-6 py-3 font-medium">{{ t('orders.status') }}</th>
                                <th class="px-6 py-3 font-medium">{{ t('orders.created_at') }}</th>
                                <th class="px-6 py-3 text-right font-medium">{{ t('common.view') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="order in orders.data" :key="order.uuid" :data-order-row="order.uuid">
                                <td class="max-w-xs px-6 py-4 align-top">
                                    <div class="truncate font-medium">{{ order.title }}</div>
                                </td>
                                <td class="px-6 py-4 align-top text-muted-foreground">
                                    {{ userName(order.customer) }}
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <span
                                        :data-orders-assignee="order.uuid"
                                        class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800 dark:bg-sky-950 dark:text-sky-200"
                                    >
                                        {{ userName(order.assigned_to) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <OrderStatusIndicators
                                            :disposition-status="order.disposition_status"
                                            :disposition-status-label="statusLabel(order, 'disposition')"
                                            :labels="indicatorLabels"
                                            :lifecycle-status="lifecycleStatus(order)"
                                            :lifecycle-status-label="statusLabel(order, 'lifecycle')"
                                            :payment-status="order.payment_status"
                                            :payment-status-label="statusLabel(order, 'payment')"
                                            :priority="order.priority"
                                            :priority-label="statusLabel(order, 'priority')"
                                            :refund-status-labels="refundStatusLabels"
                                            :refund-statuses="refundStatuses(order)"
                                        />
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top whitespace-nowrap text-muted-foreground">
                                    {{ formatDateTime(order.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-right align-top">
                                    <Link :href="route('web.orders.show', order.uuid)" class="text-sm underline">{{ t('common.view') }}</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!orders.data.length" class="p-6 text-sm text-muted-foreground">{{ t('orders.no_orders') }}</div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-border px-6 py-4">
                    <label class="flex items-center gap-2 text-sm" for="orders-page-size">
                        {{ t('orders.page_size') }}
                        <select
                            id="orders-page-size"
                            v-model.number="filters.per_page"
                            class="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                            @change="visitOrders"
                        >
                            <option v-for="size in options.per_page" :key="size" :value="size">{{ size }}</option>
                        </select>
                    </label>
                    <nav v-if="orders.last_page > 1" :aria-label="t('orders.pagination')" class="flex items-center gap-1" dusk="orders-pagination">
                        <template v-for="link in orders.links" :key="`${link.label}-${link.page ?? 'ellipsis'}`">
                            <Link
                                v-if="link.url"
                                :class="['rounded px-2 py-1 text-sm', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted']"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                            >
                                {{ link.label }}
                            </Link>
                            <span v-else class="px-2 py-1 text-sm text-muted-foreground">{{ link.label }}</span>
                        </template>
                    </nav>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
