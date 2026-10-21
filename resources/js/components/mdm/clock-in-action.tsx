import { Link, router, useForm, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ProgressRing } from '@/components/mdm/visual-summaries';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

type GpsFallbackOption = {
    value: string;
    label: string;
    requires_note: boolean;
};

const DEFAULT_GPS_FALLBACK_REASONS: GpsFallbackOption[] = [
    {
        value: 'permission_denied',
        label: 'Location permission unavailable',
        requires_note: false,
    },
    {
        value: 'gps_unavailable',
        label: 'GPS/location service unavailable',
        requires_note: false,
    },
    {
        value: 'poor_signal',
        label: 'Poor/no signal',
        requires_note: false,
    },
    {
        value: 'device_issue',
        label: 'Device issue',
        requires_note: false,
    },
    {
        value: 'indoor_accuracy',
        label: 'Indoor/location accuracy issue',
        requires_note: false,
    },
    {
        value: 'app_technical_issue',
        label: 'MDM/app technical issue',
        requires_note: false,
    },
    {
        value: 'other',
        label: 'Other',
        requires_note: true,
    },
];

export function ClockInAction({
    activeVisit,
    clockInVisit,
    scheduledVisitId,
    canClockIn,
    hideEmpty = false,
    emptyTitle,
    emptyDetail,
    gpsFallbackReasons,
}: {
    activeVisit?: DashboardActiveVisit | null;
    clockInVisit?: ClockInVisitSummary | null;
    scheduledVisitId?: number;
    canClockIn?: boolean;
    hideEmpty?: boolean;
    emptyTitle?: string;
    emptyDetail?: string;
    gpsFallbackReasons?: GpsFallbackOption[];
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
                        {continueThisVisit && activeVisit.task_progress && (
                            <p className="text-muted-foreground mt-1 text-xs">
                                {activeVisit.task_progress.completed} /{' '}
                                {activeVisit.task_progress.total} tasks complete
                            </p>
                        )}
                        {continueThisVisit && activeVisit.next_task?.title && (
                            <p className="text-status-warning mt-1 text-xs">
                                Next: {activeVisit.next_task.title}
                            </p>
                        )}
                        <Button className="mt-4 min-h-11 w-full sm:w-auto" asChild>
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
    const canStart =
        scheduledVisitId !== undefined
            ? Boolean(canClockIn)
            : Boolean(clockInVisit?.can_start);
    const showCard = targetId !== undefined && clockInVisit != null;

    if (!showCard || targetId === undefined) {
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
                        'Start Visit becomes available during the early-start window before the scheduled visit.'}
                </p>
            </div>
        );
    }

    if (!canStart) {
        return (
            <div className="surface-panel border-primary/20 from-primary/8 via-card to-brand-cyan/15 bg-gradient-to-br p-5 md:p-6">
                <p className="text-primary text-xs font-medium tracking-wide uppercase">
                    Next visit
                </p>
                <PrimaryVisitDetails visit={clockInVisit} />
                <VisitCountdown startsAtIso={clockInVisit?.starts_at_iso} />
                <p className="text-muted-foreground mt-3 text-sm">
                    {emptyDetail ??
                        `Start Visit becomes available up to ${clockInVisit?.early_start_minutes ?? 30} minutes before the scheduled start.`}
                </p>
            </div>
        );
    }

    return (
        <StartVisitForm
            scheduledVisitId={targetId}
            visit={clockInVisit}
            gpsFallbackReasons={
                gpsFallbackReasons ?? DEFAULT_GPS_FALLBACK_REASONS
            }
        />
    );
}

function PrimaryVisitDetails({
    visit,
}: {
    visit?: ClockInVisitSummary | null;
}) {
    if (!visit) {
        return null;
    }

    return (
        <div className="mt-2 space-y-1">
            <p className="text-lg font-semibold tracking-tight">
                {visit.client.name}
            </p>
            <p className="text-muted-foreground text-sm">
                {visit.time_label}
                {visit.service_type ? ` · ${visit.service_type}` : ''}
            </p>
            {visit.location_label && (
                <p className="text-muted-foreground text-sm">
                    {visit.location_label}
                </p>
            )}
            {visit.supervisor_name && (
                <p className="text-muted-foreground text-sm">
                    Supervisor: {visit.supervisor_name}
                </p>
            )}
        </div>
    );
}

