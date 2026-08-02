<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AppPageProps } from '@/types';
import type {
    UserAdministrationCapabilities,
    UserAdministrationCompany,
    UserAdministrationDetailUser,
    UserAdministrationOptions,
    UserLocale,
    UserRole,
    UserType,
} from '@/types/users';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    mode: 'create' | 'update';
    user?: UserAdministrationDetailUser | null;
    capabilities: UserAdministrationCapabilities;
    options: UserAdministrationOptions;
}

type UserFormData = {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    password_confirmation: string;
    company_id: number | null;
    role: UserRole;
    phone_number: string;
    is_active: boolean;
    notes: string;
    type: UserType;
    locale: UserLocale;
};

const props = defineProps<Props>();
const { t } = useI18n();
const page = usePage<AppPageProps>();
const user = computed(() => props.user ?? null);
const isCreate = computed(() => props.mode === 'create');
const canEdit = (field: string): boolean => props.capabilities.allowed_fields.includes(field);
const flashError = computed(() => page.props.flash?.error ?? null);

const form = useForm<UserFormData>({
    first_name: user.value?.first_name ?? '',
    last_name: user.value?.last_name ?? '',
    email: user.value?.email ?? '',
    password: '',
    password_confirmation: '',
    company_id: user.value?.company_id ?? null,
    role: user.value?.role ?? props.options.roles[0] ?? 'Employee',
    phone_number: user.value?.phone_number ?? '',
    is_active: user.value?.is_active ?? true,
    notes: user.value?.notes ?? '',
    type: user.value?.type ?? props.options.types[0] ?? 'Individual',
    locale: user.value?.locale ?? props.options.locales[0] ?? 'es',
});

function errorFor(field: keyof UserFormData): string | undefined {
    return form.errors[field];
}

function submit(): void {
    if (isCreate.value) {
        form.post(route('user.store'), { preserveScroll: true });

        return;
    }

    if (user.value) {
        form.put(route('user.update', user.value.uuid), { preserveScroll: true });
    }
}

function companyName(company: UserAdministrationCompany | null | undefined): string {
    return company?.name ?? t('users.all_companies');
}
</script>

