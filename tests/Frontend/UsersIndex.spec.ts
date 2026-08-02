import UsersIndex from '@/pages/Users/Index.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const router = vi.hoisted(() => ({ get: vi.fn() }));
const page = vi.hoisted(() => ({ props: { flash: {} } }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { props: ['title'], template: '<title>{{ title }}</title>' },
    Link: {
        inheritAttrs: false,
        props: ['href'],
        template: '<a v-bind="$attrs" :href="href"><slot /></a>',
    },
    router,
    usePage: () => page,
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) =>
            ({
                'users.active': 'Active',
                'users.active_only': 'Active users only',
                'users.active_since_unknown': 'Unknown',
                'users.all_companies': 'All companies',
                'users.all_roles': 'All roles',
                'users.all_types': 'All types',
                'users.clear_filters': 'Clear filters',
                'users.company': 'Company',
                'users.create': 'Create user',
                'users.description': 'Description',
                'users.filters': 'Filters',
                'users.first_name': 'First name',
                'users.inactive': 'Inactive',
                'users.last_name': 'Last name',
                'users.no_users': 'No users',
                'users.page_size': 'Page size',
                'users.role': 'Role',
                'users.title': 'Users',
                'users.type': 'Type',
                'users.types.Individual': 'Individual',
                'users.users': 'Users',
                'users.roles.Administrator': 'Administrator',
                'users.roles.Customer': 'Customer',
                'users.roles.Employee': 'Employee',
            })[key] ?? key,
    }),
}));

const passthroughStub = { template: '<div><slot /></div>' };

function route(name: string, parameter?: string): string {
    return (
        {
            'users.index': '/users',
            'user.create': '/user/create',
            'user.show': `/user/${parameter ?? ''}`,
        }[name] ?? `/${name}`
    );
}

function mountPage() {
    return mount(UsersIndex, {
        props: {
            users: {
                data: [
                    {
                        uuid: 'active-user',
                        first_name: 'Ana',
                        last_name: 'Active',
                        full_name: 'Active, Ana',
                        role: 'Employee',
                        type: 'Individual',
                        is_active: true,
                        activated_at: '2026-08-01T12:00:00Z',
                    },
                    {
                        uuid: 'inactive-user',
                        first_name: 'Luis',
                        last_name: 'Inactive',
                        full_name: 'Inactive, Luis',
                        role: 'Customer',
                        type: null,
                        is_active: false,
                        activated_at: null,
                    },
                ],
                current_page: 1,
                from: 1,
                last_page: 1,
                per_page: 10,
                to: 2,
                total: 2,
                links: [],
            },
            filters: {
                first_name: '',
                last_name: '',
                role: '',
                active: '1',
                type: '',
                company_id: '',
                per_page: 10,
            },
            current_user_uuid: 'current-user',
            capabilities: {
                can_view_users: true,
                can_create_user: true,
                can_open: true,
                can_open_inactive: false,
                can_update: true,
                can_update_active: false,
                can_delete: false,
                can_change_company: false,
                can_change_password: false,
                allowed_fields: [],
            },
            options: {
                roles: ['Administrator', 'Employee', 'Customer'],
                types: ['Individual', 'Business'],
                locales: ['es', 'en'],
                active: ['1', '0', 'all'],
                companies: [],
                per_page: [10, 20, 50],
            },
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

describe('Users index', () => {
    beforeEach(() => {
        router.get.mockReset();
        page.props.flash = {};
        vi.stubGlobal('route', route);
    });

    it('renders ordered names, capsules, and non-link inactive rows', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Active, Ana');
        expect(wrapper.text()).toContain('Inactive, Luis');
        expect(wrapper.find('[dusk="user-row-link-active-user"]').exists()).toBe(true);
        expect(wrapper.find('[dusk="user-row-link-inactive-user"]').exists()).toBe(false);
        expect(wrapper.find('[dusk="user-row-inactive-user"] .bg-slate-100').exists()).toBe(true);
    });

    it('applies the active toggle and page size through the server query', async () => {
        const wrapper = mountPage();
        const activeToggle = wrapper.find('[dusk="users-active-only"]');

        expect((activeToggle.element as HTMLInputElement).checked).toBe(true);

        await activeToggle.setValue(false);
        await wrapper.find('#users-page-size').setValue('20');

        expect(router.get).toHaveBeenCalledWith('/users', { active: 'all', per_page: 10 }, expect.any(Object));
        expect(router.get).toHaveBeenLastCalledWith('/users', { active: 'all', per_page: 20 }, expect.any(Object));
    });

    it('composes select filters without adding tenant logic in the browser', async () => {
        const wrapper = mountPage();

        await wrapper.find('[dusk="users-filters-trigger"]').trigger('click');
        await wrapper.find('#users-role').setValue('Employee');

        expect(router.get).toHaveBeenCalledWith(
            '/users',
            { active: '1', per_page: 10, role: 'Employee' },
            expect.objectContaining({ preserveState: true, replace: true }),
        );
    });

    it('toggles the filters inline inside the users card', async () => {
        const wrapper = mountPage();
        const trigger = wrapper.find('[dusk="users-filters-trigger"]');

        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('[dusk="users-filters-panel"]').exists()).toBe(false);

        await trigger.trigger('click');

        expect(trigger.attributes('aria-expanded')).toBe('true');
        expect(wrapper.find('[dusk="users-filters-panel"]').exists()).toBe(true);
        expect(wrapper.find('[dusk="users-filters-panel"]').classes()).not.toContain('absolute');

        await trigger.trigger('click');

        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('[dusk="users-filters-panel"]').exists()).toBe(false);
    });
});
