<script lang="ts" setup>
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import OrderFinancialSummary from '@/components/orders/OrderFinancialSummary.vue';
import OrderStatusProgress from '@/components/orders/OrderStatusProgress.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OrderApiError, useOrderApi } from '@/composables/useOrderApi';
import { getIntlLocale } from '@/lib/i18n';
import type { FinancialTotals, PublicOrder, PublicOrderServicePayload } from '@/types/orders';
import { ORDER_STATUS_SEQUENCE } from '@/types/orders';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, tm } = useI18n();
const orderApi = useOrderApi();
const uuid = ref('');
const createdDate = ref('');
const order = ref<PublicOrder | null>(null);
const loading = ref(false);
const error = ref<OrderApiError | null>(null);
const controller = ref<AbortController | null>(null);

const fieldErrors = computed(() => error.value?.validationErrors ?? {});

const statusSteps = computed(() => ORDER_STATUS_SEQUENCE.map((value) => ({ value, label: statusLabel(value) })));

const financialLabels = computed(() => ({
    budgeted: t('orders.budgeted_total'),
    authorized: t('orders.authorized_total'),
    completed: t('orders.completed_total'),
    advance_payment: t('orders.advance_payment'),
    remaining_balance: t('orders.remaining_balance'),
}));

const financials = computed<FinancialTotals>(
    () =>
        order.value?.financials ?? {
            budgeted: '0.00',
            authorized: '0.00',
            completed: '0.00',
            advance_payment: '0.00',
            remaining_balance: '0.00',
        },
);

function statusLabel(value: string): string {
    const map = tm('orders.status_labels') as Record<string, string>;

    return map?.[value] ?? value;
}

