import AppSidebar from '@/components/AppSidebar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import Dashboard from '@/pages/Dashboard.vue';
import { flushPromises, mount, shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({
    props: {
        can_create_order: true,
        can_create_user: false,
        can_view_sidebar: true,
        can_view_users: false,
        is_customer: false,
    },
    url: '/dashboard',
}));

const visibleUsers = [
    {
        uuid: 'user-1',
        first_name: 'Ana',
        last_name: 'Admin',
        role: 'Administrator',
        type: 'Employee',
        is_active: true,
    },
    {
        uuid: 'user-2',
        first_name: 'Luis',
        last_name: 'Employee',
        role: 'Employee',
        type: 'Employee',
        is_active: true,
    },
];

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<title>{{ title }}</title>',
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
        t: (key: string) =>
            ({
                'common.dashboard': 'Dashboard',
                'common.empty': 'Nothing here',
                'common.recent_orders': 'Recent orders',
                'common.view': 'View',
                'common.view_more': 'View more',
                'orders.create': 'Create order',
                'orders.created_at': 'Created at',
                'orders.lifecycle_status': 'Lifecycle status',
                'orders.orders': 'Orders',
                'orders.payment_status': 'Payment status',
                'orders.priority': 'Priority',
                'orders.refund_status': 'Refund status',
                'users.create': 'Create user',
                'users.recent': 'Recent users',
                'users.users': 'Users',
            })[key] ?? key,
        tm: () => ({}),
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

function route(name: string, parameter?: string): string {
    return (
        {
            dashboard: '/dashboard',
            'user.create': '/user/create',
            'users.index': '/users',
            'user.show': `/user/${parameter ?? ''}`,
            'web.orders.create': '/orders/create',
            'web.orders.index': '/orders',
        }[name] ?? `/${name}`
    );
}

function mountSidebar() {
    return mount(AppSidebar, {
        global: {
            mocks: {
                route,
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

function mountDashboard(props: Record<string, unknown>) {
    return mount(Dashboard, {
        props,
        global: {
            mocks: {
                route,
            },
            stubs: {
                AppLayout: passthroughStub,
                Card: passthroughStub,
                OrderStatusIndicators: passthroughStub,
                PlaceholderPattern: passthroughStub,
            },
        },
    });
}

describe('user administration navigation and dashboard contract', () => {
    beforeEach(() => {
        page.props.can_create_order = true;
        page.props.can_create_user = false;
        page.props.can_view_sidebar = true;
        page.props.can_view_users = false;
        page.props.is_customer = false;
        page.url = '/dashboard';

        vi.stubGlobal('route', route);
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                json: vi.fn().mockResolvedValue({ data: [] }),
            }),
        );
    });

    it('renders Users navigation only when the server grants view capability', () => {
        page.props.can_view_users = true;

        const wrapper = mountSidebar();
        const usersLink = wrapper.findAll('[data-main-nav] a').find((link) => link.text() === 'Users');

        expect(usersLink?.attributes('href')).toBe('/users');
    });

    it('does not render resource creation navigation even when the server grants create capabilities', () => {
        page.props.can_view_users = true;
        page.props.can_create_user = true;
        page.props.can_create_order = true;

        const wrapper = mountSidebar();

        expect(wrapper.findAll('[data-main-nav] a').map((link) => link.text())).toEqual(['Dashboard', 'Orders', 'Users']);
        expect(wrapper.text()).not.toContain('Create order');
        expect(wrapper.text()).not.toContain('Create user');
    });

    it('does not render a Users entry for an unauthorized actor', () => {
        const wrapper = mountSidebar();

        expect(wrapper.findAll('[data-main-nav] a').map((link) => link.text())).not.toContain('Users');
    });

    it('uses the sidebar layout for users who can access the lateral panel', () => {
        page.props.can_view_sidebar = true;

        const wrapper = shallowMount(AppLayout, {
            props: { breadcrumbs: [] },
        });

        expect(wrapper.findComponent(AppSidebarLayout).exists()).toBe(true);
        expect(wrapper.findComponent(AppHeaderLayout).exists()).toBe(false);
    });

    it('uses the header layout for customers without rendering the lateral panel trigger', () => {
        page.props.can_view_sidebar = false;

        const wrapper = shallowMount(AppLayout, {
            props: { breadcrumbs: [] },
        });

        expect(wrapper.findComponent(AppSidebarLayout).exists()).toBe(false);
        expect(wrapper.findComponent(AppHeaderLayout).exists()).toBe(true);
    });

    it('renders the dashboard user card with the server-provided visibility and user props', async () => {
        const wrapper = mountDashboard({
            can_create_user: true,
            can_view_users: true,
            recent_users: [
                ...visibleUsers,
                { uuid: 'user-3', first_name: 'Marta', last_name: 'Third', role: 'Employee', type: 'Employee', is_active: true },
                { uuid: 'user-4', first_name: 'Noah', last_name: 'Fourth', role: 'Employee', type: 'Employee', is_active: true },
                { uuid: 'user-5', first_name: 'Sara', last_name: 'Fifth', role: 'Employee', type: 'Employee', is_active: true },
                { uuid: 'user-6', first_name: 'Omar', last_name: 'Sixth', role: 'Employee', type: 'Employee', is_active: true },
            ],
        });

        await flushPromises();

        const userCard = wrapper.find('[data-user-card]');

        expect(userCard.exists()).toBe(true);
        expect(wrapper.find('[data-dashboard-summary]').exists()).toBe(true);
        expect(userCard.attributes('data-can-create-user')).toBe('true');
        expect(userCard.find('[data-user-card-link]').attributes('href')).toBe('/users');
        expect(wrapper.findAll('[data-user-card-user]')).toHaveLength(5);
        expect(wrapper.find('[data-user-card-user="user-1"]').attributes('href')).toBe('/user/user-1');
        expect(userCard.find('[data-user-card-users]').classes()).not.toContain('overflow-y-auto');
        expect(Array.from(wrapper.find('[data-dashboard-summary]').element.children).indexOf(userCard.element)).toBe(2);
        expect(userCard.text()).toContain('Ana Admin');
        expect(userCard.text()).toContain('Luis Employee');
    });

    it('hides the dashboard user card when the server denies user visibility', async () => {
        const wrapper = mountDashboard({
            can_create_user: false,
            can_view_users: false,
            recent_users: visibleUsers,
        });

        await flushPromises();

        expect(wrapper.find('[data-user-card]').exists()).toBe(false);
    });

    it('shows only recent orders on the customer dashboard', async () => {
        const wrapper = mountDashboard({
            can_view_users: false,
            is_customer: true,
            recent_users: visibleUsers,
        });

        await flushPromises();

        expect(wrapper.find('[data-dashboard-summary]').exists()).toBe(false);
        expect(wrapper.find('[data-recent-orders]').exists()).toBe(true);
    });
});
