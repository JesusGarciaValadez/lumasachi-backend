<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import DeleteUserAdministration from '@/components/users/DeleteUserAdministration.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/i18n';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import type { PaginatedUsers, UserAdministrationCapabilities, UserAdministrationListUser, UserAdministrationOptions } from '@/types/users';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface FilterState {
    first_name: string;
    last_name: string;
    role: string;
    active: string;
    type: string;
    company_id: string | number;
    per_page: number;
}

const props = defineProps<{
    users: PaginatedUsers;
    filters: FilterState;
    capabilities: UserAdministrationCapabilities & { can_open_inactive?: boolean };
    current_user_uuid: string;
    options: UserAdministrationOptions;
}>();

const { t } = useI18n();
const page = usePage<AppPageProps>();
const filters = ref<FilterState>({ ...props.filters });
const filtersOpen = ref(false);
const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const flash = computed(() => page.props.flash ?? {});
const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: t('users.users'), href: route('users.index') }]);

watch(
    () => props.filters,
    (next) => {
        filters.value = { ...next };
    },
    { deep: true },
);

function queryParams(): Record<string, string | number> {
    const params: Record<string, string | number> = {
        active: filters.value.active || '1',
        per_page: filters.value.per_page || 10,
    };

    for (const key of ['first_name', 'last_name', 'role', 'type', 'company_id'] as const) {
        const value = filters.value[key];

        if (value !== '') {
            params[key] = value;
        }
    }

    return params;
}

