<script lang="ts" setup>
import UserForm from '@/components/users/UserForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { UserAdministrationCapabilities, UserAdministrationOptions } from '@/types/users';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    capabilities: UserAdministrationCapabilities;
    options: UserAdministrationOptions;
}>();

const { t } = useI18n();
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('users.users'), href: route('users.index') },
    { title: t('users.create'), href: route('user.create') },
]);
</script>

<template>
    <Head :title="t('users.create_title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <UserForm :capabilities="capabilities" :options="options" mode="create" />
        </div>
    </AppLayout>
</template>
