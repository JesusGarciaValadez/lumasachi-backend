import Show from '@/pages/Orders/Show.vue';
import type { Order } from '@/types/orders';
import { flushPromises, mount } from '@vue/test-utils';
import { vi } from 'vitest';

const api = vi.hoisted(() => ({
    attachments: vi.fn(),
    catalog: vi.fn(),
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
        t: (key: string, count?: number) => {
            const value =
                {
                    'common.cancel': 'Cancel',
                    'common.confirm': 'Confirm',
                    'orders.advance_payment': 'Advance payment',
                    'orders.authorized_total': 'Authorized total',
                    'orders.base_price': 'Base price',
                    'orders.base_total': 'Base total',
                    'orders.budgeted_total': 'Budgeted total',
                    'orders.budgeted': 'Budgeted',
                    'orders.confirm_action': 'Confirm action',
                    'orders.confirm_action_description': 'Review the selected services.',
                    'orders.net_price': 'Net price',
                    'orders.net_total': 'Net total',
                    'orders.services_selected': (count: number) => `${count} selected`,
                }[key] ?? key;

            return typeof value === 'function' ? value(count ?? 0) : value;
        },
        tm: () => ({}),
    }),
}));

vi.mock('@/composables/useOrderApi', () => ({
    useOrderApi: () => api,
}));

const passthroughStub = {
    template: '<div><slot /></div>',
};

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

const ReviewBudgetPanelStub = {
    props: ['labels'],
    emits: ['submit'],
    template:
        "<button data-review-submit @click=\"$emit('submit', { selectedCount: 1, netTotal: '696.00', payload: { services: [] } })\">Submit budget</button>",
};

const CustomerApprovalPanelStub = {
    props: ['labels'],
    emits: ['submit'],
    template:
        "<button data-approval-submit @click=\"$emit('submit', { selectedCount: 1, budgetedTotal: '696.00', authorizedTotal: '928.00', payload: { authorized_service_ids: [1] } })\">Approve selected</button>",
};

const FinancialSummaryStub = {
    props: ['labels'],
    template: '<div data-financial-summary><span data-financial-base-total-label>{{ labels.baseTotal }}</span></div>',
};

const ServiceMatrixStub = {
    props: ['labels', 'mode'],
    template: '<div v-if="mode === \'readonly\'" data-service-matrix><span data-service-base-price-label>{{ labels.base_price }}</span></div>',
};

const baseOrder = {
    id: 1,
    uuid: 'order-uuid',
    title: 'Engine service',
    description: 'Order description',
    disposition_status: null,
    payment_status: 'Unpaid',
    priority: 'Normal',
    items: [],
    services: [
        {
            id: 1,
            uuid: 'service-uuid',
            order_item_id: 1,
            service_key: 'wash_block',
            service_name: 'Wash block',
            measurement: null,
            is_budgeted: true,
            is_authorized: false,
            is_completed: false,
            base_price: '100.00',
            net_price: '116.00',
        },
    ],
    history: [],
    attachments: [],
    financials: {
        budgeted: '0.00',
        authorized: '0.00',
        completed: '0.00',
        advance_payment: '0.00',
        remaining_balance: '0.00',
    },
};

function mountPage(lifecycle_status: Order['lifecycle_status']) {
    const order = { ...baseOrder, lifecycle_status } as Order;

    return mount(Show, {
        props: {
            order,
            capabilities: {
                create_order: true,
                submit_budget: lifecycle_status === 'Awaiting Review',
                approve_services: lifecycle_status === 'Awaiting Customer Approval',
                complete_services: false,
                mark_ready_for_delivery: false,
                deliver_order: false,
                cancel_order: false,
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
                OrderCustomerApprovalPanel: CustomerApprovalPanelStub,
                OrderDeliveryPanel: passthroughStub,
                OrderFinancialSummary: FinancialSummaryStub,
                OrderHistoryFeed: passthroughStub,
                OrderReviewBudgetPanel: ReviewBudgetPanelStub,
                OrderServiceMatrix: ServiceMatrixStub,
                OrderStatusIndicators: passthroughStub,
                OrderStatusProgress: passthroughStub,
                PlaceholderPattern: passthroughStub,
            },
        },
    });
}

describe('Orders/Show budget and approval confirmations', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.stubGlobal('route', (name: string, parameter?: string) => `/${name}/${parameter ?? ''}`);
        api.attachments.mockResolvedValue({ attachments: [] });
        api.catalog.mockResolvedValue({ item_types: [] });
        api.history.mockResolvedValue({ data: [], meta: null });
        api.show.mockResolvedValue(baseOrder);
    });

    it('confirms a budget with only the net total', async () => {
        const wrapper = mountPage('Awaiting Review');

        await flushPromises();
        await wrapper.get('[data-review-submit]').trigger('click');

        const confirmation = wrapper.get('[data-dialog]').text();

        expect(confirmation).toContain('Net total');
        expect(confirmation).toContain('696.00');
        expect(confirmation).not.toContain('Base price');
        expect(confirmation).not.toContain('Base total');
        expect(confirmation).not.toContain('600.00');
        expect(confirmation).not.toContain('Advance payment');
    });

    it.each(['Awaiting Review', 'Reviewed', 'Awaiting Customer Approval', 'Ready for Work', 'Ready for Delivery', 'Delivered'])(
        'hides base totals and prices from every authenticated order state (%s)',
        async (lifecycleStatus) => {
            const wrapper = mountPage(lifecycleStatus as Order['lifecycle_status']);

            await flushPromises();

            expect(wrapper.get('[data-financial-base-total-label]').text()).toBe('');
            expect(wrapper.get('[data-service-base-price-label]').text()).toBe('');
        },
    );

    it('confirms approval with only the authorized total', async () => {
        const wrapper = mountPage('Awaiting Customer Approval');

        await flushPromises();
        await wrapper.get('[data-approval-submit]').trigger('click');

        const confirmation = wrapper.get('[data-dialog]').text();

        expect(confirmation).toContain('Authorized total');
        expect(confirmation).toContain('928.00');
        expect(confirmation).not.toContain('Base price');
        expect(confirmation).not.toContain('Authorized base total');
        expect(confirmation).not.toContain('800.00');
        expect(confirmation).not.toContain('Advance payment');
        expect(wrapper.get('[data-financial-base-total-label]').text()).toBe('');
        expect(wrapper.get('[data-service-base-price-label]').text()).toBe('');
    });
});
