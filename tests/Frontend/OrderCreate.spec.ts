import Create from '@/pages/Orders/Create.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const api = vi.hoisted(() => ({
    catalog: vi.fn(),
    companies: vi.fn(),
    create: vi.fn(),
    customers: vi.fn(),
    employees: vi.fn(),
}));

const router = vi.hoisted(() => ({
    flash: vi.fn(),
    visit: vi.fn(),
}));

const page = vi.hoisted(() => ({
    props: {
        auth: {
            user: {
                role: 'Administrator',
                company_id: 10,
            },
        },
    },
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
    usePage: () => page,
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

type ActorRole = 'Super Administrator' | 'Administrator' | 'Employee';

function setActor(role: ActorRole, company_id: number | null): void {
    Object.assign(page.props.auth.user, { role, company_id });
}

function deferred<T>() {
    let resolve!: (value: T | PromiseLike<T>) => void;
    let reject!: (reason?: unknown) => void;

    const promise = new Promise<T>((promiseResolve, promiseReject) => {
        resolve = promiseResolve;
        reject = promiseReject;
    });

    return { promise, resolve, reject };
}

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
        setActor('Administrator', 10);
        vi.stubGlobal('route', (name: string, parameter?: string) => `/${name}/${parameter ?? ''}`);
        api.catalog.mockResolvedValue({
            item_types: [{ key: 'engine_block', label: 'Engine block' }],
            components_by_type: { engine_block: [{ key: 'shaft', label: 'Shaft' }] },
            services_by_type: {},
        });
        api.companies.mockResolvedValue([
            { id: 10, uuid: 'company-10', name: 'Company Ten' },
            { id: 20, uuid: 'company-20', name: 'Company Twenty' },
        ]);
        api.customers.mockResolvedValue([{ id: 1, full_name: 'Customer' }]);
        api.employees.mockResolvedValue([{ id: 2, full_name: 'Employee' }]);
    });

    it('renders a required Super Administrator company selector first and defers participant requests', async () => {
        setActor('Super Administrator', null);

        const wrapper = mountPage();

        await flushPromises();

        const firstSelect = wrapper.get('form').findAll('select')[0];

        expect(firstSelect.attributes('id')).toBe('company_id');
        expect(firstSelect.attributes('required')).toBeDefined();
        expect(firstSelect.findAll('option').map((option) => option.text())).toEqual(expect.arrayContaining(['Company Ten', 'Company Twenty']));
        expect(api.companies).toHaveBeenCalledTimes(1);
        expect(api.customers).not.toHaveBeenCalled();
        expect(api.employees).not.toHaveBeenCalled();
        expect(wrapper.get('#customer_id').findAll('option')).toHaveLength(1);
        expect(wrapper.get('#assigned_to').findAll('option')).toHaveLength(1);
    });

    it('loads the selected company participants and clears selections when the company changes', async () => {
        setActor('Super Administrator', null);
        api.customers.mockImplementation((companyId?: number) =>
            Promise.resolve(
                companyId === 10 ? [{ id: 101, full_name: 'Company Ten Customer' }] : [{ id: 201, full_name: 'Company Twenty Customer' }],
            ),
        );
        api.employees.mockImplementation((companyId?: number) =>
            Promise.resolve(
                companyId === 10 ? [{ id: 102, full_name: 'Company Ten Employee' }] : [{ id: 202, full_name: 'Company Twenty Employee' }],
            ),
        );

        const wrapper = mountPage();

        await flushPromises();
        await wrapper.get('#company_id').setValue('10');
        await flushPromises();

        expect(api.customers).toHaveBeenCalledWith(10);
        expect(api.employees).toHaveBeenCalledWith(10);
        expect(wrapper.get('#customer_id').text()).toContain('Company Ten Customer');
        expect(wrapper.get('#assigned_to').text()).toContain('Company Ten Employee');

        await wrapper.get('#customer_id').setValue('101');
        await wrapper.get('#assigned_to').setValue('102');
        await wrapper.get('#company_id').setValue('20');

        expect((wrapper.get('#customer_id').element as HTMLSelectElement).value).toBe('0');
        expect((wrapper.get('#assigned_to').element as HTMLSelectElement).value).toBe('0');
        expect(api.customers).toHaveBeenCalledWith(20);
        expect(api.employees).toHaveBeenCalledWith(20);
    });

    it('does not allow stale participant responses to overwrite the newest company selection', async () => {
        setActor('Super Administrator', null);
        const firstCustomers = deferred<Array<{ id: number; full_name: string }>>();
        const firstEmployees = deferred<Array<{ id: number; full_name: string }>>();
        const secondCustomers = deferred<Array<{ id: number; full_name: string }>>();
        const secondEmployees = deferred<Array<{ id: number; full_name: string }>>();

        api.customers.mockImplementation((companyId?: number) => (companyId === 10 ? firstCustomers.promise : secondCustomers.promise));
        api.employees.mockImplementation((companyId?: number) => (companyId === 10 ? firstEmployees.promise : secondEmployees.promise));

        const wrapper = mountPage();

        await flushPromises();
        await wrapper.get('#company_id').setValue('10');
        await flushPromises();
        await wrapper.get('#company_id').setValue('20');
        await flushPromises();

        secondCustomers.resolve([{ id: 201, full_name: 'Company Twenty Customer' }]);
        secondEmployees.resolve([{ id: 202, full_name: 'Company Twenty Employee' }]);
        await flushPromises();

        expect(wrapper.get('#customer_id').text()).toContain('Company Twenty Customer');
        expect(wrapper.get('#assigned_to').text()).toContain('Company Twenty Employee');

        firstCustomers.resolve([{ id: 101, full_name: 'Company Ten Customer' }]);
        firstEmployees.resolve([{ id: 102, full_name: 'Company Ten Employee' }]);
        await flushPromises();

        expect(wrapper.get('#customer_id').text()).not.toContain('Company Ten Customer');
        expect(wrapper.get('#assigned_to').text()).not.toContain('Company Ten Employee');
    });

    it.each(['Administrator', 'Employee'] as const)('%s loads actor-scoped participants on mount without a company selector', async (role) => {
        setActor(role, 10);

        const wrapper = mountPage();

        await flushPromises();

        expect(wrapper.find('#company_id').exists()).toBe(false);
        expect(api.companies).not.toHaveBeenCalled();
        expect(api.customers).toHaveBeenCalledWith();
        expect(api.employees).toHaveBeenCalledWith();
        expect(wrapper.get('#customer_id').text()).toContain('Customer');
        expect(wrapper.get('#assigned_to').text()).toContain('Employee');
    });

    it('keeps participant fields empty and reports a scoped lookup error', async () => {
        setActor('Super Administrator', null);
        api.customers.mockRejectedValueOnce(new OrderApiError(503, 'Customer lookup failed.'));

        const wrapper = mountPage();

        await flushPromises();
        await wrapper.get('#company_id').setValue('10');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('Customer lookup failed.');
        expect(wrapper.get('#customer_id').findAll('option')).toHaveLength(1);
        expect(wrapper.get('#assigned_to').findAll('option')).toHaveLength(1);
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
