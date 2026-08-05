<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatMoney } from '@/lib/i18n';
import type { CustomerApprovalPayload, OrderServicePayload } from '@/types/orders';
import { computed, ref } from 'vue';

interface ApprovalSubmission {
    payload: CustomerApprovalPayload;
    selectedCount: number;
    budgetedTotal: string;
    authorizedTotal: string;
}

const props = defineProps<{
    services: OrderServicePayload[];
    itemLabels: Record<number, string>;
    busy: boolean;
    labels: {
        title: string;
        help: string;
        service: string;
        measurement: string;
        netPrice: string;
        budgeted: string;
        authorized: string;
        select: string;
        preview: string;
        budgetedTotal: string;
        authorizedTotal: string;
        selected: (count: number) => string;
        submit: string;
        empty: string;
    };
}>();

const emit = defineEmits<{
    (event: 'submit', value: ApprovalSubmission): void;
}>();

const selectedIds = ref<number[]>([]);

const budgetedServices = computed(() => props.services.filter((service) => service.is_budgeted));
const selectedServices = computed(() => budgetedServices.value.filter((service) => selectedIds.value.includes(service.id)));
const groups = computed(() => {
    const grouped = new Map<number, OrderServicePayload[]>();

    for (const service of budgetedServices.value) {
        const itemServices = grouped.get(service.order_item_id) ?? [];
        itemServices.push(service);
        grouped.set(service.order_item_id, itemServices);
    }

    return [...grouped.entries()];
});

const budgetedTotal = computed(() => totalFor(budgetedServices.value));
const authorizedTotal = computed(() => totalFor(selectedServices.value));

function totalFor(services: OrderServicePayload[]): string {
    return services.reduce((total, service) => total + Number(service.net_price ?? 0), 0).toFixed(2);
}

function isSelected(serviceId: number): boolean {
    return selectedIds.value.includes(serviceId);
}

function toggle(serviceId: number): void {
    if (props.busy) {
        return;
    }

    selectedIds.value = isSelected(serviceId) ? selectedIds.value.filter((id) => id !== serviceId) : [...selectedIds.value, serviceId];
}

function submit(): void {
    if (!selectedIds.value.length || props.busy) {
        return;
    }

    emit('submit', {
        payload: {
            authorized_service_ids: selectedIds.value,
        },
        selectedCount: selectedIds.value.length,
        budgetedTotal: budgetedTotal.value,
        authorizedTotal: authorizedTotal.value,
    });
}
</script>

<template>
    <Card>
        <div class="flex flex-col gap-4 px-6">
            <div>
                <h2 class="text-base font-semibold">{{ labels.title }}</h2>
                <p class="text-sm text-muted-foreground">{{ labels.help }}</p>
            </div>

            <p v-if="!groups.length" class="text-sm text-muted-foreground">{{ labels.empty }}</p>
            <div v-else class="flex flex-col gap-6">
                <section v-for="[itemId, itemServices] in groups" :key="itemId" class="flex flex-col gap-3">
                    <h3 class="font-medium">{{ itemLabels[itemId] ?? labels.service }}</h3>
                    <div class="overflow-hidden rounded-md border">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">
                                {{
                                    itemLabels[itemId] ?? labels.service
                                }}
                            </caption>
                            <thead class="hidden border-b bg-muted/40 text-xs text-muted-foreground md:table-header-group">
                                <tr>
                                    <th class="w-20 px-3 py-2" scope="col">{{ labels.select }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.service }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.measurement }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.netPrice }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.budgeted }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.authorized }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="service in itemServices" :key="service.id" class="block p-3 md:table-row md:p-0">
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.select }}</span>
                                        <input
                                            :aria-label="`${labels.select}: ${service.service_name ?? labels.service}`"
                                            :checked="isSelected(service.id)"
                                            :disabled="busy"
                                            :dusk="`order-approval-service-${service.service_key}`"
                                            class="size-4 rounded border-input text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            type="checkbox"
                                            @change="toggle(service.id)"
                                        />
                                    </td>
                                    <td class="block px-3 py-2 align-top font-medium md:table-cell">
                                        <span class="mr-2 text-xs font-normal text-muted-foreground md:hidden">{{ labels.service }}</span>
                                        {{ service.service_name ?? labels.service }}
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.measurement }}</span>
                                        {{ service.measurement ?? '—' }}
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.netPrice }}</span>
                                        {{ formatMoney(service.net_price) }}
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.budgeted }}</span>
                                        ✓
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.authorized }}</span>
                                        {{ service.is_authorized ? '✓' : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="flex flex-col gap-2 rounded-md bg-muted/30 p-3 text-sm">
                    <h3 class="font-medium">{{ labels.preview }}</h3>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">{{ labels.budgetedTotal }}</span>
                            <span class="font-semibold" data-approval-budgeted-total>{{ formatMoney(budgetedTotal) }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">{{ labels.authorizedTotal }}</span>
                            <span class="font-semibold" data-approval-authorized-total>{{ formatMoney(authorizedTotal) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">{{ labels.selected(selectedIds.length) }}</p>
                    <Button :disabled="!selectedIds.length || busy" dusk="order-approval-submit" type="button" @click="submit">{{
                        labels.submit
                    }}</Button>
                </div>
            </div>
        </div>
    </Card>
</template>
