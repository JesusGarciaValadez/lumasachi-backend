<script lang="ts" setup>
import { Card } from '@/components/ui/card';
import type { OrderLifecycleStatus } from '@/types/orders';

defineProps<{
    status: OrderLifecycleStatus | null;
    title: string;
    statuses: Array<{ value: OrderLifecycleStatus; label: string }>;
}>();
</script>

<template>
    <Card>
        <div class="flex flex-col gap-4 px-6">
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <ol :aria-label="title" class="relative flex flex-col gap-4 text-xs sm:flex-row sm:items-start sm:gap-0">
                <li
                    v-for="step in statuses"
                    :key="step.value"
                    :class="step.value === status ? 'font-semibold text-primary' : 'text-muted-foreground'"
                    :aria-current="step.value === status ? 'step' : undefined"
                    :data-active="step.value === status ? 'true' : undefined"
                    class="relative flex min-w-0 flex-1 items-start gap-3 sm:flex-col sm:items-center sm:gap-2 sm:text-center"
                >
                    <span
                        v-if="statuses.indexOf(step) < statuses.length - 1"
                        aria-hidden="true"
                        class="absolute top-6 left-3 h-[calc(100%+1rem)] w-px bg-border sm:top-3 sm:left-1/2 sm:h-px sm:w-full"
                    />
                    <span
                        :class="step.value === status ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                        class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border bg-background"
                    >
                        {{ statuses.indexOf(step) + 1 }}
                    </span>
                    <span class="pt-1 sm:max-w-32 sm:pt-0">{{ step.label }}</span>
                </li>
            </ol>
        </div>
    </Card>
</template>
