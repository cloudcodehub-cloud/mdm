import { router, useForm, usePage } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { clockOut } from '@/routes/visits';
import type { ActiveVisitRecord } from '@/types/visit';

type LocationStatus = 'captured' | 'denied' | 'unavailable' | 'unsupported';

export function VisitClockOutReview({
    visit,
    canClockOut,
}: {
    visit: ActiveVisitRecord;
    canClockOut: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [needsAttestation, setNeedsAttestation] = useState(false);
    const [locating, setLocating] = useState(false);
    const pageErrors = usePage().props.errors;
    const form = useForm({
        unavailable_reason: '',
        location_status: 'unavailable' as LocationStatus,
        visit_notes: visit.visit_notes ?? '',
        handover_note: visit.handover_note ?? '',
        acknowledge_unfinished_required: false,
    });

    const skipped = visit.tasks.filter((task) => task.status === 'skipped');
    const pendingRequired = visit.task_summary.pending_required;

    const postClockOut = (payload: {
        latitude?: number | null;
        longitude?: number | null;
        accuracy?: number | null;
        location_method: 'browser_gps' | 'gps_unavailable';
        location_status: LocationStatus;
        unavailable_reason?: string | null;
    }) => {
        router.post(clockOut.url(visit.id), {
            ...payload,
            visit_notes: form.data.visit_notes || null,
            handover_note: form.data.handover_note || null,
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

    if (!canClockOut) {
        return null;
    }

    if (!open) {
        return (
        <Button type="button" className="min-h-11 w-full sm:w-auto" onClick={() => setOpen(true)}>
            Review & Clock Out
        </Button>
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
                    label="Handover"
                    value={form.data.handover_note || 'None'}
                />
                <ReviewItem
                    label="Clock-in location"
                    value={visit.location_status_label}
                />
            </dl>

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
                            unavailable_reason: form.data.unavailable_reason,
                        });
                    }}
                >
                    <p className="text-sm">
                        GPS is not available. Record a reason to clock out
                        without coordinates. Location will not be faked.
                    </p>
                    <div className="grid gap-2">
                        <Label htmlFor="clock_out_unavailable_reason">
                            GPS unavailable reason
                        </Label>
                        <textarea
                            id="clock_out_unavailable_reason"
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
            <InputError className="mt-2" message={pageErrors.visit} />
        </div>
    );
}

function ReviewItem({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5 whitespace-pre-wrap">{value}</dd>
        </div>
    );
}
