import { normalizeLocale } from '@/i18n';
import {
    type CatalogPayload,
    type CreateOrderPayload,
    type CustomerApprovalPayload,
    normalizeAttachment,
    normalizeHistory,
    normalizeOrder,
    normalizePublicOrder,
    type Order,
    type OrderAttachmentsResponse,
    type OrderAttachmentsResponsePayload,
    type OrderHistoryPage,
    type OrderHistoryPagePayload,
    type OrderMutationResponse,
    type OrderPayload,
    type OrderSummary,
    type OrderSummaryCollectionPayload,
    type PublicOrder,
    type PublicOrderTrackingResponse,
    type ResourcePayload,
    type SubmitBudgetPayload,
    type TrackOrderPayload,
    unwrapCollection,
    type UserCollectionPayload,
    type UserPayload,
    type WorkCompletedPayload,
} from '@/types/orders';
import { route } from 'ziggy-js';

export type OrderApiErrorKind = 'conflict' | 'validation' | 'forbidden' | 'not_found' | 'rate_limit' | 'unexpected';

export class OrderApiError extends Error {
    readonly kind: OrderApiErrorKind;

    constructor(
        readonly status: number,
        message: string,
        readonly validationErrors: Record<string, string[]> = {},
        readonly code?: string,
    ) {
        super(message);
        this.name = 'OrderApiError';
        this.kind = orderApiErrorKind(status);
    }
}

export interface OrderApi {
    index(): Promise<OrderSummary[]>;
    show(orderUuid: string): Promise<Order>;
    create(payload: CreateOrderPayload): Promise<Order>;
    submitBudget(orderUuid: string, payload: SubmitBudgetPayload): Promise<Order>;
    approveServices(orderUuid: string, payload: CustomerApprovalPayload): Promise<Order>;
    completeServices(orderUuid: string, payload: WorkCompletedPayload): Promise<Order>;
    markReadyForDelivery(orderUuid: string): Promise<Order>;
    deliver(orderUuid: string, amount?: string): Promise<Order>;
    cancel(orderUuid: string): Promise<Order>;
    history(orderUuid: string, url?: string): Promise<OrderHistoryPage>;
    attachments(orderUuid: string): Promise<OrderAttachmentsResponse>;
    catalog(): Promise<CatalogPayload>;
    employees(): Promise<UserPayload[]>;
    customers(): Promise<UserPayload[]>;
    track(payload: TrackOrderPayload, signal?: AbortSignal): Promise<PublicOrder>;
}

interface LaravelErrorPayload {
    message?: unknown;
    code?: unknown;
    errors?: unknown;
}

function orderApiErrorKind(status: number): OrderApiErrorKind {
    if (status === 409) {
        return 'conflict';
    }

    if (status === 422) {
        return 'validation';
    }

    if (status === 403) {
        return 'forbidden';
    }

    if (status === 404) {
        return 'not_found';
    }

    if (status === 429) {
        return 'rate_limit';
    }

    return 'unexpected';
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function validationErrors(value: unknown): Record<string, string[]> {
    if (!isRecord(value)) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(value).map(([key, messages]) => [key, Array.isArray(messages) ? messages.map(String) : [String(messages)]]),
    );
}

function fallbackMessage(status: number): string {
    const locale = normalizeLocale(currentLocale());
    const messages = {
        es: {
            conflict: 'La orden cambió en otra sesión. Recarga e inténtalo de nuevo.',
            validation: 'Revisa los datos enviados.',
            forbidden: 'No tienes autorización para realizar esta acción.',
            not_found: 'No se encontró el recurso solicitado.',
            rate_limit: 'Demasiadas solicitudes. Intenta de nuevo más tarde.',
            unexpected: 'No fue posible completar la solicitud.',
        },
        en: {
            conflict: 'The order changed in another session. Reload and try again.',
            validation: 'Review the submitted data.',
            forbidden: 'You are not authorized to perform this action.',
            not_found: 'The requested resource was not found.',
            rate_limit: 'Too many requests. Try again later.',
            unexpected: 'The request could not be completed.',
        },
    } as const;

    return messages[locale][orderApiErrorKind(status)];
}

function errorMessage(payload: unknown, status: number): string {
    if (isRecord(payload) && typeof payload.message === 'string') {
        return payload.message;
    }

    return fallbackMessage(status);
}

