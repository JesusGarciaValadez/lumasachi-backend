import OrderHistoryFeed from '@/components/orders/OrderHistoryFeed.vue';
import type { OrderHistory } from '@/types/orders';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

const labels = {
    previous: 'Previous',
    next: 'Next',
    noHistory: 'No history',
    eventStatus: 'Status',
    eventPriority: 'Priority',
    eventAssignment: 'Assignment',
    eventItem: 'Item',
    eventService: 'Service',
    eventPayment: 'Payment',
    eventAttachment: 'Attachment',
    eventUpdate: 'Update',
};

const entries: OrderHistory[] = [
    {
        id: 1,
        uuid: 'history-first',
        order_id: 1,
        field_changed: 'status',
        description: 'Status changed from Open to Reviewed',
        created_at: '2026-07-27T12:00:00Z',
        attachments: [],
    },
    {
        id: 2,
        uuid: 'history-second',
        order_id: 1,
        field_changed: 'status',
        description: 'Status changed from Reviewed to Awaiting Customer Approval',
        created_at: '2026-07-27T11:30:00Z',
        attachments: [],
    },
    {
        id: 3,
        uuid: 'history-third',
        order_id: 1,
        field_changed: 'attachments',
        description: 'Attachments set to: invoice.pdf',
        created_at: '2026-07-27T11:00:00Z',
        attachments: [
            {
                id: 1,
                uuid: 'attachment-uuid',
                file_name: 'invoice.pdf',
            },
        ],
    },
];

function mountFeed(props: Partial<InstanceType<typeof OrderHistoryFeed>['$props']> = {}) {
    return mount(OrderHistoryFeed, {
        props: {
            entries: [],
            loading: false,
            labels,
            ...props,
        },
        global: {
            stubs: {
                Button: { template: '<button v-bind="$attrs"><slot /></button>' },
                Icon: { template: '<span />' },
            },
        },
    });
}

describe('OrderHistoryFeed', () => {
    it('renders a loading state independently', () => {
        const wrapper = mountFeed({ loading: true });

        expect(wrapper.get('[data-history-state="loading"]')).toBeTruthy();
        expect(wrapper.find('[data-history-feed]').exists()).toBe(false);
    });

    it('renders a stable empty state independently', () => {
        const wrapper = mountFeed();

        expect(wrapper.get('[data-history-state="empty"]').text()).toContain('No history');
        expect(wrapper.find('[data-history-feed]').exists()).toBe(false);
    });

    it('renders endpoint errors separately from empty history and paginates by server link', async () => {
        const errorWrapper = mountFeed({ errorMessage: 'History unavailable' });
        expect(errorWrapper.get('[data-history-state="error"]').text()).toBe('History unavailable');

        const wrapper = mountFeed({ entries, links: { prev: null, next: '/history?page=2' } });
        const next = wrapper.findAll('button').find((button) => button.text() === 'Next');
        await next?.trigger('click');

        expect(wrapper.emitted('paginate')).toEqual([['/history?page=2']]);
    });

    it('preserves server order and distinct event data', () => {
        const wrapper = mountFeed({ entries });
        const events = wrapper.findAll('[data-event-field]');

        expect(events.map((event) => event.attributes('data-event-field'))).toEqual(['status', 'status', 'attachments']);
        expect(wrapper.text()).toContain('Status changed from Open to Reviewed');
        expect(wrapper.text()).toContain('Status changed from Reviewed to Awaiting Customer Approval');
        expect(wrapper.text()).toContain('Attachments set to: invoice.pdf');
        expect(wrapper.text()).toContain('invoice.pdf');
        expect(events[0].get('[data-event-icon]').attributes('data-icon-name')).toBe('refresh-cw');
    });
});
