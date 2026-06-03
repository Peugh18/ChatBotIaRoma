<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Bot, Building2, FlaskConical, LayoutGrid, MapPin, MessageSquare, Package, Settings, ShoppingBag, Tag, Truck, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const operationsNav: NavItem[] = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'Chat WhatsApp', url: '/chat', icon: MessageSquare },
    { title: 'Clientes', url: '/customers', icon: Users },
    { title: 'Pipeline de ventas', url: '/pipeline', icon: ShoppingBag },
];

const catalogNav: NavItem[] = [
    { title: 'Productos', url: '/products', icon: Package },
    { title: 'Categorías', url: '/categories', icon: Tag },
    { title: 'Zonas de delivery', url: '/delivery-zones', icon: Truck },
    { title: 'Sedes Shalom', url: '/sedes-shalom', icon: MapPin },
];

const configNav: NavItem[] = [
    { title: 'Empresa y voz', url: '/company-settings', icon: Building2 },
    { title: 'Personalidad del bot', url: '/bot-settings', icon: Bot },
    { title: 'Debug del bot', url: '/bot-debug', icon: FlaskConical },
];
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
            <NavMain :items="operationsNav" label="Operación" />
            <NavMain :items="catalogNav" label="Catálogo" />
            <NavMain :items="configNav" label="Configuración" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