function visitUsers(): void {
    router.get(route('users.index'), queryParams(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function scheduleTextFilter(): void {
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }

    debounceTimer.value = setTimeout(visitUsers, 300);
}

function setActiveOnly(event: Event): void {
    const input = event.target;

    if (input instanceof HTMLInputElement) {
        filters.value.active = input.checked ? '1' : 'all';
        visitUsers();
    }
}

function clearFilters(): void {
    filters.value = {
        first_name: '',
        last_name: '',
        role: '',
        active: '1',
        type: '',
        company_id: '',
        per_page: props.filters.per_page || 10,
    };
    visitUsers();
}

function isOpenable(user: UserAdministrationListUser): boolean {
    return user.is_active || props.capabilities.can_open_inactive === true;
}

function roleLabel(role: UserAdministrationListUser['role']): string {
    return t(`users.roles.${role}`);
}

function typeLabel(type: UserAdministrationListUser['type']): string {
    return type ? t(`users.types.${type}`) : '—';
}

function canDeleteUser(user: UserAdministrationListUser): boolean {
    return props.capabilities.can_delete && user.uuid !== props.current_user_uuid;
}

function capsuleClass(kind: 'role' | 'type' | 'active', value: string | boolean): string {
    if (kind === 'active') {
        return value === true
            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
            : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200';
    }

    if (kind === 'type') {
        return value === 'Business'
            ? 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200'
            : 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200';
    }

    return (
        {
            'Super Administrator': 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
            Administrator: 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-200',
            Employee: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
            Customer: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200',
        }[value as UserAdministrationListUser['role']] ?? 'bg-muted text-muted-foreground'
    );
}

onBeforeUnmount(() => {
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }
});
</script>

<template>
    <Head :title="t('users.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4" dusk="users-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">{{ t('users.title') }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('users.description') }}</p>
                </div>
                <Button v-if="capabilities.can_create_user" as-child size="sm">
                    <Link :href="route('user.create')" dusk="user-create-link">{{ t('users.create') }}</Link>
                </Button>
            </div>

            <div
                v-if="flash.success || flash.error"
                :class="flash.error ? 'border-destructive/50 text-destructive' : 'border-emerald-500/50 text-emerald-700 dark:text-emerald-300'"
                class="rounded-md border p-3 text-sm"
                dusk="users-flash"
                role="status"
            >
                {{ flash.error ?? flash.success }}
            </div>

            <Card>
                <div class="border-b border-border">
                    <div class="flex items-center justify-between gap-4 px-6 py-4">
                        <label class="flex items-center gap-2 text-sm font-medium" for="users-active-only">
                            <input
                                id="users-active-only"
                                :checked="filters.active === '1'"
                                class="size-4 rounded border-input"
                                dusk="users-active-only"
                                type="checkbox"
                                @change="setActiveOnly"
                            />
                            {{ t('users.active_only') }}
                        </label>
                        <button
                            :aria-expanded="filtersOpen"
                            aria-controls="users-filters-panel"
                            class="inline-flex items-center gap-1 text-sm underline underline-offset-2"
                            dusk="users-filters-trigger"
                            type="button"
                            @click="filtersOpen = !filtersOpen"
                        >
                            <span :class="filtersOpen ? 'rotate-180' : ''" aria-hidden="true" class="transition-transform duration-200">▼</span>
                            {{ t('users.filters') }}
                        </button>
                    </div>
                    <Transition
                        enter-active-class="overflow-hidden transition-all duration-200 ease-out"
                        enter-from-class="max-h-0 opacity-0"
                        enter-to-class="max-h-[40rem] opacity-100"
                        leave-active-class="overflow-hidden transition-all duration-150 ease-in"
                        leave-from-class="max-h-[40rem] opacity-100"
                        leave-to-class="max-h-0 opacity-0"
                    >
                        <div
                            v-if="filtersOpen"
                            id="users-filters-panel"
                            class="grid gap-4 border-t border-border px-6 py-4 sm:grid-cols-2"
                            dusk="users-filters-panel"
                        >
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="users-first-name">{{ t('users.first_name') }}</label>
                                <input
                                    id="users-first-name"
                                    v-model="filters.first_name"
                                    :placeholder="t('users.search_first_name')"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @input="scheduleTextFilter"
                                />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="users-last-name">{{ t('users.last_name') }}</label>
                                <input
                                    id="users-last-name"
                                    v-model="filters.last_name"
                                    :placeholder="t('users.search_last_name')"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @input="scheduleTextFilter"
                                />
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="users-role">{{ t('users.role') }}</label>
                                <select
                                    id="users-role"
                                    v-model="filters.role"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @change="visitUsers"
                                >
                                    <option value="">{{ t('users.all_roles') }}</option>
                                    <option v-for="role in options.roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="text-sm font-medium" for="users-type">{{ t('users.type') }}</label>
                                <select
                                    id="users-type"
                                    v-model="filters.type"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @change="visitUsers"
                                >
                                    <option value="">{{ t('users.all_types') }}</option>
                                    <option v-for="type in options.types" :key="type" :value="type">{{ typeLabel(type) }}</option>
                                </select>
                            </div>
                            <div v-if="options.companies.length" class="grid gap-2">
                                <label class="text-sm font-medium" for="users-company">{{ t('users.company') }}</label>
                                <select
                                    id="users-company"
                                    v-model="filters.company_id"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    @change="visitUsers"
                                >
                                    <option value="">{{ t('users.all_companies') }}</option>
                                    <option v-for="company in options.companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                                </select>
                            </div>
                            <div class="flex items-end justify-end sm:col-span-2">
                                <Button size="sm" type="button" variant="outline" @click="clearFilters">{{ t('users.clear_filters') }}</Button>
                            </div>
                        </div>
                    </Transition>
                </div>

                <div class="overflow-x-auto" dusk="users-table">
                    <table class="w-full min-w-[52rem] text-left text-sm">
                        <thead class="border-b border-border text-xs text-muted-foreground uppercase">
                            <tr>
                                <th class="px-6 py-3">{{ t('users.users') }}</th>
                                <th class="px-6 py-3">{{ t('users.role') }}</th>
                                <th class="px-6 py-3">{{ t('users.type') }}</th>
                                <th class="px-6 py-3">{{ t('users.active') }}</th>
                                <th class="px-6 py-3">{{ t('users.activated_at') }}</th>
                                <th class="px-6 py-3 text-right">
                                    <span class="sr-only">{{ t('users.actions') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="user in users.data" :key="user.uuid" :dusk="`user-row-${user.uuid}`" class="align-top">
                                <td class="px-6 py-4 font-medium">
                                    <Link
                                        v-if="isOpenable(user)"
                                        :dusk="`user-row-link-${user.uuid}`"
                                        :href="route('user.show', user.uuid)"
                                        class="underline underline-offset-4"
                                    >
                                        {{ user.last_name }}, {{ user.first_name }}
                                    </Link>
                                    <span v-else>{{ user.last_name }}, {{ user.first_name }}</span>
                                    <div v-if="user.company" class="mt-1 text-xs text-muted-foreground">{{ user.company.name }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="capsuleClass('role', user.role)" class="inline-flex rounded-full px-2 py-1 text-xs font-medium">{{
                                        roleLabel(user.role)
                                    }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="capsuleClass('type', user.type ?? '')"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        >{{ typeLabel(user.type) }}</span
                                    >
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="capsuleClass('active', user.is_active)"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                        >{{ user.is_active ? t('users.active') : t('users.inactive') }}</span
                                    >
                                </td>
                                <td class="px-6 py-4 text-muted-foreground">
                                    {{ user.activated_at ? formatDateTime(user.activated_at) : t('users.active_since_unknown') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <DeleteUserAdministration
                                        v-if="canDeleteUser(user)"
                                        :confirm-dusk="`user-delete-confirm-${user.uuid}`"
                                        :dialog-dusk="`user-delete-dialog-${user.uuid}`"
                                        :trigger-dusk="`user-delete-trigger-${user.uuid}`"
                                        :user-name="user.full_name"
                                        :user-uuid="user.uuid"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!users.data.length" class="p-6 text-sm text-muted-foreground">{{ t('users.no_users') }}</div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-border px-6 py-4">
                    <label class="flex items-center gap-2 text-sm" for="users-page-size">
                        {{ t('users.page_size') }}
                        <select
                            id="users-page-size"
                            v-model.number="filters.per_page"
                            class="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                            @change="visitUsers"
                        >
                            <option v-for="size in options.per_page" :key="size" :value="size">{{ size }}</option>
                        </select>
                    </label>
                    <nav v-if="users.last_page > 1" :aria-label="t('users.pagination')" class="flex items-center gap-1" dusk="users-pagination">
                        <template v-for="link in users.links" :key="`${link.label}-${link.page ?? 'ellipsis'}`">
                            <Link
                                v-if="link.url"
                                :class="['rounded px-2 py-1 text-sm', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted']"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                            >
                                {{ link.label }}
                            </Link>
                            <span v-else class="px-2 py-1 text-sm text-muted-foreground">{{ link.label }}</span>
                        </template>
                    </nav>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
