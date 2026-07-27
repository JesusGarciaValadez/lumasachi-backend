<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OrderApiError, useOrderApi } from '@/composables/useOrderApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CatalogComponentOption, CatalogPayload, CreateOrderItemPayload, CreateOrderPayload, OrderItemType, UserPayload } from '@/types/orders';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface CreateFormState extends Omit<CreateOrderPayload, 'estimated_completion' | 'notes' | 'motor_info'> {
    estimated_completion: string;
    notes: string;
    motor_info: {
        brand: string;
        liters: string;
        year: string;
        model: string;
        cylinder_count: string;
        down_payment: string;
    };
}

const { t } = useI18n();
const orderApi = useOrderApi();
const catalog = ref<CatalogPayload | null>(null);
const customers = ref<UserPayload[]>([]);
const employees = ref<UserPayload[]>([]);
const loading = ref(true);
const processing = ref(false);
const error = ref<OrderApiError | null>(null);
const form = ref<CreateFormState>({
    customer_id: 0,
    title: '',
    description: '',
    priority: 'Normal',
    assigned_to: 0,
    estimated_completion: '',
    notes: '',
    motor_info: {
        brand: '',
        liters: '',
        year: '',
        model: '',
        cylinder_count: '',
        down_payment: '',
    },
    items: [{ item_type: 'engine_block', components: [] }],
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.orders'), href: route('web.orders.index') },
    { title: t('orders.create'), href: route('web.orders.create') },
]);

const itemTypeOptions = computed(() => catalog.value?.item_types ?? []);
const availableItemTypes = computed(() => {
    const selected = new Set(form.value.items.map((item) => item.item_type));

    return itemTypeOptions.value.filter((option) => !selected.has(option.key) || form.value.items.some((item) => item.item_type === option.key));
});

