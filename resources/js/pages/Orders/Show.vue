<script setup lang="ts">
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import OrderCustomerApprovalPanel from '@/components/orders/OrderCustomerApprovalPanel.vue';
import OrderDeliveryPanel from '@/components/orders/OrderDeliveryPanel.vue';
import OrderFinancialSummary from '@/components/orders/OrderFinancialSummary.vue';
import OrderHistoryFeed from '@/components/orders/OrderHistoryFeed.vue';
import OrderReviewBudgetPanel from '@/components/orders/OrderReviewBudgetPanel.vue';
import OrderServiceMatrix from '@/components/orders/OrderServiceMatrix.vue';
import OrderStatusIndicators from '@/components/orders/OrderStatusIndicators.vue';
import OrderStatusProgress from '@/components/orders/OrderStatusProgress.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { OrderApiError, useOrderApi } from '@/composables/useOrderApi';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatMoney } from '@/lib/i18n';
import type { BreadcrumbItem } from '@/types';
import type {
    CatalogPayload,
    CustomerApprovalPayload,
    FinancialTotals,
    Order,
    OrderAttachment,
    OrderCapabilities,
    OrderHistoryPage,
    OrderLifecycleStatus,
    OrderPayload,
    RefundStatus,
    ResourcePayload,
    SubmitBudgetPayload,
} from '@/types/orders';
import { normalizeOrder, ORDER_STATUS_SEQUENCE, resolveLifecycleStatus } from '@/types/orders';
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

interface ApprovalSubmission {
    payload: CustomerApprovalPayload;
    selectedCount: number;
    budgetedBaseTotal: string;
    budgetedNetTotal: string;
    authorizedBaseTotal: string;
    authorizedNetTotal: string;
    downPayment: string;
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
const attachmentActionError = ref<string | null>(null);
const attachmentActionKey = ref<string | null>(null);
const catalog = ref<CatalogPayload | null>(null);
const catalogLoading = ref(false);
const completionSelection = ref<number[]>([]);
const pendingBudget = ref<BudgetSubmission | null>(null);
const pendingApproval = ref<ApprovalSubmission | null>(null);
const busyAction = ref<DialogAction>(null);
const dialogAction = ref<DialogAction>(null);
const dialogOpen = ref(false);
const lastError = ref<OrderApiError | null>(null);
const staleState = ref(false);
const refreshingOrder = ref(false);
let orderRequestSequence = 0;
let attachmentsRequestSequence = 0;
let historyRequestSequence = 0;

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.orders'), href: route('web.orders.index') },
    { title: `${t('orders.order')} #${order.value.uuid}`, href: route('web.orders.show', order.value.uuid) },
]);

const isStaff = computed(() => capabilities.create_order);
const canReview = computed(() => capabilities.submit_budget && order.value.status === 'Awaiting Review');
const canApprove = computed(() => capabilities.approve_services && order.value.status === 'Awaiting Customer Approval');
const canComplete = computed(() => capabilities.complete_services && ['Ready for Work', 'In Progress'].includes(order.value.status));
const canMarkReady = computed(() => capabilities.mark_ready_for_delivery && ['Ready for Work', 'In Progress'].includes(order.value.status));
const remainingBalance = computed(() => Number(order.value.financials?.remaining_balance ?? 0));
const canViewDelivery = computed(() => isStaff.value && order.value.status === 'Ready for Delivery');
const canDeliver = computed(() => capabilities.deliver_order && remainingBalance.value <= 0);

const statusSteps = computed(() => ORDER_STATUS_SEQUENCE.map((value) => ({ value, label: statusLabel(value) })));
const currentLifecycleStatus = computed<OrderLifecycleStatus | null>(() => resolveLifecycleStatus(order.value.lifecycle_status, order.value.status));
const indicatorLabels = computed(() => ({
    lifecycle: t('orders.lifecycle_status'),
    priority: t('orders.priority'),
    payment: t('orders.payment_status'),
    disposition: t('orders.disposition_status'),
    refund: t('orders.refund_status'),
}));
const refundStatusLabels = computed(() => tm('orders.refund_status_labels') as Record<string, string>);
const refundStatuses = computed<RefundStatus[]>(() => (order.value.refunds ?? []).map((refund) => refund.status));

