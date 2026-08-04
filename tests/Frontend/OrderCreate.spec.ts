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
    flash: vi.fn(),
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

        expect(router.visit).toHaveBeenCalledWith(route('web.orders.index'), expect.objectContaining({ onSuccess: expect.any(Function) }));
        expect(router.visit).not.toHaveBeenCalledWith(route('web.orders.show', 'order-uuid'));
    });

    it('does not render an advance-payment control', async () => {
        const wrapper = mountPage();

        await flushPromises();

        expect(wrapper.find('#down_payment').exists()).toBe(false);
        expect(wrapper.find('[dusk="motor-down-payment"]').exists()).toBe(false);
    });

    it('omits motor_info.down_payment from the submitted payload', async () => {
        api.create.mockResolvedValueOnce({ uuid: 'order-uuid' });
        const wrapper = mountPage();

        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const payload = api.create.mock.calls[0][0] as { motor_info?: Record<string, unknown> };

        expect(payload.motor_info).not.toHaveProperty('down_payment');
    });

    it('uses the regular border and rounded treatment on description and notes', async () => {
        const wrapper = mountPage();

        await flushPromises();

        for (const field of ['#description', '#notes']) {
            expect(wrapper.get(field).classes()).toEqual(expect.arrayContaining(['border', 'border-input', 'rounded-md']));
        }
    });

    it('flashes after successful creation and navigates to the orders index', async () => {
        api.create.mockResolvedValueOnce({ uuid: 'order-uuid' });
        const wrapper = mountPage();

        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const visitOptions = router.visit.mock.calls[0][1] as { onSuccess?: () => void };

        expect(visitOptions.onSuccess).toEqual(expect.any(Function));
        visitOptions.onSuccess?.();

        expect(router.flash).toHaveBeenCalledWith('success', expect.any(String));
        expect(router.visit).toHaveBeenCalledWith(route('web.orders.index'), expect.objectContaining({ onSuccess: expect.any(Function) }));
        expect(router.visit).not.toHaveBeenCalledWith(route('web.orders.show', 'order-uuid'));
        expect(router.visit.mock.invocationCallOrder[0]).toBeLessThan(router.flash.mock.invocationCallOrder[0]);
    });

    it('preserves entered values and shows the validation error after failed creation', async () => {
        api.create.mockRejectedValueOnce(
            new OrderApiError(422, 'Validation failed', {
                title: ['A title is required.'],
            }),
        );
        const wrapper = mountPage();

        await flushPromises();
        await wrapper.get('#title').setValue('Entered title');
        await wrapper.get('#description').setValue('Entered description');
        await wrapper.get('#notes').setValue('Entered notes');
        await wrapper.get('#brand').setValue('Entered brand');
        await wrapper.get('#liters').setValue('2.0');
        await wrapper.get('#year').setValue('2020');
        await wrapper.get('#model').setValue('Entered model');
        await wrapper.get('#cylinder_count').setValue('4');
        await wrapper.get('#customer_id').setValue('1');
        await wrapper.get('#assigned_to').setValue('2');
        await wrapper.get('[dusk="order-item-component-0-shaft"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect((wrapper.get('#title').element as HTMLInputElement).value).toBe('Entered title');
        expect((wrapper.get('#description').element as HTMLTextAreaElement).value).toBe('Entered description');
        expect((wrapper.get('#notes').element as HTMLTextAreaElement).value).toBe('Entered notes');
        expect((wrapper.get('#brand').element as HTMLInputElement).value).toBe('Entered brand');
        expect((wrapper.get('#liters').element as HTMLInputElement).value).toBe('2.0');
        expect((wrapper.get('#year').element as HTMLInputElement).value).toBe('2020');
        expect((wrapper.get('#model').element as HTMLInputElement).value).toBe('Entered model');
        expect((wrapper.get('#cylinder_count').element as HTMLInputElement).value).toBe('4');
        expect((wrapper.get('#customer_id').element as HTMLSelectElement).value).toBe('1');
        expect((wrapper.get('#assigned_to').element as HTMLSelectElement).value).toBe('2');
        expect((wrapper.get('[dusk="order-item-component-0-shaft"]').element as HTMLInputElement).checked).toBe(true);
        expect(wrapper.get('[dusk="order-create-error"]').text()).toContain('A title is required.');
        expect(router.visit).not.toHaveBeenCalled();
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
