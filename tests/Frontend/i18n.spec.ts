import { createI18nInstance, messages, normalizeLocale } from '@/i18n';
import { formatDateTime, formatMoney } from '@/lib/i18n';
import { NodeTypes, parse as parseTemplate } from '@vue/compiler-dom';
import { parse as parseSfc } from '@vue/compiler-sfc';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const bareStringAllowList = new Set(['Laracasts', '—']);
const staticAttributeAllowList = new Set(['email@example.com']);
const auditedAttributes = new Set(['alt', 'aria-label', 'placeholder', 'title']);

type TemplateNode = {
    type: number;
    content?: string;
    name?: string;
    props?: TemplateNode[];
    children?: TemplateNode[];
    value?: { content: string } | null;
    loc?: { start: { line: number } };
};

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

function templateBareStrings(file: string): string[] {
    const source = readFileSync(file, 'utf8');
    const parsed = parseSfc(source, { filename: file });

    expect(parsed.errors, `${file} contains an invalid Vue SFC`).toEqual([]);

    if (!parsed.descriptor.template) {
        return [];
    }

    const violations: string[] = [];
    let root: TemplateNode;

    try {
        root = parseTemplate(parsed.descriptor.template.content) as unknown as TemplateNode;
    } catch (error) {
        violations.push(`${file}: template parse error (${error instanceof Error ? error.message : String(error)})`);

        return violations;
    }

    const visit = (node: TemplateNode): void => {
        if (node.type === NodeTypes.TEXT) {
            const content = node.content?.trim() ?? '';

            if (content !== '' && !bareStringAllowList.has(content) && !/^[\p{P}\p{S}\s]+$/u.test(content)) {
                violations.push(`${file}:${node.loc?.start.line ?? 0}: text "${content}"`);
            }
        }

        if (node.type === NodeTypes.ELEMENT) {
            for (const prop of node.props ?? []) {
                if (prop.type !== NodeTypes.ATTRIBUTE || !auditedAttributes.has(prop.name ?? '') || !prop.value) {
                    continue;
                }

                const content = prop.value.content.trim();

                if (content !== '' && !staticAttributeAllowList.has(content)) {
                    violations.push(`${file}:${prop.loc?.start.line ?? 0}: ${prop.name}="${content}"`);
                }
            }
        }

        for (const child of node.children ?? []) {
            visit(child);
        }
    };

    visit(root);

    return violations;
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

        for (const locale of ['es', 'en'] as const) {
            const i18n = createI18nInstance(locale).global;

            for (const key of keys) {
                const value = i18n.tm(key);

                expect(i18n.te(key) || typeof value === 'object', `${key} is missing from the ${locale} catalog`).toBe(true);
                expect(value, `${key} falls back to its key in ${locale}`).not.toBe(key);
            }
        }
    });

    it('has no unreviewed bare Vue template strings or static presentational attributes', () => {
        const sourceRoot = join(process.cwd(), 'resources/js');
        const violations = sourceFiles(sourceRoot)
            .filter((file) => file.endsWith('.vue'))
            .flatMap((file) => templateBareStrings(file));

        expect(violations).toEqual([]);
    });

    it('resolves critical translations without missing-key fallbacks in both locales', () => {
        const criticalKeys = ['common.dashboard', 'common.orders', 'common.language', 'auth.login_title', 'orders.progress', 'settings.title'];

        for (const locale of ['es', 'en'] as const) {
            const i18n = createI18nInstance(locale).global;

            for (const key of criticalKeys) {
                expect(i18n.te(key), `${key} is missing from the ${locale} catalog`).toBe(true);
                expect(i18n.t(key), `${key} falls back to its key in ${locale}`).not.toBe(key);
            }
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

    it('formats dates and MXN amounts using the active locale', () => {
        document.documentElement.lang = 'es';
        const spanishMoney = formatMoney('1234.5');
        const spanishDate = formatDateTime('2026-07-27T12:00:00Z');

        document.documentElement.lang = 'en';
        const englishMoney = formatMoney('1234.5');
        const englishDate = formatDateTime('2026-07-27T12:00:00Z');

        expect(spanishMoney).toBe(Number('1234.5').toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        expect(englishMoney).toBe(Number('1234.5').toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        expect(spanishDate).not.toBe(englishDate);
    });
});
