import { createI18nInstance, normalizeLocale } from '@/i18n';
import { describe, expect, it } from 'vitest';

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
});
