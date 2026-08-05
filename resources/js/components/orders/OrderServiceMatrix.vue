<script lang="ts" setup>
import { Card } from '@/components/ui/card';
import type { OrderServicePayload } from '@/types/orders';
import { computed } from 'vue';

const props = defineProps<{
    services: OrderServicePayload[];
    itemLabels: Record<number, string>;
    selectedIds: number[];
    busy?: boolean;
    mode: 'approval' | 'completion' | 'readonly';
    title: string;
    labels: {
        select: string;
        service: string;
        measurement: string;
        net_price: string;
        budgeted: string;
        authorized: string;
        completed: string;
        yes: string;
        no: string;
        completed_total: string;
        empty: string;
    };
}>();

const emit = defineEmits<{
    (event: 'update:selectedIds', value: number[]): void;
}>();

const groups = computed(() => {
    const grouped = new Map<number, OrderServicePayload[]>();

    const services = props.mode === 'completion' ? props.services.filter((service) => service.is_budgeted) : props.services;

    for (const service of services) {
        const current = grouped.get(service.order_item_id) ?? [];
        current.push(service);
        grouped.set(service.order_item_id, current);
    }

    return [...grouped.entries()];
});

function canSelect(service: OrderServicePayload): boolean {
    if (props.busy) {
        return false;
    }

    if (props.mode === 'approval') {
        return service.is_budgeted && !service.is_completed;
    }

    if (props.mode === 'completion') {
        return service.is_authorized && !service.is_completed;
    }

    return false;
}

const completedNetTotal = computed(() =>
    props.services
        .filter((service) => service.is_budgeted && service.is_completed)
        .reduce((total, service) => total + Number(service.net_price ?? 0), 0)
        .toFixed(2),
);

function statusClass(active: boolean): string {
    return active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-muted text-muted-foreground';
}

function isSelected(serviceId: number): boolean {
    return props.selectedIds.includes(serviceId);
}

function toggle(service: OrderServicePayload): void {
    if (!canSelect(service)) {
        return;
    }

    emit('update:selectedIds', isSelected(service.id) ? props.selectedIds.filter((id) => id !== service.id) : [...props.selectedIds, service.id]);
}
</script>

<template>
    <Card>
        <div class="flex flex-col gap-4 px-6">
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <div v-if="groups.length" class="flex flex-col gap-6">
                <section v-for="[itemId, itemServices] in groups" :key="itemId" class="flex flex-col gap-3">
                    <h3 class="font-medium">{{ itemLabels[itemId] ?? labels.service }}</h3>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <thead class="border-b bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    <th class="w-10 px-3 py-2">
                                        <span class="sr-only">{{ labels.select }}</span>
                                    </th>
                                    <th class="px-3 py-2">{{ labels.service }}</th>
                                    <th class="px-3 py-2">{{ labels.measurement }}</th>
                                    <th class="px-3 py-2">{{ labels.net_price }}</th>
                                    <th class="px-3 py-2">{{ labels.budgeted }}</th>
                                    <th class="px-3 py-2">{{ labels.authorized }}</th>
                                    <th class="px-3 py-2">{{ labels.completed }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="service in itemServices" :key="service.id" :dusk="`order-service-row-${service.service_key}`">
                                    <td class="px-3 py-2 align-top">
                                        <input
                                            v-if="mode !== 'readonly'"
                                            :aria-label="service.service_name ?? labels.service"
                                            :checked="isSelected(service.id)"
                                            :disabled="!canSelect(service)"
                                            :dusk="mode === 'completion' ? `order-completion-service-${service.service_key}` : undefined"
                                            class="size-4 rounded border-input text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            type="checkbox"
                                            @change="toggle(service)"
                                        />
                                    </td>
                                    <td class="px-3 py-2 align-top font-medium">{{ service.service_name ?? labels.service }}</td>
                                    <td class="px-3 py-2 align-top">{{ service.measurement ?? '—' }}</td>
                                    <td class="px-3 py-2 align-top">{{ service.net_price ?? '0.00' }}</td>
                                    <td class="px-3 py-2 align-top">
                                        <span
                                            :class="statusClass(service.is_budgeted)"
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ service.is_budgeted ? labels.yes : labels.no }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <span
                                            :class="statusClass(service.is_authorized)"
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ service.is_authorized ? labels.yes : labels.no }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <span
                                            :class="statusClass(service.is_completed)"
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ service.is_completed ? labels.yes : labels.no }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <div v-if="mode === 'completion'" class="flex justify-between gap-3 rounded-md bg-muted/30 p-3 text-sm">
                    <span class="text-muted-foreground">{{ labels.completed_total }}</span>
                    <span class="font-semibold" data-completed-total>{{ completedNetTotal }}</span>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">{{ labels.empty }}</p>
        </div>
    </Card>
</template>
