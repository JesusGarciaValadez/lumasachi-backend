<script lang="ts" setup>
import DeleteUserAdministration from '@/components/users/DeleteUserAdministration.vue';
import UserForm from '@/components/users/UserForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { UserAdministrationCapabilities, UserAdministrationDetailUser, UserAdministrationOptions } from '@/types/users';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    user: UserAdministrationDetailUser;
    capabilities: UserAdministrationCapabilities;
    options: UserAdministrationOptions;
}>();

const { t } = useI18n();
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('users.users'), href: route('users.index') },
    { title: t('users.profile'), href: route('user.show', props.user.uuid) },
]);
</script>

<template>
    <Head :title="user.full_name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div v-if="capabilities.can_delete" class="flex justify-end">
                <DeleteUserAdministration :user-name="user.full_name" :user-uuid="user.uuid" />
            </div>
            <UserForm :capabilities="capabilities" :options="options" :user="user" mode="update" />
        </div>
    </AppLayout>
</template>
