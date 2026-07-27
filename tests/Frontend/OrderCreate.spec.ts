import Create from '@/pages/Orders/Create.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const api = vi.hoisted(() => ({
    catalog: vi.fn(),
    create: vi.fn(),
    customers: vi.fn(),
    employees: vi.fn(),
}));

const router = vi.hoisted(() => ({
    visit: vi.fn(),
}));

const OrderApiError = vi.hoisted(
    () =>
        class OrderApiError extends Error {
            readonly kind = 'validation';
            readonly status: number;

            constructor(
                status: number,
                message: string,
                readonly validationErrors: Record<string, string[]> = {},
            ) {
                super(message);
                this.status = status;
            }
        },
);

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<title><slot /></title>',
    },
    router,
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) => key,
    }),
}));

vi.mock('@/composables/useOrderApi', () => ({
    OrderApiError,
    useOrderApi: () => api,
}));

const ButtonStub = {
    inheritAttrs: false,
    template: '<button v-bind="$attrs"><slot /></button>',
};

const InputStub = {
    inheritAttrs: false,
    props: ['id', 'modelValue'],
    emits: ['update:modelValue'],
    template: '<input v-bind="$attrs" :id="id" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
};

const LabelStub = {
    template: '<label v-bind="$attrs"><slot /></label>',
};

const passthroughStub = {
    template: '<div><slot /></div>',
};

function mountPage() {
    return mount(Create, {
        global: {
            stubs: {
                AppLayout: passthroughStub,
                Button: ButtonStub,
                Card: passthroughStub,
                Input: InputStub,
                Label: LabelStub,
            },
        },
    });
}

describe('Orders/Create', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.stubGlobal('route', (name: string, parameter?: string) => `/${name}/${parameter ?? ''}`);
        api.catalog.mockResolvedValue({
            item_types: [{ key: 'engine_block', label: 'Engine block' }],
            components_by_type: { engine_block: [{ key: 'shaft', label: 'Shaft' }] },
            services_by_type: {},
        });
        api.customers.mockResolvedValue([{ id: 1, full_name: 'Customer' }]);
        api.employees.mockResolvedValue([{ id: 2, full_name: 'Employee' }]);
    });

    it('allows only one create request while processing', async () => {
        let resolveCreate: (value: { uuid: string }) => void = () => undefined;
        api.create.mockReturnValueOnce(
            new Promise((resolve) => {
                resolveCreate = resolve;
            }),
        );
        const wrapper = mountPage();

        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await wrapper.get('form').trigger('submit');

        expect(api.create).toHaveBeenCalledTimes(1);

        resolveCreate({ uuid: 'order-uuid' });
        await flushPromises();

        expect(router.visit).toHaveBeenCalledWith('/web.orders.show/order-uuid');
    });

    it('associates validation messages with their invalid fields', async () => {
        api.create.mockRejectedValueOnce(
            new OrderApiError(422, 'Validation failed', {
                title: ['A title is required.'],
            }),
        );

        const wrapper = mountPage();

        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('#title').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#title').attributes('aria-describedby')).toBe('title-error');
        expect(wrapper.get('#title-error').text()).toContain('A title is required.');
    });
});
