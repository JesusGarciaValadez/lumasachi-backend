<script lang="ts" setup>
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getIntlLocale } from '@/lib/i18n';
import type { CatalogPayload, CatalogServiceOption, OrderItem, SubmitBudgetPayload } from '@/types/orders';
import { computed, ref, watch } from 'vue';

interface ReviewRow {
    itemId: number;
    itemType: string;
    service: CatalogServiceOption;
    selected: boolean;
    measurement: string;
    notes: string;
}

interface BudgetSubmission {
    payload: SubmitBudgetPayload;
    selectedCount: number;
    baseTotal: string;
    netTotal: string;
}

const props = defineProps<{
    items: OrderItem[];
    catalog: CatalogPayload | null;
    loading: boolean;
    busy: boolean;
    errors: Record<string, string[]>;
    labels: {
        title: string;
        help: string;
        submit: string;
        service: string;
        measurement: string;
        budgeted: string;
        basePrice: string;
        netPrice: string;
        notes: string;
        preview: string;
        baseTotal: string;
        netTotal: string;
        selected: (count: number) => string;
        empty: string;
    };
}>();

const emit = defineEmits<{
    (event: 'submit', value: BudgetSubmission): void;
}>();

const rows = ref<ReviewRow[]>([]);

const groups = computed(() => {
    const grouped = new Map<number, ReviewRow[]>();

    for (const row of rows.value) {
        const itemRows = grouped.get(row.itemId) ?? [];
        itemRows.push(row);
        grouped.set(row.itemId, itemRows);
    }

    return [...grouped.entries()];
});

const selectedRows = computed(() => rows.value.filter((row) => row.selected));
const baseTotal = computed(() => selectedRows.value.reduce((total, row) => total + Number(row.service.base_price ?? 0), 0).toFixed(2));
const netTotal = computed(() => selectedRows.value.reduce((total, row) => total + Number(row.service.net_price ?? 0), 0).toFixed(2));

function buildRows(): void {
    if (!props.catalog) {
        rows.value = [];

        return;
    }

    rows.value = props.items
        .filter((item) => item.is_received)
        .flatMap((item) =>
            (props.catalog?.services_by_type[item.item_type] ?? []).map((service) => ({
                itemId: item.id,
                itemType: item.item_type,
                service,
                selected: false,
                measurement: '',
                notes: '',
            })),
        );
}

watch(() => [props.catalog, props.items], buildRows, { deep: true, immediate: true });

function itemTypeLabel(itemType: string): string {
    return props.catalog?.item_types.find((item) => item.key === itemType)?.label ?? itemType;
}

