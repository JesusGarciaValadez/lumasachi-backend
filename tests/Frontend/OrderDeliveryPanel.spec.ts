import OrderDeliveryPanel from '@/components/orders/OrderDeliveryPanel.vue';
import type { FinancialTotals } from '@/types/orders';
import { mount } from '@vue/test-utils';

const labels = {
    title: 'Delivery',
    payment_amount: 'Payment amount',
    payment_amount_help: 'Enter the amount paid by the customer.',
    submit: 'Record payment',
    deliver: 'Deliver order',
    loading: 'Loading...',
    net_total: 'Net total',
    budgeted_total: 'Budgeted total',
    authorized_total: 'Authorized total',
    completed_total: 'Completed work total',
    total_paid: 'Total paid',
    balance_remaining: 'Balance remaining',
    remaining_change: 'Remaining change',
};

function mountPanel(financials: FinancialTotals, canDeliver = true, busy = false, paymentAmount = '') {
    return mount(OrderDeliveryPanel, {
        props: {
            financials,
            canDeliver,
            busy,
            paymentAmount,
            labels,
        },
    });
}

describe('OrderDeliveryPanel', () => {
    it('renders the payment input and balance remaining for a partial payment', async () => {
        const wrapper = mountPanel({
            budgeted_net: '84.00',
            budgeted: '100.00',
            authorized: '100.00',
            completed: '100.00',
            advance_payment: '50.00',
            remaining_balance: '50.00',
        });

        expect(wrapper.get('[data-delivery-payment]').attributes('type')).toBe('number');
        expect(wrapper.get('[data-delivery-summary]').text()).toContain(labels.net_total);
        expect(wrapper.get('[data-delivery-summary-value="net-total"]').text()).toBe('84.00');
        expect(wrapper.get('[data-delivery-summary-value="total-paid"]').text()).toBe('50.00');
        expect(wrapper.get('[data-delivery-summary-value="balance-remaining"]').text()).toBe('50.00');
        expect(wrapper.find('[data-delivery-summary-value="remaining-change"]').exists()).toBe(false);
        expect(wrapper.get('[data-delivery-action]').attributes('disabled')).toBeDefined();

        await wrapper.get('[data-delivery-payment]').setValue('25.00');

        expect(wrapper.emitted('update:paymentAmount')?.at(-1)).toEqual(['25']);
        await wrapper.setProps({ paymentAmount: '25' });
        expect(wrapper.get('[data-delivery-action]').attributes('disabled')).toBeUndefined();

        await wrapper.get('[data-delivery-action]').trigger('click');

        expect(wrapper.emitted('deliver')).toEqual([['25']]);
    });

    it('renders an exact payment without a balance or change column', async () => {
        const wrapper = mountPanel(
            {
                budgeted_net: '84.00',
                budgeted: '100.00',
                authorized: '100.00',
                completed: '100.00',
                advance_payment: '100.00',
                remaining_balance: '0.00',
            },
            true,
            false,
            '100.00',
        );

        expect(wrapper.find('[data-delivery-summary-value="balance-remaining"]').exists()).toBe(false);
        expect(wrapper.find('[data-delivery-summary-value="remaining-change"]').exists()).toBe(false);
        expect(wrapper.get('[data-delivery-action]').attributes('disabled')).toBeUndefined();

        await wrapper.get('[data-delivery-action]').trigger('click');

        expect(wrapper.emitted('deliver')).toEqual([['100.00']]);
    });

    it('renders remaining change for an overpayment', () => {
        const wrapper = mountPanel(
            {
                budgeted_net: '84.00',
                budgeted: '100.00',
                authorized: '100.00',
                completed: '100.00',
                advance_payment: '125.00',
                remaining_balance: '0.00',
            },
            true,
            false,
            '125.00',
        );

        expect(wrapper.get('[data-delivery-summary-value="remaining-change"]').text()).toBe('25.00');
        expect(wrapper.find('[data-delivery-summary-value="balance-remaining"]').exists()).toBe(false);
    });
});
