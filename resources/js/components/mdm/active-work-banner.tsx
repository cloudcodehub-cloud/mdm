import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { show as showVisit } from '@/routes/visits';
import type { DashboardActiveVisit } from '@/types/dashboard';
import { cn } from '@/lib/utils';

export function ActiveWorkBanner() {
    const { auth, activeWork } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();

    if (auth.user?.role !== 'DSP' || !activeWork) {
        return null;
    }

    const visit = activeWork as DashboardActiveVisit;
    const onVisitPage = isCurrentUrl(showVisit.url(visit.id));
    const progress = visit.task_progress;
    const progressLabel = progress
        ? `${progress.completed} / ${progress.total} tasks complete`
        : null;
    const nextTitle = visit.next_task?.title;

    return (
        <>
            <div className="border-brand-cyan/25 bg-card/80 hidden border-b px-4 py-2 md:block md:px-6">
                <div className="flex min-w-0 items-center justify-between gap-3">
                    <div className="min-w-0">
                        <p className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-sm font-medium">
                            <ActiveVisitPulse />
                            {onVisitPage ? (
                                <span>Visit in progress</span>
                            ) : (
                                <Link
                                    href={showVisit(visit.id)}
                                    className="hover:text-foreground"
                                >
                                    Continue Visit
                                </Link>
                            )}
                            {progressLabel ? (
                                <span className="text-muted-foreground font-normal">
                                    {progressLabel}
                                </span>
                            ) : null}
                        </p>
                        {nextTitle ? (
                            <p className="text-status-warning mt-0.5 truncate text-xs">
                                Next: {nextTitle}
                            </p>
                        ) : null}
                    </div>
                    {!onVisitPage && (
                        <Button size="sm" variant="secondary" asChild>
                            <Link href={showVisit(visit.id)}>Continue Visit</Link>
                        </Button>
                    )}
                </div>
            </div>
            {!onVisitPage && (
                <div className="border-brand-cyan/30 bg-card/95 fixed inset-x-0 bottom-0 z-30 border-t p-3 backdrop-blur-md md:hidden">
                    <Button className="h-11 w-full" asChild>
                        <Link href={showVisit(visit.id)}>
                            <ActiveVisitPulse />
                            Continue Visit
                        </Link>
                    </Button>
                    <p className="text-muted-foreground mt-1 text-center text-xs">
                        {progressLabel}
                        {nextTitle ? ` · Next: ${nextTitle}` : ''}
                    </p>
                </div>
            )}
        </>
    );
}

function ActiveVisitPulse({ className }: { className?: string }) {
    return (
        <span className={cn('inline-flex items-center', className)}>
            <span
                className="mdm-priority-pulse bg-brand-teal size-2 shrink-0 rounded-full"
                aria-hidden="true"
            />
            <span className="sr-only">Active visit in progress</span>
        </span>
    );
}
