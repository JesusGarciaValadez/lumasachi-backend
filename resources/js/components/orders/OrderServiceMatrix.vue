<script lang="ts" setup>
import { Card } from '@/components/ui/card';
import type { OrderServicePayload } from '@/types/orders';
import { computed } from 'vue';

const props = defineProps<{
    services: OrderServicePayload[];
    itemLabels: Record<number, string>;
    selectedIds: number[];
    mode: 'approval' | 'completion' | 'readonly';
    title: string;
    labels: {
        service: string;
        measurement: string;
        base_price: string;
        net_price: string;
        budgeted: string;
        authorized: string;
        completed: string;
        empty: string;
    };
}>();

const emit = defineEmits<{
    (event: 'update:selectedIds', value: number[]): void;
}>();

const groups = computed(() => {
    const grouped = new Map<number, OrderServicePayload[]>();

    for (const service of props.services) {
        const current = grouped.get(service.order_item_id) ?? [];
        current.push(service);
        grouped.set(service.order_item_id, current);
    }

    return [...grouped.entries()];
});

function canSelect(service: OrderServicePayload): boolean {
    if (props.mode === 'approval') {
        return service.is_budgeted && !service.is_completed;
    }

    if (props.mode === 'completion') {
        return service.is_authorized && !service.is_completed;
    }

    return false;
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
                    <h3 class="font-medium">{{ itemLabels[itemId] ?? `Item ${itemId}` }}</h3>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <thead class="border-b bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    <th class="w-10 px-3 py-2"><span class="sr-only">Select</span></th>
                                    <th class="px-3 py-2">{{ labels.service }}</th>
                                    <th class="px-3 py-2">{{ labels.measurement }}</th>
                                    <th class="px-3 py-2">{{ labels.base_price }}</th>
                                    <th class="px-3 py-2">{{ labels.net_price }}</th>
                                    <th class="px-3 py-2">{{ labels.budgeted }}</th>
                                    <th class="px-3 py-2">{{ labels.authorized }}</th>
                                    <th class="px-3 py-2">{{ labels.completed }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="service in itemServices" :key="service.id">
                                    <td class="px-3 py-2 align-top">
                                        <input
                                            v-if="mode !== 'readonly'"
                                            :aria-label="service.service_name ?? service.service_key"
                                            :checked="isSelected(service.id)"
                                            :disabled="!canSelect(service)"
                                            class="size-4 rounded border-input text-primary focus:ring-primary"
                                            type="checkbox"
                                            @change="toggle(service)"
                                        />
                                    </td>
                                    <td class="px-3 py-2 align-top font-medium">{{ service.service_name ?? service.service_key }}</td>
                                    <td class="px-3 py-2 align-top">{{ service.measurement ?? '—' }}</td>
                                    <td class="px-3 py-2 align-top">{{ service.base_price ?? '0.00' }}</td>
                                    <td class="px-3 py-2 align-top">{{ service.net_price ?? '0.00' }}</td>
                                    <td class="px-3 py-2 align-top">{{ service.is_budgeted ? '✓' : '—' }}</td>
                                    <td class="px-3 py-2 align-top">{{ service.is_authorized ? '✓' : '—' }}</td>
                                    <td class="px-3 py-2 align-top">{{ service.is_completed ? '✓' : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <p v-else class="text-sm text-muted-foreground">{{ labels.empty }}</p>
        </div>
    </Card>
</template>
