import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { ConfirmAction } from '@/components/mdm/confirm-action';
import { PrintPdfAction } from '@/components/mdm/print-pdf-action';
import { StatusBadge } from '@/components/mdm/directory';
import { BackLink } from '@/components/mdm/back-link';
import {
    ContextGroup,
    ContextStrip,
    RecordHeader,
    RecordPage,
    RecordSection,
} from '@/components/mdm/record-detail';
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

type TaskCorrectionDraft = {
    visit_task_id: number;
    requested_status: string;
    requested_skip_reason_id: string;
    requested_skip_comment: string;
    requested_completion_note: string;
    dirty: boolean;
};

export default function AttendanceShow({
    record,
    can,
    documents,
}: {
    record: AttendanceRecord;
    can: { request_correction: boolean; review_corrections: boolean };
    documents?: { completed_visit?: string | null };
}) {
    const correctionForm = useForm({
        visit_id: record.visit_id ?? '',
        requested_clocked_in_at: record.clock_in_input ?? '',
        requested_clocked_out_at: record.clock_out_input ?? '',
        requested_visit_notes: record.visit_notes ?? '',
        requested_handover_note: record.handover_note ?? '',
        requested_clock_in_unavailable_reason: '',
        is_evv_reconstruction: Boolean(record.can_reconstruct_missed),
        task_corrections: (record.visit_tasks ?? []).map(
            (task): TaskCorrectionDraft => ({
                visit_task_id: task.id,
                requested_status: task.status,
                requested_skip_reason_id: task.skip_reason_id
                    ? String(task.skip_reason_id)
                    : '',
                requested_skip_comment: task.skip_comment ?? '',
                requested_completion_note: task.completion_note ?? '',
                dirty: false,
            }),
        ),
        reason: '',
        note: '',
    });

    return (
        <>
            <Head title={`Attendance · ${record.employee.name}`} />
            <RecordPage>
                <RecordHeader
                    eyebrow={
                        <BackLink href={attendanceIndex()}>
                            Back to Attendance
                        </BackLink>
                    }
                    title={record.employee.name}
                    meta={
                        <>
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
                        </>
                    }
                    actions={
                        <>
                            {documents?.completed_visit && (
                                <PrintPdfAction href={documents.completed_visit} />
                            )}
                            {record.visit_id ? (
                                <Button asChild variant="secondary">
                                    <Link href={showVisit.url(record.visit_id)}>
                                        Open visit
                                    </Link>
                                </Button>
                            ) : undefined}
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-12">
                    <RecordSection title="Times" className="lg:col-span-7">
                        <ContextStrip className="border-0 bg-transparent p-0 shadow-none">
                            <ContextGroup label="Scheduled">
                                {record.scheduled_time}
                            </ContextGroup>
                            <ContextGroup label="Recorded clocks">
                                In {record.original_clock_in ?? '—'}
                                <span className="text-muted-foreground mt-0.5 block text-xs font-normal">
                                    Out {record.original_clock_out ?? '—'}
                                </span>
                            </ContextGroup>
                            <ContextGroup label="Duration">
                                {record.worked_duration ?? '—'}
                            </ContextGroup>
                            {record.is_adjusted ? (
                                <ContextGroup label="Adjusted clocks">
                                    In {record.effective_clock_in ?? '—'}
                                    <span className="text-muted-foreground mt-0.5 block text-xs font-normal">
                                        Out {record.effective_clock_out ?? '—'}
                                        {record.original_duration
                                            ? ` · original ${record.original_duration}`
                                            : ''}
                                    </span>
                                </ContextGroup>
                            ) : null}
                            <ContextGroup label="Client">{record.client.name}</ContextGroup>
                            <ContextGroup label="DSP">{record.employee.name}</ContextGroup>
                        </ContextStrip>
                    </RecordSection>

                    {can.request_correction && (
                        <RecordSection title="Attendance correction" className="lg:col-span-5">
                            <p className="text-muted-foreground mb-3 text-sm">
                                Original DSP clock events, notes, GPS evidence,
                                and task outcomes stay on record. Pending
                                requests are not effective until approved.
                            </p>
                            {record.can_reconstruct_missed ? (
                                <p className="mb-3 text-sm">
                                    This Scheduled Visit is Missed with no Visit
                                    EVV. Reconstruction creates an
                                    administratively reconstructed Completed
                                    Visit and keeps the original Missed history.
                                </p>
                            ) : null}
                            {record.pending_overlap_conflict ? (
                                <p className="text-destructive mb-3 text-sm">
                                    A pending correction has an unresolved
                                    same-DSP overlap conflict and cannot be
                                    finalized until resolved.
                                </p>
                            ) : null}
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    correctionForm.transform((data) => {
                                        const payload: Record<string, unknown> =
                                            {
                                                visit_id: data.visit_id
                                                    ? Number(data.visit_id)
                                                    : null,
                                                requested_clocked_in_at:
                                                    data.requested_clocked_in_at ||
                                                    null,
                                                requested_clocked_out_at:
                                                    data.requested_clocked_out_at ||
                                                    null,
                                                reason: data.reason,
                                                note: data.note || null,
                                                is_evv_reconstruction: Boolean(
                                                    data.is_evv_reconstruction,
                                                ),
                                            };
                                        if (
                                            data.requested_visit_notes !==
                                            (record.visit_notes ?? '')
                                        ) {
                                            payload.requested_visit_notes =
                                                data.requested_visit_notes;
                                        }
                                        if (
                                            data.requested_handover_note !==
                                            (record.handover_note ?? '')
                                        ) {
                                            payload.requested_handover_note =
                                                data.requested_handover_note;
                                        }
                                        if (
                                            data.requested_clock_in_unavailable_reason.trim() !==
                                            ''
                                        ) {
                                            payload.requested_clock_in_unavailable_reason =
                                                data.requested_clock_in_unavailable_reason;
                                        }
                                        const taskRows = data.task_corrections
                                            .filter((row) => row.dirty)
                                            .map((row) => ({
                                                visit_task_id:
                                                    row.visit_task_id,
                                                requested_status:
                                                    row.requested_status,
                                                requested_skip_reason_id:
                                                    row.requested_status ===
                                                    'skipped'
                                                        ? Number(
                                                              row.requested_skip_reason_id,
                                                          ) || null
                                                        : null,
                                                requested_skip_comment:
                                                    row.requested_status ===
                                                    'skipped'
                                                        ? row.requested_skip_comment ||
                                                          null
                                                        : null,
                                                requested_completion_note:
                                                    row.requested_status ===
                                                    'completed'
                                                        ? row.requested_completion_note ||
                                                          null
                                                        : null,
                                            }));
                                        if (taskRows.length > 0) {
                                            payload.task_corrections = taskRows;
                                        }
                                        return payload;
                                    });
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
                                    <Label htmlFor="requested_visit_notes">
                                        Corrected visit notes
                                    </Label>
                                    <textarea
                                        id="requested_visit_notes"
                                        rows={2}
                                        value={
                                            correctionForm.data
                                                .requested_visit_notes
                                        }
                                        onChange={(event) =>
                                            correctionForm.setData(
                                                'requested_visit_notes',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input flex min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="requested_handover_note">
                                        Corrected handover note
                                    </Label>
                                    <textarea
                                        id="requested_handover_note"
                                        rows={2}
                                        value={
                                            correctionForm.data
                                                .requested_handover_note
                                        }
                                        onChange={(event) =>
                                            correctionForm.setData(
                                                'requested_handover_note',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input flex min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="requested_clock_in_unavailable_reason">
                                        GPS context / attestation note
                                    </Label>
                                    <textarea
                                        id="requested_clock_in_unavailable_reason"
                                        rows={2}
                                        value={
                                            correctionForm.data
                                                .requested_clock_in_unavailable_reason
                                        }
                                        onChange={(event) =>
                                            correctionForm.setData(
                                                'requested_clock_in_unavailable_reason',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input flex min-h-16 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                        placeholder="Cannot manufacture missing coordinates"
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={
                                            correctionForm.data
                                                .is_evv_reconstruction
                                        }
                                        onChange={(event) =>
                                            correctionForm.setData(
                                                'is_evv_reconstruction',
                                                event.target.checked,
                                            )
                                        }
                                        disabled={Boolean(
                                            record.can_reconstruct_missed,
                                        )}
                                    />
                                    {record.can_reconstruct_missed
                                        ? 'Zero-EVV Missed reconstruction (required)'
                                        : 'Mark as EVV reconstruction (missing original clocks)'}
                                </label>
                                {(record.visit_tasks ?? []).length > 0 ? (
                                    <div className="space-y-3">
                                        <Label>Task / status corrections</Label>
                                        {correctionForm.data.task_corrections.map(
                                            (row, index) => {
                                                const task =
                                                    record.visit_tasks?.[
                                                        index
                                                    ];
                                                if (!task) {
                                                    return null;
                                                }

                                                return (
                                                    <div
                                                        key={row.visit_task_id}
                                                        className="border-border space-y-2 border-t pt-3 first:border-t-0 first:pt-0"
                                                    >
                                                        <p className="text-sm font-medium">
                                                            {task.title}
                                                            <span className="text-muted-foreground ml-2 text-xs font-normal">
                                                                was{' '}
                                                                {
                                                                    task.status_label
                                                                }
                                                            </span>
                                                        </p>
                                                        <select
                                                            value={
                                                                row.requested_status
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                const next = [
                                                                    ...correctionForm
                                                                        .data
                                                                        .task_corrections,
                                                                ];
                                                                next[index] = {
                                                                    ...row,
                                                                    requested_status:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    dirty:
                                                                        event
                                                                            .target
                                                                            .value !==
                                                                        task.status,
                                                                };
                                                                correctionForm.setData(
                                                                    'task_corrections',
                                                                    next,
                                                                );
                                                            }}
                                                            className="border-input flex h-9 w-full rounded-md border bg-background px-3 text-sm"
                                                        >
                                                            <option value="pending">
                                                                Pending
                                                            </option>
                                                            <option value="completed">
                                                                Completed
                                                            </option>
                                                            <option value="skipped">
                                                                Skipped
                                                            </option>
                                                        </select>
                                                        {row.requested_status ===
                                                            'skipped' &&
                                                        row.dirty ? (
                                                            <select
                                                                value={
                                                                    row.requested_skip_reason_id
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    const next =
                                                                        [
                                                                            ...correctionForm
                                                                                .data
                                                                                .task_corrections,
                                                                        ];
                                                                    next[
                                                                        index
                                                                    ] = {
                                                                        ...row,
                                                                        requested_skip_reason_id:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    };
                                                                    correctionForm.setData(
                                                                        'task_corrections',
                                                                        next,
                                                                    );
                                                                }}
                                                                className="border-input flex h-9 w-full rounded-md border bg-background px-3 text-sm"
                                                                required
                                                            >
                                                                <option value="">
                                                                    Skip reason…
                                                                </option>
                                                                {(
                                                                    record.skip_reasons ??
                                                                    []
                                                                ).map(
                                                                    (
                                                                        reason,
                                                                    ) => (
                                                                        <option
                                                                            key={
                                                                                reason.id
                                                                            }
                                                                            value={String(
                                                                                reason.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                reason.name
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        ) : null}
                                                    </div>
                                                );
                                            },
                                        )}
                                        <InputError
                                            message={
                                                correctionForm.errors
                                                    .task_corrections
                                            }
                                        />
                                    </div>
                                ) : null}
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
                        </RecordSection>
                    )}

                    <RecordSection title="Correction history" className="lg:col-span-12">
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
                                        {correction.original_attendance_status ===
                                            'missed' &&
                                        correction.is_evv_reconstruction ? (
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                Original attendance: Missed
                                                (preserved). Reconstruction does
                                                not fabricate DSP GPS.
                                            </p>
                                        ) : (
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                Original{' '}
                                                {correction.original_clock_in ??
                                                    '—'}{' '}
                                                –{' '}
                                                {correction.original_clock_out ??
                                                    '—'}
                                            </p>
                                        )}
                                        <p className="text-xs">
                                            Requested{' '}
                                            {correction.requested_clock_in ??
                                                'unchanged'}{' '}
                                            –{' '}
                                            {correction.requested_clock_out ??
                                                'unchanged'}
                                        </p>
                                        {(correction.task_corrections ?? [])
                                            .length > 0 ? (
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                Task corrections:{' '}
                                                {(
                                                    correction.task_corrections ??
                                                    []
                                                ).length}{' '}
                                                item(s); originals retained on
                                                the correction record.
                                            </p>
                                        ) : null}
                                        {correction.review_note && (
                                            <p className="mt-2 text-sm">
                                                Review note:{' '}
                                                {correction.review_note}
                                            </p>
                                        )}
                                        {correction.can_review &&
                                            correction.status === 'pending' && (
                                                <CorrectionReview
                                                    id={correction.id}
                                                />
                                            )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </RecordSection>
                </div>
            </RecordPage>
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
                <ConfirmAction
                    triggerLabel="Reject"
                    title="Reject this correction?"
                    description="This will reject the attendance correction. Original DSP clock times stay unchanged."
                    confirmLabel="Reject correction"
                    destructive
                >
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() =>
                            form.patch(rejectCorrection.url(id), {
                                preserveScroll: true,
                            })
                        }
                        disabled={form.processing}
                    >
                        Reject correction
                    </Button>
                </ConfirmAction>
            </div>
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
