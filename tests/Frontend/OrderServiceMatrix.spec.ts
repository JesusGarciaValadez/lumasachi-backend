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
    yes: 'Yes',
    no: 'No',
    completed_total: 'Completed total',
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
                busy: false,
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
                busy: false,
                mode: 'readonly',
                title: 'Services',
                labels,
            },
        });

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0);
    });

    it('shows budgeted services and disables unauthorized or completed completion rows', async () => {
        const completionServices = [
            { ...services[0], is_authorized: true, is_completed: false },
            { ...services[0], id: 2, service_name: 'Unauthorized wash block', is_authorized: false, is_completed: false },
            { ...services[0], id: 3, service_name: 'Completed wash block', is_authorized: true, is_completed: true },
        ];
        const wrapper = mount(OrderServiceMatrix, {
            props: {
                services: completionServices,
                itemLabels: { 10: 'Engine block' },
                selectedIds: [],
                busy: false,
                mode: 'completion',
                title: 'Work completion',
                labels,
            },
        });

        const checkboxes = wrapper.findAll('input[type="checkbox"]');

        expect(wrapper.findAll('tbody tr')).toHaveLength(3);
        expect(checkboxes).toHaveLength(3);
        expect((checkboxes[0].element as HTMLInputElement).disabled).toBe(false);
        expect((checkboxes[1].element as HTMLInputElement).disabled).toBe(true);
        expect((checkboxes[2].element as HTMLInputElement).disabled).toBe(true);
        expect(wrapper.find('[data-completed-total]').text()).toBe('116.00');
        expect(wrapper.findAll('.bg-emerald-100').length).toBeGreaterThan(0);

        await checkboxes[0].trigger('change');
        expect(wrapper.emitted('update:selectedIds')).toEqual([[[1]]]);
    });

    it('disables completion controls while processing', () => {
        const wrapper = mount(OrderServiceMatrix, {
            props: {
                services,
                itemLabels: { 10: 'Engine block' },
                selectedIds: [],
                busy: true,
                mode: 'completion',
                title: 'Work completion',
                labels,
            },
        });

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(1);
        expect(wrapper.find('input[type="checkbox"]').attributes('disabled')).toBeDefined();
    });
});
