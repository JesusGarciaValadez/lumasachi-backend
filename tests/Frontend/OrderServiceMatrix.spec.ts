import OrderServiceMatrix from '@/components/orders/OrderServiceMatrix.vue';
import { mount } from '@vue/test-utils';

const labels = {
    service: 'Service',
    measurement: 'Measurement',
    base_price: 'Base price',
    net_price: 'Net price',
    budgeted: 'Budgeted',
    authorized: 'Authorized',
    completed: 'Completed',
    empty: 'No services',
};

const services = [
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
        base_price: '100.00',
        net_price: '116.00',
    },
    {
        id: 2,
        uuid: 'service-2',
        order_item_id: 10,
        service_key: 'inspect_block',
        service_name: 'Inspect block',
        measurement: null,
        is_budgeted: false,
        is_authorized: false,
        is_completed: false,
        base_price: '50.00',
        net_price: '58.00',
    },
];

describe('OrderServiceMatrix', () => {
    it('allows only budgeted incomplete services during approval', async () => {
        const wrapper = mount(OrderServiceMatrix, {
            props: {
                services,
                itemLabels: { 10: 'Engine block' },
                selectedIds: [],
                mode: 'approval',
                title: 'Services',
                labels,
            },
        });

        const checkboxes = wrapper.findAll('input[type="checkbox"]');

        expect(checkboxes).toHaveLength(2);
        expect((checkboxes[0].element as HTMLInputElement).disabled).toBe(false);
        expect((checkboxes[1].element as HTMLInputElement).disabled).toBe(true);

        await checkboxes[0].trigger('change');

        expect(wrapper.emitted('update:selectedIds')).toEqual([[[1]]]);
    });

    it('renders no mutation controls in readonly mode', () => {
        const wrapper = mount(OrderServiceMatrix, {
            props: {
                services,
                itemLabels: { 10: 'Engine block' },
                selectedIds: [],
                mode: 'readonly',
                title: 'Services',
                labels,
            },
        });

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0);
    });
});