function StartVisitForm({
    scheduledVisitId,
    visit,
    gpsFallbackReasons,
}: {
    scheduledVisitId: number;
    visit?: ClockInVisitSummary | null;
    gpsFallbackReasons: GpsFallbackOption[];
}) {
    const pageErrors = usePage().props.errors;
    const [needsAttestation, setNeedsAttestation] = useState(false);
    const [earlyConfirmOpen, setEarlyConfirmOpen] = useState(false);
    const [locating, setLocating] = useState(false);
    const form = useForm({
        gps_fallback_reason: '',
        gps_fallback_note: '',
        location_status: 'unavailable' as LocationStatus,
        early_start_confirmed: false,
    });

    const selectedReason = gpsFallbackReasons.find(
        (reason) => reason.value === form.data.gps_fallback_reason,
    );

    const postClockIn = (payload: {
        latitude?: number | null;
        longitude?: number | null;
        accuracy?: number | null;
        location_method: 'browser_gps' | 'gps_unavailable';
        location_status: LocationStatus;
        gps_fallback_reason?: string | null;
        gps_fallback_note?: string | null;
        early_start_confirmed?: boolean;
    }) => {
        router.post(clockIn.url(scheduledVisitId), {
            ...payload,
            early_start_confirmed:
                payload.early_start_confirmed ??
                Boolean(visit?.is_early_start),
        });
    };

    const captureAndStart = (earlyConfirmed: boolean) => {
        setLocating(true);
        setEarlyConfirmOpen(false);

        if (!navigator.geolocation) {
            form.setData((data) => ({
                ...data,
                location_status: 'unsupported',
                early_start_confirmed: earlyConfirmed,
            }));
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
                    early_start_confirmed: earlyConfirmed,
                });
                setLocating(false);
            },
            (error) => {
                form.setData((data) => ({
                    ...data,
                    location_status:
                        error.code === error.PERMISSION_DENIED
                            ? 'denied'
                            : 'unavailable',
                    early_start_confirmed: earlyConfirmed,
                    gps_fallback_reason:
                        error.code === error.PERMISSION_DENIED
                            ? 'permission_denied'
                            : data.gps_fallback_reason,
                }));
                setNeedsAttestation(true);
                setLocating(false);
            },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 },
        );
    };

    const startVisit = () => {
        if (visit?.is_early_start && !form.data.early_start_confirmed) {
            setEarlyConfirmOpen(true);
            return;
        }

        captureAndStart(Boolean(visit?.is_early_start));
    };

    return (
        <div className="surface-panel border-primary/20 from-primary/8 via-card to-brand-cyan/15 bg-gradient-to-br p-5 md:p-6">
            <p className="text-primary text-xs font-medium tracking-wide uppercase">
                Next visit action
            </p>
            <PrimaryVisitDetails visit={visit} />
            <VisitCountdown startsAtIso={visit?.starts_at_iso} />
            {!needsAttestation ? (
                <Button
                    className="mt-4 min-h-11 w-full sm:w-auto"
                    onClick={startVisit}
                    disabled={locating || form.processing}
                >
                    <MapPin className="size-4" />
                    {locating ? 'Getting location…' : 'Start Visit'}
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
                            gps_fallback_reason: form.data.gps_fallback_reason,
                            gps_fallback_note: selectedReason?.requires_note
                                ? form.data.gps_fallback_note
                                : null,
                            early_start_confirmed:
                                form.data.early_start_confirmed ||
                                Boolean(visit?.is_early_start),
                        });
                    }}
                >
                    <p className="text-muted-foreground text-sm">
                        GPS is not available. Choose a reason to start without
                        coordinates. Location will not be faked.
                    </p>
                    <div className="grid gap-2">
                        <Label htmlFor="gps_fallback_reason">
                            Why is GPS unavailable?
                        </Label>
                        <select
                            id="gps_fallback_reason"
                            required
                            value={form.data.gps_fallback_reason}
                            onChange={(event) =>
                                form.setData(
                                    'gps_fallback_reason',
                                    event.target.value,
                                )
                            }
                            className="border-input flex h-10 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        >
                            <option value="">Select a reason</option>
                            {gpsFallbackReasons.map((reason) => (
                                <option key={reason.value} value={reason.value}>
                                    {reason.label}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={
                                form.errors.gps_fallback_reason ||
                                pageErrors.gps_fallback_reason
                            }
                        />
                    </div>
                    {selectedReason?.requires_note && (
                        <div className="grid gap-2">
                            <Label htmlFor="gps_fallback_note">
                                Brief note
                            </Label>
                            <textarea
                                id="gps_fallback_note"
                                required
                                rows={3}
                                value={form.data.gps_fallback_note}
                                onChange={(event) =>
                                    form.setData(
                                        'gps_fallback_note',
                                        event.target.value,
                                    )
                                }
                                className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            />
                            <InputError
                                message={
                                    form.errors.gps_fallback_note ||
                                    pageErrors.gps_fallback_note
                                }
                            />
                        </div>
                    )}
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button
                            type="submit"
                            className="min-h-11 w-full sm:w-auto"
                            disabled={form.processing}
                        >
                            Start without GPS
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            className="min-h-11 w-full sm:w-auto"
                            onClick={() => setNeedsAttestation(false)}
                        >
                            Try GPS again
                        </Button>
                    </div>
                </form>
            )}
            <InputError
                className="mt-2"
                message={
                    pageErrors.scheduled_visit ||
                    pageErrors.early_start_confirmed
                }
            />

            <Dialog open={earlyConfirmOpen} onOpenChange={setEarlyConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Starting early</DialogTitle>
                        <DialogDescription>
                            You’re starting this shift early. Do you still want
                            to start?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:justify-end">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setEarlyConfirmOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                form.setData('early_start_confirmed', true);
                                captureAndStart(true);
                            }}
                        >
                            Start Visit
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
