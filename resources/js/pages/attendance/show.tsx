import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/mdm/directory';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as attendanceIndex, show } from '@/routes/attendance';
import {
    approve as approveCorrection,
    reject as rejectCorrection,
    store as storeCorrection,
} from '@/routes/attendance/corrections';
import { show as showVisit } from '@/routes/visits';
import type { AttendanceRecord } from '@/types/attendance';

export default function AttendanceShow({
    record,
    can,
}: {
    record: AttendanceRecord;
    can: { request_correction: boolean; review_corrections: boolean };
}) {
    const correctionForm = useForm({
        requested_clocked_in_at: record.clock_in_input ?? '',
        requested_clocked_out_at: record.clock_out_input ?? '',
        reason: '',
        note: '',
    });

    return (
        <>
            <Head title={`Attendance · ${record.employee.name}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link
                            href={attendanceIndex()}
                            className="hover:text-foreground"
                        >
                            Attendance
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {record.employee.name}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {record.client.name} · {record.service_date}
                    </p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <StatusBadge
                            status={record.status}
                            label={record.status_label}
                        />
                        {record.has_gps_issue && (
                            <span className="text-muted-foreground text-sm">
                                {record.gps_label ?? 'GPS issue'}
                            </span>
                        )}
                        {record.has_exception && (
                            <span className="text-sm">Exception on visit</span>
                        )}
                        {record.adjustment_label && (
                            <span className="text-sm">
                                {record.adjustment_label}
                            </span>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Times">
                        <dl className="grid gap-3 text-sm">
                            <Detail
                                label="Scheduled"
                                value={record.scheduled_time}
                            />
                            <Detail
                                label="Original clock-in"
                                value={record.original_clock_in ?? '—'}
                            />
                            <Detail
                                label="Original clock-out"
                                value={record.original_clock_out ?? '—'}
                            />
                            {record.is_adjusted && (
                                <>
                                    <Detail
                                        label="Adjusted clock-in"
                                        value={
                                            record.effective_clock_in ?? '—'
                                        }
                                    />
                                    <Detail
                                        label="Adjusted clock-out"
                                        value={
                                            record.effective_clock_out ?? '—'
                                        }
                                    />
                                </>
                            )}
                            <Detail
                                label="Worked duration"
                                value={record.worked_duration ?? '—'}
                            />
                            {record.is_adjusted && record.original_duration && (
                                <Detail
                                    label="Original duration"
                                    value={record.original_duration}
                                />
                            )}
                        </dl>
                        {record.visit_id && (
                            <Button asChild variant="secondary" className="mt-4">
                                <Link href={showVisit.url(record.visit_id)}>
                                    Open visit
                                </Link>
                            </Button>
                        )}
                    </Panel>

                    {can.request_correction && (
                        <Panel title="Attendance correction">
                            <p className="text-muted-foreground mb-3 text-sm">
                                Original DSP clock events stay on the visit
                                record. Approved adjustments are stored separately.
                            </p>
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    correctionForm.post(
                                        storeCorrection.url(record.id),
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="requested_clocked_in_at">
                                        Corrected start
                                    </Label>
                                    <input
                                        id="requested_clocked_in_at"
                                        type="datetime-local"
                                        value={
                                            correctionForm.data
                                                .requested_clocked_in_at
                                        }
                                        onChange={(event) =>
                                            correctionForm.setData(
                                                'requested_clocked_in_at',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input flex h-9 w-full rounded-md border bg-background px-3 text-sm"
                                    />
                                    <InputError
                                        message={
                                            correctionForm.errors
                                                .requested_clocked_in_at
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="requested_clocked_out_at">
                                        Corrected end
                                    </Label>
                                    <input
                                        id="requested_clocked_out_at"
                                        type="datetime-local"
                                        value={
                                            correctionForm.data
                                                .requested_clocked_out_at
                                        }
                                        onChange={(event) =>
                                            correctionForm.setData(
                                                'requested_clocked_out_at',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input flex h-9 w-full rounded-md border bg-background px-3 text-sm"
                                    />
                                    <InputError
                                        message={
                                            correctionForm.errors
                                                .requested_clocked_out_at
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="reason">Reason</Label>
                                    <textarea
                                        id="reason"
                                        rows={3}
                                        value={correctionForm.data.reason}
                                        onChange={(event) =>
                                            correctionForm.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        required
                                    />
                                    <InputError
                                        message={correctionForm.errors.reason}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="note">Note</Label>
                                    <textarea
                                        id="note"
                                        rows={3}
                                        value={correctionForm.data.note}
                                        onChange={(event) =>
                                            correctionForm.setData(
                                                'note',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input flex min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    />
                                    <InputError
                                        message={correctionForm.errors.note}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={correctionForm.processing}
                                >
                                    Submit correction
                                </Button>
                            </form>
                        </Panel>
                    )}

                    <Panel title="Correction history" className="lg:col-span-2">
                        {(record.corrections ?? []).length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No correction requests for this record.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {(record.corrections ?? []).map((correction) => (
                                    <li
                                        key={correction.id}
                                        className="border-border border-t pt-4 first:border-t-0 first:pt-0"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <StatusBadge
                                                status={correction.status}
                                                label={correction.status_label}
                                            />
                                            <span className="text-muted-foreground text-xs">
                                                {correction.requested_by_name}
                                            </span>
                                        </div>
                                        <p className="mt-2 text-sm">
                                            {correction.reason}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Original{' '}
                                            {correction.original_clock_in ??
                                                '—'}{' '}
                                            –{' '}
                                            {correction.original_clock_out ??
                                                '—'}
                                        </p>
                                        <p className="text-xs">
                                            Requested{' '}
                                            {correction.requested_clock_in ??
                                                'unchanged'}{' '}
                                            –{' '}
                                            {correction.requested_clock_out ??
                                                'unchanged'}
                                        </p>
                                        {correction.review_note && (
                                            <p className="mt-2 text-sm">
                                                Review note:{' '}
                                                {correction.review_note}
                                            </p>
                                        )}
                                        {can.review_corrections &&
                                            correction.status === 'pending' && (
                                                <CorrectionReview
                                                    id={correction.id}
                                                />
                                            )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

function CorrectionReview({ id }: { id: number }) {
    const form = useForm({ review_note: '' });

    return (
        <div className="mt-3 space-y-2">
            <Label htmlFor={`review_note_${id}`}>Review note</Label>
            <textarea
                id={`review_note_${id}`}
                rows={2}
                value={form.data.review_note}
                onChange={(event) =>
                    form.setData('review_note', event.target.value)
                }
                className="border-input flex min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
            <div className="flex gap-2">
                <Button
                    type="button"
                    onClick={() =>
                        form.patch(approveCorrection.url(id), {
                            preserveScroll: true,
                        })
                    }
                    disabled={form.processing}
                >
                    Approve
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        form.patch(rejectCorrection.url(id), {
                            preserveScroll: true,
                        })
                    }
                    disabled={form.processing}
                >
                    Reject
                </Button>
            </div>
        </div>
    );
}

function Detail({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5">{value}</dd>
        </div>
    );
}

AttendanceShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Attendance', href: attendanceIndex() },
        { title: 'Record', href: show.url(0) },
    ],
};
