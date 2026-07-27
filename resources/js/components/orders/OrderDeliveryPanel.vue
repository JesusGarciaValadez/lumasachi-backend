<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type { FinancialTotals } from '@/types/orders';
import { computed } from 'vue';

const props = defineProps<{
    financials: FinancialTotals;
    canDeliver: boolean;
    busy: boolean;
    labels: {
        title: string;
        remaining_balance: string;
        payment_required: string;
        deliver: string;
        loading: string;
    };
}>();

const emit = defineEmits<{
    (event: 'deliver'): void;
}>();

const remainingBalance = computed(() => Number(props.financials.remaining_balance ?? 0));

function formatMoney(value: string | number | null | undefined): string {
    return Number(value ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Card data-delivery-panel>
        <div class="flex flex-col gap-4 px-6">
            <div>
                <h2 class="text-base font-semibold">{{ labels.title }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ labels.remaining_balance }}: <span data-delivery-remaining>{{ formatMoney(financials.remaining_balance) }}</span>
                </p>
            </div>
            <div v-if="remainingBalance > 0" class="rounded-md border border-amber-500/50 bg-amber-500/10 p-3 text-sm" role="alert">
                {{ labels.payment_required }}
            </div>
            <Button :disabled="!canDeliver || remainingBalance > 0 || busy" class="self-start" data-delivery-action @click="emit('deliver')">
                {{ busy ? labels.loading : labels.deliver }}
            </Button>
        </div>
    </Card>
</template>
