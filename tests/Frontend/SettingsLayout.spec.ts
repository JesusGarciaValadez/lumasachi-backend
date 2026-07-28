import SettingsLayout from '@/layouts/settings/Layout.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({
    props: {
        i18n: {
            locale: 'es',
            supported_locales: ['es', 'en'],
        },
        ziggy: {
            location: 'https://localhost/settings/profile',
        },
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    usePage: () => page,
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) =>
            ({
                'common.english': 'English',
                'common.language': 'Language',
                'common.spanish': 'Spanish',
                'settings.appearance': 'Appearance',
                'settings.description': 'Manage your profile and account settings',
                'settings.language': 'Language',
                'settings.password': 'Password',
                'settings.profile': 'Profile',
                'settings.title': 'Settings',
            })[key] ?? key,
    }),
}));

describe('SettingsLayout', () => {
    beforeEach(() => {
        page.props.ziggy.location = 'https://localhost/settings/profile';
        vi.stubGlobal('route', (name: string) => `/${name}`);
    });

    it('exposes the language settings tab below appearance', () => {
        const wrapper = mount(SettingsLayout, {
            global: {
                stubs: {
                    Button: { props: ['asChild'], template: '<button><slot /></button>' },
                    Heading: { template: '<div><slot /></div>' },
                    Separator: { template: '<hr />' },
                },
            },
            slots: {
                default: '<div>Settings content</div>',
            },
        });

        const links = wrapper.findAll('a');

        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/settings/profile',
            '/settings/password',
            '/settings/appearance',
            '/settings/language',
        ]);
        expect(links.at(-1)?.text()).toBe('Language');
    });
});
