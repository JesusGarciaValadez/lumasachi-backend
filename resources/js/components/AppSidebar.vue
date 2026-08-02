<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Folder, LayoutGrid, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from './AppLogo.vue';

const i18n = useI18n();
const { t } = i18n;
const page = usePage();
const dashboardHref = route('dashboard');
const ordersHref = route('web.orders.index');
const usersHref = route('users.index');
const canViewUsers = computed(() => page.props.can_view_users === true);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: t('common.dashboard'),
        href: dashboardHref,
        icon: LayoutGrid,
        isActive: page.url === dashboardHref,
    },
    {
        title: t('orders.orders'),
        href: ordersHref,
        icon: Folder,
        isActive: page.url === ordersHref || page.url.startsWith(`${ordersHref}/`),
    },
    ...(canViewUsers.value
        ? [
              {
                  title: t('users.users'),
                  href: usersHref,
                  icon: Users,
                  isActive: page.url === usersHref || page.url.startsWith(`${usersHref}/`),
                  dusk: 'users-nav',
              },
          ]
        : []),
]);

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
