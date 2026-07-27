export type OrderStatus =
    | 'Received'
    | 'Awaiting Review'
    | 'Reviewed'
    | 'Awaiting Customer Approval'
    | 'Ready for Work'
    | 'Open'
    | 'In Progress'
    | 'Ready for Delivery'
    | 'Completed'
    | 'Delivered'
    | 'Paid'
    | 'Returned'
    | 'Not Paid'
    | 'On Hold'
    | 'Cancelled';

export const ORDER_STATUS_SEQUENCE: OrderStatus[] = [
    'Received',
    'Awaiting Review',
    'Reviewed',
    'Awaiting Customer Approval',
    'Ready for Work',
    'Open',
    'In Progress',
    'Ready for Delivery',
    'Completed',
    'Delivered',
    'Paid',
    'Returned',
    'Not Paid',
    'On Hold',
    'Cancelled',
];

export type OrderPriority = 'Low' | 'Normal' | 'High' | 'Urgent';

export type OrderItemType = 'cylinder_head' | 'engine_block' | 'crankshaft' | 'connecting_rods' | 'others';

export type MoneyValue = string | number | null;

export interface ApiResource<T> {
    data: T;
}

export type ResourcePayload<T> = T | ApiResource<T>;

export interface UserPayload {
    id: number;
    uuid?: string;
    first_name?: string;
    last_name?: string;
    full_name?: string;
    email?: string;
    email_verified_at?: string | null;
    role?: string;
    type?: string;
    is_active?: boolean;
    phone_number?: string | null;
    notes?: string | null;
    preferences?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface MotorInfoPayload {
    id?: number;
    uuid?: string;
    order_id?: number;
    brand?: string | null;
    liters?: string | null;
    year?: string | null;
    model?: string | null;
    cylinder_count?: string | null;
    center_torque?: string | null;
    rod_torque?: string | null;
    first_gap?: string | null;
    second_gap?: string | null;
    third_gap?: string | null;
    center_clearance?: string | null;
    rod_clearance?: string | null;
    down_payment?: MoneyValue;
    total_cost?: MoneyValue;
    is_fully_paid?: boolean;
}

export interface OrderItemComponentPayload {
    id: number;
    uuid: string;
    component_name: string;
    component_key?: string;
    component_label?: string | null;
    is_received: boolean;
}

export interface OrderItemPayload {
    id: number;
    uuid: string;
    item_type: OrderItemType;
    item_type_label?: string | null;
    is_received: boolean;
    components: ResourcePayload<OrderItemComponentPayload[]>;
}

export interface OrderItem extends Omit<OrderItemPayload, 'components'> {
    components: OrderItemComponentPayload[];
}

export interface OrderServicePayload {
    id: number;
    uuid: string;
    order_item_id: number;
    service_key: string;
    service_name?: string | null;
    measurement?: string | null;
    is_budgeted: boolean;
    is_authorized: boolean;
    is_completed: boolean;
    notes?: string | null;
    base_price: MoneyValue;
    net_price: MoneyValue;
}

export interface OrderHistoryPayload {
    id: number;
    uuid: string;
    order_id: number;
    field_changed: string;
    old_value?: string | boolean | null;
    new_value?: string | boolean | null;
    comment?: string | null;
    description?: string;
    created_by?: number | null;
    creator?: ResourcePayload<UserPayload> | null;
    created_at?: string;
    attachments?: OrderAttachmentPayload[];
}

export interface OrderHistory extends Omit<OrderHistoryPayload, 'creator' | 'attachments'> {
    creator?: UserPayload | null;
    attachments: OrderAttachment[];
}

export interface OrderAttachmentPayload {
    id: number;
    uuid: string;
    attachable_type?: string;
    attachable_id?: number;
    file_name: string;
    file_path?: string;
    mime_type?: string | null;
    file_size?: number | null;
    human_file_size?: string;
    uploaded_by?: ResourcePayload<UserPayload> | null;
    created_at?: string;
    updated_at?: string;
    url?: string;
    is_image?: boolean;
    is_document?: boolean;
    is_pdf?: boolean;
    extension?: string;
}

export interface OrderAttachment extends Omit<OrderAttachmentPayload, 'uploaded_by'> {
    uploaded_by?: UserPayload | null;
}

export interface FinancialTotals {
    budgeted: MoneyValue;
    budgeted_base?: MoneyValue;
    budgeted_net?: MoneyValue;
    authorized: MoneyValue;
    completed: MoneyValue;
    advance_payment: MoneyValue;
    remaining_balance: MoneyValue;
}

export interface OrderBasePayload {
    id: number;
    uuid: string;
    title: string;
    description: string;
    status: OrderStatus;
    status_label?: string | null;
    priority: OrderPriority;
    priority_label?: string | null;
    estimated_completion?: string | null;
    actual_completion?: string | null;
    notes?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface OrderPayload extends OrderBasePayload {
    customer?: ResourcePayload<UserPayload> | null;
    created_by?: ResourcePayload<UserPayload> | null;
    updated_by?: ResourcePayload<UserPayload> | null;
    assigned_to?: ResourcePayload<UserPayload> | null;
    motor_info?: ResourcePayload<MotorInfoPayload> | null;
    items?: ResourcePayload<OrderItemPayload[]> | null;
    services?: ResourcePayload<OrderServicePayload[]> | null;
    history?: ResourcePayload<OrderHistoryPayload[]> | null;
    attachments?: ResourcePayload<OrderAttachmentPayload[]> | null;
    financials?: FinancialTotals | null;
}

export interface Order extends OrderBasePayload {
    customer?: UserPayload | null;
    created_by?: UserPayload | null;
    updated_by?: UserPayload | null;
    assigned_to?: UserPayload | null;
    motor_info?: MotorInfoPayload | null;
    items: OrderItem[];
    services: OrderServicePayload[];
    history: OrderHistory[];
    attachments: OrderAttachment[];
    financials?: FinancialTotals | null;
}

export type OrderSummary = Pick<OrderBasePayload, 'id' | 'uuid' | 'title' | 'status' | 'status_label' | 'priority' | 'priority_label' | 'created_at'>;

export interface OrderCapabilities {
    create_order: boolean;
    submit_budget: boolean;
    approve_services: boolean;
    complete_services: boolean;
    mark_ready_for_delivery: boolean;
    deliver_order: boolean;
}

export interface CreateOrderMotorInfoPayload {
    brand?: string | null;
    liters?: string | null;
    year?: string | null;
    model?: string | null;
    cylinder_count?: string | null;
    down_payment?: MoneyValue;
}

export interface CreateOrderItemPayload {
    item_type: OrderItemType;
    components?: string[];
}

export interface CreateOrderPayload {
    customer_id: number;
    title: string;
    description: string;
    priority: OrderPriority;
    assigned_to: number;
    estimated_completion?: string | null;
    notes?: string | null;
    motor_info?: CreateOrderMotorInfoPayload;
    items: CreateOrderItemPayload[];
}

export interface SubmitBudgetPayload {
    services: Array<{
        order_item_id: number;
        service_key: string;
        measurement?: string | null;
        notes?: string | null;
    }>;
}

export interface CustomerApprovalPayload {
    authorized_service_ids: number[];
    down_payment?: MoneyValue;
}

export interface WorkCompletedPayload {
    completed_service_ids: number[];
}

export interface OrderMutationResponse {
    message: string;
    order: ResourcePayload<OrderPayload>;
}

export interface CatalogItemTypeOption {
    key: OrderItemType;
    label: string;
}

export interface CatalogComponentOption {
    key: string;
    label: string;
}

export interface CatalogServiceOption {
    service_key: string;
    service_name: string;
    base_price: MoneyValue;
    net_price: MoneyValue;
    requires_measurement: boolean;
    display_order: number;
    item_type: OrderItemType;
}

export interface CatalogPayload {
    item_types: CatalogItemTypeOption[];
    components_by_type: Partial<Record<OrderItemType, CatalogComponentOption[]>>;
    services_by_type: Partial<Record<OrderItemType, CatalogServiceOption[]>>;
}

export type UserCollectionPayload = ResourcePayload<UserPayload[]>;

export interface PublicOrderItemComponentPayload {
    component_name: string;
    component_key?: string;
    component_label?: string | null;
    is_received: boolean;
}

export interface PublicOrderItemPayload {
    item_type: OrderItemType;
    item_type_label?: string | null;
    is_received: boolean;
    components: ResourcePayload<PublicOrderItemComponentPayload[]>;
}

export interface PublicOrderServicePayload {
    service_key: string;
    service_name?: string | null;
    measurement?: string | null;
    is_budgeted: boolean;
    is_authorized: boolean;
    is_completed: boolean;
    base_price: MoneyValue;
    net_price: MoneyValue;
}

export interface PublicOrderHistoryPayload {
    field_changed: string;
    description?: string | null;
    comment?: string | null;
    created_at?: string;
}

export interface PublicOrderAttachmentPayload {
    file_name: string;
    mime_type?: string | null;
    file_size?: number | null;
    human_file_size?: string;
    is_image?: boolean;
    is_document?: boolean;
    is_pdf?: boolean;
    extension?: string;
    created_at?: string;
}

export interface PublicOrderPayload {
    uuid: string;
    title: string;
    description: string;
    status: OrderStatus;
    status_label?: string | null;
    priority: OrderPriority;
    priority_label?: string | null;
    estimated_completion?: string | null;
    actual_completion?: string | null;
    motor_info?: ResourcePayload<MotorInfoPayload> | null;
    items?: ResourcePayload<PublicOrderItemPayload[]> | null;
    services?: ResourcePayload<PublicOrderServicePayload[]> | null;
    financials?: FinancialTotals | null;
    history?: ResourcePayload<PublicOrderHistoryPayload[]> | null;
    attachments?: ResourcePayload<PublicOrderAttachmentPayload[]> | null;
    created_at?: string;
}

export interface PublicOrderItem extends Omit<PublicOrderItemPayload, 'components'> {
    components: PublicOrderItemComponentPayload[];
}

export interface PublicOrder extends Omit<PublicOrderPayload, 'motor_info' | 'items' | 'services' | 'history' | 'attachments'> {
    motor_info?: MotorInfoPayload | null;
    items: PublicOrderItem[];
    services: PublicOrderServicePayload[];
    history: PublicOrderHistoryPayload[];
    attachments: PublicOrderAttachmentPayload[];
}

export interface PublicOrderTrackingResponse {
    order: ResourcePayload<PublicOrderPayload>;
}

export interface TrackOrderPayload {
    uuid: string;
    created_date: string;
}

export type OrderSummaryCollectionPayload = ResourcePayload<OrderSummary[]>;

export interface OrderHistoryPage {
    data: OrderHistory[];
    links?: Record<string, string | null>;
    meta?: Record<string, unknown> | null;
}

export interface OrderHistoryPagePayload {
    data: OrderHistoryPayload[];
    links?: Record<string, string | null>;
    meta?: Record<string, unknown> | null;
}

export interface OrderAttachmentsResponse {
    order_id: number;
    attachments: OrderAttachment[];
    total_size?: number;
    total_size_formatted?: string;
}

export interface OrderAttachmentsResponsePayload {
    order_id: number;
    attachments: ResourcePayload<OrderAttachmentPayload[]>;
    total_size?: number;
    total_size_formatted?: string;
}

export function unwrapResource<T>(resource: ResourcePayload<T> | null | undefined): T | null | undefined {
    if (resource === null || resource === undefined) {
        return resource;
    }

    if (typeof resource === 'object' && 'data' in resource) {
        return resource.data;
    }

    return resource;
}

export function unwrapCollection<T>(collection: ResourcePayload<T[]> | null | undefined): T[] {
    const unwrapped = unwrapResource(collection);

    return Array.isArray(unwrapped) ? unwrapped : [];
}

export function normalizeAttachment(resource: OrderAttachmentPayload): OrderAttachment {
    return {
        ...resource,
        uploaded_by: unwrapResource(resource.uploaded_by) ?? null,
    };
}

export function normalizeHistory(resource: OrderHistoryPayload): OrderHistory {
    return {
        ...resource,
        creator: unwrapResource(resource.creator) ?? null,
        attachments: unwrapCollection(resource.attachments).map(normalizeAttachment),
    };
}

export function normalizeOrderItem(resource: OrderItemPayload): OrderItem {
    return {
        ...resource,
        components: unwrapCollection(resource.components),
    };
}

export function normalizeOrder(resource: ResourcePayload<OrderPayload>): Order {
    const raw = unwrapResource(resource) as OrderPayload;

    return {
        ...raw,
        customer: unwrapResource(raw.customer) ?? null,
        created_by: unwrapResource(raw.created_by) ?? null,
        updated_by: unwrapResource(raw.updated_by) ?? null,
        assigned_to: unwrapResource(raw.assigned_to) ?? null,
        motor_info: unwrapResource(raw.motor_info) ?? null,
        items: unwrapCollection(raw.items).map(normalizeOrderItem),
        services: unwrapCollection(raw.services),
        history: unwrapCollection(raw.history).map(normalizeHistory),
        attachments: unwrapCollection(raw.attachments).map(normalizeAttachment),
    };
}

export function normalizePublicOrder(resource: ResourcePayload<PublicOrderPayload>): PublicOrder {
    const raw = unwrapResource(resource) as PublicOrderPayload;

    return {
        ...raw,
        motor_info: unwrapResource(raw.motor_info) ?? null,
        items: unwrapCollection(raw.items).map((item) => ({
            ...item,
            components: unwrapCollection(item.components),
        })),
        services: unwrapCollection(raw.services),
        history: unwrapCollection(raw.history),
        attachments: unwrapCollection(raw.attachments),
    };
}
