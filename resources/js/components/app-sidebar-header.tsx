import { usePage } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { AlertsMenu } from '@/components/mdm/alerts-menu';
import { DemoWeather } from '@/components/mdm/demo-weather';
import { LiveClock } from '@/components/mdm/live-clock';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth, demoWeather, title: pageHeading } = usePage().props;
    const getInitials = useInitials();
    const pageTitle =
        typeof pageHeading === 'string' && pageHeading.length > 0
            ? pageHeading
            : (breadcrumbs.at(-1)?.title ?? 'Dashboard');

    return (
        <header className="flex h-16 shrink-0 items-center gap-3 border-b border-white/70 bg-card/88 px-4 shadow-[var(--shadow-soft)] backdrop-blur-[14px] transition-[width,height] duration-200 ease-linear dark:border-white/10 md:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <BrandMark
                    variant="icon"
                    className="size-7 md:hidden"
                    alt="MDM"
                />
                <SidebarTrigger className="-ml-1" />
                <div className="min-w-0">
                    <p className="text-muted-foreground hidden text-[11px] tracking-wide uppercase sm:block">
                        {auth.user?.role}
                    </p>
                    <h1 className="truncate text-sm font-semibold tracking-tight md:text-base">
                        {pageTitle}
                    </h1>
                    {breadcrumbs.length > 1 && (
                        <div className="hidden md:block">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-1 sm:gap-2">
                <LiveClock />
                {demoWeather && <DemoWeather weather={demoWeather} />}
                <AlertsMenu />
                {auth.user && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                className="h-10 rounded-full px-1.5"
                            >
                                <Avatar className="size-8 overflow-hidden rounded-full">
                                    <AvatarImage
                                        src={auth.user.avatar}
                                        alt={auth.user.name}
                                    />
                                    <AvatarFallback className="rounded-full bg-primary/12 text-primary">
                                        {getInitials(auth.user.name)}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-56" align="end">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </header>
    );
}
