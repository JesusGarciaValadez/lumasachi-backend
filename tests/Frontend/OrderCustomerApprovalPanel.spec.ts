import OrderCustomerApprovalPanel from '@/components/orders/OrderCustomerApprovalPanel.vue';
import type { OrderServicePayload } from '@/types/orders';
import { mount } from '@vue/test-utils';

const labels = {
    title: 'Customer approval',
    help: 'Select services',
    service: 'Service',
    measurement: 'Measurement',
    basePrice: 'Base price',
    netPrice: 'Net price',
    budgeted: 'Budgeted',
    authorized: 'Authorized',
    select: 'Select',
    preview: 'Preview total',
    budgetedBaseTotal: 'Budgeted base total',
    budgetedNetTotal: 'Budgeted net total',
    authorizedBaseTotal: 'Authorized base total',
    authorizedNetTotal: 'Authorized net total',
    selected: (count: number) => `${count} selected`,
    advancePayment: 'Advance payment',
    submit: 'Approve selected',
    empty: 'No budgeted services',
};

const services: OrderServicePayload[] = [
    {
        id: 1,
        uuid: 'service-1',
        order_item_id: 10,
        service_key: 'wash_block',
        service_name: 'Wash block',
        measurement: null,
        is_budgeted: true,
        is_authorized: false,
        is_completed: false,
        base_price: '600.00',
        net_price: '696.00',
    },
    {
        id: 2,
        uuid: 'service-2',
        order_item_id: 10,
        service_key: 'deck_block',
        service_name: 'Deck block',
        measurement: '20',
        is_budgeted: true,
        is_authorized: false,
        is_completed: false,
        base_price: '800.00',
        net_price: '928.00',
    },
    {
        id: 3,
        uuid: 'service-3',
        order_item_id: 11,
        service_key: 'not_budgeted',
        service_name: 'Not budgeted',
        measurement: null,
        is_budgeted: false,
        is_authorized: false,
        is_completed: false,
        base_price: '300.00',
        net_price: '348.00',
    },
];

function mountPanel(props: Record<string, unknown> = {}) {
    return mount(OrderCustomerApprovalPanel, {
        props: {
            services,
            itemLabels: { 10: 'Engine block', 11: 'Cylinder head' },
            busy: false,
            errors: {},
            labels,
            ...props,
        },
    });
}

describe('OrderCustomerApprovalPanel', () => {
    it('renders only budgeted services and shows persisted authorization state read-only', () => {
        const wrapper = mountPanel();

        expect(wrapper.text()).toContain('Wash block');
        expect(wrapper.text()).toContain('Deck block');
        expect(wrapper.text()).not.toContain('Not budgeted');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.find('[data-approval-budgeted-base-total]').text()).toBe('1,400.00');
        expect(wrapper.find('[data-approval-budgeted-net-total]').text()).toBe('1,624.00');
        expect(wrapper.text()).toContain('—');
    });

    it('updates authorized preview totals and emits selected IDs with advance payment', async () => {
        const wrapper = mountPanel();
        const checkboxes = wrapper.findAll('input[type="checkbox"]');

        await checkboxes[1].setValue(true);
        await wrapper.get('#approval-down-payment').setValue('250.00');

        expect(wrapper.find('[data-approval-authorized-base-total]').text()).toBe('800.00');
        expect(wrapper.find('[data-approval-authorized-net-total]').text()).toBe('928.00');

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('submit')?.[0]?.[0]).toMatchObject({
            selectedCount: 1,
            budgetedBaseTotal: '1400.00',
            budgetedNetTotal: '1624.00',
            authorizedBaseTotal: '800.00',
            authorizedNetTotal: '928.00',
            downPayment: '250.00',
            payload: {
                authorized_service_ids: [2],
                down_payment: '250.00',
            },
        });
    });

    it('keeps selected services after validation errors', async () => {
        const wrapper = mountPanel({ errors: { down_payment: ['The down payment cannot be negative.'] } });
        const checkbox = wrapper.find('input[type="checkbox"]');

        await checkbox.setValue(true);

        expect(wrapper.get('[role="alert"]').text()).toContain('The down payment cannot be negative.');
        expect((checkbox.element as HTMLInputElement).checked).toBe(true);

        await wrapper.setProps({ errors: { down_payment: ['The down payment cannot be negative.'] } });
        expect((checkbox.element as HTMLInputElement).checked).toBe(true);
    });

    it('disables selection and submission while processing', () => {
        const wrapper = mountPanel({ busy: true });

        expect(wrapper.findAll('input[type="checkbox"]')[0].attributes('disabled')).toBeDefined();
        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });
});
