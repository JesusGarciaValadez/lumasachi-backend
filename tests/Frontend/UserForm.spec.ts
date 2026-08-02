import UserForm from '@/components/users/UserForm.vue';
import type { UserAdministrationOptions } from '@/types/users';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const form = vi.hoisted(() => ({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    company_id: null,
    role: 'Employee',
    phone_number: '',
    is_active: true,
    notes: '',
    type: 'Individual',
    locale: 'es',
    errors: {} as Record<string, string>,
    processing: false,
    post: vi.fn(),
    put: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    useForm: () => form,
    usePage: () => ({ props: { flash: {} } }),
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) =>
            ({
                'users.active_since_unknown': 'Unknown',
                'users.all_companies': 'All companies',
                'users.back_to_users': 'Back to users',
                'users.company': 'Company',
                'users.create_title': 'Create user',
                'users.email': 'Email',
                'users.edit_title': 'Edit user',
                'users.first_name': 'First name',
                'users.is_active': 'Active user',
                'users.last_name': 'Last name',
                'users.locale': 'Language',
                'users.notes': 'Notes',
                'users.password': 'Password',
                'users.password_confirmation': 'Confirm password',
                'users.phone_number': 'Phone',
                'users.profile': 'User profile',
                'users.role': 'Role',
                'users.roles.Employee': 'Employee',
                'users.save': 'Save user',
                'users.type': 'Type',
                'users.types.Individual': 'Individual',
                'users.update': 'Update user',
            })[key] ?? key,
    }),
}));

const passthroughStub = { template: '<div><slot /></div>' };

function capabilities(superAdministrator: boolean) {
    return {
        can_view_users: true,
        can_create_user: superAdministrator,
        can_open: true,
        can_open_inactive: superAdministrator,
        can_update: true,
        can_update_active: superAdministrator,
        can_delete: superAdministrator,
        can_change_company: superAdministrator,
        can_change_password: superAdministrator,
        allowed_fields: superAdministrator
            ? ['first_name', 'last_name', 'email', 'password', 'role', 'phone_number', 'is_active', 'notes', 'type', 'locale', 'company_id']
            : ['first_name', 'last_name', 'email', 'role', 'phone_number', 'notes', 'type', 'locale'],
    };
}

const options = {
    roles: ['Super Administrator', 'Administrator', 'Employee', 'Customer'],
    types: ['Individual', 'Business'],
    locales: ['es', 'en'],
    active: ['1', '0', 'all'],
    companies: [{ id: 1, uuid: 'company-1', name: 'Company One' }],
    per_page: [10, 20, 50],
} satisfies UserAdministrationOptions;

function route(name: string, uuid?: string): string {
    return name === 'user.update' ? `/user/${uuid}` : name === 'user.store' ? '/user' : '/users';
}

describe('UserForm', () => {
    beforeEach(() => {
        Object.assign(form, {
            first_name: '',
            last_name: '',
            email: '',
            password: '',
            password_confirmation: '',
            company_id: null,
            role: 'Employee',
            phone_number: '',
            is_active: true,
            notes: '',
            type: 'Individual',
            locale: 'es',
            errors: {},
            processing: false,
        });
        form.post.mockReset();
        form.put.mockReset();
        vi.stubGlobal('route', route);
    });

    it('renders all Super Administrator creation fields including password and activation', () => {
        const wrapper = mount(UserForm, {
            props: { mode: 'create', capabilities: capabilities(true), options },
            global: {
                mocks: { route },
                stubs: { Button: passthroughStub, Card: passthroughStub, Input: passthroughStub, Label: passthroughStub },
            },
        });

        expect(wrapper.find('[dusk="user-create-form"]').exists()).toBe(true);
        expect(wrapper.find('[dusk="user-password"]').exists()).toBe(true);
        expect(wrapper.find('[dusk="user-company"]').exists()).toBe(true);
        expect(wrapper.find('[dusk="user-is-active"]').exists()).toBe(true);
    });

    it('submits the Super Administrator creation form through the named store route', async () => {
        const wrapper = mount(UserForm, {
            props: { mode: 'create', capabilities: capabilities(true), options },
            global: {
                mocks: { route },
                stubs: { Button: passthroughStub, Card: passthroughStub, Input: passthroughStub, Label: passthroughStub },
            },
        });

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.post).toHaveBeenCalledWith('/user', expect.any(Object));
    });

    it('omits Administrator-forbidden controls and submits the scoped update route', async () => {
        const wrapper = mount(UserForm, {
            props: {
                mode: 'update',
                user: {
                    uuid: 'user-1',
                    first_name: 'Ana',
                    last_name: 'Admin',
                    full_name: 'Admin, Ana',
                    email: 'ana@example.test',
                    phone_number: null,
                    company_id: 1,
                    company: options.companies[0],
                    role: 'Employee',
                    type: 'Individual',
                    is_active: true,
                    activated_at: null,
                    notes: null,
                    locale: 'es',
                },
                capabilities: capabilities(false),
                options: { ...options, roles: ['Administrator', 'Employee', 'Customer'] as UserAdministrationOptions['roles'] },
            },
            global: {
                mocks: { route },
                stubs: { Button: passthroughStub, Card: passthroughStub, Input: passthroughStub, Label: passthroughStub },
            },
        });

        expect(wrapper.find('[dusk="user-form"]').exists()).toBe(true);
        expect(wrapper.find('[dusk="user-password"]').exists()).toBe(false);
        expect(wrapper.find('[dusk="user-company"]').exists()).toBe(false);
        expect(wrapper.find('[dusk="user-is-active"]').exists()).toBe(false);

        await wrapper.find('form').trigger('submit.prevent');

        expect(form.put).toHaveBeenCalledWith('/user/user-1', expect.any(Object));
    });
});