<template>
    <form :dusk="isCreate ? 'user-create-form' : 'user-form'" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
        <div v-if="flashError" class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive" role="alert">
            {{ flashError }}
        </div>

        <Card>
            <div class="grid gap-4 p-6 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <h1 class="text-xl font-semibold">{{ isCreate ? t('users.create_title') : t('users.edit_title') }}</h1>
                </div>

                <div class="grid gap-2">
                    <Label for="user-first-name">{{ t('users.first_name') }}</Label>
                    <Input
                        id="user-first-name"
                        v-model="form.first_name"
                        :aria-invalid="Boolean(errorFor('first_name'))"
                        autocomplete="given-name"
                        dusk="user-first-name"
                        required
                    />
                    <InputError :message="errorFor('first_name')" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-last-name">{{ t('users.last_name') }}</Label>
                    <Input
                        id="user-last-name"
                        v-model="form.last_name"
                        :aria-invalid="Boolean(errorFor('last_name'))"
                        autocomplete="family-name"
                        dusk="user-last-name"
                        required
                    />
                    <InputError :message="errorFor('last_name')" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="user-email">{{ t('users.email') }}</Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        :aria-describedby="errorFor('email') ? 'user-email-error' : undefined"
                        :aria-invalid="Boolean(errorFor('email'))"
                        autocomplete="username"
                        dusk="user-email"
                        required
                        type="email"
                    />
                    <InputError id="user-email-error" :message="errorFor('email')" dusk="user-error-email" />
                </div>

                <template v-if="canEdit('password')">
                    <div class="grid gap-2">
                        <Label for="user-password">{{ t('users.password') }}</Label>
                        <Input
                            id="user-password"
                            v-model="form.password"
                            :aria-invalid="Boolean(errorFor('password'))"
                            :required="isCreate"
                            autocomplete="new-password"
                            dusk="user-password"
                            type="password"
                        />
                        <InputError :message="errorFor('password')" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="user-password-confirmation">{{ t('users.password_confirmation') }}</Label>
                        <Input
                            id="user-password-confirmation"
                            v-model="form.password_confirmation"
                            :required="isCreate"
                            autocomplete="new-password"
                            dusk="user-password-confirmation"
                            type="password"
                        />
                    </div>
                </template>

                <div v-if="canEdit('company_id')" class="grid gap-2">
                    <Label for="user-company">{{ t('users.company') }}</Label>
                    <select
                        id="user-company"
                        v-model.number="form.company_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        dusk="user-company"
                    >
                        <option :value="null">{{ t('users.all_companies') }}</option>
                        <option v-for="company in options.companies" :key="company.id" :value="company.id">
                            {{ company.name }}
                        </option>
                    </select>
                    <InputError :message="errorFor('company_id')" />
                </div>
                <div v-else-if="user" class="grid gap-2">
                    <Label>{{ t('users.company') }}</Label>
                    <p class="rounded-md border border-input px-3 py-2 text-sm text-muted-foreground">{{ companyName(user.company) }}</p>
                </div>

                <div v-if="canEdit('role')" class="grid gap-2">
                    <Label for="user-role">{{ t('users.role') }}</Label>
                    <select
                        id="user-role"
                        v-model="form.role"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        dusk="user-role"
                        required
                    >
                        <option v-for="role in options.roles" :key="role" :value="role">{{ t(`users.roles.${role}`) }}</option>
                    </select>
                    <InputError :message="errorFor('role')" />
                </div>

                <div v-if="canEdit('type')" class="grid gap-2">
                    <Label for="user-type">{{ t('users.type') }}</Label>
                    <select
                        id="user-type"
                        v-model="form.type"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        dusk="user-type"
                        required
                    >
                        <option v-for="type in options.types" :key="type" :value="type">{{ t(`users.types.${type}`) }}</option>
                    </select>
                    <InputError :message="errorFor('type')" />
                </div>

                <div v-if="canEdit('locale')" class="grid gap-2">
                    <Label for="user-locale">{{ t('users.locale') }}</Label>
                    <select
                        id="user-locale"
                        v-model="form.locale"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        dusk="user-locale"
                        required
                    >
                        <option v-for="locale in options.locales" :key="locale" :value="locale">{{ t(`users.locales.${locale}`) }}</option>
                    </select>
                    <InputError :message="errorFor('locale')" />
                </div>

                <div v-if="canEdit('phone_number')" class="grid gap-2">
                    <Label for="user-phone">{{ t('users.phone_number') }}</Label>
                    <Input id="user-phone" v-model="form.phone_number" autocomplete="tel" dusk="user-phone" />
                    <InputError :message="errorFor('phone_number')" />
                </div>

                <div v-if="canEdit('notes')" class="grid gap-2 sm:col-span-2">
                    <Label for="user-notes">{{ t('users.notes') }}</Label>
                    <textarea
                        id="user-notes"
                        v-model="form.notes"
                        class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        dusk="user-notes"
                    />
                    <InputError :message="errorFor('notes')" />
                </div>

                <div v-if="canEdit('is_active')" class="flex items-center gap-2 sm:col-span-2">
                    <input id="user-is-active" v-model="form.is_active" class="size-4 rounded border-input" dusk="user-is-active" type="checkbox" />
                    <Label for="user-is-active">{{ t('users.is_active') }}</Label>
                    <InputError :message="errorFor('is_active')" />
                </div>
            </div>
        </Card>

        <div class="flex items-center justify-end gap-3">
            <Button as-child type="button" variant="outline">
                <Link :href="route('users.index')">{{ t('users.back_to_users') }}</Link>
            </Button>
            <Button :disabled="form.processing" dusk="user-form-submit" type="submit">
                {{ isCreate ? t('users.save') : t('users.update') }}
            </Button>
        </div>
    </form>
</template>
