export const USER_ROLES = ['Super Administrator', 'Administrator', 'Employee', 'Customer'] as const;
export type UserRole = (typeof USER_ROLES)[number];

export const USER_TYPES = ['Individual', 'Business'] as const;
export type UserType = (typeof USER_TYPES)[number];

export type UserLocale = 'es' | 'en';

export interface UserAdministrationCompany {
    id: number;
    uuid: string;
    name: string;
}

export interface UserAdministrationListUser {
    uuid: string;
    first_name: string;
    last_name: string;
    full_name: string;
    role: UserRole;
    type: UserType | null;
    is_active: boolean;
    activated_at: string | null;
    company?: UserAdministrationCompany | null;
}

export interface UserAdministrationDetailUser extends UserAdministrationListUser {
    email: string;
    phone_number: string | null;
    company_id: number | null;
    notes: string | null;
    locale: UserLocale | null;
}

export interface UserAdministrationCapabilities {
    can_view_users: boolean;
    can_create_user: boolean;
    can_open: boolean;
    can_open_inactive?: boolean;
    can_update: boolean;
    can_update_active: boolean;
    can_delete: boolean;
    can_change_company: boolean;
    can_change_password: boolean;
    allowed_fields: string[];
}

export interface UserAdministrationOptions {
    roles: UserRole[];
    types: UserType[];
    locales: UserLocale[];
    active: string[];
    companies: UserAdministrationCompany[];
    per_page: number[];
}

export interface PaginatedUsers {
    data: UserAdministrationListUser[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: Array<{ url: string | null; label: string; page: number | null; active: boolean }>;
}
