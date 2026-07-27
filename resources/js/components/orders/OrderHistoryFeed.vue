<script lang="ts" setup>
import Icon from '@/components/Icon.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/i18n';
import type { OrderHistory } from '@/types/orders';
import { computed } from 'vue';

const props = defineProps<{
    entries: OrderHistory[];
    loading: boolean;
    errorMessage?: string;
    links?: Record<string, string | null>;
    labels: {
        previous: string;
        next: string;
        noHistory: string;
        eventStatus: string;
        eventPriority: string;
        eventAssignment: string;
        eventItem: string;
        eventService: string;
        eventPayment: string;
        eventAttachment: string;
        eventUpdate: string;
    };
}>();

const emit = defineEmits<{
    (event: 'paginate', url: string): void;
}>();

type EventKind = 'status' | 'priority' | 'assignment' | 'item' | 'service' | 'payment' | 'attachment' | 'update';

const eventKinds = computed(() =>
    props.entries.map((entry) => ({
        entry,
        kind: eventKind(entry.field_changed),
    })),
);

function eventKind(field: string): EventKind {
    if (field === 'status') return 'status';
    if (field === 'priority') return 'priority';
    if (field === 'assigned_to') return 'assignment';
    if (field.includes('item')) return 'item';
    if (field.includes('service')) return 'service';
    if (field.includes('payment')) return 'payment';
    if (field === 'attachments') return 'attachment';

    return 'update';
}

function eventLabel(kind: EventKind): string {
    return props.labels[`event${kind.charAt(0).toUpperCase()}${kind.slice(1)}` as keyof typeof props.labels];
}

function iconName(kind: EventKind): string {
    return {
        status: 'refresh-cw',
        priority: 'flag',
        assignment: 'user-round',
        item: 'package',
        service: 'wrench',
        payment: 'banknote',
        attachment: 'paperclip',
        update: 'history',
    }[kind];
}
</script>

<template>
    <div v-if="loading" aria-busy="true" aria-live="polite" class="relative min-h-32 rounded-md border" data-history-state="loading">
        <div class="absolute inset-0 animate-pulse bg-muted/30" />
    </div>
    <div v-else-if="errorMessage" class="text-sm text-destructive" data-history-state="error" role="alert">{{ errorMessage }}</div>
    <p v-else-if="!entries.length" class="text-sm text-muted-foreground" data-history-state="empty">{{ labels.noHistory }}</p>
    <template v-else>
        <ol class="relative flex flex-col gap-4 border-s ps-6" data-history-feed>
            <li v-for="{ entry, kind } in eventKinds" :key="entry.uuid" :data-event-field="entry.field_changed" class="relative">
                <span
                    :data-icon-name="iconName(kind)"
                    class="absolute -start-[2.05rem] flex size-7 items-center justify-center rounded-full border bg-background text-muted-foreground"
                    data-event-icon
                >
                    <Icon :name="iconName(kind)" :size="14" />
                </span>
                <div class="rounded-md border p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs font-medium text-muted-foreground">{{ eventLabel(kind) }}</span>
                        <time :datetime="entry.created_at" class="text-xs text-muted-foreground">{{ formatDateTime(entry.created_at) }}</time>
                    </div>
                    <p class="mt-1">{{ entry.description }}</p>
                    <p v-if="entry.creator?.full_name" class="mt-2 text-xs text-muted-foreground">{{ entry.creator.full_name }}</p>
                    <ul v-if="entry.attachments.length" class="mt-2 flex flex-col gap-1 text-xs text-muted-foreground">
                        <li v-for="attachment in entry.attachments" :key="attachment.uuid">{{ attachment.file_name }}</li>
                    </ul>
                </div>
            </li>
        </ol>
        <div v-if="links?.prev || links?.next" class="flex justify-between gap-3">
            <Button :disabled="!links?.prev || loading" size="sm" variant="outline" @click="links?.prev && emit('paginate', links.prev)">
                {{ labels.previous }}
            </Button>
            <Button :disabled="!links?.next || loading" size="sm" variant="outline" @click="links?.next && emit('paginate', links.next)">
                {{ labels.next }}
            </Button>
        </div>
    </template>
</template>
