import OrderFinancialSummary from '@/components/orders/OrderFinancialSummary.vue';
import type { FinancialTotals } from '@/types/orders';
import { mount } from '@vue/test-utils';

const labels = {
    budgeted: 'Budgeted total',
    baseTotal: 'Base total',
    netTotal: 'Net total',
    authorized: 'Authorized total',
    completed: 'Completed total',
    advance_payment: 'Advance payment',
    remaining_balance: 'Remaining balance',
    payment_state: 'Payment state',
    zero_total: 'Zero total',
    partial_payment: 'Partial payment',
    paid_in_full: 'Paid in full',
    overpaid: 'Overpaid',
};

const baseFinancials: FinancialTotals = {
    budgeted: '1234.50',
    budgeted_base: '1,000.00',
    budgeted_net: '1,160.00',
    authorized: '1,160.00',
    completed: '100.00',
    advance_payment: '50.00',
    remaining_balance: '50.00',
};

function mountSummary(financials: FinancialTotals = baseFinancials) {
    return mount(OrderFinancialSummary, {
        props: {
            financials,
            labels,
            title: 'Financial summary',
        },
    });
}

describe('OrderFinancialSummary', () => {
    it.each([
        ['partial payment', { completed: '100.00', advance_payment: '50.00', remaining_balance: '50.00' }, 'Partial payment'],
        ['exact payment', { completed: '100.00', advance_payment: '100.00', remaining_balance: '0.00' }, 'Paid in full'],
        ['overpayment', { completed: '100.00', advance_payment: '125.00', remaining_balance: '0.00' }, 'Overpaid'],
        ['zero total', { completed: '0.00', advance_payment: '0.00', remaining_balance: '0.00' }, 'Zero total'],
    ])('distinguishes %s payment state', (_name, values, expectedState) => {
        const wrapper = mountSummary({ ...baseFinancials, ...values });

        expect(wrapper.find('[data-payment-state]').text()).toBe(expectedState);
        expect(wrapper.find('[data-financial-value="completed"]').text()).toBe(
            Number(values.completed).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
        );
        expect(wrapper.find('[data-financial-value="remaining-balance"]').text()).toBe(
            Number(values.remaining_balance).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
        );
    });
});
