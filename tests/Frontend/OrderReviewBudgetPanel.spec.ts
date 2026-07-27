import OrderReviewBudgetPanel from '@/components/orders/OrderReviewBudgetPanel.vue';
import type { CatalogPayload, OrderItem } from '@/types/orders';
import { mount } from '@vue/test-utils';

const labels = {
    title: 'Review and budget',
    help: 'Select services',
    submit: 'Submit budget',
    service: 'Service',
    measurement: 'Measurement',
    budgeted: 'PPTO',
    basePrice: 'Base price',
    netPrice: 'Net price',
    notes: 'Notes',
    preview: 'Preview total',
    baseTotal: 'Base total',
    netTotal: 'Net total',
    selected: (count: number) => `${count} selected`,
    empty: 'No services',
};

const items: OrderItem[] = [
    { id: 10, uuid: 'item-10', item_type: 'engine_block', is_received: true, components: [] },
    { id: 11, uuid: 'item-11', item_type: 'cylinder_head', is_received: true, components: [] },
    { id: 12, uuid: 'item-12', item_type: 'crankshaft', is_received: false, components: [] },
];

const catalog: CatalogPayload = {
    item_types: [
        { key: 'engine_block', label: 'Engine block' },
        { key: 'cylinder_head', label: 'Cylinder head' },
        { key: 'crankshaft', label: 'Crankshaft' },
    ],
    components_by_type: {},
    services_by_type: {
        engine_block: [
            {
                service_key: 'wash_block',
                service_name: 'Wash block',
                base_price: '600.00',
                net_price: '696.00',
                requires_measurement: false,
                display_order: 1,
                item_type: 'engine_block',
            },
            {
                service_key: 'deck_block',
                service_name: 'Deck block',
                base_price: '800.00',
                net_price: '928.00',
                requires_measurement: true,
                display_order: 2,
                item_type: 'engine_block',
            },
        ],
        cylinder_head: [
            {
                service_key: 'wash_head',
                service_name: 'Wash head',
                base_price: '300.00',
                net_price: '348.00',
                requires_measurement: false,
                display_order: 1,
                item_type: 'cylinder_head',
            },
        ],
    },
};

function mountPanel(props: Record<string, unknown> = {}) {
    return mount(OrderReviewBudgetPanel, {
        props: {
            items,
            catalog,
            loading: false,
            busy: false,
            errors: {},
            labels,
            ...props,
        },
    });
}

describe('OrderReviewBudgetPanel', () => {
    it('groups services by received item and excludes unreceived items', () => {
        const wrapper = mountPanel();

        expect(wrapper.text()).toContain('Engine block');
        expect(wrapper.text()).toContain('Cylinder head');
        expect(wrapper.text()).not.toContain('Crankshaft');
        expect(wrapper.findAll('tbody tr')).toHaveLength(3);
    });

    it('shows measurement only for catalog services that require it', () => {
        const wrapper = mountPanel();

        expect(wrapper.findAll('[data-review-measurement]')).toHaveLength(1);
        expect((wrapper.find('[data-review-measurement]').element as HTMLInputElement).required).toBe(true);
    });

    it('calculates preview totals from selected services only', async () => {
        const wrapper = mountPanel();

        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);

        expect(wrapper.find('[data-review-base-total]').text()).toBe('600.00');
        expect(wrapper.find('[data-review-net-total]').text()).toBe('696.00');
    });

    it('keeps selected rows and maps validation errors to selected payload indexes', async () => {
        const wrapper = mountPanel({ errors: { 'services.0.measurement': ['A measurement is required.'] } });
        const checkboxes = wrapper.findAll('input[type="checkbox"]');

        await checkboxes[1].setValue(true);

        expect(wrapper.find('[data-review-measurement]').attributes('aria-invalid')).toBe('true');
        expect(wrapper.text()).toContain('A measurement is required.');

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('submit')?.[0]?.[0]).toMatchObject({
            selectedCount: 1,
            payload: {
                services: [
                    {
                        order_item_id: 10,
                        service_key: 'deck_block',
                        measurement: null,
                        notes: null,
                    },
                ],
            },
        });

        await wrapper.setProps({ errors: { 'services.0.measurement': ['A measurement is required.'] } });
        expect((checkboxes[1].element as HTMLInputElement).checked).toBe(true);
    });
});
