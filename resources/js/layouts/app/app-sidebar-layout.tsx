import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ActiveWorkBanner } from '@/components/mdm/active-work-banner';
import { MobileBottomNav } from '@/components/mdm/mobile-bottom-nav';
import { InboxActivityProvider } from '@/hooks/use-inbox-activity';
import { useModuleAccent } from '@/lib/module-accent';
import { cn } from '@/lib/utils';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const moduleAccent = useModuleAccent();
    const { auth, activeWork } = usePage().props;
    const showMobileWorkBar =
        auth.user?.role === 'DSP' && Boolean(activeWork);

    return (
        <InboxActivityProvider>
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    data-module={moduleAccent}
                    className={cn(
                        'min-w-0 overflow-x-clip pb-[calc(4rem+env(safe-area-inset-bottom,0px))] md:pb-0',
                        showMobileWorkBar &&
                            'pb-[calc(9.5rem+env(safe-area-inset-bottom,0px))] md:pb-0',
                    )}
                >
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    <ActiveWorkBanner />
                    {children}
                </AppContent>
                <MobileBottomNav />
            </AppShell>
        </InboxActivityProvider>
    );
}
