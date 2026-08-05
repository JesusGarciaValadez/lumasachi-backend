<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/i18n';
import type { FinancialTotals, MoneyValue } from '@/types/orders';
import { computed } from 'vue';

type DeliveryFinancialTotals = FinancialTotals & {
    paid?: MoneyValue;
    remaining_change?: MoneyValue;
};

const props = defineProps<{
    financials: DeliveryFinancialTotals;
    canDeliver: boolean;
    busy: boolean;
    paymentAmount?: string;
    labels: {
        title: string;
        payment_amount?: string;
        payment_amount_help?: string;
        submit?: string;
        deliver?: string;
        loading: string;
        net_total?: string;
        budgeted_total?: string;
        authorized_total?: string;
        completed_total?: string;
        total_paid?: string;
        balance_remaining?: string;
        remaining_change?: string;
    };
}>();

const emit = defineEmits<{
    (event: 'update:paymentAmount', value: string): void;
    (event: 'deliver', amount: string): void;
}>();

const remainingBalance = computed(() => Number(props.financials.remaining_balance ?? 0));
const netTotal = computed(() => props.financials.budgeted_net ?? props.financials.budgeted);
const completedTotal = computed(() => Number(props.financials.completed ?? 0));
const totalPaid = computed(() => Number(props.financials.paid ?? props.financials.advance_payment ?? 0));
const remainingChange = computed(() => {
    const persistedChange = Number(props.financials.remaining_change ?? 0);

    return persistedChange > 0 ? persistedChange : Math.max(totalPaid.value - completedTotal.value, 0);
});
const paymentAmount = computed({
    get: () => props.paymentAmount ?? '',
    set: (value: string | number) => emit('update:paymentAmount', String(value)),
});
const paymentAmountIsValid = computed(() => {
    const amount = (props.paymentAmount ?? '').trim();

    return amount !== '' && Number.isFinite(Number(amount)) && Number(amount) >= 0;
});
const paymentAmountLabel = computed(() => props.labels.payment_amount ?? 'Payment amount');
const paymentAmountHelp = computed(() => props.labels.payment_amount_help ?? 'Enter the amount paid by the customer.');
const submitLabel = computed(() => props.labels.submit ?? props.labels.deliver ?? 'Record payment');
const summaryLabels = computed(() => ({
    netTotal: props.labels.net_total ?? 'Net total',
    budgetedTotal: props.labels.budgeted_total ?? 'Budgeted total',
    authorizedTotal: props.labels.authorized_total ?? 'Authorized total',
    completedTotal: props.labels.completed_total ?? 'Completed work total',
    totalPaid: props.labels.total_paid ?? 'Total paid',
    balanceRemaining: props.labels.balance_remaining ?? 'Balance remaining',
    remainingChange: props.labels.remaining_change ?? 'Remaining change',
}));
</script>

<template>
    <Card data-delivery-panel>
        <div class="flex flex-col gap-4 px-6">
            <h2 class="text-base font-semibold">{{ labels.title }}</h2>

            <div v-if="canDeliver" class="flex max-w-sm flex-col gap-2">
                <label class="text-sm font-medium" for="delivery-payment-amount">{{ paymentAmountLabel }}</label>
                <Input
                    id="delivery-payment-amount"
                    v-model="paymentAmount"
                    :disabled="busy"
                    data-delivery-payment
                    dusk="order-delivery-payment"
                    min="0"
                    step="0.01"
                    type="number"
                />
                <p class="text-sm text-muted-foreground">{{ paymentAmountHelp }}</p>
            </div>

            <div class="overflow-x-auto rounded-md border" data-delivery-summary>
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ summaryLabels.netTotal }}</th>
                            <th class="px-3 py-2 font-medium">{{ summaryLabels.budgetedTotal }}</th>
                            <th class="px-3 py-2 font-medium">{{ summaryLabels.authorizedTotal }}</th>
                            <th class="px-3 py-2 font-medium">{{ summaryLabels.completedTotal }}</th>
                            <th class="px-3 py-2 font-medium">{{ summaryLabels.totalPaid }}</th>
                            <th v-if="remainingBalance > 0" class="px-3 py-2 font-medium">{{ summaryLabels.balanceRemaining }}</th>
                            <th v-else-if="remainingChange > 0" class="px-3 py-2 font-medium">{{ summaryLabels.remainingChange }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t">
                            <td class="px-3 py-2 font-semibold" data-delivery-summary-value="net-total">{{ formatMoney(netTotal) }}</td>
                            <td class="px-3 py-2 font-semibold" data-delivery-summary-value="budgeted-total">
                                {{ formatMoney(financials.budgeted) }}
                            </td>
                            <td class="px-3 py-2 font-semibold" data-delivery-summary-value="authorized-total">
                                {{ formatMoney(financials.authorized) }}
                            </td>
                            <td class="px-3 py-2 font-semibold" data-delivery-summary-value="completed-total">
                                {{ formatMoney(financials.completed) }}
                            </td>
                            <td class="px-3 py-2 font-semibold" data-delivery-summary-value="total-paid">{{ formatMoney(totalPaid) }}</td>
                            <td v-if="remainingBalance > 0" class="px-3 py-2 font-semibold" data-delivery-summary-value="balance-remaining">
                                {{ formatMoney(remainingBalance) }}
                            </td>
                            <td v-else-if="remainingChange > 0" class="px-3 py-2 font-semibold" data-delivery-summary-value="remaining-change">
                                {{ formatMoney(remainingChange) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Button
                v-if="canDeliver"
                :disabled="!paymentAmountIsValid || busy"
                class="self-start"
                data-delivery-action
                dusk="order-delivery-action"
                type="button"
                @click="emit('deliver', paymentAmount)"
            >
                {{ busy ? labels.loading : submitLabel }}
            </Button>
        </div>
    </Card>
</template>
