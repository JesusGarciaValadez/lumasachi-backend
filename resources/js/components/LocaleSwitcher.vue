<script lang="ts" setup>
import { SUPPORTED_LOCALES, type SupportedLocale } from '@/i18n';
import type { AppPageProps } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    showFlags?: boolean;
    variant?: 'compact' | 'settings';
}

const props = withDefaults(defineProps<Props>(), {
    showFlags: false,
    variant: 'compact',
});

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

const localeFlag = (value: string): string => {
    if (value === 'es') return '🇪🇸';
    if (value === 'en') return '🇺🇸';

    return '';
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
    <div :class="props.variant === 'settings' ? 'grid gap-2' : 'flex items-center gap-2'">
        <label :class="props.variant === 'settings' ? 'text-sm font-medium text-foreground' : 'sr-only'" for="locale-switcher">
            {{ t('common.language') }}
        </label>
        <div class="relative">
            <select
                id="locale-switcher"
                :aria-label="t('common.language')"
                :class="[
                    'appearance-none pr-10',
                    props.variant === 'settings'
                        ? 'h-12 w-full rounded-lg border border-input bg-background px-4 text-base shadow-sm transition outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50'
                        : 'rounded-md border border-sidebar-border bg-background px-2 py-1 text-xs',
                ]"
                :disabled="changing"
                :value="locale"
                @change="changeLocale"
            >
                <option v-for="value in locales" :key="value" :value="value">
                    {{ props.showFlags ? `${localeFlag(value)} ${localeLabel(value)}` : localeLabel(value) }}
                </option>
            </select>
            <ChevronDown
                :class="[
                    'pointer-events-none absolute right-3 text-muted-foreground',
                    props.variant === 'settings' ? 'top-3.5 h-5 w-5' : 'top-1 h-4 w-4',
                ]"
                aria-hidden="true"
            />
        </div>
    </div>
</template>
