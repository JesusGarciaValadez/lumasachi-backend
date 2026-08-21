<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OrderApiError, useOrderApi } from '@/composables/useOrderApi';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import type {
    CatalogComponentOption,
    CatalogPayload,
    CreateOrderItemPayload,
    CreateOrderPayload,
    OrderCreateCompanyOption,
    OrderItemType,
    OrderParticipantPayload,
} from '@/types/orders';
import { Head, router, usePage } from '@inertiajs/vue3';
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
    };
}

const { t } = useI18n();
const orderApi = useOrderApi();
const page = usePage<AppPageProps>();
const catalog = ref<CatalogPayload | null>(null);
const companies = ref<OrderCreateCompanyOption[]>([]);
const customers = ref<OrderParticipantPayload[]>([]);
const employees = ref<OrderParticipantPayload[]>([]);
const loading = ref(true);
const companiesLoading = ref(false);
const participantsLoading = ref(false);
const processing = ref(false);
const error = ref<OrderApiError | null>(null);
const participantError = ref<OrderApiError | null>(null);
let participantRequestSequence = 0;
const form = ref<CreateFormState>({
    company_id: null,
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
    },
    items: [{ item_type: 'engine_block', components: [] }],
});

const isSuperAdministrator = computed(() => page.props.auth.user.role === 'Super Administrator');
const selectedCompanyId = computed<number | undefined>(() => {
    const companyId = form.value.company_id;

    return typeof companyId === 'number' && companyId > 0 ? companyId : undefined;
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

function displayName(user: OrderParticipantPayload): string {
    return user.full_name || `${user.first_name} ${user.last_name}`.trim();
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

function resetParticipants(): void {
    participantRequestSequence += 1;
    participantsLoading.value = false;
    participantError.value = null;
    customers.value = [];
    employees.value = [];
}

function participantLookupError(caughtError: unknown): OrderApiError {
    return caughtError instanceof OrderApiError ? caughtError : new OrderApiError(0, t('orders.participant_lookup_failed'));
}

async function loadParticipants(companyId?: number): Promise<void> {
    const requestSequence = ++participantRequestSequence;
    const customersRequest = companyId === undefined ? orderApi.customers() : orderApi.customers(companyId);
    const employeesRequest = companyId === undefined ? orderApi.employees() : orderApi.employees(companyId);

    participantsLoading.value = true;
    participantError.value = null;
    customers.value = [];
    employees.value = [];

    try {
        const [customersResponse, employeesResponse] = await Promise.all([customersRequest, employeesRequest]);

        if (requestSequence !== participantRequestSequence) {
            return;
        }

        customers.value = customersResponse;
        employees.value = employeesResponse;
    } catch (caughtError: unknown) {
        if (requestSequence !== participantRequestSequence) {
            return;
        }

        participantError.value = participantLookupError(caughtError);
    } finally {
        if (requestSequence === participantRequestSequence) {
            participantsLoading.value = false;
        }
    }
}

function handleCompanyChange(): void {
    form.value.customer_id = 0;
    form.value.assigned_to = 0;
    error.value = null;

    if (selectedCompanyId.value === undefined) {
        resetParticipants();

        return;
    }

    void loadParticipants(selectedCompanyId.value);
}

function createPayload(): CreateOrderPayload {
    const payload: CreateOrderPayload = {
        ...form.value,
        estimated_completion: form.value.estimated_completion || null,
        notes: form.value.notes || null,
        motor_info: Object.fromEntries(
            Object.entries(form.value.motor_info).map(([key, value]) => [key, value || null]),
        ) as CreateOrderPayload['motor_info'],
    };

    if (!isSuperAdministrator.value || form.value.company_id === null || form.value.company_id === undefined) {
        delete payload.company_id;
    }

    return payload;
}

async function submit(): Promise<void> {
    if (processing.value) {
        return;
    }

    processing.value = true;
    error.value = null;

    try {
        await orderApi.create(createPayload());

        router.visit(route('web.orders.index'), {
            onSuccess: () => router.flash('success', t('orders.created')),
        });
    } catch (caughtError: unknown) {
        error.value = caughtError instanceof OrderApiError ? caughtError : null;
    } finally {
        processing.value = false;
    }
}

async function initialize(): Promise<void> {
    try {
        const catalogRequest = orderApi.catalog();

        if (isSuperAdministrator.value) {
            companiesLoading.value = true;

            const [catalogResponse, companiesResponse] = await Promise.all([catalogRequest, orderApi.companies()]);

            catalog.value = catalogResponse;
            companies.value = companiesResponse;
        } else {
            const [catalogResponse] = await Promise.all([catalogRequest, loadParticipants()]);

            catalog.value = catalogResponse;
        }
    } catch (caughtError: unknown) {
        error.value = caughtError instanceof OrderApiError ? caughtError : new OrderApiError(0, t('orders.order_form_load_failed'));
    } finally {
        companiesLoading.value = false;
        loading.value = false;
    }
}

onMounted(initialize);
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
            <form v-else class="flex flex-col gap-4" dusk="order-create-form" @submit.prevent="submit">
                <div
                    v-if="error"
                    class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                    dusk="order-create-error"
                    role="alert"
                >
                    {{ error.message }}
                    <div v-if="Object.keys(error.validationErrors).length" class="mt-2 flex flex-col gap-1">
                        <span v-for="(messages, key) in error.validationErrors" :key="key">{{ key }}: {{ messages[0] }}</span>
                    </div>
                </div>
                <div
                    v-if="participantError"
                    aria-live="polite"
                    class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                    dusk="order-participant-error"
                    role="alert"
                >
                    {{ participantError.message }}
                </div>

                <Card v-if="isSuperAdministrator">
                    <div class="flex flex-col gap-4 px-6">
                        <div class="flex flex-col gap-1">
                            <Label for="company_id">{{ t('orders.company') }}</Label>
                            <select
                                id="company_id"
                                v-model.number="form.company_id"
                                :aria-describedby="fieldError('company_id') ? 'company-id-error' : undefined"
                                :aria-invalid="Boolean(fieldError('company_id'))"
                                :disabled="companiesLoading || processing"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                dusk="order-company"
                                required
                                @change="handleCompanyChange"
                            >
                                <option :value="null" disabled>
                                    {{ companiesLoading ? t('orders.loading_companies') : t('orders.select_company') }}
                                </option>
                                <option v-for="company in companies" :key="company.id" :value="company.id">
                                    {{ company.name }}
                                </option>
                            </select>
                            <p v-if="!companiesLoading && !companies.length && !error" aria-live="polite" class="text-sm text-muted-foreground">
                                {{ t('orders.no_companies') }}
                            </p>
                            <p v-if="fieldError('company_id')" id="company-id-error" class="text-sm text-destructive">
                                {{ fieldError('company_id') }}
                            </p>
                        </div>
                    </div>
                </Card>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <div class="flex flex-col gap-4 px-6">
                            <h1 class="text-xl font-semibold">{{ t('orders.order_details') }}</h1>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="flex flex-col gap-1 sm:col-span-2">
                                    <Label for="title">{{ t('orders.title') }}</Label
                                    ><Input
                                        id="title"
                                        dusk="order-title"
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
                                        dusk="order-description"
                                        v-model="form.description"
                                        :aria-describedby="fieldError('description') ? 'description-error' : undefined"
                                        :aria-invalid="Boolean(fieldError('description'))"
                                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
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
                                        dusk="order-priority"
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
                                        dusk="order-customer"
                                        v-model.number="form.customer_id"
                                        :aria-describedby="fieldError('customer_id') ? 'customer-id-error' : undefined"
                                        :aria-invalid="Boolean(fieldError('customer_id'))"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :disabled="
                                            participantsLoading || customers.length === 0 || (isSuperAdministrator && selectedCompanyId === undefined)
                                        "
                                        required
                                    >
                                        <option :value="0" disabled>
                                            {{
                                                participantsLoading
                                                    ? t('orders.loading_participants')
                                                    : isSuperAdministrator && selectedCompanyId === undefined
                                                      ? t('orders.select_company_first')
                                                      : customers.length === 0
                                                        ? t('orders.no_customers')
                                                        : t('orders.select_customer')
                                            }}
                                        </option>
                                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                            {{ displayName(customer) }}
                                        </option>
                                    </select>
                                    <p v-if="participantsLoading" aria-live="polite" class="text-sm text-muted-foreground">
                                        {{ t('orders.loading_participants') }}
                                    </p>
                                    <p
                                        v-else-if="isSuperAdministrator && selectedCompanyId === undefined"
                                        aria-live="polite"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t('orders.select_company_first') }}
                                    </p>
                                    <p
                                        v-else-if="!participantError && customers.length === 0"
                                        aria-live="polite"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t('orders.no_customers') }}
                                    </p>
                                    <p v-if="fieldError('customer_id')" id="customer-id-error" class="text-sm text-destructive">
                                        {{ fieldError('customer_id') }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="assigned_to">{{ t('orders.assigned_to') }}</Label
                                    ><select
                                        id="assigned_to"
                                        dusk="order-assignee"
                                        v-model.number="form.assigned_to"
                                        :aria-describedby="fieldError('assigned_to') ? 'assigned-to-error' : undefined"
                                        :aria-invalid="Boolean(fieldError('assigned_to'))"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :disabled="
                                            participantsLoading || employees.length === 0 || (isSuperAdministrator && selectedCompanyId === undefined)
                                        "
                                        required
                                    >
                                        <option :value="0" disabled>
                                            {{
                                                participantsLoading
                                                    ? t('orders.loading_participants')
                                                    : isSuperAdministrator && selectedCompanyId === undefined
                                                      ? t('orders.select_company_first')
                                                      : employees.length === 0
                                                        ? t('orders.no_employees')
                                                        : t('orders.select_employee')
                                            }}
                                        </option>
                                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                            {{ displayName(employee) }}
                                        </option>
                                    </select>
                                    <p v-if="participantsLoading" aria-live="polite" class="text-sm text-muted-foreground">
                                        {{ t('orders.loading_participants') }}
                                    </p>
                                    <p
                                        v-else-if="isSuperAdministrator && selectedCompanyId === undefined"
                                        aria-live="polite"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t('orders.select_company_first') }}
                                    </p>
                                    <p
                                        v-else-if="!participantError && employees.length === 0"
                                        aria-live="polite"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t('orders.no_employees') }}
                                    </p>
                                    <p v-if="fieldError('assigned_to')" id="assigned-to-error" class="text-sm text-destructive">
                                        {{ fieldError('assigned_to') }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1 sm:col-span-2">
                                    <Label for="notes">{{ t('orders.notes') }}</Label
                                    ><textarea
                                        id="notes"
                                        v-model="form.notes"
                                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
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
                                    ><Input id="brand" v-model="form.motor_info!.brand" dusk="motor-brand" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="liters">{{ t('orders.liters') }}</Label
                                    ><Input id="liters" v-model="form.motor_info!.liters" dusk="motor-liters" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="year">{{ t('orders.year') }}</Label
                                    ><Input id="year" v-model="form.motor_info!.year" dusk="motor-year" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="model">{{ t('orders.model') }}</Label
                                    ><Input id="model" v-model="form.motor_info!.model" dusk="motor-model" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label for="cylinder_count">{{ t('orders.cylinder_count') }}</Label
                                    ><Input id="cylinder_count" v-model="form.motor_info!.cylinder_count" dusk="motor-cylinder-count" />
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
                                        :dusk="`order-item-type-${index}`"
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
                                        :dusk="`order-item-component-${index}-${component.key}`"
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
                    <Button :disabled="processing" dusk="order-create-submit" type="submit">{{
                        processing ? t('common.loading') : t('orders.create')
                    }}</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
