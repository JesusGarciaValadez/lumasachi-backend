<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Card } from '@/components/ui/card';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { useI18n } from 'vue-i18n';
import { onMounted, ref, computed } from 'vue';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
  { title: t('common.orders'), href: '/orders' },
];

const loading = ref(true);
const orders = ref<any[]>([]);

function formatDate(value?: string | null) {
  if (!value) return '—';
  try { return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); } catch { return value; }
}

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
  } catch (e) {
    orders.value = [];
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <Head :title="t('common.orders')" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <Card>
        <div class="px-6 py-2">
          <h1 class="text-xl font-semibold mb-4">{{ t('common.orders') }}</h1>
          <div v-if="loading" class="relative min-h-[40vh] rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <PlaceholderPattern />
          </div>
          <div v-else>
            <div v-if="orders.length" class="divide-y">
              <div v-for="o in orders" :key="o.uuid" class="py-3 flex items-center justify-between gap-4">
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
