import { Link, router } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { HighPriorityIndicator } from '@/components/mdm/priority-indicator';
import {
    OpsRecordCard,
    ResponsiveOpsList,
} from '@/components/mdm/responsive-ops-list';
import { EmptyState, Panel, StatCard } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { show as showClient } from '@/routes/clients';
import { show as showEmployee } from '@/routes/employees';
import { index as incidentsIndex, show as showIncident } from '@/routes/incidents';
import { index as exceptionsIndex, show as showException } from '@/routes/visit-exceptions';
import { show as showScheduled } from '@/routes/scheduled-visits';
import { show as showVisit } from '@/routes/visits';
import type { OperationsBoard, OperationsVisitRow } from '@/types/operations';

export function OperationsBoardView({
    operations,
    heading = 'Supervisor operations',
    description,
}: {
    operations: OperationsBoard;
    heading?: string;
    description?: string;
}) {
    return (
        <div className="page-shell">
            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {heading}
                    </h1>
                    <p className="text-muted-foreground text-sm break-words">
                        {description ??
                            `Caseload board for ${operations.today} (${operations.timezone_label}).`}
                    </p>
                </div>
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
                    <Button
                        className="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        asChild
                        variant="secondary"
                    >
                        <Link href={incidentsIndex()}>Incident register</Link>
                    </Button>
                    <Button
                        className="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        asChild
                        variant="secondary"
                    >
                        <Link href={exceptionsIndex()}>Exception review</Link>
                    </Button>
                </div>
            </div>

            <div className="grid gap-3 grid-cols-2 sm:gap-4 xl:grid-cols-5">
                {operations.metrics.map((metric) => (
                    <StatCard
                        key={metric.key}
                        label={metric.label}
                        value={metric.value}
                        hint={metric.hint}
                        href={metric.href}
                    />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-12">
                {(operations.urgent_staffing_needed_visits ?? []).length >
                    0 && (
                    <Panel
                        title="Urgent Staffing Needed"
                        description={`Unstaffed visits within ${
                            operations.staffing_urgency_threshold_hours ?? '—'
                        } hours of start (Agency timezone), or already past start.`}
                        className="border-destructive/40 lg:col-span-12"
                    >
                        <StaffingQueueList
                            visits={operations.urgent_staffing_needed_visits ?? []}
                            empty="No urgent Staffing Needed visits."
                            detail="staffing"
                        />
                    </Panel>
                )}
                <Panel
                    title="Staffing Needed"
                    description="Upcoming visits with no assigned DSP, soonest first."
                    className="lg:col-span-4"
                >
                    <StaffingQueueList
                        visits={operations.staffing_needed_visits ?? []}
                        empty="No visits need staffing."
                        detail="staffing"
                    />
                </Panel>
                <Panel
                    title="Compliance Grace visits"
                    description="Nearest visit preserved after a DSP Hard Stop."
                    className="lg:col-span-4"
                >
                    <StaffingQueueList
                        visits={operations.grace_visits ?? []}
                        empty="No Grace visits."
                        detail="compliance"
                    />
                </Panel>
                <Panel
                    title="Compliance At Risk"
                    description="Later visits needing action after a DSP Hard Stop."
                    className="lg:col-span-4"
                >
                    <StaffingQueueList
                        visits={operations.at_risk_visits ?? []}
                        empty="No At Risk visits."
                        detail="compliance"
                    />
                </Panel>
                <Panel
                    title="Unacknowledged availability overrides"
                    description="Near-term visits assigned over a DSP's approved leave or unavailable period that the DSP has not acknowledged."
                    className="lg:col-span-12"
                >
                    {(operations.availability_overrides?.unacknowledged ?? [])
                        .length === 0 ? (
                        <EmptyState
                            compact
                            message="No unacknowledged availability overrides."
                        />
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {(
                                operations.availability_overrides
                                    ?.unacknowledged ?? []
                            ).map((override) => (
                                <li key={override.id}>
                                    <Link
                                        href={showScheduled.url(
                                            override.scheduled_visit_id,
                                        )}
                                        className="hover:text-foreground font-medium"
                                    >
                                        {override.client.name} ·{' '}
                                        {override.service_date}{' '}
                                        {override.time_label}
                                    </Link>
                                    <p className="text-muted-foreground text-xs">
                                        {override.employee.name} ·{' '}
                                        {override.conflict_kind === 'leave'
                                            ? 'Approved leave'
                                            : 'Approved unavailable'}{' '}
                                        overridden
                                        {override.overridden_by_name
                                            ? ` by ${override.overridden_by_name}`
                                            : ''}{' '}
                                        ({override.overridden_at_label}) ·
                                        Reason: {override.override_reason}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
                <Panel
                    title="Today's visits"
                    description="Scheduled, in progress, completed, late, and exceptions."
                    className="lg:col-span-12"
                >
                    <OperationsVisitTable
                        visits={operations.today_visits}
                        empty="No visits on today's caseload."
                    />
                </Panel>
                <Panel
                    title="Active visits"
                    description="Currently clocked-in visits."
                    className="lg:col-span-8"
                >
                    <OperationsVisitTable
                        visits={operations.active_visits}
                        empty="No active visits."
                    />
                </Panel>
                <Panel title="Assigned DSPs" className="lg:col-span-4">
                    {operations.assigned_dsps.length === 0 ? (
                        <EmptyState compact message="No assigned DSPs." />
                    ) : (
                        <ul className="space-y-1">
                            {operations.assigned_dsps.map((dsp) => (
                                <li key={dsp.id}>
                                    <Link
                                        href={showEmployee.url(dsp.id)}
                                        className="hover:text-foreground text-sm font-medium"
                                    >
                                        {dsp.name}
                                    </Link>
                                    <p className="text-muted-foreground text-xs">
                                        {dsp.employee_number}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
                <Panel title="Completed visits" className="lg:col-span-8">
                    <OperationsVisitTable
                        visits={operations.completed_visits}
                        empty="No completed visits today."
                    />
                </Panel>
                <Panel title="Assigned clients" className="lg:col-span-4">
                    {operations.assigned_clients.length === 0 ? (
                        <EmptyState compact message="No assigned clients." />
                    ) : (
                        <ul className="space-y-1">
                            {operations.assigned_clients.map((client) => (
                                <li key={client.id}>
                                    <Link
                                        href={showClient.url(client.id)}
                                        className="hover:text-foreground text-sm font-medium"
                                    >
                                        {client.name}
                                    </Link>
                                    <p className="text-muted-foreground text-xs">
                                        {client.client_number}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
                <Panel title="Skipped tasks" className="lg:col-span-6">
                    {operations.skipped_tasks.length === 0 ? (
                        <EmptyState compact message="No skipped tasks today." />
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {operations.skipped_tasks.map((task) => (
                                <li key={task.id}>
                                    <Link
                                        href={showVisit.url(task.visit_id)}
                                        className="hover:text-foreground font-medium"
                                    >
                                        {task.title}
                                    </Link>
                                    <p className="text-muted-foreground text-xs">
                                        {task.client_name} · {task.dsp_name}
                                        {task.skip_reason_name
                                            ? ` · ${task.skip_reason_name}`
                                            : ''}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
                <Panel title="Handover notes" className="lg:col-span-6">
                    {operations.handover_notes.length === 0 ? (
                        <EmptyState compact message="No handover notes today." />
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {operations.handover_notes.map((note) => (
                                <li key={note.id}>
                                    <Link
                                        href={showVisit.url(note.visit_id)}
                                        className="hover:text-foreground font-medium"
                                    >
                                        {note.client_name}
                                    </Link>
                                    <p className="text-muted-foreground whitespace-pre-wrap text-xs">
                                        {note.handover_note}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
                <Panel title="GPS / location exceptions" className="lg:col-span-6">
                    <ExceptionLinks
                        exceptions={operations.exceptions.gps}
                        empty="No open GPS exceptions."
                    />
                </Panel>
                <Panel title="Client refusals" className="lg:col-span-6">
                    <ExceptionLinks
                        exceptions={operations.exceptions.client_refusals}
                        empty="No open client refusals."
                    />
                </Panel>
                <Panel title="Critical task skips" className="lg:col-span-6">
                    <ExceptionLinks
                        exceptions={operations.exceptions.critical_skips}
                        empty="No open critical task skips."
                    />
                </Panel>
                <Panel title="Unfinished-task exceptions" className="lg:col-span-6">
                    <ExceptionLinks
                        exceptions={operations.exceptions.unfinished}
                        empty="No open unfinished-task exceptions."
                    />
                </Panel>
                <Panel title="Missed safety checks" className="lg:col-span-6">
                    <ExceptionLinks
                        exceptions={operations.exceptions.missed_safety ?? []}
                        empty="No open missed safety checks."
                    />
                </Panel>
                <Panel title="Extended / open visits" className="lg:col-span-6">
                    <ExceptionLinks
                        exceptions={[
                            ...(operations.exceptions.extended_visits ?? []),
                            ...(operations.exceptions.open_escalations ?? []),
                        ]}
                        empty="No open extended-visit or escalation exceptions."
                    />
                </Panel>
                {operations.material_corrections !== undefined ? (
                    <Panel
                        title="Material reconstruction / correction attention"
                        description="Admin visibility for material EVV reconstructions and Missed → Cancelled reclassifications. Approval authority is unchanged."
                        className="lg:col-span-12"
                    >
                        {operations.material_corrections.length === 0 ? (
                            <EmptyState
                                compact
                                message="No recent material reconstruction or reclassification events."
                            />
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {operations.material_corrections.map((item) => (
                                    <li key={item.key}>
                                        <div className="rounded-lg border border-border/70 px-3 py-2.5">
                                            {item.url ? (
                                                <Link
                                                    href={item.url}
                                                    className="hover:text-foreground font-medium break-words"
                                                >
                                                    {item.kind_label}
                                                </Link>
                                            ) : (
                                                <span className="font-medium break-words">
                                                    {item.kind_label}
                                                </span>
                                            )}
                                            <p className="text-muted-foreground mt-1 text-xs break-words">
                                                {[
                                                    item.client_name,
                                                    item.employee_name,
                                                    item.service_date,
                                                    item.occurred_at,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                ) : null}
                <Panel
                    title="Incidents awaiting acknowledgment"
                    description="Submitted Incidents needing management acknowledgment (not resolution)."
                    className="lg:col-span-6"
                >
                    {(operations.incidents?.awaiting_acknowledgment ?? []).length ===
                    0 ? (
                        <EmptyState
                            compact
                            message="No Incidents awaiting acknowledgment."
                        />
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {(operations.incidents?.awaiting_acknowledgment ?? []).map(
                                (incident) => (
                                    <li key={incident.id}>
                                        <Link
                                            href={showIncident.url(incident.id)}
                                            className="hover:text-foreground font-medium"
                                        >
                                            {incident.tracking_number ??
                                                `Incident #${incident.id}`}
                                        </Link>
                                        <p className="text-muted-foreground text-xs">
                                            {incident.client.name}
                                            {incident.overdue_for_acknowledgment
                                                ? ' · Overdue for acknowledgment'
                                                : ''}
                                        </p>
                                    </li>
                                ),
                            )}
                        </ul>
                    )}
                </Panel>
                <Panel
                    title="Incidents overdue for acknowledgment"
                    description="Derived overdue condition after the Agency threshold."
                    className="lg:col-span-6"
                >
                    {(operations.incidents?.overdue_for_acknowledgment ?? [])
                        .length === 0 ? (
                        <EmptyState
                            compact
                            message="No Incidents overdue for acknowledgment."
                        />
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {(
                                operations.incidents?.overdue_for_acknowledgment ??
                                []
                            ).map((incident) => (
                                <li key={incident.id}>
                                    <Link
                                        href={showIncident.url(incident.id)}
                                        className="hover:text-foreground font-medium"
                                    >
                                        {incident.tracking_number ??
                                            `Incident #${incident.id}`}
                                    </Link>
                                    <p className="text-muted-foreground text-xs">
                                        {incident.client.name} ·{' '}
                                        {incident.submitted_at_label}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </div>
    );
}

export function StaffingQueueList({
    visits,
    empty,
    detail,
}: {
    visits: OperationsVisitRow[];
    empty: string;
    detail: 'staffing' | 'compliance';
}) {
    if (visits.length === 0) {
        return <EmptyState compact message={empty} />;
    }

    return (
        <ul className="space-y-2">
            {visits.map((visit) => {
                const note =
                    detail === 'compliance'
                        ? visit.compliance_staffing_reason
                        : visit.attention_reason;

                return (
                    <li key={visit.scheduled_visit_id}>
                        <Link
                            href={showScheduled.url(visit.scheduled_visit_id)}
                            className="hover:text-foreground block rounded-lg border border-border/70 px-3 py-2.5 text-sm"
                        >
                            <span className="flex flex-wrap items-center gap-2 font-medium break-words">
                                {visit.client.name}
                                {visit.staffing_urgency_label && (
                                    <span
                                        className={
                                            visit.staffing_urgency === 'overdue'
                                                ? 'bg-destructive text-destructive-foreground rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase'
                                                : 'rounded bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 uppercase dark:text-amber-400'
                                        }
                                    >
                                        {visit.staffing_urgency_label}
                                    </span>
                                )}
                            </span>
                            <span className="text-muted-foreground mt-1 block text-xs break-words">
                                {visit.service_date} · {visit.time_label} ·{' '}
                                {visit.service_type}
                            </span>
                            <span className="text-muted-foreground block text-xs break-words">
                                {visit.employee?.name ?? 'No DSP assigned'}
                            </span>
                            {note && (
                                <span className="text-muted-foreground mt-1 block text-xs break-words">
                                    {note}
                                </span>
                            )}
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

export function OperationsVisitTable({
    visits,
    empty,
}: {
    visits: OperationsVisitRow[];
    empty: string;
}) {
    if (visits.length === 0) {
        return <EmptyState compact message={empty} />;
    }

    const openVisit = (visit: OperationsVisitRow) => {
        router.visit(
            visit.visit_id
                ? showVisit.url(visit.visit_id)
                : showScheduled.url(visit.scheduled_visit_id),
        );
    };

    return (
        <ResponsiveOpsList
            cards={visits.map((visit) => (
                <OpsRecordCard
                    key={`card-${visit.scheduled_visit_id}-${visit.visit_id ?? 'scheduled'}`}
                    title={visit.client.name}
                    subtitle={
                        visit.employee?.name ??
                        visit.staffing_urgency_label ??
                        'Staffing Needed'
                    }
                    status={
                        <>
                            <HighPriorityIndicator
                                active={Boolean(visit.has_high_priority_open)}
                                label="Unresolved high-priority exception"
                            />
                            <StatusBadge
                                status={visit.operational_status}
                                label={visit.operational_status_label}
                            />
                        </>
                    }
                    meta={
                        <>
                            <p>
                                {visit.service_type} · {visit.service_date} ·{' '}
                                {visit.time_label}
                            </p>
                            <p>
                                In {visit.clocked_in_at_label ?? '—'} · Out{' '}
                                {visit.clocked_out_at_label ?? '—'}
                                {visit.location_status_label
                                    ? ` · ${visit.location_status_label}`
                                    : ''}
                            </p>
                            <p>
                                {visit.task_summary.completed}/
                                {visit.task_summary.total} tasks done
                                {visit.task_summary.skipped > 0
                                    ? ` · ${visit.task_summary.skipped} skipped`
                                    : ''}
                            </p>
                        </>
                    }
                    onClick={() => openVisit(visit)}
                />
            ))}
            table={
                <table className="w-full min-w-[40rem] text-left text-sm">
                    <thead className="text-muted-foreground text-xs uppercase">
                        <tr>
                            <th className="pb-2 font-medium">Client / DSP</th>
                            <th className="pb-2 font-medium">Service</th>
                            <th className="pb-2 font-medium">Scheduled</th>
                            <th className="pb-2 font-medium">Clock</th>
                            <th className="pb-2 font-medium">Tasks</th>
                            <th className="pb-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visits.map((visit) => (
                            <tr
                                key={`${visit.scheduled_visit_id}-${visit.visit_id ?? 'scheduled'}`}
                                className="hover:bg-muted/40 cursor-pointer border-t"
                                onClick={() => openVisit(visit)}
                            >
                                <td className="py-3">
                                    <p className="font-medium">
                                        {visit.client.name}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {visit.employee?.name ??
                                            visit.staffing_urgency_label ??
                                            'Staffing Needed'}
                                    </p>
                                </td>
                                <td className="py-3">{visit.service_type}</td>
                                <td className="py-3">
                                    <p>{visit.time_label}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {visit.service_date}
                                    </p>
                                </td>
                                <td className="py-3 text-xs">
                                    <p>In {visit.clocked_in_at_label ?? '—'}</p>
                                    <p>Out {visit.clocked_out_at_label ?? '—'}</p>
                                    {visit.location_status_label && (
                                        <p className="text-muted-foreground">
                                            {visit.location_status_label}
                                        </p>
                                    )}
                                </td>
                                <td className="py-3 text-xs">
                                    {visit.task_summary.completed}/
                                    {visit.task_summary.total} done
                                    {visit.task_summary.skipped > 0
                                        ? ` · ${visit.task_summary.skipped} skipped`
                                        : ''}
                                </td>
                                <td className="py-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <HighPriorityIndicator
                                            active={Boolean(
                                                visit.has_high_priority_open,
                                            )}
                                            label="Unresolved high-priority exception"
                                        />
                                        <StatusBadge
                                            status={visit.operational_status}
                                            label={
                                                visit.operational_status_label
                                            }
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            }
        />
    );
}

export function ExceptionLinks({
    exceptions,
    empty,
}: {
    exceptions: Array<{
        id: number;
        type_label: string;
        message: string;
        client_name: string | null;
        dsp_name: string | null;
        is_high_priority_open?: boolean;
    }>;
    empty: string;
}) {
    if (exceptions.length === 0) {
        return <EmptyState compact message={empty} />;
    }

    return (
        <ul className="space-y-2">
            {exceptions.map((exception) => (
                <li key={exception.id}>
                    <Link
                        href={showException.url(exception.id)}
                        className="hover:text-foreground block rounded-lg border border-border/70 px-3 py-2.5 text-sm"
                    >
                        <span className="flex flex-wrap items-center gap-2 font-medium break-words">
                            <HighPriorityIndicator
                                active={Boolean(exception.is_high_priority_open)}
                            />
                            {exception.type_label}
                        </span>
                        <span className="text-muted-foreground mt-1 block text-xs break-words">
                            {exception.client_name} · {exception.dsp_name}
                        </span>
                        <span className="text-muted-foreground block text-xs break-words">
                            {exception.message}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
