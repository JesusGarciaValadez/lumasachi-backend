import Show from '@/pages/Orders/Show.vue';
import type { Order } from '@/types/orders';
import { flushPromises, mount } from '@vue/test-utils';
import { vi } from 'vitest';

const api = vi.hoisted(() => ({
    attachments: vi.fn(),
    catalog: vi.fn(),
    deliver: vi.fn(),
    history: vi.fn(),
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
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) =>
            ({
                'common.confirm': 'Confirm',
                'common.loading': 'Loading...',
                'orders.advance_payment': 'Advance payment',
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
                'orders.remaining_balance': 'Remaining balance',
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
    status: 'Ready for Delivery',
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
        remaining_balance: '0.00',
    },
} as Order;

const deliveredOrder = { ...order, status: 'Delivered' } as Order;

function mountPage() {
    return mount(Show, {
        props: {
            order,
            capabilities: {
                create_order: true,
                submit_budget: false,
                approve_services: false,
                complete_services: false,
                mark_ready_for_delivery: false,
                deliver_order: true,
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
        api.catalog.mockResolvedValue({ item_types: [] });
        api.deliver.mockResolvedValue(deliveredOrder);
        api.history.mockResolvedValue({ data: [], meta: null });
        api.show.mockResolvedValue(deliveredOrder);
    });

    it('confirms with the displayed financial values and removes delivery actions after success', async () => {
        const wrapper = mountPage();

        await flushPromises();

        await wrapper.get('[data-delivery-action]').trigger('click');

        expect(wrapper.get('[data-delivery-confirmation]').text()).toContain('order-uuid');
        expect(wrapper.get('[data-delivery-confirmation]').text()).toContain('100.00');

        await wrapper.get('[data-confirm-action]').trigger('click');
        await flushPromises();

        expect(api.deliver).toHaveBeenCalledWith('order-uuid');
        expect(api.show).toHaveBeenCalledWith('order-uuid');
        expect(wrapper.find('[data-delivery-panel]').exists()).toBe(false);
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
});
