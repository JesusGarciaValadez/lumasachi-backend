<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Card } from '@/components/ui/card';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { useI18n } from 'vue-i18n';
import { computed, onMounted, ref } from 'vue';

interface UserResource { id: number; full_name: string; email: string; }
interface CategoryResource { data: Array<{ id: number; name: string; color?: string | null; }> }
interface OrderResourceTs {
  id?: number;
  uuid?: string;
  customer?: UserResource | null;
  title?: string;
  description?: string | null;
  status?: string;
  priority?: string;
  categories?: CategoryResource;
  estimated_completion?: string | null;
  actual_completion?: string | null;
  notes?: string | null;
  created_by?: UserResource | null;
  updated_by?: UserResource | null;
  assigned_to?: UserResource | null;
  created_at?: string;
  updated_at?: string;
}

const props = defineProps<{ order: any }>();
const i18n = useI18n();
const { t } = i18n;

// Normalize possible shapes: {uuid:..} or {data:{uuid:..}}
const order = computed<OrderResourceTs>(() => {
  const raw: any = props.order ?? {};
  if (raw && typeof raw === 'object' && 'uuid' in raw) return raw as OrderResourceTs;
  if (raw && typeof raw === 'object' && 'data' in raw) return (raw.data ?? {}) as OrderResourceTs;
  return raw as OrderResourceTs;
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: t('common.orders'), href: '/orders' },
  { title: `${t('orders.order')} #${order.value?.uuid ?? ''}`, href: `/orders/${order.value?.uuid ?? ''}` },
]);

const attachments = ref<any[]>([]);
const attachmentsLoading = ref(true);
const history = ref<any>({ data: [], meta: null });
const historyLoading = ref(true);

const orderUuid = computed(() => order.value?.uuid ?? '');

function formatDate(value?: string | null) {
  if (!value) return '—';
  try { return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); } catch { return value; }
}

function labelForStatus(status: string) {
  // tm returns raw message tree, safer for keys with spaces
  const map = (i18n.tm('orders.status_labels') as any) as Record<string, string>;
  return map?.[status] ?? status;
}
function labelForPriority(priority: string) {
  const map = (i18n.tm('orders.priority_labels') as any) as Record<string, string>;
  return map?.[priority] ?? priority;
}

onMounted(async () => {
  // Fetch attachments
  try {
    const res = await fetch(`/api/v1/orders/${orderUuid.value}/attachments`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    const json = await res.json().catch(() => null);
    attachments.value = json?.attachments ?? [];
  } catch (error: unknown) {
    console.error('Error fetching attachments', error);
    attachments.value = [];
  } finally {
    attachmentsLoading.value = false;
  }

  // Fetch history (first page)
  try {
    const res = await fetch(`/api/v1/orders/${orderUuid.value}/history`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    const json = await res.json().catch(() => null);
    history.value = json ?? { data: [] };
  } catch (error: unknown) {
    console.error('Error fetching history', error);
    history.value = { data: [] };
  } finally {
    historyLoading.value = false;
  }
});
</script>

<template>
  <Head :title="`${t('orders.order')} #${order?.uuid ?? ''}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <!-- Header info -->
      <Card>
        <div class="px-6">
          <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
              <h1 class="text-xl md:text-2xl font-semibold">{{ order?.title ?? '' }}</h1>
              <p class="text-sm text-muted-foreground mt-1">#{{ order?.uuid ?? '' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                {{ t('orders.status') }}: {{ labelForStatus(order.status) }}
              </span>
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                {{ t('orders.priority') }}: {{ labelForPriority(order.priority) }}
              </span>
            </div>
          </div>

          <!-- Description -->
          <div class="mt-6">
            <div class="text-sm text-muted-foreground mb-2">{{ t('orders.description') }}</div>
            <h2 class="text-md md:text-md font-semibold">{{ order?.description ?? '' }}</h2>
          </div>

          <!-- Meta grid -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 text-sm">
            <div>
              <div class="text-muted-foreground">{{ t('orders.customer') }}</div>
              <div>{{ order?.customer?.data?.full_name ?? '—' }}</div>
            </div>
            <div>
              <div class="text-muted-foreground">{{ t('orders.assigned_to') }}</div>
              <div>{{ order?.assigned_to?.data?.full_name ?? '—' }}</div>
            </div>
            <div>
              <div class="text-muted-foreground">{{ t('orders.created_at') }}</div>
              <div>{{ formatDate(order?.created_at) }}</div>
            </div>
            <div>
              <div class="text-muted-foreground">{{ t('orders.estimated_completion') }}</div>
              <div>{{ formatDate(order?.estimated_completion) }}</div>
            </div>
            <div>
              <div class="text-muted-foreground">{{ t('orders.actual_completion') }}</div>
              <div>{{ formatDate(order?.actual_completion) }}</div>
            </div>
          </div>

          <!-- Categories -->
          <div class="mt-6">
            <div class="text-sm text-muted-foreground mb-2">{{ t('orders.categories') }}</div>
            <div class="flex flex-wrap gap-2">
              <template v-if="order?.categories && order?.categories?.data?.length">
                <span v-for="c in order.categories?.data" :key="c.id" class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs">
                  <span class="mr-2 inline-block size-2 rounded-full" :style="{ backgroundColor: c.color || '#999' }"></span>
                  {{ c.name }}
                </span>
              </template>
              <span v-else class="text-sm text-muted-foreground">—</span>
            </div>
          </div>

          <!-- Notes -->
          <div class="mt-6">
            <div class="text-sm text-muted-foreground mb-2">{{ t('orders.notes') }}</div>
            <div v-if="order?.notes" class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap">{{ order?.notes }}</div>
            <div v-else class="text-sm text-muted-foreground">{{ t('orders.no_notes') }}</div>
          </div>
        </div>
      </Card>

      <!-- Attachments and History -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
          <div class="px-6">
            <h2 class="text-base font-semibold mb-3">{{ t('orders.attachments') }}</h2>
            <div v-if="attachmentsLoading" class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
              <PlaceholderPattern />
            </div>
            <div v-else>
              <div v-if="attachments.length" class="space-y-2">
                <div v-for="a in attachments" :key="a.uuid" class="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                  <div class="truncate">
                    <div class="font-medium truncate">{{ a.file_name }}</div>
                    <div class="text-muted-foreground text-xs">{{ a.human_file_size }}</div>
                  </div>
                  <div class="flex items-center gap-2">
                    <a :href="`/api/v1/attachments/${a.uuid}/preview`" target="_blank" class="underline text-sm">Preview</a>
                    <a :href="`/api/v1/attachments/${a.uuid}/download`" class="underline text-sm">Download</a>
                  </div>
                </div>
              </div>
              <div v-else class="text-sm text-muted-foreground">{{ t('common.empty') }}</div>
            </div>
          </div>
        </Card>

        <Card>
          <div class="px-6">
            <h2 class="text-base font-semibold mb-3">{{ t('orders.history') }}</h2>
            <div v-if="historyLoading" class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
              <PlaceholderPattern />
            </div>
            <div v-else>
              <div v-if="history.data?.length" class="space-y-3">
                <div v-for="h in history.data" :key="h.uuid" class="rounded-md border p-3 text-sm">
                  <div class="text-xs text-muted-foreground">{{ formatDate(h.created_at) }} • {{ h.creator?.full_name ?? '' }}</div>
                  <div class="mt-1">{{ h.description }}</div>
                </div>
              </div>
              <div v-else class="text-sm text-muted-foreground">{{ t('common.empty') }}</div>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