function apiUrl(name: string, orderUuid?: string): string {
    return String(orderUuid ? route(name, { order: orderUuid }) : route(name));
}

async function request<T>(url: string, method: 'GET' | 'POST', body?: unknown, signal?: AbortSignal): Promise<T> {
    const headers: HeadersInit = {
        Accept: 'application/json',
        'Accept-Language': currentLocale(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    const options: RequestInit = {
        method,
        headers,
        credentials: 'same-origin',
        signal,
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    let response: Response;

    try {
        response = await fetch(url, options);
    } catch (error) {
        if (error instanceof Error && error.name === 'AbortError') {
            throw error;
        }

        throw new OrderApiError(0, fallbackMessage(0));
    }

    const payload: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        const errorPayload = isRecord(payload) ? (payload as LaravelErrorPayload) : {};

        throw new OrderApiError(
            response.status,
            errorMessage(payload, response.status),
            validationErrors(errorPayload.errors),
            typeof errorPayload.code === 'string' ? errorPayload.code : undefined,
        );
    }

    return payload as T;
}

function currentLocale(): string {
    if (typeof document !== 'undefined' && document.documentElement.lang) {
        return document.documentElement.lang;
    }

    return typeof navigator !== 'undefined' ? navigator.language : 'es';
}

function mutationOrder(payload: OrderMutationResponse): Order {
    return normalizeOrder(payload.order);
}

export function useOrderApi(): OrderApi {
    return {
        async index() {
            const payload = await request<OrderSummaryCollectionPayload>(apiUrl('api.orders.index'), 'GET');

            return unwrapCollection(payload);
        },

        async show(orderUuid) {
            const payload = await request<ResourcePayload<OrderPayload>>(apiUrl('api.orders.show', orderUuid), 'GET');

            return normalizeOrder(payload);
        },

        async create(payload) {
            return mutationOrder(await request<OrderMutationResponse>(apiUrl('api.orders.store'), 'POST', payload));
        },

        async submitBudget(orderUuid, payload) {
            return mutationOrder(await request<OrderMutationResponse>(apiUrl('api.orders.budget', orderUuid), 'POST', payload));
        },

        async approveServices(orderUuid, payload) {
            return mutationOrder(await request<OrderMutationResponse>(apiUrl('api.orders.customer-approval', orderUuid), 'POST', payload));
        },

        async completeServices(orderUuid, payload) {
            return mutationOrder(await request<OrderMutationResponse>(apiUrl('api.orders.work-completed', orderUuid), 'POST', payload));
        },

        async markReadyForDelivery(orderUuid) {
            return mutationOrder(await request<OrderMutationResponse>(apiUrl('api.orders.ready-for-delivery', orderUuid), 'POST'));
        },

        async deliver(orderUuid, amount) {
            return mutationOrder(
                await request<OrderMutationResponse>(apiUrl('api.orders.deliver', orderUuid), 'POST', amount === undefined ? undefined : { amount }),
            );
        },

        async cancel(orderUuid) {
            return mutationOrder(await request<OrderMutationResponse>(apiUrl('api.orders.cancel', orderUuid), 'POST'));
        },

        async history(orderUuid, url) {
            const payload = await request<OrderHistoryPagePayload>(url ?? apiUrl('api.orders.history', orderUuid), 'GET');

            return {
                ...payload,
                data: payload.data.map(normalizeHistory),
            };
        },

        async attachments(orderUuid) {
            const payload = await request<OrderAttachmentsResponsePayload>(apiUrl('api.orders.attachments.index', orderUuid), 'GET');

            return {
                ...payload,
                attachments: unwrapCollection(payload.attachments).map(normalizeAttachment),
            };
        },

        async catalog() {
            return request<CatalogPayload>(apiUrl('api.catalog.engine-options'), 'GET');
        },

        async employees() {
            return unwrapCollection(await request<UserCollectionPayload>(apiUrl('api.users.employees'), 'GET'));
        },

        async customers() {
            return unwrapCollection(await request<UserCollectionPayload>(apiUrl('api.users.customers'), 'GET'));
        },

        async track(payload, signal) {
            const response = await request<PublicOrderTrackingResponse>(apiUrl('api.orders.track'), 'POST', payload, signal);

            return normalizePublicOrder(response.order);
        },
    };
}
