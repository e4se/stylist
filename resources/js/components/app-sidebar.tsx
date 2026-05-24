import { Link, usePage } from '@inertiajs/react';
import {
    Gauge,
    LayoutGrid,
    Shirt,
    Telescope as TelescopeIcon,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, telescope } from '@/routes';
import horizon from '@/routes/horizon';
import wardrobe from '@/routes/wardrobe';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Wardrobe',
        href: wardrobe.index(),
        icon: Shirt,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const footerNavItems: NavItem[] = [
        ...(auth.can.viewTelescope
            ? [
                  {
                      title: 'Telescope',
                      href: telescope(),
                      icon: TelescopeIcon,
                  },
              ]
            : []),
        ...(auth.can.viewHorizon
            ? [
                  {
                      title: 'Horizon',
                      href: horizon.index(),
                      icon: Gauge,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
