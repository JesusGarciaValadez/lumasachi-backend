import OrdersIndex from '@/pages/Orders/Index.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const api = vi.hoisted(() => ({
    index: vi.fn(),
}));

const page = vi.hoisted(() => ({
    props: {
        flash: {},
    },
    flash: {},
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<title><slot /></title>',
    },
    Link: {
        inheritAttrs: false,
        props: ['href'],
        template: '<a v-bind="$attrs" :href="href"><slot /></a>',
    },
    usePage: () => page,
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) => key,
        tm: () => ({}),
    }),
}));

vi.mock('@/composables/useOrderApi', () => ({
    OrderApiError: class OrderApiError extends Error {},
    useOrderApi: () => api,
}));

const passthroughStub = { template: '<div><slot /></div>' };

function route(name: string): string {
    return `/${name}`;
}

function mountPage() {
    return mount(OrdersIndex, {
        props: {
            can_create_order: false,
        },
        global: {
            mocks: { route },
            stubs: {
                AppLayout: passthroughStub,
                Button: passthroughStub,
                Card: passthroughStub,
            },
        },
    });
}

describe('Orders/Index', () => {
    beforeEach(() => {
        api.index.mockReset();
        api.index.mockResolvedValue([]);
        page.props.flash = {};
        page.flash = {};
        vi.stubGlobal('route', route);
    });

    it('renders the client-side success flash in an accessible stable status element', async () => {
        const message = 'Order created successfully.';
        page.flash = { success: message };

        const wrapper = mountPage();

        await flushPromises();

        const status = wrapper.get('[dusk="orders-flash"][role="status"]');

        expect(status.text()).toContain(message);
    });
});
