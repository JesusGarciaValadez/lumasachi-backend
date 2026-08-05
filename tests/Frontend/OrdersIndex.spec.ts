import OrdersIndex from '@/pages/Orders/Index.vue';
import type { OrderListUser, OrderSummary } from '@/types/orders';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const router = vi.hoisted(() => ({
    get: vi.fn(),
}));

const page = vi.hoisted(() => ({
    props: {
        flash: {},
    },
    flash: {},
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<title><slot /></title>',
    },
    Link: {
        inheritAttrs: false,
        props: ['href'],
        template: '<a v-bind="$attrs" :href="href"><slot /></a>',
    },
    router,
    usePage: () => page,
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) => key,
        tm: () => ({}),
    }),
}));

const passthroughStub = { template: '<div><slot /></div>' };

function route(name: string, parameter?: string): string {
    if (name === 'web.orders.show') {
        return `/orders/${parameter ?? ''}`;
    }

    return `/${name}`;
}

function order(uuid: string, title: string, assignedTo: OrderListUser | null, createdAt: string): OrderSummary {
    return {
        id: uuid === 'newest' ? 2 : 1,
        uuid,
        title,
        lifecycle_status: 'Received',
        lifecycle_status_label: 'Received',
        disposition_status: null,
        disposition_status_label: null,
        payment_status: 'Unpaid',
        payment_status_label: 'Unpaid',
        priority: 'Normal',
        priority_label: 'Normal',
        created_at: createdAt,
        assigned_to: assignedTo,
        customer: { id: 2, uuid: 'customer-1', full_name: 'Customer Name' },
        company: { id: 1, uuid: 'company-1', name: 'Acme Engines' },
        refunds: [],
    };
}

function mountPage(overrides: Record<string, unknown> = {}) {
    return mount(OrdersIndex, {
        props: {
            can_create_order: false,
            orders: {
                data: [order('newest', 'Newest order', { id: 1, uuid: 'assignee-1', full_name: 'Ana Employee' }, '2026-08-04T12:00:00Z')],
                current_page: 1,
                from: 1,
                last_page: 1,
                per_page: 10,
                to: 1,
                total: 1,
                links: [],
            },
            filters: {
                title: '',
                company_id: '',
                assigned_to: '',
                priority: '',
                lifecycle_status: '',
                payment_status: '',
                disposition_status: '',
                created_from: '',
                created_to: '',
                per_page: 10,
            },
            options: {
                companies: [{ id: 1, uuid: 'company-1', name: 'Acme Engines' }],
                assignees: [{ id: 1, uuid: 'assignee-1', full_name: 'Ana Employee' }],
                priorities: ['Low', 'Normal', 'High', 'Urgent'],
                lifecycle_statuses: ['Received'],
                payment_statuses: ['Unpaid', 'Partially Paid', 'Paid'],
                disposition_statuses: ['Returned', 'Cancelled'],
                per_page: [10, 20, 50],
            },
            ...overrides,
        },
        global: {
            mocks: { route },
            stubs: {
                AppLayout: passthroughStub,
                Button: passthroughStub,
                Card: passthroughStub,
                OrderStatusIndicators: passthroughStub,
            },
        },
    });
}

describe('Orders/Index', () => {
    beforeEach(() => {
        router.get.mockReset();
        page.props.flash = {};
        page.flash = {};
        vi.stubGlobal('route', route);
    });

    it('renders the server-provided order list with customer and assignee pills', () => {
        const wrapper = mountPage({
            orders: {
                data: [
                    order('newest', 'Newest order', { id: 1, uuid: 'assignee-1', full_name: 'Ana Employee' }, '2026-08-04T12:00:00Z'),
                    order('older', 'Older order', { id: 2, uuid: 'assignee-2', full_name: 'Luis Employee' }, '2026-08-03T12:00:00Z'),
                ],
                current_page: 1,
                from: 1,
                last_page: 1,
                per_page: 10,
                to: 2,
                total: 2,
                links: [],
            },
        });

        expect(wrapper.findAll('[data-order-row]')).toHaveLength(2);
        expect(wrapper.find('[data-order-row="newest"]').text()).toContain('Newest order');
        expect(wrapper.find('[data-order-row="newest"]').text()).toContain('Customer Name');
        expect(wrapper.find('[data-order-row="newest"]').text()).not.toContain('Acme Engines');
        expect(wrapper.find('[data-orders-assignee="newest"]').text()).toContain('Ana Employee');
    });

    it('renders pagination and page-size selector from server props', () => {
        const wrapper = mountPage({
            orders: {
                data: [],
                current_page: 2,
                from: 11,
                last_page: 3,
                per_page: 10,
                to: 20,
                total: 30,
                links: [
                    { url: '/orders?page=1', label: '1', page: 1, active: false },
                    { url: '/orders?page=2', label: '2', page: 2, active: true },
                ],
            },
        });

        expect(wrapper.find('[dusk="orders-pagination"]').exists()).toBe(true);
        expect(wrapper.find('#orders-page-size').findAll('option')).toHaveLength(3);
    });

    it('sends flexible title and date filters through Inertia', async () => {
        const wrapper = mountPage();

        await wrapper.get('[dusk="orders-filters-trigger"]').trigger('click');
        await wrapper.get('#orders-title').setValue('engine');
        await wrapper.get('#orders-created-from').setValue('2026-08-01');
        await wrapper.get('#orders-created-to').setValue('2026-08-04');
        await wrapper.get('#orders-created-to').trigger('change');
        await flushPromises();

        expect(router.get).toHaveBeenCalledWith(
            '/web.orders.index',
            expect.objectContaining({
                title: 'engine',
                created_from: '2026-08-01',
                created_to: '2026-08-04',
                per_page: 10,
            }),
            expect.objectContaining({ preserveScroll: true, preserveState: true, replace: true }),
        );
    });

    it('renders the success flash in an accessible stable status element', () => {
        const message = 'Order created successfully.';
        page.flash = { success: message };

        const wrapper = mountPage();

        const status = wrapper.get('[dusk="orders-flash"][role="status"]');

        expect(status.text()).toContain(message);
    });
});
