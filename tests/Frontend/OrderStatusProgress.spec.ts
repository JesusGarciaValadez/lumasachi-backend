import OrderStatusIndicators from '@/components/orders/OrderStatusIndicators.vue';
import OrderStatusProgress from '@/components/orders/OrderStatusProgress.vue';
import { ORDER_STATUS_SEQUENCE } from '@/types/orders';
import { mount } from '@vue/test-utils';

const labels = ORDER_STATUS_SEQUENCE.map((value) => ({ value, label: value }));

describe('order status presentation', () => {
    it('renders only lifecycle steps and marks the active step accessibly', () => {
        const wrapper = mount(OrderStatusProgress, {
            props: {
                status: 'Ready for Work',
                statuses: labels,
                title: 'Order progress',
            },
        });

        expect(wrapper.findAll('li')).toHaveLength(7);
        expect(wrapper.find('[aria-current="step"]').text()).toContain('Ready for Work');
        expect(wrapper.findAll('[aria-hidden="true"]')).toHaveLength(6);
    });

    it('keeps priority, payment, disposition, and refund indicators distinct', () => {
        const wrapper = mount(OrderStatusIndicators, {
            props: {
                labels: {
                    lifecycle: 'Lifecycle',
                    priority: 'Priority',
                    payment: 'Payment',
                    unpaid: 'Unpaid',
                    disposition: 'Disposition',
                    refund: 'Refund',
                },
                priority: 'Urgent',
                priorityLabel: 'Urgent',
                lifecycleStatus: 'Ready for Work',
                lifecycleStatusLabel: 'Ready for Work',
                paymentStatus: 'Paid',
                paymentStatusLabel: 'Paid',
                dispositionStatus: 'Returned',
                dispositionStatusLabel: 'Returned',
                refundStatuses: ['Requested', 'Processed'],
                refundStatusLabels: { Requested: 'Requested', Processed: 'Processed' },
            },
        });

        expect(wrapper.find('[data-status-indicator="priority"]').text()).toContain('Priority: Urgent');
        expect(wrapper.find('[data-status-indicator="payment"]').text()).toContain('Payment: Paid');
        expect(wrapper.find('[data-status-indicator="disposition"]').text()).toContain('Disposition: Returned');
        expect(wrapper.findAll('[data-status-indicator="refund"]')).toHaveLength(2);
    });

    it('renders the localized unpaid label for partially paid orders while preserving the payment status class', () => {
        const wrapper = mount(OrderStatusIndicators, {
            props: {
                labels: {
                    lifecycle: 'Lifecycle',
                    priority: 'Priority',
                    payment: 'Payment',
                    unpaid: 'Unpaid',
                    disposition: 'Disposition',
                    refund: 'Refund',
                },
                priority: 'Normal',
                paymentStatus: 'Partially Paid',
                paymentStatusLabel: 'Partial payment',
            },
        });

        expect(wrapper.find('[data-status-indicator="payment"]').text()).toContain('Payment: Unpaid');
        expect(wrapper.find('[data-status-indicator="payment"]').text()).not.toContain('Partial payment');
        expect(wrapper.find('[data-status-indicator="payment"]').classes()).toContain('bg-amber-100');
    });
});
