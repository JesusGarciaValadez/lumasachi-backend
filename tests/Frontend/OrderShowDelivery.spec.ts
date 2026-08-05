import Show from '@/pages/Orders/Show.vue';
import type { Order } from '@/types/orders';
import { router } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { vi } from 'vitest';

const api = vi.hoisted(() => ({
    attachments: vi.fn(),
    cancel: vi.fn(),
    catalog: vi.fn(),
    deliver: vi.fn(),
    history: vi.fn(),
    markReadyForDelivery: vi.fn(),
    show: vi.fn(),
}));

const OrderApiError = vi.hoisted(
    () =>
        class OrderApiError extends Error {
            readonly status: number;
            readonly kind: 'conflict' | 'unexpected';
            readonly validationErrors: Record<string, string[]> = {};

            constructor(status: number, message: string) {
                super(message);
                this.status = status;
                this.kind = status === 409 ? 'conflict' : 'unexpected';
            }
        },
);

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<title><slot /></title>',
    },
    router: {
        flash: vi.fn(),
        visit: vi.fn((_url: string, options?: { onSuccess?: () => void }) => options?.onSuccess?.()),
    },
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) =>
            ({
                'common.confirm': 'Confirm',
                'common.loading': 'Loading...',
                'orders.cancel_order': 'Cancel order',
                'orders.confirm_cancel': 'The order will be marked cancelled.',
                'orders.action_failed': 'The action failed.',
                'orders.payment_amount': 'Payment amount',
                'orders.payment_amount_help': 'Enter the amount paid.',
                'orders.record_payment': 'Record payment',
                'orders.completed_total': 'Completed total',
                'orders.confirm_delivery': 'The order will be marked delivered.',
                'orders.deliver': 'Deliver order',
                'orders.delivery': 'Delivery',
                'orders.attachment_not_authorized': 'Not authorized to open attachment.',
                'orders.attachment_not_found': 'Attachment not found.',
                'orders.attachment_not_previewable': 'Attachment cannot be previewed.',
                'orders.attachment_action_failed': 'Attachment action failed.',
                'orders.financial_summary': 'Financial summary',
                'orders.no_attachments': 'No attachments',
                'orders.preview': 'Preview',
                'orders.download': 'Download',
                'orders.ready_for_delivery_success': 'Order marked ready.',
                'orders.uuid': 'Order UUID',
            })[key] ?? key,
        tm: (key: string) =>
            key === 'orders.status_labels'
                ? { Delivered: 'Delivered', 'Ready for Delivery': 'Ready for delivery' }
                : key === 'orders.priority_labels'
                  ? { Normal: 'Normal' }
                  : {},
    }),
}));

vi.mock('@/composables/useOrderApi', () => ({
    OrderApiError,
    useOrderApi: () => api,
}));

const ButtonStub = {
    inheritAttrs: false,
    template: '<button v-bind="$attrs"><slot /></button>',
};

const CardStub = {
    template: '<div><slot /></div>',
};

const DialogStub = {
    props: ['open'],
    template: '<div v-if="open" data-dialog><slot /></div>',
};

const passthroughStub = {
    template: '<div><slot /></div>',
};

const order = {
    id: 1,
    uuid: 'order-uuid',
    title: 'Engine service',
    description: 'Order description',
    lifecycle_status: 'Ready for Delivery',
    disposition_status: null,
    payment_status: 'Paid',
    priority: 'Normal',
    items: [],
    services: [],
    history: [],
    attachments: [],
    financials: {
        budgeted: '100.00',
        authorized: '100.00',
        completed: '100.00',
        advance_payment: '100.00',
        paid: '100.00',
        remaining_balance: '0.00',
        remaining_change: '0.00',
    },
} as Order;

const deliveredOrder = { ...order, lifecycle_status: 'Delivered' } as Order;
const readyForWorkOrder = { ...order, lifecycle_status: 'Ready for Work' } as Order;
const cancelledOrder = { ...order, disposition_status: 'Cancelled', disposition_status_label: 'Cancelled' } as Order;

function mountPage({
    cancel_order = true,
    lifecycleStatus = order.lifecycle_status,
    mark_ready_for_delivery = false,
}: {
    cancel_order?: boolean;
    lifecycleStatus?: Order['lifecycle_status'];
    mark_ready_for_delivery?: boolean;
} = {}) {
    const pageOrder = lifecycleStatus === 'Ready for Work' ? readyForWorkOrder : order;

    return mount(Show, {
        props: {
            order: pageOrder,
            capabilities: {
                create_order: true,
                submit_budget: false,
                approve_services: false,
                complete_services: false,
                mark_ready_for_delivery,
                deliver_order: true,
                cancel_order,
            },
        },
        global: {
            mocks: {
                route: (name: string, parameter?: string) => `/${name}/${parameter ?? ''}`,
            },
            stubs: {
                AppLayout: passthroughStub,
                Button: ButtonStub,
                Card: CardStub,
                Dialog: DialogStub,
                DialogClose: passthroughStub,
                DialogContent: passthroughStub,
                DialogDescription: passthroughStub,
                DialogFooter: passthroughStub,
                DialogHeader: passthroughStub,
                DialogTitle: passthroughStub,
                OrderCustomerApprovalPanel: passthroughStub,
                OrderReviewBudgetPanel: passthroughStub,
                OrderServiceMatrix: passthroughStub,
                OrderStatusProgress: passthroughStub,
                PlaceholderPattern: passthroughStub,
            },
        },
    });
}

