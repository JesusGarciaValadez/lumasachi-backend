<script lang="ts" setup>
import { Card } from '@/components/ui/card';
import type { FinancialTotals } from '@/types/orders';

defineProps<{
    financials: FinancialTotals;
    title: string;
    labels: {
        budgeted: string;
        authorized: string;
        completed: string;
        advance_payment: string;
        remaining_balance: string;
    };
}>();
</script>

<template>
    <Card>
        <div class="flex flex-col gap-4 px-6">
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <dl class="grid grid-cols-2 gap-4 text-sm md:grid-cols-5">
                <div>
                    <dt class="text-muted-foreground">{{ labels.budgeted }}</dt>
                    <dd class="font-semibold">{{ financials.budgeted ?? '0.00' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ labels.authorized }}</dt>
                    <dd class="font-semibold">{{ financials.authorized ?? '0.00' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ labels.completed }}</dt>
                    <dd class="font-semibold">{{ financials.completed ?? '0.00' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ labels.advance_payment }}</dt>
                    <dd class="font-semibold">{{ financials.advance_payment ?? '0.00' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">{{ labels.remaining_balance }}</dt>
                    <dd class="font-semibold">{{ financials.remaining_balance ?? '0.00' }}</dd>
                </div>
            </dl>
        </div>
    </Card>
</template>
