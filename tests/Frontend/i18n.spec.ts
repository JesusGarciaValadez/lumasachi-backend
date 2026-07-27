import { createI18nInstance, messages, normalizeLocale } from '@/i18n';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

function leafKeys(value: unknown, prefix = ''): string[] {
    if (typeof value !== 'object' || value === null) {
        return [prefix];
    }

    return Object.entries(value).flatMap(([key, child]) => leafKeys(child, prefix ? `${prefix}.${key}` : key));
}

function sourceFiles(directory: string): string[] {
    return readdirSync(directory).flatMap((entry) => {
        const path = join(directory, entry);

        return statSync(path).isDirectory() ? sourceFiles(path) : path.endsWith('.vue') || path.endsWith('.ts') ? [path] : [];
    });
}

describe('i18n locale lifecycle helpers', () => {
    it('normalizes regional locale tags to supported locales', () => {
        expect(normalizeLocale('es-MX')).toBe('es');
        expect(normalizeLocale('en_US')).toBe('en');
    });

    it('falls back to Spanish for unsupported or missing locale values', () => {
        expect(normalizeLocale('fr')).toBe('es');
        expect(normalizeLocale()).toBe('es');
    });

    it('creates an i18n instance with the resolved locale', () => {
        const i18n = createI18nInstance('en-US');

        expect(i18n.global.locale.value).toBe('en');
        expect(i18n.global.t('common.language')).toBe('Language');
    });

    it('keeps Spanish and English message trees in parity', () => {
        expect(leafKeys(messages.es).sort()).toEqual(leafKeys(messages.en).sort());
    });

    it('provides translated representative application and accessibility copy', () => {
        const spanish = createI18nInstance('es').global;
        const english = createI18nInstance('en').global;

        for (const key of [
            'welcome.title',
            'auth.login_title',
            'settings.delete_confirm',
            'catalog.no_services',
            'common.toggle_sidebar',
            'common.close',
            'common.breadcrumb',
            'common.more',
            'common.navigation_menu',
            'common.search',
        ]) {
            expect(spanish.te(key)).toBe(true);
            expect(english.te(key)).toBe(true);
            expect(spanish.t(key)).not.toBe(key);
            expect(english.t(key)).not.toBe(key);
        }
    });

    it('defines every literal translation key used by Vue application sources', () => {
        const sourceRoot = join(process.cwd(), 'resources/js');
        const keys = new Set<string>();
        const keyPattern = /\b(?:t|tm)\(\s*['"]([^'"]+)['"]/g;

        for (const file of sourceFiles(sourceRoot)) {
            const source = readFileSync(file, 'utf8');

            for (const match of source.matchAll(keyPattern)) {
                keys.add(match[1]);
            }
        }

        const i18n = createI18nInstance('es').global;

        for (const key of keys) {
            expect(i18n.tm(key), `${key} is missing from the Spanish catalog`).not.toBe(key);
        }
    });

    it('pluralizes selected and completed service counts in both locales', () => {
        const spanish = createI18nInstance('es').global;
        const english = createI18nInstance('en').global;

        expect(spanish.t('orders.services_selected', 0)).toContain('ningún');
        expect(spanish.t('orders.services_selected', 1)).toContain('un servicio');
        expect(spanish.t('orders.services_selected', 3)).toContain('3 servicios');
        expect(english.t('orders.services_completed', 0)).toContain('no services');
        expect(english.t('orders.services_completed', 1)).toContain('one service');
        expect(english.t('orders.services_completed', 3)).toContain('3 services');
    });
});
