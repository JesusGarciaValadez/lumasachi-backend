<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase :description="t('auth.register_description')" :title="t('auth.register_title')">
        <Head :title="t('welcome.register')" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="first_name">{{ t('auth.first_name') }}</Label>
                    <Input
                        id="first_name"
                        v-model="form.first_name"
                        :tabindex="1"
                        autocomplete="first_name"
                        autofocus
                        :placeholder="t('auth.first_name')"
                        required
                        type="text"
                    />
                    <InputError :message="form.errors.first_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="last_name">{{ t('auth.last_name') }}</Label>
                    <Input
                        id="last_name"
                        v-model="form.last_name"
                        :tabindex="1"
                        autocomplete="last_name"
                        autofocus
                        :placeholder="t('auth.last_name')"
                        required
                        type="text"
                    />
                    <InputError :message="form.errors.last_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ t('auth.email') }}</Label>
                    <Input id="email" type="email" required :tabindex="2" autocomplete="email" v-model="form.email" placeholder="email@example.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('auth.password') }}</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        v-model="form.password"
                        :placeholder="t('auth.password_placeholder')"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ t('auth.confirm_password') }}</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        :placeholder="t('auth.confirm_password_placeholder')"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="5" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ t('auth.register_title') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ t('auth.already_account') }}
                <TextLink :href="route('login')" :tabindex="6" class="underline underline-offset-4">{{ t('auth.login') }}</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