function formatMoney(value: string | number | null | undefined): string {
    return Number(value ?? 0).toLocaleString(getIntlLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function selectedIndex(row: ReviewRow): number {
    return selectedRows.value.indexOf(row);
}

function errorFor(row: ReviewRow, field: 'service_key' | 'measurement'): string | undefined {
    const index = selectedIndex(row);

    return index < 0 ? undefined : props.errors[`services.${index}.${field}`]?.[0];
}

function toggle(row: ReviewRow): void {
    if (!props.busy) {
        row.selected = !row.selected;
    }
}

function submit(): void {
    if (!selectedRows.value.length || props.busy) {
        return;
    }

    emit('submit', {
        payload: {
            services: selectedRows.value.map((row) => ({
                order_item_id: row.itemId,
                service_key: row.service.service_key,
                measurement: row.measurement || null,
                notes: row.notes || null,
            })),
        },
        selectedCount: selectedRows.value.length,
        baseTotal: baseTotal.value,
        netTotal: netTotal.value,
    });
}
</script>

<template>
    <Card>
        <div class="flex flex-col gap-4 px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold">{{ labels.title }}</h2>
                    <p class="text-sm text-muted-foreground">{{ labels.help }}</p>
                </div>
                <Button :disabled="!selectedRows.length || busy" type="button" @click="submit">{{ labels.submit }}</Button>
            </div>

            <div v-if="loading" aria-busy="true" aria-live="polite" class="relative min-h-32 rounded-md border"><PlaceholderPattern /></div>
            <p v-else-if="!groups.length" class="text-sm text-muted-foreground">{{ labels.empty }}</p>
            <div v-else class="flex flex-col gap-6">
                <section v-for="[itemId, itemRows] in groups" :key="itemId" class="flex flex-col gap-3">
                    <h3 class="font-medium">{{ itemTypeLabel(itemRows[0].itemType) }}</h3>
                    <div class="overflow-hidden rounded-md border">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">
                                {{
                                    itemTypeLabel(itemRows[0].itemType)
                                }}
                            </caption>
                            <thead class="hidden border-b bg-muted/40 text-xs text-muted-foreground md:table-header-group">
                                <tr>
                                    <th class="w-20 px-3 py-2" scope="col">{{ labels.budgeted }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.service }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.measurement }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.basePrice }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.netPrice }}</th>
                                    <th class="px-3 py-2" scope="col">{{ labels.notes }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="row in itemRows" :key="`${row.itemId}-${row.service.service_key}`" class="block p-3 md:table-row md:p-0">
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.budgeted }}</span>
                                        <input
                                            :aria-describedby="
                                                errorFor(row, 'service_key')
                                                    ? `review-service-${row.itemId}-${row.service.service_key}-error`
                                                    : undefined
                                            "
                                            :aria-label="`${labels.budgeted}: ${row.service.service_name}`"
                                            :checked="row.selected"
                                            :disabled="busy"
                                            class="size-4 rounded border-input text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            type="checkbox"
                                            @change="toggle(row)"
                                        />
                                    </td>
                                    <td class="block px-3 py-2 align-top font-medium md:table-cell">
                                        <span class="mr-2 text-xs font-normal text-muted-foreground md:hidden">{{ labels.service }}</span>
                                        {{ row.service.service_name }}
                                        <p
                                            v-if="errorFor(row, 'service_key')"
                                            :id="`review-service-${row.itemId}-${row.service.service_key}-error`"
                                            class="mt-1 text-xs text-destructive"
                                            role="alert"
                                        >
                                            {{ errorFor(row, 'service_key') }}
                                        </p>
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.measurement }}</span>
                                        <Input
                                            v-if="row.service.requires_measurement"
                                            :id="`review-measurement-${row.itemId}-${row.service.service_key}`"
                                            v-model="row.measurement"
                                            :aria-describedby="
                                                errorFor(row, 'measurement')
                                                    ? `review-measurement-${row.itemId}-${row.service.service_key}-error`
                                                    : undefined
                                            "
                                            :aria-invalid="Boolean(errorFor(row, 'measurement'))"
                                            :data-review-measurement="row.service.service_key"
                                            :required="row.service.requires_measurement"
                                        />
                                        <span v-else>—</span>
                                        <p
                                            v-if="errorFor(row, 'measurement')"
                                            :id="`review-measurement-${row.itemId}-${row.service.service_key}-error`"
                                            class="mt-1 text-xs text-destructive"
                                            role="alert"
                                        >
                                            {{ errorFor(row, 'measurement') }}
                                        </p>
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.basePrice }}</span>
                                        {{ formatMoney(row.service.base_price) }}
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <span class="mr-2 text-xs text-muted-foreground md:hidden">{{ labels.netPrice }}</span>
                                        {{ formatMoney(row.service.net_price) }}
                                    </td>
                                    <td class="block px-3 py-2 align-top md:table-cell">
                                        <Label :for="`review-notes-${row.itemId}-${row.service.service_key}`" class="sr-only">{{
                                            labels.notes
                                        }}</Label>
                                        <Input
                                            :id="`review-notes-${row.itemId}-${row.service.service_key}`"
                                            v-model="row.notes"
                                            :placeholder="labels.notes"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="hidden border-t bg-muted/20 text-sm font-semibold md:table-footer-group">
                                <tr>
                                    <td class="px-3 py-2" colspan="3">{{ labels.preview }}</td>
                                    <td class="px-3 py-2">{{ formatMoney(baseTotal) }}</td>
                                    <td class="px-3 py-2">{{ formatMoney(netTotal) }}</td>
                                    <td class="px-3 py-2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
                <div class="grid gap-2 rounded-md bg-muted/30 p-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">{{ labels.baseTotal }}</span>
                        <span class="font-semibold" data-review-base-total>{{ formatMoney(baseTotal) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">{{ labels.netTotal }}</span>
                        <span class="font-semibold" data-review-net-total>{{ formatMoney(netTotal) }}</span>
                    </div>
                </div>
                <p class="text-right text-sm text-muted-foreground">{{ labels.selected(selectedRows.length) }}</p>
            </div>
        </div>
    </Card>
</template>
