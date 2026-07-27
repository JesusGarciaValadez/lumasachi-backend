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
                'orders.financial_summary': 'Financial summary',
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
    OrderApiError: class OrderApiError extends Error {
        readonly kind = 'unexpected';
        readonly validationErrors: Record<string, string[]> = {};
    },
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
});
