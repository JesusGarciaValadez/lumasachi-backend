<script setup lang="ts">
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import OrderFinancialSummary from '@/components/orders/OrderFinancialSummary.vue';
import OrderReviewBudgetPanel from '@/components/orders/OrderReviewBudgetPanel.vue';
import OrderServiceMatrix from '@/components/orders/OrderServiceMatrix.vue';
import OrderStatusProgress from '@/components/orders/OrderStatusProgress.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OrderApiError, useOrderApi } from '@/composables/useOrderApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type {
    CatalogPayload,
    FinancialTotals,
    Order,
    OrderAttachment,
    OrderCapabilities,
    OrderHistoryPage,
    OrderPayload,
    OrderStatus,
    ResourcePayload,
    SubmitBudgetPayload,
} from '@/types/orders';
import { normalizeOrder } from '@/types/orders';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type DialogAction = 'budget' | 'approval' | 'completion' | 'ready' | 'deliver' | null;

interface BudgetSubmission {
    payload: SubmitBudgetPayload;
    selectedCount: number;
    baseTotal: string;
    netTotal: string;
}

const { order: initialOrder, capabilities } = defineProps<{
    order: ResourcePayload<OrderPayload>;
    capabilities: OrderCapabilities;
}>();

const { t, tm } = useI18n();
const orderApi = useOrderApi();
const currentOrder = ref<Order>(normalizeOrder(initialOrder));
const order = computed(() => currentOrder.value);
const orderUuid = computed(() => order.value.uuid);
const attachments = ref<OrderAttachment[]>(order.value.attachments);
const attachmentsLoading = ref(true);
const attachmentsError = ref<OrderApiError | null>(null);
const history = ref<OrderHistoryPage>({ data: [], meta: null });
const historyLoading = ref(true);
const historyError = ref<OrderApiError | null>(null);
const attachmentActionError = ref<OrderApiError | null>(null);
const catalog = ref<CatalogPayload | null>(null);
const catalogLoading = ref(false);
const approvalSelection = ref<number[]>([]);
const completionSelection = ref<number[]>([]);
const downPayment = ref('');
const pendingBudget = ref<BudgetSubmission | null>(null);
const busyAction = ref<DialogAction>(null);
const dialogAction = ref<DialogAction>(null);
const dialogOpen = ref(false);
const lastError = ref<OrderApiError | null>(null);
const staleState = ref(false);
let orderRequestSequence = 0;

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.orders'), href: route('web.orders.index') },
    { title: `${t('orders.order')} #${order.value.uuid}`, href: route('web.orders.show', order.value.uuid) },
]);

const isStaff = computed(() => capabilities.create_order);
const canReview = computed(() => capabilities.submit_budget && order.value.status === 'Awaiting Review');
const canApprove = computed(() => capabilities.approve_services && order.value.status === 'Awaiting Customer Approval');
const canComplete = computed(() => isStaff.value && ['Ready for Work', 'In Progress'].includes(order.value.status));
const canMarkReady = computed(() => isStaff.value && ['Ready for Work', 'In Progress'].includes(order.value.status));
const remainingBalance = computed(() => Number(order.value.financials?.remaining_balance ?? 0));
const canDeliver = computed(() => isStaff.value && order.value.status === 'Ready for Delivery' && remainingBalance.value <= 0);

const statusSteps = computed(() =>
    (
        [
            'Awaiting Review',
            'Reviewed',
            'Awaiting Customer Approval',
            'Ready for Work',
            'In Progress',
            'Ready for Delivery',
            'Delivered',
        ] as OrderStatus[]
    ).map((value) => ({ value, label: statusLabel(value) })),
);

const itemLabels = computed<Record<number, string>>(() => Object.fromEntries(order.value.items.map((item) => [item.id, item.item_type])));

const serviceLabels = computed(() => ({
    service: t('orders.service'),
    measurement: t('orders.measurement'),
    base_price: t('orders.base_price'),
    net_price: t('orders.net_price'),
    budgeted: t('orders.budgeted'),
    authorized: t('orders.authorized'),
    completed: t('orders.completed'),
    empty: t('orders.no_services'),
}));

const financialLabels = computed(() => ({
    budgeted: t('orders.budgeted_total'),
    baseTotal: t('orders.base_total'),
    netTotal: t('orders.net_total'),
    authorized: t('orders.authorized_total'),
    completed: t('orders.completed_total'),
    advance_payment: t('orders.advance_payment'),
    remaining_balance: t('orders.remaining_balance'),
}));