const itemLabels = computed<Record<number, string>>(() =>
    Object.fromEntries(order.value.items.map((item) => [item.id, item.item_type_label ?? t('orders.item_type')])),
);

const serviceLabels = computed(() => ({
    select: t('orders.select'),
    service: t('orders.service'),
    measurement: t('orders.measurement'),
    base_price: t('orders.base_price'),
    net_price: t('orders.net_price'),
    budgeted: t('orders.budgeted'),
    authorized: t('orders.authorized'),
    completed: t('orders.completed'),
    yes: t('orders.yes'),
    no: t('orders.no'),
    completed_total: t('orders.completed_total'),
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
    payment_state: t('orders.payment_state'),
    zero_total: t('orders.zero_total'),
    partial_payment: t('orders.partial_payment'),
    paid_in_full: t('orders.paid_in_full'),
    overpaid: t('orders.overpaid'),
}));

const deliveryLabels = computed(() => ({
    title: t('orders.delivery'),
    remaining_balance: t('orders.remaining_balance'),
    payment_required: t('orders.payment_required'),
    deliver: t('orders.deliver'),
    loading: t('common.loading'),
}));

const historyLabels = computed(() => ({
    previous: t('orders.previous'),
    next: t('orders.next'),
    noHistory: t('orders.no_history'),
    eventStatus: t('orders.history_event_status'),
    eventPriority: t('orders.history_event_priority'),
    eventAssignment: t('orders.history_event_assignment'),
    eventItem: t('orders.history_event_item'),
    eventService: t('orders.history_event_service'),
    eventPayment: t('orders.history_event_payment'),
    eventAttachment: t('orders.history_event_attachment'),
    eventUpdate: t('orders.history_event_update'),
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
    selected: (count: number) => t('orders.services_selected', count),
    empty: t('orders.no_services'),
}));

