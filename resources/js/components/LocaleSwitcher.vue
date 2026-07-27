<script lang="ts" setup>
import { SUPPORTED_LOCALES, type SupportedLocale } from '@/i18n';
import type { AppPageProps } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage<AppPageProps>();
const changing = ref(false);

const locale = computed<SupportedLocale>(() => {
    const value = page.props.i18n.locale;

    return SUPPORTED_LOCALES.includes(value as SupportedLocale) ? (value as SupportedLocale) : 'es';
});

const locales = computed(() => page.props.i18n.supported_locales ?? [...SUPPORTED_LOCALES]);

const localeLabel = (value: string): string => {
    if (value === 'es') return t('common.spanish');
    if (value === 'en') return t('common.english');

    return value;
};

function changeLocale(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    if (value === locale.value || changing.value) {
        return;
    }

    changing.value = true;
    router.post(
        route('locale.update'),
        { locale: value },
        {
            preserveScroll: true,
            onFinish: () => {
                changing.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="flex items-center gap-2">
        <label class="sr-only" for="locale-switcher">{{ t('common.language') }}</label>
        <select
            id="locale-switcher"
            :aria-label="t('common.language')"
            :disabled="changing"
            :value="locale"
            class="rounded-md border border-sidebar-border bg-background px-2 py-1 text-xs"
            @change="changeLocale"
        >
            <option v-for="value in locales" :key="value" :value="value">{{ localeLabel(value) }}</option>
        </select>
    </div>
</template>