function displayName(user: UserPayload): string {
    return user.full_name ?? `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
}

function componentsFor(itemType: OrderItemType): CatalogComponentOption[] {
    return catalog.value?.components_by_type[itemType] ?? [];
}

function fieldError(key: string): string | undefined {
    return error.value?.validationErrors[key]?.[0];
}

function componentErrors(index: number): string[] {
    const prefix = `items.${index}.components`;

    return Object.entries(error.value?.validationErrors ?? {})
        .filter(([key]) => key === prefix || key.startsWith(`${prefix}.`))
        .flatMap(([, messages]) => messages);
}

function addItem(): void {
    const next = itemTypeOptions.value.find((option) => !form.value.items.some((item) => item.item_type === option.key));

    if (next) {
        form.value.items.push({ item_type: next.key, components: [] });
    }
}

function optionsFor(index: number) {
    const currentType = form.value.items[index]?.item_type;

    return itemTypeOptions.value.filter(
        (option) => option.key === currentType || !form.value.items.some((item, itemIndex) => itemIndex !== index && item.item_type === option.key),
    );
}

function removeItem(index: number): void {
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1);
    }
}

function changeItemType(item: CreateOrderItemPayload): void {
    const allowed = new Set(componentsFor(item.item_type).map((component) => component.key));
    item.components = (item.components ?? []).filter((component) => allowed.has(component));
}

function toggleComponent(item: CreateOrderItemPayload, componentKey: string, checked: boolean): void {
    const components = item.components ?? [];

    item.components = checked ? [...components, componentKey] : components.filter((component) => component !== componentKey);
}

function handleComponentChange(item: CreateOrderItemPayload, componentKey: string, event: Event): void {
    const target = event.target;

    if (target instanceof HTMLInputElement) {
        toggleComponent(item, componentKey, target.checked);
    }
}

async function submit(): Promise<void> {
    if (processing.value) {
        return;
    }

    processing.value = true;
    error.value = null;

    try {
        const order = await orderApi.create({
            ...form.value,
            estimated_completion: form.value.estimated_completion || null,
            notes: form.value.notes || null,
            motor_info: Object.fromEntries(
                Object.entries(form.value.motor_info).map(([key, value]) => [key, value || null]),
            ) as CreateOrderPayload['motor_info'],
        });

        router.visit(route('web.orders.show', order.uuid));
    } catch (caughtError: unknown) {
        error.value = caughtError instanceof OrderApiError ? caughtError : null;
    } finally {
        processing.value = false;
    }
}

onMounted(async () => {
    try {
        const [catalogResponse, customersResponse, employeesResponse] = await Promise.all([
            orderApi.catalog(),
            orderApi.customers(),
            orderApi.employees(),
        ]);

        catalog.value = catalogResponse;
        customers.value = customersResponse;
        employees.value = employeesResponse;
    } catch (caughtError: unknown) {
        error.value = caughtError instanceof OrderApiError ? caughtError : null;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head :title="t('orders.create')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div
                v-if="loading"
                aria-live="polite"
                class="relative min-h-[40vh] rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                {{ t('common.loading') }}
            </div>
            <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive" role="alert">
                    {{ error.message }}
                    <div v-if="Object.keys(error.validationErrors).length" class="mt-2 flex flex-col gap-1">
                        <span v-for="(messages, key) in error.validationErrors" :key="key">{{ key }}: {{ messages[0] }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <div class="flex flex-col gap-4 px-6">
                            <h1 class="text-xl font-semibold">{{ t('orders.order_details') }}</h1>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="flex flex-col gap-1 sm:col-span-2">
                                    <Label for="title">{{ t('orders.title') }}</Label
                                    ><Input
                                        id="title"
                                        v-model="form.title"
                                        :aria-describedby="fieldError('title') ? 'title-error' : undefined"
                                        :aria-invalid="Boolean(fieldError('title'))"
                                        required
                                    />
                                    <p v-if="fieldError('title')" id="title-error" class="text-sm text-destructive">{{ fieldError('title') }}</p>
                                </div>
                                <div class="flex flex-col gap-1 sm:col-span-2">
                                    <Label for="description">{{ t('orders.description') }}</Label
                                    ><textarea
                                        id="description"
                                        v-model="form.description"
                                        :aria-describedby="fieldError('description') ? 'description-error' : undefined"
                                        :aria-invalid="Boolean(fieldError('description'))"
                                        class="border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        required
                                        rows="4"
                                    />
                                    <p v-if="fieldError('description')" id="description-error" class="text-sm text-destructive">
                                        {{ fieldError('description') }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="priority">{{ t('orders.priority') }}</Label
                                    ><select
                                        id="priority"
                                        v-model="form.priority"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option value="Low">{{ t('orders.priority_labels.Low') }}</option>
                                        <option value="Normal">{{ t('orders.priority_labels.Normal') }}</option>
                                        <option value="High">{{ t('orders.priority_labels.High') }}</option>
                                        <option value="Urgent">{{ t('orders.priority_labels.Urgent') }}</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="estimated_completion">{{ t('orders.estimated_completion') }}</Label
                                    ><Input id="estimated_completion" v-model="form.estimated_completion" type="date" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="customer_id">{{ t('orders.customer') }}</Label
                                    ><select
                                        id="customer_id"
                                        v-model.number="form.customer_id"
                                        :aria-describedby="fieldError('customer_id') ? 'customer-id-error' : undefined"
                                        :aria-invalid="Boolean(fieldError('customer_id'))"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        required
                                    >
                                        <option :value="0" disabled>{{ t('orders.select_customer') }}</option>
                                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                            {{ displayName(customer) }}
                                        </option>
                                    </select>
                                    <p v-if="fieldError('customer_id')" id="customer-id-error" class="text-sm text-destructive">
                                        {{ fieldError('customer_id') }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="assigned_to">{{ t('orders.assigned_to') }}</Label
                                    ><select
                                        id="assigned_to"
                                        v-model.number="form.assigned_to"
                                        :aria-describedby="fieldError('assigned_to') ? 'assigned-to-error' : undefined"
                                        :aria-invalid="Boolean(fieldError('assigned_to'))"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        required
                                    >
                                        <option :value="0" disabled>{{ t('orders.select_employee') }}</option>
                                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                            {{ displayName(employee) }}
                                        </option>
                                    </select>
                                    <p v-if="fieldError('assigned_to')" id="assigned-to-error" class="text-sm text-destructive">
                                        {{ fieldError('assigned_to') }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1 sm:col-span-2">
                                    <Label for="notes">{{ t('orders.notes') }}</Label
                                    ><textarea
                                        id="notes"
                                        v-model="form.notes"
                                        class="border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        rows="3"
                                    />
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <div class="flex flex-col gap-4 px-6">
                            <h2 class="text-base font-semibold">{{ t('orders.motor_information') }}</h2>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="flex flex-col gap-1">
                                    <Label for="brand">{{ t('orders.brand') }}</Label
                                    ><Input id="brand" v-model="form.motor_info!.brand" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="liters">{{ t('orders.liters') }}</Label
                                    ><Input id="liters" v-model="form.motor_info!.liters" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="year">{{ t('orders.year') }}</Label
                                    ><Input id="year" v-model="form.motor_info!.year" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="model">{{ t('orders.model') }}</Label
                                    ><Input id="model" v-model="form.motor_info!.model" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="cylinder_count">{{ t('orders.cylinder_count') }}</Label
                                    ><Input id="cylinder_count" v-model="form.motor_info!.cylinder_count" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="down_payment">{{ t('orders.advance_payment') }}</Label
                                    ><Input
                                        id="down_payment"
                                        v-model="form.motor_info!.down_payment"
                                        :aria-describedby="fieldError('motor_info.down_payment') ? 'down-payment-error' : undefined"
                                        :aria-invalid="Boolean(fieldError('motor_info.down_payment'))"
                                        inputmode="decimal"
                                        min="0"
                                        step="0.01"
                                        type="number"
                                    />
                                    <p v-if="fieldError('motor_info.down_payment')" id="down-payment-error" class="text-sm text-destructive">
                                        {{ fieldError('motor_info.down_payment') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                <Card>
                    <div class="flex flex-col gap-4 px-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold">{{ t('orders.received_items') }}</h2>
                                <p class="text-sm text-muted-foreground">{{ t('orders.received_items_help') }}</p>
                            </div>
                            <Button :disabled="availableItemTypes.length === 0" type="button" variant="outline" @click="addItem">{{
                                t('orders.add_item')
                            }}</Button>
                        </div>
                        <div v-for="(item, index) in form.items" :key="index" class="flex flex-col gap-4 rounded-md border p-4">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="flex min-w-56 flex-1 flex-col gap-1">
                                    <Label :for="`item-type-${index}`">{{ t('orders.item_type') }}</Label
                                    ><select
                                        :id="`item-type-${index}`"
                                        v-model="item.item_type"
                                        :aria-describedby="fieldError(`items.${index}.item_type`) ? `item-type-${index}-error` : undefined"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        @change="changeItemType(item)"
                                    >
                                        <option v-for="option in optionsFor(index)" :key="option.key" :value="option.key">{{ option.label }}</option>
                                    </select>
                                    <p
                                        v-if="fieldError(`items.${index}.item_type`)"
                                        :id="`item-type-${index}-error`"
                                        class="text-sm text-destructive"
                                    >
                                        {{ fieldError(`items.${index}.item_type`) }}
                                    </p>
                                </div>
                                <Button v-if="form.items.length > 1" type="button" variant="ghost" @click="removeItem(index)">{{
                                    t('orders.remove_item')
                                }}</Button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <label
                                    v-for="component in componentsFor(item.item_type)"
                                    :key="component.key"
                                    class="flex items-center gap-2 rounded-md border p-3 text-sm"
                                    ><input
                                        :checked="item.components?.includes(component.key)"
                                        :aria-describedby="componentErrors(index).length ? `item-components-${index}-error` : undefined"
                                        class="size-4 rounded border-input text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        type="checkbox"
                                        @change="handleComponentChange(item, component.key, $event)"
                                    />{{ component.label }}</label
                                >
                            </div>
                            <div v-if="componentErrors(index).length" :id="`item-components-${index}-error`" class="flex flex-col gap-1" role="alert">
                                <p
                                    v-for="(message, messageIndex) in componentErrors(index)"
                                    :key="`${index}-${messageIndex}-${message}`"
                                    class="text-sm text-destructive"
                                >
                                    {{ message }}
                                </p>
                            </div>
                        </div>
                        <p v-if="fieldError('items')" class="text-sm text-destructive">{{ fieldError('items') }}</p>
                    </div>
                </Card>

                <div class="flex justify-end">
                    <Button :disabled="processing" type="submit">{{ processing ? t('common.loading') : t('orders.create') }}</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
