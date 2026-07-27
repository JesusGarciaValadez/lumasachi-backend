<script lang="ts" setup>
import { Card } from '@/components/ui/card';
import { formatMoney } from '@/lib/i18n';
import type { FinancialTotals } from '@/types/orders';
import { computed } from 'vue';

const props = defineProps<{
    financials: FinancialTotals;
    title: string;
    labels: {
        budgeted: string;
        baseTotal?: string;
        netTotal?: string;
        authorized: string;
        completed: string;
        advance_payment: string;
        remaining_balance: string;
        payment_state?: string;
        zero_total?: string;
        partial_payment?: string;
        paid_in_full?: string;
        overpaid?: string;
    };
}>();

type PaymentState = 'zero_total' | 'partial_payment' | 'paid_in_full' | 'overpaid';

const paymentState = computed<PaymentState>(() => {
    const completed = Number(props.financials.completed ?? 0);
    const advancePayment = Number(props.financials.advance_payment ?? 0);
    const remainingBalance = Number(props.financials.remaining_balance ?? 0);

    if (completed === 0) {
        return 'zero_total';
    }

    if (remainingBalance > 0) {
        return 'partial_payment';
    }

    return advancePayment > completed ? 'overpaid' : 'paid_in_full';
});

const paymentStateLabel = computed(() => props.labels[paymentState.value]);

function paymentStateClass(state: PaymentState): string {
    return {
        zero_total: 'bg-muted text-muted-foreground',
        partial_payment: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        paid_in_full: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        overpaid: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    }[state];
}
</script>

<template>
    <Card>
        <div class="flex flex-col gap-4 px-6">
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <dl class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4 lg:grid-cols-8">
                <div v-if="labels.baseTotal && financials.budgeted_base !== undefined">
                    <dt class="text-muted-foreground">{{ labels.baseTotal }}</dt>
                    <dd class="font-semibold" data-financial-value="budgeted-base">{{ formatMoney(financials.budgeted_base) }}</dd>
                </div>
                <div v-if="labels.netTotal && financials.budgeted_net !== undefined">
                    <dt class="text-muted-foreground">{{ labels.netTotal }}</dt>
                    <dd class="font-semibold" data-financial-value="budgeted-net">{{ formatMoney(financials.budgeted_net) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ labels.budgeted }}</dt>
                    <dd class="font-semibold" data-financial-value="budgeted">{{ formatMoney(financials.budgeted) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ labels.authorized }}</dt>
                    <dd class="font-semibold" data-financial-value="authorized">{{ formatMoney(financials.authorized) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ labels.completed }}</dt>
                    <dd class="font-semibold" data-financial-value="completed">{{ formatMoney(financials.completed) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ labels.advance_payment }}</dt>
                    <dd class="font-semibold" data-financial-value="advance-payment">{{ formatMoney(financials.advance_payment) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ labels.remaining_balance }}</dt>
                    <dd class="font-semibold" data-financial-value="remaining-balance">{{ formatMoney(financials.remaining_balance) }}</dd>
                </div>
                <div v-if="labels.payment_state && paymentStateLabel">
                    <dt class="text-muted-foreground">{{ labels.payment_state }}</dt>
                    <dd>
                        <span
                            :class="paymentStateClass(paymentState)"
                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            data-payment-state
                        >
                            {{ paymentStateLabel }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>
    </Card>
</template>
