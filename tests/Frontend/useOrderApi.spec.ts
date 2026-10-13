import { OrderApiError, useOrderApi } from '@/composables/useOrderApi';
import type { CreateOrderPayload } from '@/types/orders';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('ziggy-js', () => ({
    route: (name: string) => `/${name}`,
}));

const fetchMock = vi.fn<(input: RequestInfo | URL, init?: RequestInit) => Promise<Response>>();
const createOrderPayload: CreateOrderPayload = {
    customer_id: 1,
    title: 'Order title',
    description: '',
    priority: 'Normal',
    assigned_to: 1,
    items: [],
};

describe('useOrderApi', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; Path=/';
        fetchMock.mockReset().mockResolvedValue({
            ok: false,
            status: 419,
            json: async () => ({ message: 'CSRF token mismatch.' }),
        } as Response);
        vi.stubGlobal('fetch', fetchMock);
    });

    it('sends the decoded XSRF cookie with API requests', async () => {
        document.cookie = 'XSRF-TOKEN=encrypted%3Dcookie%2Bvalue; Path=/';

        await expect(useOrderApi().create(createOrderPayload)).rejects.toBeInstanceOf(OrderApiError);

        const requestOptions = fetchMock.mock.calls[0]?.[1];

        expect(new Headers(requestOptions?.headers).get('X-XSRF-TOKEN')).toBe('encrypted=cookie+value');
        expect(requestOptions?.credentials).toBe('same-origin');
    });

    it('does not add an empty XSRF header when the cookie is absent', async () => {
        await expect(useOrderApi().create(createOrderPayload)).rejects.toBeInstanceOf(OrderApiError);

        const requestOptions = fetchMock.mock.calls[0]?.[1];

        expect(new Headers(requestOptions?.headers).has('X-XSRF-TOKEN')).toBe(false);
    });
});
