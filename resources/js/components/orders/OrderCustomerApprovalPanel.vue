<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getIntlLocale } from '@/lib/i18n';
import type { CustomerApprovalPayload, OrderServicePayload } from '@/types/orders';
import { computed, ref } from 'vue';

interface ApprovalSubmission {
    payload: CustomerApprovalPayload;
    selectedCount: number;
    budgetedBaseTotal: string;
    budgetedNetTotal: string;
    authorizedBaseTotal: string;
    authorizedNetTotal: string;
    downPayment: string;
}

const props = defineProps<{
    services: OrderServicePayload[];
    itemLabels: Record<number, string>;
    busy: boolean;
    errors: Record<string, string[]>;
    labels: {
        title: string;
        help: string;
        service: string;
        measurement: string;
        basePrice: string;
        netPrice: string;
        budgeted: string;
        authorized: string;
        select: string;
        preview: string;
        budgetedBaseTotal: string;
        budgetedNetTotal: string;
        authorizedBaseTotal: string;
        authorizedNetTotal: string;
        selected: (count: number) => string;
        advancePayment: string;
        submit: string;
        empty: string;
    };
}>();

const emit = defineEmits<{
    (event: 'submit', value: ApprovalSubmission): void;
}>();

const selectedIds = ref<number[]>([]);
const downPayment = ref('');

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

const budgetedBaseTotal = computed(() => totalFor(budgetedServices.value, 'base_price'));
const budgetedNetTotal = computed(() => totalFor(budgetedServices.value, 'net_price'));
const authorizedBaseTotal = computed(() => totalFor(selectedServices.value, 'base_price'));
const authorizedNetTotal = computed(() => totalFor(selectedServices.value, 'net_price'));

function totalFor(services: OrderServicePayload[], field: 'base_price' | 'net_price'): string {
    return services.reduce((total, service) => total + Number(service[field] ?? 0), 0).toFixed(2);
}

function formatMoney(value: string | number | null | undefined): string {
    return Number(value ?? 0).toLocaleString(getIntlLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function errorFor(key: string): string | undefined {
    return props.errors[key]?.[0];
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

function formattedDownPayment(): string | undefined {
    if (downPayment.value === '') {
        return undefined;
    }

    const numericValue = Number(downPayment.value);

    return Number.isFinite(numericValue) ? numericValue.toFixed(2) : String(downPayment.value);
}

function submit(): void {
    if (!selectedIds.value.length || props.busy) {
        return;
    }

    const submittedDownPayment = formattedDownPayment();

    emit('submit', {
        payload: {
            authorized_service_ids: selectedIds.value,
            down_payment: submittedDownPayment,
        },
        selectedCount: selectedIds.value.length,
        budgetedBaseTotal: budgetedBaseTotal.value,
        budgetedNetTotal: budgetedNetTotal.value,
        authorizedBaseTotal: authorizedBaseTotal.value,
        authorizedNetTotal: authorizedNetTotal.value,
        downPayment: submittedDownPayment ?? '',
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
                    <h3 class="font-medium">{{ itemLabels[itemId] ?? `Item ${itemId}` }}</h3>
                    <div class="overflow-hidden rounded-md border">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">
                                {{
                                    itemLabels[itemId] ?? `Item ${itemId}`
                                }}
                            </caption>
                            <thead class="hidden border-b bg-muted/40 text-xs text-muted-foreground md:table-header-group">
                                <tr>
                                    <th class="w-20 px-3 py-2" scope="col">{{ labels.select }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.service }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.measurement }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.basePrice }}</th>
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
                                            :aria-label="`${labels.select}: ${service.service_name ?? service.service_key}`"
                                            :checked="isSelected(service.id)"
                                            :disabled="busy"
                                            class="size-4 rounded border-input text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            type="checkbox"
                                            @change="toggle(service.id)"
                                        />
                                    </td>
                                    <td class="block px-3 py-2 align-top font-medium md:table-cell">
                                        <span class="mr-2 text-xs font-normal text-muted-foreground md:hidden">{{ labels.service }}</span>
                                        {{ service.service_name ?? service.service_key }}
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.measurement }}</span>
                                        {{ service.measurement ?? '—' }}
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.basePrice }}</span>
                                        {{ formatMoney(service.base_price) }}
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
                            <span class="text-muted-foreground">{{ labels.budgetedBaseTotal }}</span>
                            <span class="font-semibold" data-approval-budgeted-base-total>{{ formatMoney(budgetedBaseTotal) }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">{{ labels.budgetedNetTotal }}</span>
                            <span class="font-semibold" data-approval-budgeted-net-total>{{ formatMoney(budgetedNetTotal) }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">{{ labels.authorizedBaseTotal }}</span>
                            <span class="font-semibold" data-approval-authorized-base-total>{{ formatMoney(authorizedBaseTotal) }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">{{ labels.authorizedNetTotal }}</span>
                            <span class="font-semibold" data-approval-authorized-net-total>{{ formatMoney(authorizedNetTotal) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:max-w-sm">
                    <Label for="approval-down-payment">{{ labels.advancePayment }}</Label>
                    <Input
                        id="approval-down-payment"
                        v-model="downPayment"
                        :aria-describedby="errorFor('down_payment') ? 'approval-down-payment-error' : undefined"
                        :aria-invalid="Boolean(errorFor('down_payment'))"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                        type="number"
                    />
                    <p v-if="errorFor('down_payment')" id="approval-down-payment-error" class="text-sm text-destructive" role="alert">
                        {{ errorFor('down_payment') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">{{ labels.selected(selectedIds.length) }}</p>
                    <Button :disabled="!selectedIds.length || busy" type="button" @click="submit">{{ labels.submit }}</Button>
                </div>
            </div>
        </div>
    </Card>
</template>
