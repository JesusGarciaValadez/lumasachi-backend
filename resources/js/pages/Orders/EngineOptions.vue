<template>
    <div
        class="space-y-6 rounded-lg border border-gray-100 bg-white p-6 text-gray-900 shadow-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100"
    >
        <div class="flex items-start gap-3">
            <div>
                <p class="text-xs tracking-wide text-gray-500 uppercase dark:text-gray-400">Catálogo</p>
                <h1 class="text-2xl font-semibold">Engine Options</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Componentes y servicios filtrables por tipo de pieza.</p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <button
                    :disabled="isLoading"
                    class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                    @click="refresh"
                >
                    {{ isLoading ? 'Cargando…' : 'Refrescar' }}
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm text-gray-700 dark:text-gray-200">Tipo de ítem</label>
            <select
                v-model="selectedType"
                class="rounded border border-gray-200 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/70 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-indigo-400"
                @change="loadForType"
            >
                <option v-for="t in itemTypes" :key="t.key" :value="t.key">{{ t.label }}</option>
            </select>
            <span class="text-xs text-gray-500 dark:text-gray-400">Idioma: {{ langDisplay }}</span>
        </div>

        <div
            v-if="error"
            class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/40 dark:text-red-100"
        >
            {{ error }}
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium">Componentes</h2>
                    <span v-if="isLoading" class="text-xs text-gray-500 dark:text-gray-400">Cargando…</span>
                </div>
                <div v-if="isLoading" class="space-y-2">
                    <div class="h-4 w-3/4 animate-pulse rounded bg-gray-200 dark:bg-gray-800"></div>
                    <div class="h-4 w-1/2 animate-pulse rounded bg-gray-200 dark:bg-gray-800"></div>
                    <div class="h-4 w-2/3 animate-pulse rounded bg-gray-200 dark:bg-gray-800"></div>
                </div>
                <ul v-else class="list-inside list-disc space-y-1 text-sm">
                    <li v-for="c in components" :key="c.key">{{ c.label }}</li>
                    <li v-if="components.length === 0" class="text-gray-500 dark:text-gray-400">Sin componentes para este tipo.</li>
                </ul>
            </div>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium">Servicios</h2>
                    <span v-if="isLoading" class="text-xs text-gray-500 dark:text-gray-400">Cargando…</span>
                </div>
                <div class="overflow-hidden rounded border border-gray-200 dark:border-gray-800">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr class="text-left">
                                <th class="px-3 py-2">Servicio</th>
                                <th class="px-3 py-2 text-right">Base</th>
                                <th class="px-3 py-2 text-right">Neto</th>
                                <th class="px-3 py-2">Mide?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="isLoading">
                                <tr v-for="i in 4" :key="i" class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2"><div class="h-4 w-24 animate-pulse rounded bg-gray-200 dark:bg-gray-800"></div></td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="ml-auto h-4 w-12 animate-pulse rounded bg-gray-200 dark:bg-gray-800"></div>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="ml-auto h-4 w-12 animate-pulse rounded bg-gray-200 dark:bg-gray-800"></div>
                                    </td>
                                    <td class="px-3 py-2"><div class="h-4 w-10 animate-pulse rounded bg-gray-200 dark:bg-gray-800"></div></td>
                                </tr>
                            </template>
                            <tr v-else-if="services.length === 0" class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400" colspan="4">Sin servicios para este tipo.</td>
                            </tr>
                            <tr v-for="s in services" v-else :key="s.service_key" class="border-t border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2">{{ s.service_name }}</td>
                                <td class="px-3 py-2 text-right">{{ s.base_price }}</td>
                                <td class="px-3 py-2 text-right">{{ s.net_price }}</td>
                                <td class="px-3 py-2">{{ s.requires_measurement ? 'Sí' : 'No' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

interface ItemType {
    key: string;
    label: string;
}
interface ComponentItem {
    key: string;
    label: string;
}
interface ServiceItem {
    service_key: string;
    service_name: string;
    base_price: string;
    net_price: string;
    requires_measurement: boolean;
    display_order: number;
    item_type: string;
}

const selectedType = ref<string>('');
const itemTypes = ref<ItemType[]>([]);
const components = ref<ComponentItem[]>([]);
const services = ref<ServiceItem[]>([]);
const isLoading = ref(false);
const error = ref<string | null>(null);

function lang(): string {
    // Simplify navigator language to 'en' | 'es'
    const nav = (navigator.language || 'en').toLowerCase();
    if (nav.startsWith('es')) return 'es';
    return 'en';
}

const langDisplay = computed(() => (lang() === 'es' ? 'ES' : 'EN'));

function withLocale(url: string): string {
    const locale = lang();
    const hasQuery = url.includes('?');
    const separator = hasQuery ? '&' : '?';
    return `${url}${separator}locale=${encodeURIComponent(locale)}`;
}

async function fetchJson(url: string) {
    const res = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'Accept-Language': lang(),
        },
        credentials: 'same-origin',
    });
    if (!res.ok) {
        const message = `Request failed: ${res.status}`;
        throw new Error(message);
    }
    return await res.json();
}

async function loadFull() {
    try {
        isLoading.value = true;
        error.value = null;
        const url = withLocale(route('api.catalog.engine-options') as string);
        const data = await fetchJson(url);
        itemTypes.value = data.item_types || [];
        if (!itemTypes.value.length) {
            components.value = [];
            services.value = [];
            return;
        }

        const currentValid = itemTypes.value.find((t) => t.key === selectedType.value);
        selectedType.value = currentValid ? currentValid.key : itemTypes.value[0].key;

        components.value = data.components_by_type?.[selectedType.value] || [];
        services.value = data.services_by_type?.[selectedType.value] || [];
    } catch (e: any) {
        error.value = e?.message || 'No se pudo cargar el catálogo';
    } finally {
        isLoading.value = false;
    }
}

async function loadForType() {
    try {
        isLoading.value = true;
        error.value = null;
        if (!selectedType.value) return loadFull();
        const valid = itemTypes.value.find((t) => t.key === selectedType.value);
        if (!valid && itemTypes.value.length) {
            selectedType.value = itemTypes.value[0].key;
        }
        const base = route('api.catalog.engine-options') as string;
        const url = withLocale(`${base}?item_type=${encodeURIComponent(selectedType.value)}`);
        const data = await fetchJson(url);
        components.value = data.components || [];
        services.value = data.services || [];
    } catch (e: any) {
        error.value = e?.message || 'No se pudo cargar el catálogo';
    } finally {
        isLoading.value = false;
    }
}

async function refresh() {
    return selectedType.value ? loadForType() : loadFull();
}

onMounted(() => {
    loadFull().catch(() => {});
});
</script>