describe('Orders/Show delivery workflow', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.stubGlobal('route', (name: string, parameter?: string) => `/${name}/${parameter ?? ''}`);
        api.attachments.mockResolvedValue({ attachments: [] });
        api.cancel.mockResolvedValue(cancelledOrder);
        api.catalog.mockResolvedValue({ item_types: [] });
        api.deliver.mockResolvedValue(deliveredOrder);
        api.history.mockResolvedValue({ data: [], meta: null });
        api.show.mockResolvedValue(deliveredOrder);
    });

    it('allows an administrator to cancel the order and removes the button after success', async () => {
        api.show.mockResolvedValueOnce(cancelledOrder);
        const wrapper = mountPage();

        await flushPromises();

        await wrapper.get('[data-order-cancel-button]').trigger('click');
        expect(wrapper.get('[data-cancel-confirmation]').text()).toContain('marked cancelled');

        await wrapper.get('[data-confirm-action]').trigger('click');
        await flushPromises();

        expect(api.cancel).toHaveBeenCalledWith('order-uuid');
        expect(wrapper.find('[data-order-cancel-button]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Cancelled');
    });

    it('does not render cancellation controls when the server capability is false', async () => {
        const wrapper = mountPage({ cancel_order: false });

        await flushPromises();

        expect(wrapper.find('[data-order-cancel-button]').exists()).toBe(false);
    });

    it('confirms with the displayed financial values and removes delivery actions after success', async () => {
        const wrapper = mountPage();

        await flushPromises();

        await wrapper.get('[data-delivery-payment]').setValue('100.00');
        await wrapper.get('[data-delivery-action]').trigger('click');

        expect(wrapper.get('[data-delivery-confirmation]').text()).toContain('order-uuid');
        expect(wrapper.get('[data-delivery-confirmation]').text()).toContain('100.00');
        expect(wrapper.get('[data-delivery-confirmation]').text()).not.toContain('Remaining balance');

        await wrapper.get('[data-confirm-action]').trigger('click');
        await flushPromises();

        expect(api.deliver).toHaveBeenCalledWith('order-uuid', '100.00');
        expect(api.show).toHaveBeenCalledWith('order-uuid');
        expect(wrapper.find('[data-delivery-panel]').exists()).toBe(true);
        expect(wrapper.find('[data-delivery-payment]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Delivered');
    });

    it('keeps attachment authorization and missing states distinct', async () => {
        const attachment = {
            id: 1,
            uuid: 'attachment-uuid',
            file_name: 'invoice.pdf',
            extension: 'pdf',
            human_file_size: '1 KB',
            uploaded_by: null,
        };
        api.attachments.mockResolvedValueOnce({ attachments: [attachment] });
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({ ok: false, status: 403, json: async () => ({}) })
            .mockResolvedValueOnce({ ok: false, status: 404, json: async () => ({}) });
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = mountPage();

        await flushPromises();
        const preview = wrapper.findAll('button').find((button) => button.text() === 'Preview');
        expect(preview).toBeDefined();

        await preview?.trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('Not authorized');

        await preview?.trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('Attachment not found');
    });

    it('renders the attachment loading and empty states independently', async () => {
        api.attachments.mockReturnValueOnce(new Promise(() => undefined));
        const loadingWrapper = mountPage();

        expect(loadingWrapper.get('[data-attachments-state="loading"]')).toBeTruthy();

        api.attachments.mockResolvedValueOnce({ attachments: [] });
        const emptyWrapper = mountPage();
        await flushPromises();

        expect(emptyWrapper.get('[data-attachments-state="empty"]').text()).toContain('No attachments');
    });

    it('keeps the stale-state reload available when refreshing fails', async () => {
        api.deliver.mockRejectedValueOnce(new OrderApiError(409, 'Order changed.'));
        const wrapper = mountPage();

        await flushPromises();
        await wrapper.get('[data-delivery-payment]').setValue('100.00');
        await wrapper.get('[data-delivery-action]').trigger('click');
        await wrapper.get('[data-confirm-action]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('Order changed.');
        api.show.mockRejectedValueOnce(new OrderApiError(500, 'Reload failed.'));

        await wrapper.get('[role="alert"] button').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('Reload failed.');
        expect(wrapper.get('[role="alert"] button').text()).toContain('orders.reload');
    });

    it('redirects with a success flash after marking an order ready for delivery', async () => {
        api.markReadyForDelivery.mockResolvedValueOnce(readyForWorkOrder);
        const wrapper = mountPage({ lifecycleStatus: 'Ready for Work', mark_ready_for_delivery: true });

        await flushPromises();
        await wrapper.get('[dusk="order-ready-submit"]').trigger('click');
        await wrapper.get('[data-confirm-action]').trigger('click');
        await flushPromises();

        expect(api.markReadyForDelivery).toHaveBeenCalledWith('order-uuid');
        expect(router.visit).toHaveBeenCalled();
        expect(router.flash).toHaveBeenCalledWith('success', 'Order marked ready.');
    });

    it('keeps the ready-for-delivery form visible after a save error', async () => {
        api.markReadyForDelivery.mockRejectedValueOnce(new OrderApiError(500, 'Could not save the order.'));
        const wrapper = mountPage({ lifecycleStatus: 'Ready for Work', mark_ready_for_delivery: true });

        await flushPromises();
        await wrapper.get('[dusk="order-ready-submit"]').trigger('click');
        await wrapper.get('[data-confirm-action]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[dusk="order-ready-for-delivery"]')).toBeTruthy();
        expect(wrapper.get('[role="alert"]').text()).toContain('Could not save the order.');
    });
});
