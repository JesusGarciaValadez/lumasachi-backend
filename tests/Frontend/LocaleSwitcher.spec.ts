import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const router = vi.hoisted(() => ({ post: vi.fn() }));
const page = vi.hoisted(() => ({
    props: {
        i18n: {
            locale: 'es',
            supported_locales: ['es', 'en'],
        },
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router,
    usePage: () => page,
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) =>
            ({
                'common.english': 'English',
                'common.language': 'Language',
                'common.spanish': 'Spanish',
            })[key] ?? key,
    }),
}));

describe('LocaleSwitcher', () => {
    beforeEach(() => {
        router.post.mockReset();
        vi.stubGlobal('route', (name: string) => `/${name}`);
    });

    it('renders supported locale options and posts a locale change', async () => {
        const wrapper = mount(LocaleSwitcher);

        expect(wrapper.findAll('option').map((option) => option.text())).toEqual(['Spanish', 'English']);

        await wrapper.get('select').setValue('en');
        await flushPromises();

        expect(router.post).toHaveBeenCalledWith(
            '/locale.update',
            { locale: 'en' },
            expect.objectContaining({ preserveScroll: true, onFinish: expect.any(Function) }),
        );
    });

    it('does not submit when the selected locale is already active', async () => {
        const wrapper = mount(LocaleSwitcher);

        await wrapper.get('select').setValue('es');

        expect(router.post).not.toHaveBeenCalled();
    });

    it('renders the larger settings variant with flag labels', () => {
        const wrapper = mount(LocaleSwitcher, {
            props: {
                showFlags: true,
                variant: 'settings',
            },
        });

        expect(wrapper.find('label').text()).toBe('Language');
        expect(wrapper.find('select').classes()).toContain('h-12');
        expect(wrapper.findAll('option').map((option) => option.text())).toEqual(['🇪🇸 Spanish', '🇺🇸 English']);
    });
});