const reviewLabels = computed(() => ({
    title: t('orders.review_budget'),
    help: t('orders.review_budget_help'),
    submit: t('orders.submit_budget'),
    service: t('orders.service'),
    measurement: t('orders.measurement'),
    budgeted: t('orders.budgeted'),
    basePrice: t('orders.base_price'),
    netPrice: t('orders.net_price'),
    notes: t('orders.notes'),
    preview: t('orders.preview_total'),
    baseTotal: t('orders.base_total'),
    netTotal: t('orders.net_total'),
    selected: t('orders.services_selected'),
    empty: t('orders.no_services'),
}));

const financials = computed<FinancialTotals>(
    () =>
        order.value.financials ?? {
            budgeted: '0.00',
            authorized: '0.00',
            completed: '0.00',
            advance_payment: '0.00',
            remaining_balance: '0.00',
        },
);

const completedServices = computed(() => order.value.services.filter((service) => service.is_completed));
const uncompletedAuthorizedServices = computed(() => order.value.services.filter((service) => service.is_authorized && !service.is_completed));

function statusLabel(status: string): string {
    const map = tm('orders.status_labels') as Record<string, string>;

    return map?.[status] ?? status;
}

function priorityLabel(priority: string): string {
    const map = tm('orders.priority_labels') as Record<string, string>;

    return map?.[priority] ?? priority;
}

function itemTypeLabel(itemType: string): string {
    const option = catalog.value?.item_types.find((item) => item.key === itemType);

    return option?.label ?? itemType;
}

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
    } catch {
        return value;
    }
}

