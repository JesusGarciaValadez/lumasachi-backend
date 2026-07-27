import { normalizePublicOrder } from '@/types/orders';

describe('public order normalization', () => {
    it('unwraps nested public collections and keeps empty collections stable', () => {
        const order = normalizePublicOrder({
            data: {
                uuid: 'order-uuid',
                title: 'Engine service',
                description: 'Public description',
                status: 'Awaiting Review',
                priority: 'Normal',
                motor_info: { data: { brand: 'Brand' } },
                items: {
                    data: [
                        {
                            item_type: 'engine_block',
                            is_received: true,
                            components: { data: [{ component_name: 'Head', is_received: true }] },
                        },
                    ],
                },
                services: null,
                history: { data: [] },
                attachments: undefined,
            },
        });

        expect(order.motor_info?.brand).toBe('Brand');
        expect(order.items[0].components).toEqual([{ component_name: 'Head', is_received: true }]);
        expect(order.services).toEqual([]);
        expect(order.history).toEqual([]);
        expect(order.attachments).toEqual([]);
    });
});
