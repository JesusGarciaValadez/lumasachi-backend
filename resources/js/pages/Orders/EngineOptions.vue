<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-semibold">Engine Options Catalog</h1>

    <div class="flex items-center gap-3">
      <label class="text-sm text-gray-600">Item type</label>
      <select v-model="selectedType" @change="loadForType" class="border rounded px-3 py-2">
        <option v-for="t in itemTypes" :key="t.key" :value="t.key">{{ t.label }}</option>
      </select>
      <button @click="refresh" class="ml-auto text-sm px-3 py-2 rounded bg-gray-100 hover:bg-gray-200">Refresh</button>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
      <div>
        <h2 class="text-lg font-medium mb-2">Components</h2>
        <ul class="list-disc list-inside space-y-1">
          <li v-for="c in components" :key="c.key">{{ c.label }}</li>
        </ul>
      </div>
      <div>
        <h2 class="text-lg font-medium mb-2">Services</h2>
        <table class="w-full text-sm border">
          <thead>
            <tr class="bg-gray-50 text-left">
              <th class="px-3 py-2">Service</th>
              <th class="px-3 py-2 text-right">Base</th>
              <th class="px-3 py-2 text-right">Net</th>
              <th class="px-3 py-2">Measure?</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in services" :key="s.service_key" class="border-t">
              <td class="px-3 py-2">{{ s.service_name }}</td>
              <td class="px-3 py-2 text-right">{{ s.base_price }}</td>
              <td class="px-3 py-2 text-right">{{ s.net_price }}</td>
              <td class="px-3 py-2">{{ s.requires_measurement ? 'Yes' : 'No' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import route from 'ziggy-js'

interface ItemType { key: string; label: string }
interface ComponentItem { key: string; label: string }
interface ServiceItem {
  service_key: string
  service_name: string
  base_price: string
  net_price: string
  requires_measurement: boolean
  display_order: number
  item_type: string
}

const selectedType = ref<string>('engine_block')
const itemTypes = ref<ItemType[]>([])
const components = ref<ComponentItem[]>([])
const services = ref<ServiceItem[]>([])

function lang(): string {
  // Simplify navigator language to 'en' | 'es'
  const nav = (navigator.language || 'en').toLowerCase()
  if (nav.startsWith('es')) return 'es'
  return 'en'
}

async function fetchJson(url: string) {
  const res = await fetch(url, {
    headers: {
      'Accept': 'application/json',
      'Accept-Language': lang(),
    },
    credentials: 'same-origin',
  })
  if (!res.ok) throw new Error(`Request failed: ${res.status}`)
  return await res.json()
}

async function loadFull() {
  const url = route('api.catalog.engine-options') as string
  const data = await fetchJson(url)
  itemTypes.value = data.item_types || []
  // pick default type if exists
  if (!selectedType.value && itemTypes.value.length) {
    selectedType.value = itemTypes.value[0].key
  }
  // Also fill from services_by_type/components_by_type if present
  if (selectedType.value) {
    components.value = (data.components_by_type?.[selectedType.value] || [])
    services.value = (data.services_by_type?.[selectedType.value] || [])
  }
}

async function loadForType() {
  if (!selectedType.value) return loadFull()
  const url = `${route('api.catalog.engine-options') as string}?item_type=${encodeURIComponent(selectedType.value)}`
  const data = await fetchJson(url)
  components.value = data.components || []
  services.value = data.services || []
}

async function refresh() {
  if (selectedType.value) return loadForType()
  return loadFull()
}

onMounted(async () => {
  try {
    await loadFull()
    await loadForType()
  } catch (e) {
    console.error(e)
  }
})
</script>