function formatMoney(value: string | number | null | undefined): string {
    return Number(value ?? 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadCatalog(): Promise<void> {
    if (!isStaff.value || !canReview.value) {
        return;
    }

    catalogLoading.value = true;

    try {
        catalog.value = await orderApi.catalog();
    } catch (error: unknown) {
        lastError.value = error instanceof OrderApiError ? error : null;
    } finally {
        catalogLoading.value = false;
    }
}

async function loadAttachments(): Promise<void> {
    attachmentsLoading.value = true;

    try {
        attachments.value = (await orderApi.attachments(orderUuid.value)).attachments;
        attachmentsError.value = null;
    } catch (error: unknown) {
        attachmentsError.value = error instanceof OrderApiError ? error : null;
        attachments.value = [];
    } finally {
        attachmentsLoading.value = false;
    }
}

async function loadHistory(): Promise<void> {
    await loadHistoryPage();
}

async function loadHistoryPage(url?: string): Promise<void> {
    historyLoading.value = true;

    try {
        history.value = await orderApi.history(orderUuid.value, url);
        historyError.value = null;
    } catch (error: unknown) {
        historyError.value = error instanceof OrderApiError ? error : null;
        history.value = { data: [], meta: null };
    } finally {
        historyLoading.value = false;
    }
}

async function openAttachment(attachment: OrderAttachment, mode: 'preview' | 'download'): Promise<void> {
    attachmentActionError.value = null;

    try {
        const response = await fetch(`/api/v1/attachments/${attachment.uuid}/${mode}`, {
            credentials: 'same-origin',
            headers: { Accept: '*/*', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as { message?: unknown } | null;

            throw new OrderApiError(response.status, typeof payload?.message === 'string' ? payload.message : t('orders.attachment_action_failed'));
        }

        const objectUrl = URL.createObjectURL(await response.blob());

        if (mode === 'preview') {
            window.open(objectUrl, '_blank', 'noopener,noreferrer');
        } else {
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = attachment.file_name;
            document.body.appendChild(link);
            link.click();
            link.remove();
        }

        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000);
    } catch (error: unknown) {
        attachmentActionError.value = error instanceof OrderApiError ? error : new OrderApiError(0, t('orders.attachment_action_failed'));
    }
}

async function refreshOrder(): Promise<void> {
    const requestSequence = ++orderRequestSequence;
    const refreshed = await orderApi.show(orderUuid.value);

    if (requestSequence !== orderRequestSequence) {
        return;
    }

    currentOrder.value = refreshed;
    staleState.value = false;
    await Promise.all([loadAttachments(), loadHistory()]);
}

function handleError(error: unknown): void {
    lastError.value = error instanceof OrderApiError ? error : null;
    staleState.value = error instanceof OrderApiError && (error.kind === 'conflict' || Object.hasOwn(error.validationErrors, 'status'));
}

async function submitBudget(payload: SubmitBudgetPayload): Promise<void> {
    if (!payload.services.length) {
        return;
    }

    busyAction.value = 'budget';
    lastError.value = null;

    try {
        await orderApi.submitBudget(orderUuid.value, payload);
        await refreshOrder();
    } catch (error: unknown) {
        handleError(error);
    } finally {
        busyAction.value = null;
    }
}

function prepareBudget(submission: BudgetSubmission): void {
    pendingBudget.value = submission;
    openConfirmation('budget');
}

async function approveServices(): Promise<void> {
    busyAction.value = 'approval';
    lastError.value = null;

    try {
        await orderApi.approveServices(orderUuid.value, {
            authorized_service_ids: approvalSelection.value,
            down_payment: downPayment.value === '' ? undefined : downPayment.value,
        });
        await refreshOrder();
        approvalSelection.value = [];
        downPayment.value = '';
    } catch (error: unknown) {
        handleError(error);
    } finally {
        busyAction.value = null;
    }
}

async function completeServices(): Promise<void> {
    busyAction.value = 'completion';
    lastError.value = null;

    try {
        await orderApi.completeServices(orderUuid.value, { completed_service_ids: completionSelection.value });
        await refreshOrder();
        completionSelection.value = [];
    } catch (error: unknown) {
        handleError(error);
    } finally {
        busyAction.value = null;
    }
}

async function markReadyForDelivery(): Promise<void> {
    busyAction.value = 'ready';
    lastError.value = null;

    try {
        await orderApi.markReadyForDelivery(orderUuid.value);
        await refreshOrder();
    } catch (error: unknown) {
        handleError(error);
    } finally {
        busyAction.value = null;
    }
}

async function deliverOrder(): Promise<void> {
    busyAction.value = 'deliver';
    lastError.value = null;

    try {
        await orderApi.deliver(orderUuid.value);
        await refreshOrder();
    } catch (error: unknown) {
        handleError(error);
    } finally {
        busyAction.value = null;
    }
}

function openConfirmation(action: DialogAction): void {
    dialogAction.value = action;
    dialogOpen.value = true;
}

async function confirmAction(): Promise<void> {
    const action = dialogAction.value;
    dialogOpen.value = false;

    if (action === 'budget' && pendingBudget.value) {
        const submission = pendingBudget.value;
        pendingBudget.value = null;
        await submitBudget(submission.payload);
    }
    if (action === 'approval') await approveServices();
    if (action === 'completion') await completeServices();
    if (action === 'ready') await markReadyForDelivery();
    if (action === 'deliver') await deliverOrder();
}

onMounted(async () => {
    await Promise.all([loadAttachments(), loadHistory(), loadCatalog()]);
});
</script>

<template>
    <Head :title="`${t('orders.order')} #${order.uuid}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <Card>
                <div class="flex flex-col gap-5 px-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <h1 class="truncate text-xl font-semibold md:text-2xl">{{ order.title }}</h1>
                            <p class="text-sm break-all text-muted-foreground">#{{ order.uuid }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                            >
                                {{ statusLabel(order.status) }}
                            </span>
                            <span
                                class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                            >
                                {{ priorityLabel(order.priority) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-sm whitespace-pre-wrap text-muted-foreground">{{ order.description }}</p>
                    <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <div class="text-muted-foreground">{{ t('orders.customer') }}</div>
                            <div>{{ order.customer?.full_name ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground">{{ t('orders.assigned_to') }}</div>
                            <div>{{ order.assigned_to?.full_name ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground">{{ t('orders.created_at') }}</div>
                            <div>{{ formatDate(order.created_at) }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground">{{ t('orders.estimated_completion') }}</div>
                            <div>{{ formatDate(order.estimated_completion) }}</div>
                        </div>
                    </div>
                    <div v-if="lastError" class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive" role="alert">
                        {{ lastError.message }}
                        <div v-if="Object.keys(lastError.validationErrors).length" class="mt-2 flex flex-col gap-1">
                            <span v-for="(messages, key) in lastError.validationErrors" :key="key">{{ key }}: {{ messages[0] }}</span>
                        </div>
                    </div>
                    <div
                        v-if="staleState"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-500/50 bg-amber-500/10 p-3 text-sm"
                        role="alert"
                    >
                        <span>{{ t('orders.stale_state') }}</span>
                        <Button size="sm" variant="outline" @click="refreshOrder">{{ t('orders.reload') }}</Button>
                    </div>
                </div>
            </Card>

            <OrderStatusProgress :status="order.status" :statuses="statusSteps" :title="t('orders.progress')" />

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <div class="flex flex-col gap-4 px-6">
                        <h2 class="text-base font-semibold">{{ t('orders.motor_information') }}</h2>
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
                    </div>
                </Card>
                <Card>
                    <div class="flex flex-col gap-4 px-6">
                        <h2 class="text-base font-semibold">{{ t('orders.received_items') }}</h2>
                        <div v-if="order.items.length" class="flex flex-col gap-3">
                            <div v-for="item in order.items" :key="item.id" class="rounded-md border p-3 text-sm">
                                <div class="font-medium">{{ itemTypeLabel(item.item_type) }}</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span v-for="component in item.components" :key="component.id" class="rounded-full bg-muted px-2 py-1 text-xs">
                                        {{ component.component_name }}
                                    </span>
                                    <span v-if="!item.components.length" class="text-muted-foreground">{{ t('orders.no_components') }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">{{ t('orders.no_items') }}</p>
                    </div>
                </Card>
            </div>

            <OrderFinancialSummary
                v-if="order.financials"
                :financials="financials"
                :labels="financialLabels"
                :title="t('orders.financial_summary')"
            />

            <OrderReviewBudgetPanel
                v-if="canReview"
                :busy="busyAction !== null"
                :catalog="catalog"
                :errors="lastError?.validationErrors ?? {}"
                :items="order.items"
                :labels="reviewLabels"
                :loading="catalogLoading"
                @submit="prepareBudget"
            />

            <OrderServiceMatrix
                v-if="canApprove"
                :item-labels="itemLabels"
                :labels="serviceLabels"
                :selected-ids="approvalSelection"
                :services="order.services"
                :title="t('orders.customer_approval')"
                mode="approval"
                @update:selected-ids="approvalSelection = $event"
            />
            <Card v-if="canApprove">
                <div class="flex flex-col gap-3 px-6">
                    <Label for="down-payment">{{ t('orders.advance_payment') }}</Label>
                    <Input id="down-payment" v-model="downPayment" inputmode="decimal" min="0" step="0.01" type="number" />
                    <Button :disabled="!approvalSelection.length || busyAction !== null" class="self-start" @click="openConfirmation('approval')">
                        {{ busyAction === 'approval' ? t('common.loading') : t('orders.approve_selected') }}
                    </Button>
                </div>
            </Card>

            <OrderServiceMatrix
                v-if="canComplete"
                :item-labels="itemLabels"
                :labels="serviceLabels"
                :selected-ids="completionSelection"
                :services="order.services"
                :title="t('orders.work_completion')"
                mode="completion"
                @update:selected-ids="completionSelection = $event"
            />
            <Card v-if="canComplete">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6">
                    <p class="text-sm text-muted-foreground">{{ t('orders.work_completion_help') }}</p>
                    <Button :disabled="!completionSelection.length || busyAction !== null" @click="openConfirmation('completion')">
                        {{ busyAction === 'completion' ? t('common.loading') : t('orders.mark_completed') }}
                    </Button>
                </div>
            </Card>

            <Card v-if="canMarkReady">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6">
                    <div>
                        <h2 class="text-base font-semibold">{{ t('orders.ready_for_delivery') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ completedServices.length }} / {{ order.services.length }} {{ t('orders.services_completed') }}
                        </p>
                        <p v-if="uncompletedAuthorizedServices.length" class="mt-1 text-xs text-muted-foreground">
                            {{ t('orders.uncompleted_services') }}:
                            {{ uncompletedAuthorizedServices.map((service) => service.service_name ?? service.service_key).join(', ') }}
                        </p>
                    </div>
                    <Button :disabled="busyAction !== null" @click="openConfirmation('ready')">{{
                        busyAction === 'ready' ? t('common.loading') : t('orders.mark_ready')
                    }}</Button>
                </div>
            </Card>

            <Card v-if="order.status === 'Ready for Delivery' && isStaff">
                <div class="flex flex-col gap-4 px-6">
                    <div>
                        <h2 class="text-base font-semibold">{{ t('orders.delivery') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ t('orders.remaining_balance') }}: {{ formatMoney(order.financials?.remaining_balance) }}
                        </p>
                    </div>
                    <div v-if="remainingBalance > 0" class="rounded-md border border-amber-500/50 bg-amber-500/10 p-3 text-sm" role="alert">
                        {{ t('orders.payment_required') }}
                    </div>
                    <Button :disabled="!canDeliver || busyAction !== null" class="self-start" @click="openConfirmation('deliver')">{{
                        busyAction === 'deliver' ? t('common.loading') : t('orders.deliver')
                    }}</Button>
                </div>
            </Card>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <div class="flex flex-col gap-3 px-6">
                        <h2 class="text-base font-semibold">{{ t('orders.attachments') }}</h2>
                        <div v-if="attachmentsLoading" class="relative min-h-32 rounded-md border"><PlaceholderPattern /></div>
                        <div v-else-if="attachmentsError" class="text-sm text-destructive" role="alert">{{ attachmentsError.message }}</div>
                        <div v-else-if="attachments.length" class="flex flex-col gap-2">
                            <div
                                v-for="attachment in attachments"
                                :key="attachment.uuid"
                                class="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm"
                            >
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ attachment.file_name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ attachment.human_file_size }}</div>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button class="text-sm underline" type="button" @click="openAttachment(attachment, 'preview')">
                                        {{ t('orders.preview') }}</button
                                    ><button class="text-sm underline" type="button" @click="openAttachment(attachment, 'download')">
                                        {{ t('orders.download') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">{{ t('orders.no_attachments') }}</p>
                        <div v-if="attachmentActionError" class="text-sm text-destructive" role="alert">{{ attachmentActionError.message }}</div>
                    </div>
                </Card>
                <Card>
                    <div class="flex flex-col gap-3 px-6">
                        <h2 class="text-base font-semibold">{{ t('orders.history') }}</h2>
                        <div v-if="historyLoading" class="relative min-h-32 rounded-md border"><PlaceholderPattern /></div>
                        <div v-else-if="historyError" class="text-sm text-destructive" role="alert">{{ historyError.message }}</div>
                        <div v-else-if="history.data.length" class="flex flex-col gap-3">
                            <div v-for="entry in history.data" :key="entry.uuid" class="rounded-md border p-3 text-sm">
                                <div class="text-xs text-muted-foreground">{{ formatDate(entry.created_at) }}</div>
                                <div class="mt-1">{{ entry.description }}</div>
                            </div>
                            <div v-if="history.links?.prev || history.links?.next" class="flex justify-between gap-3">
                                <Button
                                    :disabled="!history.links?.prev || historyLoading"
                                    size="sm"
                                    variant="outline"
                                    @click="loadHistoryPage(history.links?.prev ?? undefined)"
                                    >{{ t('orders.previous') }}</Button
                                >
                                <Button
                                    :disabled="!history.links?.next || historyLoading"
                                    size="sm"
                                    variant="outline"
                                    @click="loadHistoryPage(history.links?.next ?? undefined)"
                                    >{{ t('orders.next') }}</Button
                                >
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">{{ t('orders.no_history') }}</p>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="dialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('orders.confirm_action') }}</DialogTitle>
                <DialogDescription>{{ t('orders.confirm_action_description') }}</DialogDescription>
            </DialogHeader>
            <div class="rounded-md bg-muted p-3 text-sm">
                <span v-if="dialogAction === 'budget' && pendingBudget">
                    {{ pendingBudget.selectedCount }} {{ t('orders.services_selected') }} · {{ t('orders.base_total') }}:
                    {{ formatMoney(pendingBudget.baseTotal) }} · {{ t('orders.net_total') }}: {{ formatMoney(pendingBudget.netTotal) }}
                </span>
                <span v-else-if="dialogAction === 'approval'">{{ approvalSelection.length }} {{ t('orders.services_selected') }}</span>
                <span v-else-if="dialogAction === 'completion'">{{ completionSelection.length }} {{ t('orders.services_selected') }}</span>
                <span v-else-if="dialogAction === 'ready'">{{ t('orders.confirm_ready') }}</span>
                <span v-else>{{ t('orders.confirm_delivery') }}</span>
            </div>
            <DialogFooter>
                <DialogClose as-child
                    ><Button variant="outline">{{ t('common.cancel') }}</Button></DialogClose
                >
                <Button :disabled="busyAction !== null" @click="confirmAction">{{ t('common.confirm') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
