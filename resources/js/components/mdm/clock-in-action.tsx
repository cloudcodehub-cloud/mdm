import { Link, router, useForm, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ProgressRing } from '@/components/mdm/visual-summaries';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { clockIn } from '@/routes/scheduled-visits';
import { show as showVisit } from '@/routes/visits';
import { VisitCountdown } from '@/components/mdm/visit-countdown';
import type {
    ClockInVisitSummary,
    DashboardActiveVisit,
} from '@/types/dashboard';

type LocationStatus =
    | 'captured'
    | 'denied'
    | 'unavailable'
    | 'unsupported';

export function ClockInAction({
    activeVisit,
    clockInVisit,
    scheduledVisitId,
    canClockIn,
    hideEmpty = false,
    emptyTitle,
    emptyDetail,
}: {
    activeVisit?: DashboardActiveVisit | null;
    clockInVisit?: ClockInVisitSummary | null;
    scheduledVisitId?: number;
    canClockIn?: boolean;
    hideEmpty?: boolean;
    emptyTitle?: string;
    emptyDetail?: string;
}) {
    if (activeVisit) {
        const continueThisVisit =
            scheduledVisitId === undefined ||
            activeVisit.scheduled_visit_id === scheduledVisitId;

        return (
            <div className="surface-panel border-primary/20 from-primary/8 via-card to-brand-mint/20 bg-gradient-to-br p-5 md:p-6">
                <p className="text-primary text-xs font-medium tracking-wide uppercase">
                    Active visit
                </p>
                <div className="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <p className="text-lg font-semibold tracking-tight">
                            {continueThisVisit
                                ? activeVisit.client.name
                                : `Finish ${activeVisit.client.name} first`}
                        </p>
                        {continueThisVisit && (
                            <VisitCountdown
                                startedAtIso={activeVisit.clocked_in_at}
                            />
                        )}
                        <p className="text-muted-foreground mt-1 text-sm">
                            {continueThisVisit
                                ? `Visit in progress · ${activeVisit.service_type}.`
                                : `You already have an active visit for ${activeVisit.client.name}. Continue that visit before starting another.`}
                        </p>
                        <Button className="mt-4 w-full sm:w-auto" asChild>
                            <Link href={showVisit(activeVisit.id)}>
                                <MapPin className="size-4" />
                                Continue Visit
                            </Link>
                        </Button>
                    </div>
                    {continueThisVisit && activeVisit.task_progress && (
                        <div className="flex flex-col gap-3 sm:items-end">
                            <ProgressRing
                                value={activeVisit.task_progress.percent}
                                label={`${activeVisit.task_progress.percent}%`}
                                detail={`${activeVisit.task_progress.completed} completed · ${activeVisit.task_progress.pending} pending · ${activeVisit.task_progress.skipped} skipped`}
                            />
                        </div>
                    )}
                </div>
            </div>
        );
    }

    const targetId = scheduledVisitId ?? clockInVisit?.id;
    const showStart =
        targetId !== undefined &&
        (scheduledVisitId !== undefined
            ? Boolean(canClockIn)
            : Boolean(clockInVisit));

    if (!showStart || targetId === undefined) {
        if (hideEmpty) {
            return null;
        }

        return (
            <div className="rounded-2xl border border-dashed border-border/80 bg-muted/30 p-4">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {emptyTitle ?? 'Upcoming Visit'}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {emptyDetail ??
                        'Start Visit becomes available on the scheduled service date during the visit window.'}
                </p>
            </div>
        );
    }

    return (
        <StartVisitForm
            scheduledVisitId={targetId}
            clientName={clockInVisit?.client.name}
            serviceType={clockInVisit?.service_type}
            timeLabel={clockInVisit?.time_label}
            startsAtIso={clockInVisit?.starts_at_iso}
        />
    );
}

function StartVisitForm({
    scheduledVisitId,
    clientName,
    serviceType,
    timeLabel,
    startsAtIso,
}: {
    scheduledVisitId: number;
    clientName?: string;
    serviceType?: string;
    timeLabel?: string;
    startsAtIso?: string | null;
}) {
    const pageErrors = usePage().props.errors;
    const [needsAttestation, setNeedsAttestation] = useState(false);
    const [locating, setLocating] = useState(false);
    const form = useForm({
        unavailable_reason: '',
        location_status: 'unavailable' as LocationStatus,
    });

    const postClockIn = (payload: {
        latitude?: number | null;
        longitude?: number | null;
        accuracy?: number | null;
        location_method: 'browser_gps' | 'gps_unavailable';
        location_status: LocationStatus;
        unavailable_reason?: string | null;
    }) => {
        router.post(clockIn.url(scheduledVisitId), payload);
    };

    const startVisit = () => {
        setLocating(true);

        if (!navigator.geolocation) {
            form.setData('location_status', 'unsupported');
            setNeedsAttestation(true);
            setLocating(false);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                postClockIn({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy ?? null,
                    location_method: 'browser_gps',
                    location_status: 'captured',
                });
                setLocating(false);
            },
            (error) => {
                form.setData(
                    'location_status',
                    error.code === error.PERMISSION_DENIED
                        ? 'denied'
                        : 'unavailable',
                );
                setNeedsAttestation(true);
                setLocating(false);
            },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 },
        );
    };

    return (
        <div className="surface-panel border-primary/20 from-primary/8 via-card to-brand-cyan/15 bg-gradient-to-br p-5 md:p-6">
            <p className="text-primary text-xs font-medium tracking-wide uppercase">
                Next visit action
            </p>
            <p className="text-muted-foreground mt-1 text-sm">
                {clientName
                    ? `Start visit for ${clientName}${serviceType ? ` · ${serviceType}` : ''}${timeLabel ? ` · ${timeLabel}` : ''}.`
                    : 'Clock in to start this scheduled visit. Browser GPS is used when available.'}
            </p>
            <VisitCountdown startsAtIso={startsAtIso} />
            {!needsAttestation ? (
                <Button
                    className="mt-4 w-full sm:w-auto"
                    onClick={startVisit}
                    disabled={locating || form.processing}
                >
                    <MapPin className="size-4" />
                    {locating ? 'Getting location…' : 'Start Visit / Clock In'}
                </Button>
            ) : (
                <form
                    className="mt-4 space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        postClockIn({
                            latitude: null,
                            longitude: null,
                            accuracy: null,
                            location_method: 'gps_unavailable',
                            location_status: form.data.location_status,
                            unavailable_reason: form.data.unavailable_reason,
                        });
                    }}
                >
                    <p className="text-muted-foreground text-sm">
                        GPS is not available. Record a reason to clock in
                        without coordinates. Location will not be faked.
                    </p>
                    <div className="grid gap-2">
                        <Label htmlFor="unavailable_reason">
                            GPS unavailable reason
                        </Label>
                        <textarea
                            id="unavailable_reason"
                            required
                            rows={3}
                            value={form.data.unavailable_reason}
                            onChange={(event) =>
                                form.setData(
                                    'unavailable_reason',
                                    event.target.value,
                                )
                            }
                            className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        />
                        <InputError
                            message={
                                form.errors.unavailable_reason ||
                                pageErrors.unavailable_reason
                            }
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Clock in without GPS
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setNeedsAttestation(false)}
                        >
                            Try GPS again
                        </Button>
                    </div>
                </form>
            )}
            <InputError
                className="mt-2"
                message={pageErrors.scheduled_visit}
            />
        </div>
    );
}
