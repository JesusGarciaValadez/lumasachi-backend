<script lang="ts" setup>
import type { OrderDispositionStatus, OrderLifecycleStatus, OrderPaymentStatus, OrderPriority, RefundStatus } from '@/types/orders';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        labels: {
            lifecycle: string;
            priority: string;
            payment: string;
            disposition: string;
            refund: string;
        };
        priority: OrderPriority;
        priorityLabel?: string | null;
        lifecycleStatus?: OrderLifecycleStatus | null;
        lifecycleStatusLabel?: string | null;
        paymentStatus?: OrderPaymentStatus | null;
        paymentStatusLabel?: string | null;
        dispositionStatus?: OrderDispositionStatus | null;
        dispositionStatusLabel?: string | null;
        refundStatuses?: RefundStatus[];
        refundStatusLabels?: Record<string, string>;
    }>(),
    {
        priorityLabel: null,
        lifecycleStatus: null,
        lifecycleStatusLabel: null,
        paymentStatus: null,
        paymentStatusLabel: null,
        dispositionStatus: null,
        dispositionStatusLabel: null,
        refundStatuses: () => [],
        refundStatusLabels: () => ({}),
    },
);

const uniqueRefundStatuses = computed(() => [...new Set(props.refundStatuses)]);

function priorityClass(priority: OrderPriority): string {
    return {
        Low: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
        Normal: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
        High: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        Urgent: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    }[priority];
}

function statusClass(kind: 'lifecycle' | 'payment' | 'disposition' | 'refund', status: string): string {
    if (kind === 'payment') {
        return (
            {
                Unpaid: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
                'Partially Paid': 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                Paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
            }[status] ?? 'bg-muted text-muted-foreground'
        );
    }

    if (kind === 'disposition') {
        return (
            {
                Returned: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
                Cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
            }[status] ?? 'bg-muted text-muted-foreground'
        );
    }

    if (kind === 'refund') {
        return (
            {
                Requested: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
                Approved: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
                Processed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                Rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
            }[status] ?? 'bg-muted text-muted-foreground'
        );
    }

    return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200';
}
</script>

<template>
    <div :aria-label="labels.lifecycle" class="flex flex-wrap items-center gap-2">
        <span :class="priorityClass(priority)" class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" data-status-indicator="priority">
            {{ labels.priority }}: {{ priorityLabel ?? priority }}
        </span>
        <span
            v-if="lifecycleStatus"
            :class="statusClass('lifecycle', lifecycleStatus)"
            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
            data-status-indicator="lifecycle"
        >
            {{ labels.lifecycle }}: {{ lifecycleStatusLabel ?? lifecycleStatus }}
        </span>
        <span
            v-if="paymentStatus"
            :class="statusClass('payment', paymentStatus)"
            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
            data-status-indicator="payment"
        >
            {{ labels.payment }}: {{ paymentStatusLabel ?? paymentStatus }}
        </span>
        <span
            v-if="dispositionStatus"
            :class="statusClass('disposition', dispositionStatus)"
            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
            data-status-indicator="disposition"
        >
            {{ labels.disposition }}: {{ dispositionStatusLabel ?? dispositionStatus }}
        </span>
        <span
            v-for="status in uniqueRefundStatuses"
            :key="status"
            :class="statusClass('refund', status)"
            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
            data-status-indicator="refund"
        >
            {{ labels.refund }}: {{ refundStatusLabels[status] ?? status }}
        </span>
    </div>
</template>