function formatDate(value?: string | null): string {
    if (!value) return '—';

    try {
        return new Intl.DateTimeFormat(getIntlLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
    } catch {
        return value;
    }
}

function formatMoney(value: string | number | null | undefined): string {
    return Number(value ?? 0).toLocaleString(getIntlLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function serviceState(service: PublicOrderServicePayload): string {
    if (service.is_completed) return t('orders.completed');
    if (service.is_authorized) return t('orders.authorized');
    if (service.is_budgeted) return t('orders.budgeted');

    return t('orders.not_budgeted');
}

function submitErrorMessage(): string {
    if (!error.value) return '';
    if (error.value.kind === 'not_found') return t('orders.track_not_found');
    if (error.value.kind === 'rate_limit') return t('orders.track_rate_limit');
    if (error.value.status === 0) return t('orders.track_network_error');

    return error.value.message;
}

async function lookup(): Promise<void> {
    controller.value?.abort();
    const requestController = new AbortController();
    controller.value = requestController;
    order.value = null;
    error.value = null;
    loading.value = true;

    try {
        const result = await orderApi.track({ uuid: uuid.value, created_date: createdDate.value }, requestController.signal);

        if (controller.value !== requestController) return;

        order.value = result;
    } catch (caughtError: unknown) {
        if (caughtError instanceof Error && caughtError.name === 'AbortError') return;
        if (controller.value !== requestController) return;
        error.value = caughtError instanceof OrderApiError ? caughtError : new OrderApiError(0, t('orders.track_network_error'));
    } finally {
        if (controller.value === requestController) {
            loading.value = false;
        }
    }
}
</script>

<template>
    <Head :title="t('orders.track')" />
    <div class="min-h-screen bg-background px-4 py-8 text-foreground sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <LocaleSwitcher class="self-end" />
            <header class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold">{{ t('orders.track') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('orders.track_help') }}</p>
            </header>

            <Card>
                <form class="grid gap-4 p-6 md:grid-cols-[1fr_14rem_auto] md:items-end" @submit.prevent="lookup">
                    <div class="flex flex-col gap-1">
                        <Label for="track-uuid">{{ t('orders.uuid') }}</Label
                        ><Input
                            id="track-uuid"
                            v-model="uuid"
                            :aria-describedby="fieldErrors.uuid ? 'track-uuid-error' : undefined"
                            :aria-invalid="Boolean(fieldErrors.uuid)"
                            autocomplete="off"
                            required
                        />
                        <p v-if="fieldErrors.uuid" id="track-uuid-error" class="text-sm text-destructive">{{ fieldErrors.uuid[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label for="track-date">{{ t('orders.creation_date') }}</Label
                        ><Input
                            id="track-date"
                            v-model="createdDate"
                            :aria-describedby="fieldErrors.created_date ? 'track-date-error' : undefined"
                            :aria-invalid="Boolean(fieldErrors.created_date)"
                            required
                            type="date"
                        />
                        <p v-if="fieldErrors.created_date" id="track-date-error" class="text-sm text-destructive">
                            {{ fieldErrors.created_date[0] }}
                        </p>
                    </div>
                    <Button :disabled="loading" type="submit">{{ loading ? t('common.loading') : t('orders.lookup') }}</Button>
                </form>
            </Card>

            <div
                v-if="error"
                aria-live="assertive"
                class="rounded-md border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive"
                role="alert"
            >
                {{ submitErrorMessage() }}
            </div>
            <div v-if="loading" aria-live="polite" class="relative min-h-48 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <PlaceholderPattern />
            </div>

            <template v-if="order && !loading">
                <Card>
                    <div class="flex flex-col gap-4 p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-xl font-semibold">{{ order.title }}</h2>
                                <p class="text-sm break-all text-muted-foreground">#{{ order.uuid }}</p>
                            </div>
                            <div
                                class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                            >
                                {{ order.status_label ?? statusLabel(order.status) }}
                            </div>
                        </div>
                        <p class="text-sm whitespace-pre-wrap text-muted-foreground">{{ order.description }}</p>
                        <dl class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                            <div>
                                <dt class="text-muted-foreground">{{ t('orders.priority') }}</dt>
                                <dd>{{ order.priority_label ?? order.priority }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ t('orders.created_at') }}</dt>
                                <dd>{{ formatDate(order.created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ t('orders.estimated_completion') }}</dt>
                                <dd>{{ formatDate(order.estimated_completion) }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ t('orders.actual_completion') }}</dt>
                                <dd>{{ formatDate(order.actual_completion) }}</dd>
                            </div>
                        </dl>
                    </div>
                </Card>

                <OrderStatusProgress :status="order.status" :statuses="statusSteps" :title="t('orders.progress')" />

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card
                        ><div class="flex flex-col gap-4 p-6">
                            <h2 class="font-semibold">{{ t('orders.motor_information') }}</h2>
                            <dl class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="text-muted-foreground">{{ t('orders.brand') }}</dt>
                                    <dd>{{ order.motor_info?.brand ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">{{ t('orders.liters') }}</dt>
                                    <dd>{{ order.motor_info?.liters ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">{{ t('orders.year') }}</dt>
                                    <dd>{{ order.motor_info?.year ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">{{ t('orders.model') }}</dt>
                                    <dd>{{ order.motor_info?.model ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">{{ t('orders.cylinder_count') }}</dt>
                                    <dd>{{ order.motor_info?.cylinder_count ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div></Card
                    >
                    <Card
                        ><div class="flex flex-col gap-4 p-6">
                            <h2 class="font-semibold">{{ t('orders.received_items') }}</h2>
                            <div v-if="order.items.length" class="flex flex-col gap-3">
                                <div v-for="(item, index) in order.items" :key="index" class="rounded-md border p-3 text-sm">
                                    <div class="font-medium">{{ item.item_type_label ?? t('orders.item_type') }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="(component, componentIndex) in item.components"
                                            :key="componentIndex"
                                            class="rounded-full bg-muted px-2 py-1 text-xs"
                                            >{{ component.component_label ?? t('orders.components') }}</span
                                        ><span v-if="!item.components.length" class="text-muted-foreground">{{ t('orders.no_components') }}</span>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">{{ t('orders.no_items') }}</p>
                        </div></Card
                    >
                </div>

                <OrderFinancialSummary
                    v-if="order.financials"
                    :financials="financials"
                    :labels="financialLabels"
                    :title="t('orders.financial_summary')"
                />

                <Card
                    ><div class="flex flex-col gap-4 p-6">
                        <h2 class="font-semibold">{{ t('orders.services') }}</h2>
                        <div v-if="order.services.length" class="flex flex-col gap-3">
                            <div
                                v-for="(service, index) in order.services"
                                :key="index"
                                class="grid gap-2 rounded-md border p-3 text-sm sm:grid-cols-[1fr_auto_auto]"
                            >
                                <div>
                                    <div class="font-medium">{{ service.service_name ?? t('orders.service') }}</div>
                                    <div class="text-xs text-muted-foreground">{{ service.measurement ?? '—' }}</div>
                                </div>
                                <div>{{ formatMoney(service.net_price) }}</div>
                                <div class="text-muted-foreground">{{ serviceState(service) }}</div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">{{ t('orders.no_services') }}</p>
                    </div></Card
                >

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card
                        ><div class="flex flex-col gap-3 p-6">
                            <h2 class="font-semibold">{{ t('orders.attachments') }}</h2>
                            <div v-if="order.attachments.length" class="flex flex-col gap-2">
                                <div v-for="(attachment, index) in order.attachments" :key="index" class="rounded-md border p-3 text-sm">
                                    <div class="font-medium">{{ attachment.file_name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ attachment.human_file_size }}</div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">{{ t('orders.no_attachments') }}</p>
                        </div></Card
                    >
                    <Card
                        ><div class="flex flex-col gap-3 p-6">
                            <h2 class="font-semibold">{{ t('orders.history') }}</h2>
                            <div v-if="order.history.length" class="flex flex-col gap-3">
                                <div v-for="(entry, index) in order.history" :key="index" class="rounded-md border p-3 text-sm">
                                    <div class="text-xs text-muted-foreground">{{ formatDate(entry.created_at) }}</div>
                                    <div class="mt-1">{{ entry.description ?? entry.comment }}</div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">{{ t('orders.no_history') }}</p>
                        </div></Card
                    >
                </div>
            </template>
        </div>
    </div>
</template>
