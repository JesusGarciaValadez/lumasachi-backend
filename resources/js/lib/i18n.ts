export type IntlLocale = 'es-MX' | 'en-US';

export function getIntlLocale(): IntlLocale {
    const locale = typeof document !== 'undefined' ? document.documentElement.lang : '';

    return locale.toLowerCase().startsWith('en') ? 'en-US' : 'es-MX';
}
