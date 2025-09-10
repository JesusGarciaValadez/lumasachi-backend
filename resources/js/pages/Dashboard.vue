<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Card } from '@/components/ui/card';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { onMounted, ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('common.dashboard'),
        href: '/dashboard',
    },
];

const loading = ref(true);
const orders = ref<any[]>([]);

function formatDate(value?: string | null) {
  if (!value) return '—';
  try { return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); } catch { return value; }
}

const recentFive = computed(() => {
  const list = [...orders.value];
  list.sort((a,b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
  return list.slice(0,5);
});

onMounted(async () => {
  try {
    const res = await fetch('/api/v1/orders', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    const json = await res.json().catch(() => null);
    orders.value = Array.isArray(json?.data)
      ? json.data
      : (Array.isArray(json) ? json : []);
  } catch (error: unknown) {
    console.error('Error fetching orders', error);
    orders.value = [];
  } finally {
    loading.value = false;
  }
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <Card>
              <div class="px-6 py-2">
                <div class="flex items-center justify-between mb-2">
                  <h2 class="text-base font-semibold">{{ t('common.recent_orders') }}</h2>
                  <Link :href="route('web.orders.index')" class="text-sm underline">{{ t('common.view_more') }}</Link>
                </div>
                <div v-if="loading" class="relative min-h-[30vh] rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                  <PlaceholderPattern />
                </div>
                <div v-else>
                  <div v-if="recentFive.length" class="divide-y">
                    <div v-for="o in recentFive" :key="o.uuid" class="py-3 flex items-center justify-between gap-4">
                      <div class="min-w-0">
                        <div class="font-medium truncate">{{ o.title }}</div>
                        <div class="text-xs text-muted-foreground truncate">
                          {{ t('orders.status') }}: {{ o.status }} • {{ t('orders.priority') }}: {{ o.priority }} • {{ t('orders.created_at') }}: {{ formatDate(o.created_at) }}
                        </div>
                      </div>
                      <Link :href="route('web.orders.show', o.uuid)" class="text-sm underline shrink-0">Ver</Link>
                    </div>
                  </div>
                  <div v-else class="text-sm text-muted-foreground">{{ t('common.empty') }}</div>
                </div>
              </div>
            </Card>
        </div>
    </AppLayout>
</template>
