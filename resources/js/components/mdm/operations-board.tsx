import { Link, router } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
import { HighPriorityIndicator } from '@/components/mdm/priority-indicator';
import { EmptyState, Panel, StatCard } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { show as showClient } from '@/routes/clients';
import { show as showEmployee } from '@/routes/employees';
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
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        {heading}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {description ??
                            `Caseload board for ${operations.today} (${operations.timezone_label}).`}
                    </p>
                </div>
                <Button asChild variant="secondary">
                    <Link href={exceptionsIndex()}>Exception review</Link>
                </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
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
            </div>
        </div>
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

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
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
                            onClick={() =>
                                router.visit(
                                    visit.visit_id
                                        ? showVisit.url(visit.visit_id)
                                        : showScheduled.url(
                                              visit.scheduled_visit_id,
                                          ),
                                )
                            }
                        >
                            <td className="py-3">
                                <p className="font-medium">
                                    {visit.client.name}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {visit.employee.name}
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
                                <div className="flex items-center gap-2">
                                    <HighPriorityIndicator
                                        active={Boolean(visit.has_high_priority_open)}
                                        label="Unresolved high-priority exception"
                                    />
                                    <StatusBadge
                                        status={visit.operational_status}
                                        label={visit.operational_status_label}
                                    />
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
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
                        className="hover:text-foreground block text-sm"
                    >
                        <span className="flex items-center gap-2 font-medium">
                            <HighPriorityIndicator
                                active={Boolean(exception.is_high_priority_open)}
                            />
                            {exception.type_label}
                        </span>
                        <span className="text-muted-foreground block text-xs">
                            {exception.client_name} · {exception.dsp_name}
                        </span>
                        <span className="text-muted-foreground block text-xs">
                            {exception.message}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
