<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    ChartColumn,
    FolderGit2,
    History,
    ImagePlus,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import CreditBalance from '@/components/credits/CreditBalance.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { report } from '@/routes/admin';
import { index as adminUsers } from '@/routes/admin/users';
import { history, index as generations } from '@/routes/generations';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Generate',
        href: generations(),
        icon: ImagePlus,
    },
    {
        title: 'History',
        href: history(),
        icon: History,
    },
    ...(page.props.auth.user.is_admin
        ? [
              { title: 'Overview', href: report(), icon: ChartColumn },
              { title: 'Users', href: adminUsers(), icon: Users },
          ]
        : []),
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/sha-hin/prompt-edit',
        icon: FolderGit2,
    },
    {
        title: 'Pixelforge docs',
        href: 'https://pixelforge.promptedit.dev/docs',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="generations()">
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
            <CreditBalance />
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
