import type { ReactNode } from 'react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ClockInAction } from '@/components/mdm/clock-in-action';
import { StatusBadge, controlClassName } from '@/components/mdm/directory';
import { IdentityHeader } from '@/components/mdm/identity-header';
import { Panel } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { show as showClient } from '@/routes/clients';
import { show as showEmployee } from '@/routes/employees';
import {
    edit,
    index as visitsIndex,
    show,
} from '@/routes/scheduled-visits';
import { show as showVisit } from '@/routes/visits';
import type { ClockInVisitSummary, DashboardActiveVisit } from '@/types/dashboard';
import type { DspScheduleOption, VisitRecord } from '@/types/directory';

export default function ScheduledVisitsShow({
    visit,
    can,
    activeVisit,
    clockInVisit,
    dsps = [],
}: {
    visit: VisitRecord;
    can: { update: boolean; clock_in: boolean; duplicate?: boolean; replace?: boolean };
    activeVisit: DashboardActiveVisit | null;
    clockInVisit: ClockInVisitSummary | null;
    dsps?: DspScheduleOption[];
}) {
    const role = usePage().props.auth.user.role;
    const canOpenDirectories = role === 'ADMIN' || role === 'SUPERVISOR';

    return (
        <>
            <Head title={visit.service_type} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <IdentityHeader
                    eyebrow={
                        <Link
                            href={visitsIndex()}
                            className="hover:text-foreground"
                        >
                            Scheduled Visits
                        </Link>
                    }
                    title={visit.client?.name ?? visit.client_name}
                    meta={
                        <>
                            <StatusBadge
                                status={visit.status}
                                label={visit.status_label}
                            />
                            <span className="text-muted-foreground text-sm">
                                {visit.service_type} · {visit.service_date} ·{' '}
                                {visit.time_label}
                            </span>
                        </>
                    }
                    actions={
                        can.update || can.duplicate || visit.recorded_visit ? (
                            <>
                                {can.update && (
                                    <Button asChild variant="secondary">
                                        <Link href={edit(visit.id)}>Edit</Link>
                                    </Button>
                                )}
                                {can.duplicate && (
                                    <Form
                                        action={`/scheduled-visits/${visit.id}/duplicate`}
                                        method="post"
                                    >
                                        <input type="hidden" name="preset" value="next_week" />
                                        <Button type="submit" variant="outline">
                                            Same day next week
                                        </Button>
                                    </Form>
                                )}
                                {visit.visit_phase === 'completed' &&
                                    visit.recorded_visit && (
                                        <Button asChild>
                                            <Link
                                                href={showVisit(
                                                    visit.recorded_visit.id,
                                                )}
                                            >
                                                View Visit Summary
                                            </Link>
                                        </Button>
                                    )}
                                {visit.visit_phase === 'active' &&
                                    visit.active_visit_id &&
                                    role !== 'DSP' && (
                                        <Button asChild>
                                            <Link
                                                href={showVisit(
                                                    visit.active_visit_id,
                                                )}
                                            >
                                                Continue Visit
                                            </Link>
                                        </Button>
                                    )}
                            </>
                        ) : undefined
                    }
                />

                {visit.needs_attention && (
                    <p className="border-warning/40 bg-warning/10 rounded-md border px-3 py-2 text-sm">
                        {visit.attention_reason ?? 'This visit needs staffing attention.'}
                    </p>
                )}

                {role === 'DSP' && visit.visit_phase !== 'completed' && (
                    <ClockInAction
                        activeVisit={activeVisit}
                        clockInVisit={clockInVisit}
                        scheduledVisitId={visit.id}
                        canClockIn={can.clock_in}
                        emptyTitle="Upcoming Visit"
                        emptyDetail={
                            visit.start_unavailable_reason ??
                            `Start Visit becomes available on ${visit.service_date ?? 'the scheduled date'} during ${visit.time_label}. Future visits cannot be started early.`
                        }
                    />
                )}

                {visit.visit_phase === 'completed' && visit.recorded_visit && (
                    <Panel title="Completed Visit">
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <Detail
                                label="Clock in"
                                value={visit.recorded_visit.clocked_in_at_label}
                            />
                            <Detail
                                label="Clock out"
                                value={
                                    visit.recorded_visit.clocked_out_at_label ??
                                    '—'
                                }
                            />
                            <Detail
                                label="Duration"
                                value={
                                    visit.recorded_visit.duration_label ?? '—'
                                }
                            />
                            <Detail
                                label="GPS / location"
                                value={
                                    visit.recorded_visit
                                        .location_status_label ?? '—'
                                }
                            />
                            <Detail
                                label="Tasks"
                                value={`${visit.recorded_visit.task_summary.completed} completed · ${visit.recorded_visit.task_summary.skipped} skipped · ${visit.recorded_visit.task_summary.pending} pending`}
                            />
                            {visit.recorded_visit.exceptions.length > 0 && (
                                <Detail
                                    label="Attention"
                                    value={`${visit.recorded_visit.exceptions.length} exception${visit.recorded_visit.exceptions.length === 1 ? '' : 's'}`}
                                />
                            )}
                        </dl>
                        {visit.recorded_visit.tasks.some(
                            (task) => task.status === 'skipped',
                        ) && (
                            <div className="mt-3">
                                <p className="text-muted-foreground text-xs">
                                    Skipped tasks
                                </p>
                                <ul className="mt-1 text-sm">
                                    {visit.recorded_visit.tasks
                                        .filter(
                                            (task) => task.status === 'skipped',
                                        )
                                        .map((task) => (
                                            <li key={task.id}>{task.title}</li>
                                        ))}
                                </ul>
                            </div>
                        )}
                        {(visit.recorded_visit.visit_notes ||
                            visit.recorded_visit.handover_note) && (
                            <div className="mt-3 space-y-2 text-sm">
                                {visit.recorded_visit.visit_notes && (
                                    <p>
                                        <span className="text-muted-foreground text-xs">
                                            Notes
                                        </span>
                                        <br />
                                        {visit.recorded_visit.visit_notes}
                                    </p>
                                )}
                                {visit.recorded_visit.handover_note && (
                                    <p>
                                        <span className="text-muted-foreground text-xs">
                                            Handover
                                        </span>
                                        <br />
                                        {visit.recorded_visit.handover_note}
                                    </p>
                                )}
                            </div>
                        )}
                        <Button className="mt-4" asChild>
                            <Link href={showVisit(visit.recorded_visit.id)}>
                                View Visit Summary
                            </Link>
                        </Button>
                    </Panel>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title={
                            visit.visit_phase === 'completed'
                                ? 'Visit details'
                                : visit.visit_phase === 'active'
                                  ? 'Active Visit'
                                  : 'Upcoming Visit'
                        }
                    >
                        <dl className="grid gap-3 text-sm">
                            <Detail label="Service" value={visit.service_type} />
                            <Detail
                                label="Client"
                                value={
                                    canOpenDirectories && visit.client ? (
                                        <Link
                                            href={showClient(visit.client.id)}
                                            className="hover:text-foreground"
                                        >
                                            {visit.client.name} (
                                            {visit.client.client_number})
                                        </Link>
                                    ) : (
                                        (visit.client?.name ?? visit.client_name)
                                    )
                                }
                            />
                            <Detail
                                label="DSP"
                                value={
                                    canOpenDirectories && visit.employee ? (
                                        <Link
                                            href={showEmployee(visit.employee.id)}
                                            className="hover:text-foreground"
                                        >
                                            {visit.employee.name} (
                                            {visit.employee.employee_number})
                                        </Link>
                                    ) : (
                                        visit.dsp_name
                                    )
                                }
                            />
                            <Detail
                                label="Supervisor"
                                value={
                                    visit.supervisor?.name ??
                                    visit.supervisor_name ??
                                    'None'
                                }
                            />
                            <Detail
                                label="Shift template"
                                value={
                                    visit.shift_template?.name ??
                                    visit.shift_name ??
                                    'Custom times'
                                }
                            />
                            <Detail label="Window" value={visit.time_label} />
                        </dl>
                    </Panel>
                    <Panel title="Notes">
                        {visit.notes ? (
                            <p className="text-sm whitespace-pre-wrap">
                                {visit.notes}
                            </p>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                No schedule notes.
                            </p>
                        )}
                    </Panel>
                </div>

                {can.replace && (
                    <Panel title="Replace DSP">
                        <Form
                            action={`/scheduled-visits/${visit.id}/replace`}
                            method="post"
                            className="grid gap-3 md:grid-cols-2"
                        >
                            <select
                                name="employee_id"
                                required
                                className={controlClassName}
                                defaultValue=""
                            >
                                <option value="">Select replacement DSP</option>
                                {dsps
                                    .filter((dsp) => dsp.id !== visit.employee_id)
                                    .map((dsp) => (
                                        <option key={dsp.id} value={dsp.id}>
                                            {dsp.name}
                                        </option>
                                    ))}
                            </select>
                            <input
                                name="reason"
                                placeholder="Call-off / replacement reason"
                                required
                                className={controlClassName}
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="mark_call_off" value="1" />
                                Mark original DSP unavailable for this window
                            </label>
                            <Button type="submit">Assign replacement</Button>
                        </Form>
                        <p className="text-muted-foreground mt-2 text-xs">
                            Original assignment history is kept. Use the
                            availability board when creating a new visit if you
                            need a visual comparison.
                        </p>
                    </Panel>
                )}

                {(visit.assignments?.length ?? 0) > 0 && (
                    <Panel title="Assignment history">
                        <ul className="space-y-2 text-sm">
                            {visit.assignments?.map((row) => (
                                <li key={row.id}>
                                    {row.employee_name} · {row.kind}
                                    {row.reason ? ` · ${row.reason}` : ''}
                                    {row.ended_at ? ' (ended)' : ''}
                                </li>
                            ))}
                        </ul>
                    </Panel>
                )}
            </div>
        </>
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
            <dd className="mt-0.5">{value}</dd>
        </div>
    );
}

ScheduledVisitsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scheduled Visits', href: visitsIndex() },
        { title: 'Visit', href: show.url(0) },
    ],
};
