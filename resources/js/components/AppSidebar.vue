<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, FileText, Folder, Stethoscope, Dumbbell } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

// Both `href` and `url` are set because NavMain may read either one
// (older starter kit versions use `url`, newer ones use `href`).
const mainNavItems = [
    {
        title: 'PDF Records',
        href: '/pdf-records',
        url: '/pdf-records',
        icon: FileText,
    },
    {
        title: 'Medical Certificates',
        href: '/medical-certificates',
        url: '/medical-certificates',
        icon: Stethoscope,
    },
    {
        title: 'Fit to Work',
        href: '/fit-to-work',
        url: '/fit-to-work',
        icon: Dumbbell,
    },

    {
        title: 'aics',
        href: '/aics',
        url: '/aics',
        icon: Dumbbell,
    },
] as NavItem[];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
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
                        <!-- Your routes file has no "dashboard" route, so the logo goes to the PDF records page -->
                        <Link :href="route('pdf-records.index')">
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
</template>