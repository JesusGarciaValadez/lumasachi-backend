<script lang="ts" setup>
import { Card } from '@/components/ui/card';
import type { OrderStatus } from '@/types/orders';

defineProps<{
    status: OrderStatus;
    title: string;
    statuses: Array<{ value: OrderStatus; label: string }>;
}>();
</script>

<template>
    <Card>
        <div class="flex flex-col gap-4 px-6">
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <ol :aria-label="title" class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                <li
                    v-for="step in statuses"
                    :key="step.value"
                    :class="step.value === status ? 'font-semibold text-primary' : 'text-muted-foreground'"
                    :aria-current="step.value === status ? 'step' : undefined"
                    class="flex items-center gap-2"
                >
                    <span
                        :class="step.value === status ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                        class="flex size-6 shrink-0 items-center justify-center rounded-full border"
                    >
                        {{ statuses.indexOf(step) + 1 }}
                    </span>
                    <span>{{ step.label }}</span>
                </li>
            </ol>
        </div>
    </Card>
</template>
