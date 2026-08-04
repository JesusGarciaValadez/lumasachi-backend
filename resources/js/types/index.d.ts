import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface I18nPageProps {
    locale: string;
    supported_locales: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    dusk?: string;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    i18n: I18nPageProps;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    can_view_sidebar?: boolean;
    is_customer?: boolean;
    can_view_users?: boolean;
    can_create_user?: boolean;
    can_create_order?: boolean;
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

export interface User {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone_number?: string | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    locale?: string | null;
}

export type BreadcrumbItemType = BreadcrumbItem;
