import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { navigationForRole } from '@/lib/navigation';
import { dashboard } from '@/routes';

export function AppSidebar() {
    const { auth } = usePage().props;
    const role = auth.user?.role;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            tooltip={{ children: 'Dashboard' }}
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navigationForRole(role)} />
            </SidebarContent>
            <SidebarFooter className="hidden p-2 md:flex">
                <SidebarTrigger className="w-full justify-start gap-2 rounded-md px-2" />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
