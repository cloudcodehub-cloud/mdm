import { router, useForm, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { clockOut } from '@/routes/visits';
import type { GpsFallbackReasonOption } from '@/types/dashboard';
import type { ActiveVisitRecord } from '@/types/visit';

type LocationStatus = 'captured' | 'denied' | 'unavailable' | 'unsupported';

const DEFAULT_GPS_FALLBACK_REASONS: GpsFallbackReasonOption[] = [
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

export function VisitClockOutReview({
    visit,
    canClockOut,
    gpsFallbackReasons,
}: {
    visit: ActiveVisitRecord;
    canClockOut: boolean;
    gpsFallbackReasons?: GpsFallbackReasonOption[];
}) {
    const [open, setOpen] = useState(false);
    const [needsAttestation, setNeedsAttestation] = useState(false);
    const [locating, setLocating] = useState(false);
    const pageErrors = usePage().props.errors;
    const reasons = gpsFallbackReasons ?? DEFAULT_GPS_FALLBACK_REASONS;
    const form = useForm({
        gps_fallback_reason: '',
        gps_fallback_note: '',
        location_status: 'unavailable' as LocationStatus,
        visit_notes: visit.visit_notes ?? '',
        handover_note: visit.handover_note ?? '',
        extended_visit_reason: visit.extended_visit_reason ?? '',
        acknowledge_unfinished_required: false,
    });

    const selectedReason = reasons.find(
        (reason) => reason.value === form.data.gps_fallback_reason,
    );
    const skipped = visit.tasks.filter((task) => task.status === 'skipped');
    const pendingRequired = visit.task_summary.pending_required;

    const postClockOut = (payload: {
        latitude?: number | null;
        longitude?: number | null;
        accuracy?: number | null;
        location_method: 'browser_gps' | 'gps_unavailable';
        location_status: LocationStatus;
        gps_fallback_reason?: string | null;
        gps_fallback_note?: string | null;
    }) => {
        router.post(clockOut.url(visit.id), {
            ...payload,
            visit_notes: form.data.visit_notes || null,
            handover_note: form.data.handover_note || null,
            extended_visit_reason: form.data.extended_visit_reason || null,
            acknowledge_unfinished_required:
                form.data.acknowledge_unfinished_required,
        });
    };

    const startClockOut = () => {
        setLocating(true);

        if (!navigator.geolocation) {
            form.setData('location_status', 'unsupported');
            setNeedsAttestation(true);
            setLocating(false);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                postClockOut({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy ?? null,
                    location_method: 'browser_gps',
                    location_status: 'captured',
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

    if (!canClockOut) {
        return null;
    }

    if (!open) {
        return (
            <div className="sticky-form-actions justify-start">
                <Button
                    type="button"
                    className="min-h-11 w-full sm:w-auto"
                    onClick={() => setOpen(true)}
                >
                    Review & clock out
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <dl className="grid gap-3 text-sm">
                <ReviewItem label="Client" value={visit.client.name} />
                <ReviewItem
                    label="Actual clock-in"
                    value={visit.clocked_in_at_label}
                />
                <ReviewItem
                    label="Task completion"
                    value={`${visit.task_summary.completed} completed · ${visit.task_summary.skipped} skipped · ${visit.task_summary.pending} pending`}
                />
                <ReviewItem
                    label="Skipped items"
                    value={
                        skipped.length === 0
                            ? 'None'
                            : skipped
                                  .map(
                                      (task) =>
                                          `${task.title}${task.skip_reason_name ? ` (${task.skip_reason_name})` : ''}`,
                                  )
                                  .join('; ')
                    }
                />
                <ReviewItem
                    label="Visit notes"
                    value={form.data.visit_notes || 'None'}
                />
                <ReviewItem
                    label="Wrap-up / handover"
                    value={form.data.handover_note || 'Required — add above'}
                />
                <ReviewItem
                    label="Clock-in location"
                    value={visit.location_status_label}
                />
            </dl>

            {!form.data.handover_note ? (
                <div className="rounded-xl border border-amber-500/30 bg-amber-50/70 p-3 dark:bg-amber-950/20">
                    <p className="text-sm font-medium">
                        Visit Wrap-Up / Handover Note is required before
                        clock-out.
                    </p>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Save the handover note in Visit wrap-up, then return
                        here.
                    </p>
                    <InputError
                        className="mt-2"
                        message={pageErrors.handover_note}
                    />
                </div>
            ) : null}

            {visit.requires_extended_visit_reason ? (
                <div className="space-y-2 rounded-xl border border-amber-500/30 bg-amber-50/70 p-3 dark:bg-amber-950/20">
                    <p className="text-sm font-medium">
                        This visit is 30+ minutes past scheduled end.
                    </p>
                    <Label htmlFor="extended_visit_reason">
                        Extended-visit reason
                    </Label>
                    <textarea
                        id="extended_visit_reason"
                        rows={3}
                        className="border-input bg-background min-h-16 w-full rounded-md border px-3 py-2 text-sm"
                        value={form.data.extended_visit_reason}
                        onChange={(event) =>
                            form.setData(
                                'extended_visit_reason',
                                event.target.value,
                            )
                        }
                    />
                    <InputError
                        message={
                            form.errors.extended_visit_reason ||
                            pageErrors.extended_visit_reason
                        }
                    />
                </div>
            ) : null}

            {pageErrors.safety_checks ? (
                <InputError message={pageErrors.safety_checks} />
            ) : null}

            {pendingRequired > 0 && (
                <div className="rounded-xl border border-amber-500/30 bg-amber-50/70 p-3 dark:bg-amber-950/20">
                    <p className="text-sm font-medium">
                        {pendingRequired} required task
                        {pendingRequired === 1 ? '' : 's'} still unfinished.
                    </p>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Clocking out will leave them unfinished and create a
                        supervisor-visible exception. They will not be marked
                        complete.
                    </p>
                    <label className="mt-3 flex items-start gap-2 text-sm">
                        <Checkbox
                            checked={form.data.acknowledge_unfinished_required}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'acknowledge_unfinished_required',
                                    checked === true,
                                )
                            }
                        />
                        <span>
                            I acknowledge unfinished required tasks and still
                            need to clock out.
                        </span>
                    </label>
                    <InputError
                        className="mt-2"
                        message={pageErrors.acknowledge_unfinished_required}
                    />
                </div>
            )}

            {!needsAttestation ? (
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        className="min-h-11 w-full sm:w-auto"
                        onClick={startClockOut}
                        disabled={
                            locating ||
                            !form.data.handover_note ||
                            (visit.requires_extended_visit_reason &&
                                !form.data.extended_visit_reason) ||
                            (pendingRequired > 0 &&
                                !form.data.acknowledge_unfinished_required)
                        }
                    >
                        <MapPin className="size-4" />
                        {locating ? 'Getting location…' : 'Clock out'}
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => setOpen(false)}
                    >
                        Back to visit
                    </Button>
                </div>
            ) : (
                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        postClockOut({
                            latitude: null,
                            longitude: null,
                            accuracy: null,
                            location_method: 'gps_unavailable',
                            location_status: form.data.location_status,
                            gps_fallback_reason: form.data.gps_fallback_reason,
                            gps_fallback_note: selectedReason?.requires_note
                                ? form.data.gps_fallback_note
                                : null,
                        });
                    }}
                >
                    <p className="text-sm">
                        GPS is not available. Choose a reason to clock out
                        without coordinates. Location will not be faked.
                    </p>
                    <div className="grid gap-2">
                        <Label htmlFor="clock_out_gps_fallback_reason">
                            Why is GPS unavailable?
                        </Label>
                        <select
                            id="clock_out_gps_fallback_reason"
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
                            {reasons.map((reason) => (
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
                            <Label htmlFor="clock_out_gps_fallback_note">
                                Brief note
                            </Label>
                            <textarea
                                id="clock_out_gps_fallback_note"
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
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Clock out without GPS
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
        </div>
    );
}

function ReviewItem({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs tracking-wide uppercase">
                {label}
            </dt>
            <dd className="mt-0.5 font-medium">{value}</dd>
        </div>
    );
}
