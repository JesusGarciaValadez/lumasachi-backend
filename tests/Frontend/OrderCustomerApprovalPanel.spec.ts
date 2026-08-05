import OrderCustomerApprovalPanel from '@/components/orders/OrderCustomerApprovalPanel.vue';
import type { OrderServicePayload } from '@/types/orders';
import { mount } from '@vue/test-utils';

const labels = {
    title: 'Customer approval',
    help: 'Select services',
    service: 'Service',
    measurement: 'Measurement',
    netPrice: 'Net price',
    budgeted: 'Budgeted',
    authorized: 'Authorized',
    select: 'Select',
    preview: 'Preview total',
    budgetedTotal: 'Budgeted total',
    authorizedTotal: 'Authorized total',
    selected: (count: number) => `${count} selected`,
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
        expect(wrapper.text()).toContain('Net price');
        expect(wrapper.text()).not.toContain('Base price');
        expect(wrapper.text()).not.toContain('600.00');
        expect(wrapper.text()).not.toContain('800.00');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.text()).toContain('Budgeted total');
        expect(wrapper.text()).not.toContain('Budgeted base total');
        expect(wrapper.text()).not.toContain('Budgeted net total');
        expect(wrapper.findAll('[data-approval-budgeted-total]')).toHaveLength(1);
        expect(wrapper.find('[data-approval-budgeted-total]').text()).toBe('1,624.00');
        expect(wrapper.text()).toContain('—');
        expect(wrapper.find('[dusk="order-approval-down-payment"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Advance payment');
    });

    it('updates authorized preview totals and emits selected IDs without advance payment', async () => {
        const wrapper = mountPanel();
        const checkboxes = wrapper.findAll('input[type="checkbox"]');

        await checkboxes[1].setValue(true);

        expect(wrapper.text()).toContain('Authorized total');
        expect(wrapper.text()).not.toContain('Authorized base total');
        expect(wrapper.text()).not.toContain('Authorized net total');
        expect(wrapper.findAll('[data-approval-authorized-total]')).toHaveLength(1);
        expect(wrapper.find('[data-approval-authorized-total]').text()).toBe('928.00');
        expect(wrapper.text()).not.toContain('800.00');

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('submit')?.[0]?.[0]).toMatchObject({
            selectedCount: 1,
            budgetedTotal: '1624.00',
            authorizedTotal: '928.00',
            payload: {
                authorized_service_ids: [2],
            },
        });

        const submission = wrapper.emitted('submit')?.[0]?.[0] as Record<string, unknown>;

        expect(submission).not.toHaveProperty('budgetedBaseTotal');
        expect(submission).not.toHaveProperty('budgetedNetTotal');
        expect(submission).not.toHaveProperty('authorizedBaseTotal');
        expect(submission).not.toHaveProperty('authorizedNetTotal');
        expect(submission).not.toHaveProperty('downPayment');
        expect(submission.payload).not.toHaveProperty('down_payment');
    });

    it('keeps selected services when the panel becomes busy', async () => {
        const wrapper = mountPanel();
        const checkbox = wrapper.find('input[type="checkbox"]');

        await checkbox.setValue(true);
        expect((checkbox.element as HTMLInputElement).checked).toBe(true);

        await wrapper.setProps({ busy: true });

        expect((checkbox.element as HTMLInputElement).checked).toBe(true);
    });

    it('disables selection and submission while processing', () => {
        const wrapper = mountPanel({ busy: true });

        expect(wrapper.findAll('input[type="checkbox"]')[0].attributes('disabled')).toBeDefined();
        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });
});
