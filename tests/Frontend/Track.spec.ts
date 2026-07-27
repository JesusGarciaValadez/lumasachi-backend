import { OrderApiError } from '@/composables/useOrderApi';
import Track from '@/pages/Orders/Track.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { vi } from 'vitest';

const { track } = vi.hoisted(() => ({ track: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<title><slot /></title>',
    },
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) =>
            ({
                'common.loading': 'Loading',
                'orders.advance_payment': 'Advance payment',
                'orders.authorized': 'Authorized',
                'orders.budgeted': 'Budgeted',
                'orders.budgeted_total': 'Budgeted total',
                'orders.completed': 'Completed',
                'orders.completed_total': 'Completed total',
                'orders.financial_summary': 'Financial summary',
                'orders.lookup': 'Lookup',
                'orders.remaining_balance': 'Remaining balance',
                'orders.track': 'Track order',
                'orders.track_help': 'Enter the order details.',
                'orders.track_network_error': 'Network error',
                'orders.track_not_found': 'Order not found.',
                'orders.track_rate_limit': 'Too many attempts.',
                'orders.progress': 'Order progress',
                'orders.not_budgeted': 'Not budgeted',
                'orders.service': 'Service',
            })[key] ?? key,
        tm: () => ({ Delivered: 'Delivered' }),
    }),
}));

vi.mock('@/composables/useOrderApi', () => ({
    OrderApiError: class OrderApiError extends Error {
        readonly kind: string;

        constructor(
            readonly status: number,
            message: string,
            readonly validationErrors: Record<string, string[]> = {},
        ) {
            super(message);
            this.name = 'OrderApiError';
            this.kind = status === 404 ? 'not_found' : status === 422 ? 'validation' : status === 429 ? 'rate_limit' : 'unexpected';
        }
    },
    useOrderApi: () => ({ track }),
}));

const publicOrder = {
    uuid: 'order-uuid',
    title: 'Engine service',
    description: 'Public description',
    status: 'Delivered' as const,
    status_label: 'Delivered',
    priority: 'Normal' as const,
    priority_label: 'Normal',
    created_at: '2026-07-26T12:00:00Z',
    motor_info: null,
    items: [],
    services: [],
    financials: {
        budgeted: '100.00',
        authorized: '100.00',
        completed: '100.00',
        advance_payment: '50.00',
        remaining_balance: '50.00',
    },
    history: [],
    attachments: [],
};

describe('Orders/Track', () => {
    beforeEach(() => {
        track.mockReset();
    });

    it('submits the public lookup contract and renders a read-only result', async () => {
        track.mockResolvedValue(publicOrder);
        const wrapper = mount(Track);

        await wrapper.get('#track-uuid').setValue('order-uuid');
        await wrapper.get('#track-date').setValue('2026-07-26');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(track).toHaveBeenCalledWith({ uuid: 'order-uuid', created_date: '2026-07-26' }, expect.any(AbortSignal));
        expect(wrapper.text()).toContain('Engine service');
        expect(wrapper.findAll('button')).toHaveLength(1);
    });

    it('clears a previous result after a failed lookup without clearing the form', async () => {
        track.mockResolvedValueOnce(publicOrder).mockRejectedValueOnce(new Error('No connection'));
        const wrapper = mount(Track);
        const uuid = wrapper.get('#track-uuid');
        const date = wrapper.get('#track-date');

        await uuid.setValue('order-uuid');
        await date.setValue('2026-07-26');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Engine service');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).not.toContain('Engine service');
        expect(wrapper.get('[role="alert"]').text()).toContain('Network error');
        expect((uuid.element as HTMLInputElement).value).toBe('order-uuid');
        expect((date.element as HTMLInputElement).value).toBe('2026-07-26');
    });

    it('renders field-level validation errors, not-found, and rate-limit states', async () => {
        const wrapper = mount(Track);

        track.mockRejectedValueOnce(
            new OrderApiError(422, 'Validation failed', {
                uuid: ['The UUID is invalid.'],
                created_date: ['The date is invalid.'],
            }),
        );
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('The UUID is invalid.');
        expect(wrapper.text()).toContain('The date is invalid.');

        track.mockRejectedValueOnce(new OrderApiError(404, 'Order not found.'));
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe('Order not found.');

        track.mockRejectedValueOnce(new OrderApiError(429, 'Too many requests.'));
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe('Too many attempts.');
    });

    it('keeps unexpected errors distinct from network errors', async () => {
        track.mockRejectedValueOnce(new OrderApiError(500, 'Unexpected server error.'));
        const wrapper = mount(Track);

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('Unexpected server error.');
    });

    it('ignores a superseded response', async () => {
        let resolveFirst: (value: typeof publicOrder) => void = () => undefined;
        const firstRequest = new Promise<typeof publicOrder>((resolve) => {
            resolveFirst = resolve;
        });
        const newerOrder = { ...publicOrder, title: 'Newer engine service' };
        track.mockReturnValueOnce(firstRequest).mockResolvedValueOnce(newerOrder);
        const wrapper = mount(Track);

        await wrapper.get('#track-uuid').setValue('first-order');
        await wrapper.get('#track-date').setValue('2026-07-26');
        await wrapper.get('form').trigger('submit');
        await wrapper.get('#track-uuid').setValue('second-order');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Newer engine service');
        resolveFirst(publicOrder);
        await flushPromises();
        expect(wrapper.text()).toContain('Newer engine service');
        expect(wrapper.text()).not.toContain('Engine service\n');
    });

    it('ignores a superseded error', async () => {
        let rejectFirst: (reason?: unknown) => void = () => undefined;
        const firstRequest = new Promise<typeof publicOrder>((_resolve, reject) => {
            rejectFirst = reject;
        });
        track.mockReturnValueOnce(firstRequest).mockResolvedValueOnce(publicOrder);
        const wrapper = mount(Track);

        await wrapper.get('#track-uuid').setValue('first-order');
        await wrapper.get('#track-date').setValue('2026-07-26');
        await wrapper.get('form').trigger('submit');
        await wrapper.get('#track-uuid').setValue('second-order');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        rejectFirst(new Error('The first request failed.'));
        await flushPromises();

        expect(wrapper.text()).toContain('Engine service');
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });
});
