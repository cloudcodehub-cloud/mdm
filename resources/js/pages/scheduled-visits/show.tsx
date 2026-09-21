import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ClockInAction } from '@/components/mdm/clock-in-action';
import { CompactTaskList } from '@/components/mdm/compact-task-list';
import { StatusBadge, controlClassName } from '@/components/mdm/directory';
import {
    ActionGroup,
    AttentionBanner,
    FactGrid,
    FactItem,
    RecordHeader,
    RecordPage,
    RecordSection,
} from '@/components/mdm/record-detail';
import { PrintPdfAction } from '@/components/mdm/print-pdf-action';
import { HighPriorityIndicator } from '@/components/mdm/priority-indicator';
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
    documents,
}: {
    visit: VisitRecord;
    can: { update: boolean; clock_in: boolean; duplicate?: boolean; replace?: boolean };
    activeVisit: DashboardActiveVisit | null;
    clockInVisit: ClockInVisitSummary | null;
    dsps?: DspScheduleOption[];
    documents?: { visit_handout?: string };
}) {
    const role = usePage().props.auth.user.role;
    const canOpenDirectories = role === 'ADMIN' || role === 'SUPERVISOR';
    const recorded = visit.recorded_visit;
    const taskCount = recorded?.task_summary.total
        ?? visit.one_off_tasks?.length
        ?? 0;
    const taskProgress = recorded
        ? `${recorded.task_summary.completed}/${recorded.task_summary.total}`
        : visit.one_off_tasks && visit.one_off_tasks.length > 0
          ? `${visit.one_off_tasks.length} one-off`
          : 'Care-plan tasks apply at start';
    const highPriority = Boolean(recorded?.has_high_priority_open);

    return (
        <>
            <Head title={visit.service_type} />
            <RecordPage>
                <RecordHeader
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
                            <HighPriorityIndicator
                                active={highPriority}
                                label="Unresolved high-priority exception"
                            />
                            <span className="text-muted-foreground text-sm">
                                {visit.service_type}
                            </span>
                        </>
                    }
                    actions={
                        <ActionGroup>
                            {documents?.visit_handout && (
                                <PrintPdfAction href={documents.visit_handout} />
                            )}
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
                            {visit.visit_phase === 'completed' && recorded && (
                                <Button asChild>
                                    <Link href={showVisit(recorded.id)}>
                                        View Visit Summary
                                    </Link>
                                </Button>
                            )}
                            {visit.visit_phase === 'active' &&
                                visit.active_visit_id &&
                                role !== 'DSP' && (
                                    <Button asChild>
                                        <Link href={showVisit(visit.active_visit_id)}>
                                            Continue Visit
                                        </Link>
                                    </Button>
                                )}
                        </ActionGroup>
                    }
                />

                {visit.needs_attention && (
                    <AttentionBanner>
                        {visit.attention_reason ?? 'This visit needs staffing attention.'}
                    </AttentionBanner>
                )}

                {role === 'DSP' && visit.visit_phase !== 'completed' && (
                    <ClockInAction
                        activeVisit={activeVisit}
                        clockInVisit={clockInVisit ?? (can.clock_in ? {
                            id: visit.id,
                            service_date: visit.service_date,
                            service_type: visit.service_type,
                            time_label: visit.time_label,
                            starts_at_iso: visit.starts_at_iso,
                            client: visit.client ?? {
                                id: visit.client_id ?? 0,
                                name: visit.client_name ?? '',
                                client_number: '',
                            },
                        } : null)}
                        scheduledVisitId={visit.id}
                        canClockIn={can.clock_in}
                        emptyTitle="Upcoming Visit"
                        emptyDetail={
                            visit.start_unavailable_reason ??
                            `Start Visit becomes available on ${visit.service_date ?? 'the scheduled date'} during ${visit.time_label}. Future visits cannot be started early.`
                        }
                    />
                )}

                <RecordSection title="Key facts" compact>
                    <FactGrid>
                        <FactItem
                            label="Client"
                            value={
                                canOpenDirectories && visit.client ? (
                                    <Link
                                        href={showClient(visit.client.id)}
                                        className="hover:text-foreground"
                                    >
                                        {visit.client.name} ({visit.client.client_number})
                                    </Link>
                                ) : (
                                    (visit.client?.name ?? visit.client_name)
                                )
                            }
                        />
                        <FactItem
                            label="Service"
                            value={
                                visit.services && visit.services.length > 0
                                    ? visit.services.map((service) => service.name).join(' · ')
                                    : visit.service_type
                            }
                        />
                        <FactItem label="Status" value={visit.status_label} />
                        <FactItem label="Date" value={visit.service_date ?? '—'} />
                        <FactItem label="Start / End" value={visit.time_label} />
                        <FactItem
                            label="DSP"
                            value={
                                canOpenDirectories && visit.employee ? (
                                    <Link
                                        href={showEmployee(visit.employee.id)}
                                        className="hover:text-foreground"
                                    >
                                        {visit.employee.name} ({visit.employee.employee_number})
                                    </Link>
                                ) : (
                                    visit.dsp_name
                                )
                            }
                        />
                        <FactItem
                            label="Supervisor"
                            value={visit.supervisor?.name ?? visit.supervisor_name ?? 'None'}
                        />
                        <FactItem
                            label="Tasks"
                            value={`${taskProgress}${typeof taskCount === 'number' && recorded ? '' : ''}`}
                        />
                    </FactGrid>
                </RecordSection>

                {visit.notes ? (
                    <RecordSection title="Important instructions" compact>
                        <p className="text-sm whitespace-pre-wrap">{visit.notes}</p>
                    </RecordSection>
                ) : null}

                {recorded && (
                    <RecordSection
                        title={visit.visit_phase === 'completed' ? 'Completed visit' : 'Recorded activity'}
                        compact
                    >
                        <FactGrid className="xl:grid-cols-3">
                            <FactItem label="Clock in" value={recorded.clocked_in_at_label} />
                            <FactItem label="Clock out" value={recorded.clocked_out_at_label ?? '—'} />
                            <FactItem label="Duration" value={recorded.duration_label ?? '—'} />
                            <FactItem
                                label="Location"
                                value={recorded.location_status_label ?? '—'}
                            />
                            <FactItem
                                label="Exceptions"
                                value={String(recorded.exceptions.length)}
                            />
                        </FactGrid>
                        {recorded.tasks.length > 0 && (
                            <div className="mt-4">
                                <CompactTaskList
                                    tasks={recorded.tasks}
                                    empty="No tasks recorded."
                                />
                            </div>
                        )}
                    </RecordSection>
                )}

                {can.replace && (
                    <RecordSection title="Replace DSP">
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
                    </RecordSection>
                )}

                {(visit.assignments?.length ?? 0) > 0 && (
                    <RecordSection title="Assignment history" compact>
                        <ul className="space-y-2 text-sm">
                            {visit.assignments?.map((row) => (
                                <li key={row.id}>
                                    {row.employee_name} · {row.kind}
                                    {row.reason ? ` · ${row.reason}` : ''}
                                    {row.ended_at ? ' (ended)' : ''}
                                </li>
                            ))}
                        </ul>
                    </RecordSection>
                )}
            </RecordPage>
        </>
    );
}

ScheduledVisitsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Scheduled Visits', href: visitsIndex() },
        { title: 'Visit', href: show.url(0) },
    ],
};
