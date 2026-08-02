import AppSidebar from '@/components/AppSidebar.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({
    props: {
        can_create_order: true,
    },
    url: '/orders/order-uuid',
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        inheritAttrs: false,
        template: '<a v-bind="$attrs"><slot /></a>',
    },
    usePage: () => page,
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) =>
            ({
                'common.dashboard': 'Dashboard',
                'orders.create': 'Create order',
                'orders.orders': 'Orders',
            })[key] ?? key,
    }),
}));

const passthroughStub = {
    template: '<div><slot /></div>',
};

const navMainStub = {
    props: ['items'],
    template:
        '<nav data-main-nav><a v-for="item in items" :key="item.title" :data-active="item.isActive" :href="item.href">{{ item.title }}</a></nav>',
};

function mountSidebar() {
    return mount(AppSidebar, {
        global: {
            mocks: {
                route: (name: string) =>
                    ({
                        dashboard: '/dashboard',
                        'web.orders.index': '/orders',
                    })[name],
            },
            stubs: {
                AppLogo: passthroughStub,
                NavFooter: passthroughStub,
                NavMain: navMainStub,
                NavUser: passthroughStub,
                Sidebar: passthroughStub,
                SidebarContent: passthroughStub,
                SidebarFooter: passthroughStub,
                SidebarHeader: passthroughStub,
                SidebarMenu: passthroughStub,
                SidebarMenuButton: passthroughStub,
                SidebarMenuItem: passthroughStub,
            },
        },
    });
}

describe('AppSidebar', () => {
    beforeEach(() => {
        page.props.can_create_order = true;
        page.url = '/orders/order-uuid';
        vi.stubGlobal(
            'route',
            (name: string) =>
                ({
                    dashboard: '/dashboard',
                    'web.orders.index': '/orders',
                })[name],
        );
    });

    it('uses named routes and keeps the orders section active for an order detail URL', () => {
        const wrapper = mountSidebar();
        const links = wrapper.findAll('[data-main-nav] a');

        expect(links.map((link) => link.attributes('href'))).toEqual(['/dashboard', '/orders']);
        expect(links[1].attributes('data-active')).toBe('true');
    });

    it('does not render resource creation navigation even when capabilities are granted', () => {
        page.props.can_create_order = true;

        const wrapper = mountSidebar();

        expect(wrapper.findAll('[data-main-nav] a')).toHaveLength(2);
        expect(wrapper.text()).not.toContain('Create order');
    });
});
