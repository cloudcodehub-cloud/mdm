import { useEffect, useState, type ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
import { VisitClockOutReview } from '@/components/mdm/visit-clock-out-review';
import { VisitNotesForm } from '@/components/mdm/visit-notes-form';
import { VisitTaskCard } from '@/components/mdm/visit-task-card';
import { dashboard } from '@/routes';
import { show as showScheduled } from '@/routes/scheduled-visits';
import { show } from '@/routes/visits';
import type {
    ActiveVisitRecord,
    SkipReasonOption,
} from '@/types/visit';

export default function VisitsShow({
    visit,
    skip_reasons = [],
    can,
}: {
    visit: ActiveVisitRecord;
    skip_reasons?: SkipReasonOption[];
    can?: {
        clock_in?: boolean;
        record_tasks?: boolean;
        update_notes?: boolean;
        clock_out?: boolean;
    };
}) {
    const completed = visit.status === 'completed';
    const title = completed
        ? `Visit summary · ${visit.client.name}`
        : `Active visit · ${visit.client.name}`;

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        <Link
                            href={showScheduled(visit.scheduled_visit.id)}
                            className="hover:text-foreground"
                        >
                            Scheduled visit
                        </Link>
                    </p>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {completed ? 'Visit summary' : visit.client.name}
                    </h1>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <StatusBadge
                            status={visit.status}
                            label={visit.status_label}
                        />
                        <span className="text-muted-foreground text-sm">
                            {visit.service_type}
                        </span>
                    </div>
                </div>

                {completed ? (
                    <VisitSummary visit={visit} />
                ) : (
                    <ActiveVisit
                        visit={visit}
                        skipReasons={skip_reasons}
                        can={can}
                    />
                )}
            </div>
        </>
    );
}

function ActiveVisit({
    visit,
    skipReasons,
    can,
}: {
    visit: ActiveVisitRecord;
    skipReasons: SkipReasonOption[];
    can?: {
        record_tasks?: boolean;
        update_notes?: boolean;
        clock_out?: boolean;
    };
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Panel title="Visit">
                <dl className="grid gap-3 text-sm">
                    <Detail label="Client" value={visit.client.name} />
                    <Detail label="Service" value={visit.service_type} />
                    <Detail
                        label="Scheduled shift"
                        value={`${visit.scheduled_visit.service_date} · ${visit.scheduled_visit.time_label}${visit.scheduled_visit.shift_name ? ` · ${visit.scheduled_visit.shift_name}` : ''}`}
                    />
                    <Detail
                        label="Clock-in time"
                        value={visit.clocked_in_at_label}
                    />
                    <Detail
                        label="Location"
                        value={visit.location_status_label}
                    />
                    {visit.unavailable_reason && (
                        <Detail
                            label="GPS note"
                            value={visit.unavailable_reason}
                        />
                    )}
                    <Detail
                        label="Elapsed"
                        value={<ElapsedSince iso={visit.clocked_in_at} />}
                    />
                </dl>
            </Panel>
            <Panel
                title="Care-plan tasks"
                description="Complete or skip each visit task. Care-plan templates are not changed."
            >
                {visit.tasks.length === 0 ? (
                    <EmptyState message="No care-plan tasks apply to this visit." />
                ) : (
                    <ul className="space-y-3">
                        {visit.tasks.map((task) => (
                            <VisitTaskCard
                                key={task.id}
                                visitId={visit.id}
                                task={task}
                                skipReasons={skipReasons}
                                canRecord={Boolean(can?.record_tasks)}
                            />
                        ))}
                    </ul>
                )}
            </Panel>
            <Panel title="Visit notes and handover">
                <VisitNotesForm
                    visitId={visit.id}
                    visitNotes={visit.visit_notes}
                    handoverNote={visit.handover_note}
                    canUpdate={Boolean(can?.update_notes)}
                />
            </Panel>
            <Panel
                title="Finish visit"
                description="Review the visit before clock-out. Clock-out uses server time and a new GPS request."
            >
                <VisitClockOutReview
                    visit={visit}
                    canClockOut={Boolean(can?.clock_out)}
                />
            </Panel>
        </div>
    );
}

function VisitSummary({ visit }: { visit: ActiveVisitRecord }) {
    const skipped = visit.tasks.filter((task) => task.status === 'skipped');

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Panel title="Visit">
                <dl className="grid gap-3 text-sm">
                    <Detail label="Client" value={visit.client.name} />
                    <Detail label="Service" value={visit.service_type} />
                    <Detail
                        label="Clock-in"
                        value={visit.clocked_in_at_label}
                    />
                    <Detail
                        label="Clock-out"
                        value={visit.clocked_out_at_label ?? '—'}
                    />
                    <Detail
                        label="Clock-in location"
                        value={visit.location_status_label}
                    />
                    <Detail
                        label="Clock-out location"
                        value={
                            visit.clock_out_location_status_label ??
                            'Not recorded'
                        }
                    />
                </dl>
            </Panel>
            <Panel title="Task summary">
                <dl className="grid gap-3 text-sm">
                    <Detail
                        label="Completed"
                        value={String(visit.task_summary.completed)}
                    />
                    <Detail
                        label="Skipped"
                        value={String(visit.task_summary.skipped)}
                    />
                    <Detail
                        label="Still pending"
                        value={String(visit.task_summary.pending)}
                    />
                    <Detail
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
                </dl>
            </Panel>
            <Panel title="Notes and handover">
                <dl className="grid gap-3 text-sm">
                    <Detail
                        label="Visit notes"
                        value={visit.visit_notes || 'None'}
                    />
                    <Detail
                        label="Handover"
                        value={visit.handover_note || 'None'}
                    />
                </dl>
            </Panel>
            <Panel title="Care-plan tasks">
                {visit.tasks.length === 0 ? (
                    <EmptyState message="No care-plan tasks applied to this visit." />
                ) : (
                    <ul className="space-y-3">
                        {visit.tasks.map((task) => (
                            <li
                                key={task.id}
                                className="rounded-xl border border-border/70 p-3"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <p className="font-medium">{task.title}</p>
                                    <StatusBadge
                                        status={task.status}
                                        label={task.status_label}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </div>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5 whitespace-pre-wrap">{value}</dd>
        </div>
    );
}

function ElapsedSince({ iso }: { iso: string }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, []);

    const started = new Date(iso).getTime();
    const seconds = Math.max(0, Math.floor((now - started) / 1000));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainder = seconds % 60;

    return (
        <span className="tabular-nums">
            Active · {String(hours).padStart(2, '0')}:
            {String(minutes).padStart(2, '0')}:
            {String(remainder).padStart(2, '0')}
        </span>
    );
}

VisitsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Active visit', href: show.url(0) },
    ],
};