const approvalLabels = computed(() => ({
    title: t('orders.customer_approval'),
    help: t('orders.customer_approval_help'),
    service: t('orders.service'),
    measurement: t('orders.measurement'),
    basePrice: t('orders.base_price'),
    netPrice: t('orders.net_price'),
    budgeted: t('orders.budgeted'),
    authorized: t('orders.authorized'),
    select: t('orders.select'),
    preview: t('orders.preview_total'),
    budgetedBaseTotal: t('orders.base_total'),
    budgetedNetTotal: t('orders.net_total'),
    authorizedBaseTotal: t('orders.authorized_base_total'),
    authorizedNetTotal: t('orders.authorized_net_total'),
    selected: (count: number) => t('orders.services_selected', count),
    advancePayment: t('orders.advance_payment'),
    submit: t('orders.approve_selected'),
    empty: t('orders.no_budgeted_services'),
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

const authorizedServices = computed(() => order.value.services.filter((service) => service.is_authorized));
const completedAuthorizedServices = computed(() => authorizedServices.value.filter((service) => service.is_completed));
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

    return option?.label ?? t('orders.item_type');
}

function attachmentActionMessage(error: unknown): string {
    if (!(error instanceof OrderApiError)) {
        return t('orders.attachment_action_failed');
    }

    if (error.status === 403) {
        return t('orders.attachment_not_authorized');
    }

    if (error.status === 404) {
        return t('orders.attachment_not_found');
    }

    if (error.status === 400) {
        return t('orders.attachment_not_previewable');
    }

    return t('orders.attachment_action_failed');
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
    const requestSequence = ++attachmentsRequestSequence;
    attachmentsLoading.value = true;

    try {
        const response = await orderApi.attachments(orderUuid.value);

        if (requestSequence !== attachmentsRequestSequence) {
            return;
        }

        attachments.value = response.attachments;
        attachmentsError.value = null;
    } catch (error: unknown) {
        if (requestSequence !== attachmentsRequestSequence) {
            return;
        }

        attachmentsError.value = error instanceof OrderApiError ? error : null;
        attachments.value = [];
    } finally {
        if (requestSequence === attachmentsRequestSequence) {
            attachmentsLoading.value = false;
        }
    }
}

async function loadHistory(): Promise<void> {
    await loadHistoryPage();
}

async function loadHistoryPage(url?: string): Promise<void> {
    const requestSequence = ++historyRequestSequence;
    historyLoading.value = true;

    try {
        const response = await orderApi.history(orderUuid.value, url);

        if (requestSequence !== historyRequestSequence) {
            return;
        }

        history.value = response;
        historyError.value = null;
    } catch (error: unknown) {
        if (requestSequence !== historyRequestSequence) {
            return;
        }

        historyError.value = error instanceof OrderApiError ? error : null;
        history.value = { data: [], meta: null };
    } finally {
        if (requestSequence === historyRequestSequence) {
            historyLoading.value = false;
        }
    }
}

async function openAttachment(attachment: OrderAttachment, mode: 'preview' | 'download'): Promise<void> {
    if (attachmentActionKey.value) {
        return;
    }

    attachmentActionKey.value = `${attachment.uuid}:${mode}`;
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
        attachmentActionError.value = attachmentActionMessage(error);
    } finally {
        attachmentActionKey.value = null;
    }
}

async function refreshOrder(): Promise<void> {
    if (refreshingOrder.value) {
        return;
    }

    const requestSequence = ++orderRequestSequence;
    refreshingOrder.value = true;

    try {
        const refreshed = await orderApi.show(orderUuid.value);

        if (requestSequence !== orderRequestSequence) {
            return;
        }

        currentOrder.value = normalizeOrder(refreshed);
        lastError.value = null;
        staleState.value = false;
        await Promise.all([loadAttachments(), loadHistory()]);
    } catch (error: unknown) {
        if (requestSequence === orderRequestSequence) {
            handleError(error);
            staleState.value = true;
        }
    } finally {
        if (requestSequence === orderRequestSequence) {
            refreshingOrder.value = false;
        }
    }
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

async function approveServices(payload: CustomerApprovalPayload): Promise<void> {
    busyAction.value = 'approval';
    lastError.value = null;

    try {
        const approvedOrder = await orderApi.approveServices(orderUuid.value, payload);
        currentOrder.value = approvedOrder;
        await refreshOrder();
    } catch (error: unknown) {
        handleError(error);
    } finally {
        busyAction.value = null;
    }
}

function prepareApproval(submission: ApprovalSubmission): void {
    pendingApproval.value = submission;
    openConfirmation('approval');
}

async function completeServices(): Promise<void> {
    busyAction.value = 'completion';
    lastError.value = null;

    try {
        const completedOrder = await orderApi.completeServices(orderUuid.value, { completed_service_ids: completionSelection.value });
        currentOrder.value = completedOrder;
        completionSelection.value = [];
        await refreshOrder();
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
        const readyOrder = await orderApi.markReadyForDelivery(orderUuid.value);
        currentOrder.value = readyOrder;
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
        const deliveredOrder = await orderApi.deliver(orderUuid.value);
        currentOrder.value = deliveredOrder;
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
    if (action === 'approval' && pendingApproval.value) {
        const submission = pendingApproval.value;
        pendingApproval.value = null;
        await approveServices(submission.payload);
    }
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
                        <OrderStatusIndicators
                            :disposition-status="order.disposition_status"
                            :disposition-status-label="order.disposition_status_label"
                            :labels="indicatorLabels"
                            :lifecycle-status="currentLifecycleStatus"
                            :lifecycle-status-label="order.lifecycle_status_label"
                            :payment-status="order.payment_status"
                            :payment-status-label="order.payment_status_label"
                            :priority="order.priority"
                            :priority-label="priorityLabel(order.priority)"
                            :refund-status-labels="refundStatusLabels"
                            :refund-statuses="refundStatuses"
                        />
                    </div>
                    <p class="text-sm whitespace-pre-wrap text-muted-foreground">{{ order.description }}</p>
                    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-muted-foreground">{{ t('orders.customer') }}</dt>
                            <dd>{{ order.customer?.full_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('orders.assigned_to') }}</dt>
                            <dd>{{ order.assigned_to?.full_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('orders.created_at') }}</dt>
                            <dd>{{ formatDateTime(order.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('orders.estimated_completion') }}</dt>
                            <dd>{{ formatDateTime(order.estimated_completion) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">{{ t('orders.actual_completion') }}</dt>
                            <dd>{{ formatDateTime(order.actual_completion) }}</dd>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <dt class="text-muted-foreground">{{ t('orders.notes') }}</dt>
                            <dd class="whitespace-pre-wrap">{{ order.notes ?? t('orders.no_notes') }}</dd>
                        </div>
                    </dl>
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
                        <Button :disabled="refreshingOrder" size="sm" variant="outline" @click="refreshOrder">
                            {{ refreshingOrder ? t('common.loading') : t('orders.reload') }}
                        </Button>
                    </div>
                </div>
            </Card>

            <OrderStatusProgress :status="currentLifecycleStatus" :statuses="statusSteps" :title="t('orders.progress')" />

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
                                        {{ component.component_label ?? t('orders.components') }}
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

            <OrderCustomerApprovalPanel
                v-if="canApprove"
                :busy="busyAction !== null"
                :errors="lastError?.validationErrors ?? {}"
                :item-labels="itemLabels"
                :labels="approvalLabels"
                :services="order.services"
                @submit="prepareApproval"
            />

            <OrderServiceMatrix
                v-if="canComplete"
                :busy="busyAction !== null"
                :item-labels="itemLabels"
                :labels="serviceLabels"
                :selected-ids="completionSelection"
                :services="order.services"
                :title="t('orders.work_completion')"
                mode="completion"
                @update:selected-ids="completionSelection = $event"
            />
            <OrderServiceMatrix
                v-else-if="order.services.length"
                :item-labels="itemLabels"
                :labels="serviceLabels"
                :selected-ids="[]"
                :services="order.services"
                :title="t('orders.services')"
                mode="readonly"
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
                            {{ t('orders.services_completed', completedAuthorizedServices.length) }} ({{ completedAuthorizedServices.length }} /
                            {{ authorizedServices.length }})
                        </p>
                        <p v-if="uncompletedAuthorizedServices.length" class="mt-1 text-xs text-muted-foreground">
                            {{ t('orders.uncompleted_services') }}:
                            {{ uncompletedAuthorizedServices.map((service) => service.service_name ?? t('orders.service')).join(', ') }}
                        </p>
                    </div>
                    <Button :disabled="busyAction !== null" @click="openConfirmation('ready')">{{
                        busyAction === 'ready' ? t('common.loading') : t('orders.mark_ready')
                    }}</Button>
                </div>
            </Card>

            <OrderDeliveryPanel
                v-if="canViewDelivery"
                :busy="busyAction !== null"
                :can-deliver="canDeliver"
                :financials="financials"
                :labels="deliveryLabels"
                @deliver="openConfirmation('deliver')"
            />

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <Card>
                    <div class="flex flex-col gap-3 px-6">
                        <h2 class="text-base font-semibold">{{ t('orders.attachments') }}</h2>
                        <div
                            v-if="attachmentsLoading"
                            aria-busy="true"
                            aria-live="polite"
                            class="relative min-h-32 rounded-md border"
                            data-attachments-state="loading"
                        >
                            <PlaceholderPattern />
                        </div>
                        <div v-else-if="attachmentsError" class="text-sm text-destructive" role="alert">{{ attachmentsError.message }}</div>
                        <div v-else-if="attachments.length" class="flex flex-col gap-2">
                            <div
                                v-for="attachment in attachments"
                                :key="attachment.uuid"
                                class="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm"
                            >
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ attachment.file_name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ attachment.extension ?? attachment.mime_type ?? '—' }} · {{ attachment.human_file_size ?? '—' }} ·
                                        {{ attachment.uploaded_by?.full_name ?? '—' }}
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button
                                        :aria-busy="attachmentActionKey === `${attachment.uuid}:preview`"
                                        :disabled="attachmentActionKey !== null"
                                        class="text-sm underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        type="button"
                                        @click="openAttachment(attachment, 'preview')"
                                    >
                                        {{ t('orders.preview') }}</button
                                    ><button
                                        :aria-busy="attachmentActionKey === `${attachment.uuid}:download`"
                                        :disabled="attachmentActionKey !== null"
                                        class="text-sm underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        type="button"
                                        @click="openAttachment(attachment, 'download')"
                                    >
                                        {{ t('orders.download') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground" data-attachments-state="empty">{{ t('orders.no_attachments') }}</p>
                        <div v-if="attachmentActionError" class="text-sm text-destructive" role="alert">{{ attachmentActionError }}</div>
                    </div>
                </Card>
                <Card>
                    <div class="flex flex-col gap-3 px-6">
                        <h2 class="text-base font-semibold">{{ t('orders.history') }}</h2>
                        <OrderHistoryFeed
                            :entries="history.data"
                            :error-message="historyError?.message"
                            :labels="historyLabels"
                            :links="history.links"
                            :loading="historyLoading"
                            @paginate="loadHistoryPage"
                        />
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
                    {{ t('orders.services_selected', pendingBudget.selectedCount) }} · {{ t('orders.base_total') }}:
                    {{ formatMoney(pendingBudget.baseTotal) }} · {{ t('orders.net_total') }}: {{ formatMoney(pendingBudget.netTotal) }}
                </span>
                <span v-else-if="dialogAction === 'approval' && pendingApproval">
                    {{ t('orders.services_selected', pendingApproval.selectedCount) }} · {{ t('orders.authorized_base_total') }}:
                    {{ formatMoney(pendingApproval.authorizedBaseTotal) }} · {{ t('orders.authorized_net_total') }}:
                    {{ formatMoney(pendingApproval.authorizedNetTotal) }} · {{ t('orders.advance_payment') }}:
                    {{ pendingApproval.downPayment || '—' }}
                </span>
                <span v-else-if="dialogAction === 'completion'">{{ t('orders.services_selected', completionSelection.length) }}</span>
                <span v-else-if="dialogAction === 'ready'">
                    {{ t('orders.services_completed', completedAuthorizedServices.length) }} ({{ completedAuthorizedServices.length }} /
                    {{ authorizedServices.length }})
                    <span v-if="uncompletedAuthorizedServices.length">
                        · {{ t('orders.uncompleted_services') }}:
                        {{ uncompletedAuthorizedServices.map((service) => service.service_name ?? t('orders.service')).join(', ') }}
                    </span>
                </span>
                <span v-else-if="dialogAction === 'deliver'" class="flex flex-col gap-1" data-delivery-confirmation>
                    <span>{{ t('orders.confirm_delivery') }}</span>
                    <span>{{ t('orders.uuid') }}: #{{ order.uuid }}</span>
                    <span>
                        {{ t('orders.completed_total') }}: {{ formatMoney(financials.completed) }} · {{ t('orders.advance_payment') }}:
                        {{ formatMoney(financials.advance_payment) }} · {{ t('orders.remaining_balance') }}:
                        {{ formatMoney(financials.remaining_balance) }}
                    </span>
                </span>
                <span v-else>{{ t('orders.confirm_delivery') }}</span>
            </div>
            <DialogFooter>
                <DialogClose as-child
                    ><Button variant="outline">{{ t('common.cancel') }}</Button></DialogClose
                >
                <Button :disabled="busyAction !== null" data-confirm-action @click="confirmAction">{{ t('common.confirm') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
