<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Dumbbell, FileText, Folder, HeartPulse, Stethoscope } from 'lucide-vue-next';
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
        title: 'AICS',
        href: '/aics',
        url: '/aics',
        icon: HeartPulse,
    },
     {
        title: 'Employment Medical Certificates',
        href: '/employment-medical-certificates',
        url: '/employment-medical-certificates',
        icon: HeartPulse,
    },
] as NavItem[];

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

       
    </Sidebar>
</template>