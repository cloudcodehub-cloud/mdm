import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ActiveWorkBanner } from '@/components/mdm/active-work-banner';
import { MobileBottomNav } from '@/components/mdm/mobile-bottom-nav';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { InboxActivityProvider } from '@/hooks/use-inbox-activity';
import { useModuleAccent } from '@/lib/module-accent';
import { cn } from '@/lib/utils';
import { show as showVisit } from '@/routes/visits';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const moduleAccent = useModuleAccent();
    const { auth, activeWork } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const onActiveVisitPage =
        auth.user?.role === 'DSP' &&
        activeWork != null &&
        isCurrentUrl(showVisit.url(activeWork.id));
    // Extra clearance only when the fixed Continue Visit bar is actually shown.
    const showMobileContinueBar =
        auth.user?.role === 'DSP' &&
        Boolean(activeWork) &&
        !onActiveVisitPage;

    return (
        <InboxActivityProvider>
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    data-module={moduleAccent}
                    data-mobile-continue-bar={
                        showMobileContinueBar ? 'true' : 'false'
                    }
                    className={cn(
                        'min-w-0 overflow-x-clip pb-[calc(4rem+env(safe-area-inset-bottom,0px))] md:pb-0',
                        showMobileContinueBar &&
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
