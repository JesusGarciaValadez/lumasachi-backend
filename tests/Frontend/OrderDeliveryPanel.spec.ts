import OrderDeliveryPanel from '@/components/orders/OrderDeliveryPanel.vue';
import type { FinancialTotals } from '@/types/orders';
import { mount } from '@vue/test-utils';

const labels = {
    title: 'Delivery',
    remaining_balance: 'Remaining balance',
    payment_required: 'Delivery is blocked while a balance remains.',
    deliver: 'Deliver order',
    loading: 'Loading...',
};

function mountPanel(financials: FinancialTotals, canDeliver = true, busy = false) {
    return mount(OrderDeliveryPanel, {
        props: {
            financials,
            canDeliver,
            busy,
            labels,
        },
    });
}

describe('OrderDeliveryPanel', () => {
    it('blocks delivery and explains a positive remaining balance', () => {
        const wrapper = mountPanel({
            budgeted: '100.00',
            authorized: '100.00',
            completed: '100.00',
            advance_payment: '50.00',
            remaining_balance: '50.00',
        });

        expect(wrapper.find('[data-delivery-remaining]').text()).toBe('50.00');
        expect(wrapper.get('[role="alert"]').text()).toContain(labels.payment_required);
        expect(wrapper.get('[data-delivery-action]').attributes('disabled')).toBeDefined();
    });

    it.each([
        ['exact payment', '100.00', '100.00'],
        ['overpayment', '125.00', '100.00'],
        ['zero total', '0.00', '0.00'],
    ])('allows delivery for %s', async (_name, advancePayment, completed) => {
        const wrapper = mountPanel({
            budgeted: completed,
            authorized: completed,
            completed,
            advance_payment: advancePayment,
            remaining_balance: '0.00',
        });

        const action = wrapper.get('[data-delivery-action]');

        expect(action.attributes('disabled')).toBeUndefined();

        await action.trigger('click');

        expect(wrapper.emitted('deliver')).toHaveLength(1);
    });
});
