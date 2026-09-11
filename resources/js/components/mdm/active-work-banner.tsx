import { Link, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { show as showVisit } from '@/routes/visits';
import type { DashboardActiveVisit } from '@/types/dashboard';

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
        ? `${progress.completed}/${progress.total} tasks complete`
        : visit.service_type;

    return (
        <>
            <div className="border-brand-cyan/30 from-primary/8 hidden border-b bg-gradient-to-r via-card/80 to-brand-mint/15 px-4 py-2.5 md:block md:px-6">
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-primary text-[11px] font-medium tracking-wide uppercase">
                            Visit in progress
                        </p>
                        <p className="truncate text-sm font-medium">
                            {visit.client.name}
                            <span className="text-muted-foreground font-normal">
                                {' '}
                                · {progressLabel}
                            </span>
                        </p>
                    </div>
                    {!onVisitPage && (
                        <Button size="sm" asChild>
                            <Link href={showVisit(visit.id)}>
                                Continue Visit
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
            {!onVisitPage && (
                <div className="border-brand-cyan/30 from-primary/15 to-brand-mint/20 fixed inset-x-0 bottom-0 z-30 border-t bg-gradient-to-r p-3 backdrop-blur-md md:hidden">
                    <Button className="h-11 w-full" asChild>
                        <Link href={showVisit(visit.id)}>
                            <MapPin className="size-4" />
                            Continue {visit.client.name}
                        </Link>
                    </Button>
                    <p className="text-muted-foreground mt-1 text-center text-xs">
                        {progressLabel}
                    </p>
                </div>
            )}
        </>
    );
}
