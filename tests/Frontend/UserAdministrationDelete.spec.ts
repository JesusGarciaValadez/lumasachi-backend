import DeleteUserAdministration from '@/components/users/DeleteUserAdministration.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const form = vi.hoisted(() => ({
    clearErrors: vi.fn(),
    delete: vi.fn(),
    errors: {} as Record<string, string>,
    processing: false,
    reset: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: () => form,
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) =>
            ({
                'common.cancel': 'Cancel',
                'users.delete': 'Archive user',
                'users.delete_description': 'Their records and history will be preserved and can be restored later.',
                'users.delete_title': 'Archive this user?',
            })[key] ?? key,
    }),
}));

const passthroughStub = { template: '<div><slot /></div>' };

const dialogStub = {
    props: ['open'],
    template: '<div data-dialog-root><slot /></div>',
};

const dialogContentStub = {
    inheritAttrs: false,
    template: '<div v-bind="$attrs"><slot /></div>',
};

const dialogCloseStub = {
    template: '<div><slot /></div>',
};

const buttonStub = {
    inheritAttrs: false,
    props: ['disabled'],
    template: '<button v-bind="$attrs" :disabled="disabled"><slot /></button>',
};

function mountComponent() {
    return mount(DeleteUserAdministration, {
        props: {
            userName: 'User, Example',
            userUuid: 'user-1',
        },
        global: {
            mocks: {
                route: (name: string, uuid: string) => (name === 'user.destroy' ? `/user/${uuid}` : `/${name}`),
            },
            stubs: {
                Button: buttonStub,
                Dialog: dialogStub,
                DialogClose: dialogCloseStub,
                DialogContent: dialogContentStub,
                DialogDescription: passthroughStub,
                DialogFooter: passthroughStub,
                DialogHeader: passthroughStub,
                DialogTitle: passthroughStub,
                Trash2: passthroughStub,
            },
        },
    });
}

describe('DeleteUserAdministration', () => {
    beforeEach(() => {
        form.clearErrors.mockReset();
        form.delete.mockReset();
        form.errors = {};
        form.processing = false;
        form.reset.mockReset();
        vi.stubGlobal('route', (name: string, uuid: string) => (name === 'user.destroy' ? `/user/${uuid}` : `/${name}`));
    });

    it('opens an archive confirmation modal and submits the target delete route', async () => {
        const wrapper = mountComponent();

        expect(wrapper.find('[dusk="user-delete-dialog"]').exists()).toBe(false);
        expect(wrapper.get('[dusk="user-delete-trigger"]').classes()).toContain('cursor-pointer');

        await wrapper.get('[dusk="user-delete-trigger"]').trigger('click');

        expect(wrapper.get('[dusk="user-delete-dialog"]').text()).toContain('preserved');
        expect(wrapper.get('[dusk="user-delete-dialog"]').text()).toContain('restored later');

        await wrapper.get('form').trigger('submit');

        expect(form.delete).toHaveBeenCalledWith('/user/user-1', expect.objectContaining({ preserveScroll: true }));
    });

    it('closes after a successful delete response', async () => {
        const wrapper = mountComponent();

        await wrapper.get('[dusk="user-delete-trigger"]').trigger('click');
        await wrapper.get('form').trigger('submit');

        const options = form.delete.mock.calls[0][1] as { onSuccess: () => void };
        options.onSuccess();
        await nextTick();

        expect(wrapper.find('[dusk="user-delete-dialog"]').exists()).toBe(false);
    });
});
