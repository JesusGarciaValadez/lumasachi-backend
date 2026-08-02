<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    userUuid: string;
    userName?: string;
    triggerDusk?: string;
    dialogDusk?: string;
    confirmDusk?: string;
}

const props = withDefaults(defineProps<Props>(), {
    userName: '',
    triggerDusk: 'user-delete-trigger',
    dialogDusk: 'user-delete-dialog',
    confirmDusk: 'user-delete-confirm',
});

const { t } = useI18n();
const open = ref(false);
const form = useForm({});
const deleteError = computed(() => (form.errors as Record<string, string | undefined>)['delete']);

watch(open, (isOpen) => {
    if (!isOpen) {
        form.clearErrors();
        form.reset();
    }
});

function submit(event: Event): void {
    event.preventDefault();

    form.delete(route('user.destroy', props.userUuid), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <Button
            :aria-label="t('users.delete')"
            :dusk="triggerDusk"
            :title="t('users.delete')"
            class="cursor-pointer"
            size="icon"
            type="button"
            variant="destructive"
            @click="open = true"
        >
            <Trash2 class="size-4" />
            <span class="sr-only">{{ t('users.delete') }}</span>
        </Button>

        <DialogContent v-if="open">
            <form :dusk="dialogDusk" @submit="submit">
                <DialogHeader class="space-y-3">
                    <DialogTitle>{{ t('users.delete_title') }}</DialogTitle>
                    <DialogDescription>{{ t('users.delete_description') }}</DialogDescription>
                </DialogHeader>

                <p v-if="userName" class="mt-4 rounded-md bg-muted p-3 text-sm font-medium">{{ userName }}</p>
                <p v-if="deleteError" class="mt-4 text-sm text-destructive" role="alert">{{ deleteError }}</p>

                <DialogFooter class="mt-6 gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="outline">{{ t('common.cancel') }}</Button>
                    </DialogClose>
                    <Button :disabled="form.processing" :dusk="confirmDusk" type="submit" variant="destructive">
                        {{ t('users.delete') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
