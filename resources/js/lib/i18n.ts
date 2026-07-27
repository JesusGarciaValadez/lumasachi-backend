export type IntlLocale = 'es-MX' | 'en-US';

export function getIntlLocale(): IntlLocale {
    const locale = typeof document !== 'undefined' ? document.documentElement.lang : '';

    return locale.toLowerCase().startsWith('en') ? 'en-US' : 'es-MX';
}

export function formatDateTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    try {
        return new Intl.DateTimeFormat(getIntlLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
    } catch {
        return value;
    }
}

export function formatMoney(value: string | number | null | undefined): string {
    return Number(value ?? 0).toLocaleString(getIntlLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
