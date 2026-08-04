<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type User } from '@/types';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const { t } = useI18n();
const breadcrumbItems = computed<BreadcrumbItem[]>(() => [{ title: t('settings.profile_title'), href: '/settings/profile' }]);

const page = usePage();
const user = page.props.auth.user as User;

const form = useForm({
    first_name: user.first_name,
    last_name: user.last_name,
    email: user.email,
    phone_number: user.phone_number ?? '',
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('settings.profile_title')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall :description="t('settings.profile_description')" :title="t('settings.profile_information')" />

                <form class="space-y-6" dusk="customer-profile-form" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="first_name">{{ t('settings.first_name') }}</Label>
                        <Input
                            id="first_name"
                            v-model="form.first_name"
                            autocomplete="given-name"
                            class="mt-1 block w-full"
                            :placeholder="t('settings.first_name')"
                            dusk="profile-first-name"
                            required
                        />
                        <InputError :message="form.errors.first_name" class="mt-2" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">{{ t('settings.last_name') }}</Label>
                        <Input
                            id="last_name"
                            v-model="form.last_name"
                            autocomplete="family-name"
                            class="mt-1 block w-full"
                            :placeholder="t('settings.last_name')"
                            dusk="profile-last-name"
                            required
                        />
                        <InputError :message="form.errors.last_name" class="mt-2" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ t('settings.email') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            :placeholder="t('settings.email')"
                            dusk="profile-email"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone_number">{{ t('settings.phone_number') }}</Label>
                        <Input
                            id="phone_number"
                            v-model="form.phone_number"
                            :placeholder="t('settings.phone_number')"
                            autocomplete="tel"
                            class="mt-1 block w-full"
                            dusk="profile-phone"
                        />
                        <InputError :message="form.errors.phone_number" class="mt-2" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            {{ t('settings.unverified_email') }}
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ t('settings.resend_verification') }}
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            {{ t('settings.verification_sent') }}
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing" dusk="profile-form-submit">{{ t('settings.save') }}</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600" dusk="profile-saved">{{ t('settings.saved') }}</p>
                        </Transition>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
