import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ClockInAction } from '@/components/mdm/clock-in-action';
import { CompactTaskList } from '@/components/mdm/compact-task-list';
import { StatusBadge } from '@/components/mdm/directory';
import {
    ActionGroup,
    AttentionBanner,
    ContextGroup,
    ContextStrip,
    RecordHeader,
    RecordPage,
    RecordSection,
} from '@/components/mdm/record-detail';
import { PrintPdfAction } from '@/components/mdm/print-pdf-action';
import { HighPriorityIndicator } from '@/components/mdm/priority-indicator';
import { ActivityTimeline } from '@/components/mdm/activity-timeline';
import { ReplaceDspDrawer } from '@/components/mdm/replace-dsp-drawer';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
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
                        <ActionGroup
                            primary={
                                visit.visit_phase === 'completed' && recorded ? (
                                    <Button asChild>
                                        <Link href={showVisit(recorded.id)}>
                                            View visit summary
                                        </Link>
                                    </Button>
                                ) : visit.visit_phase === 'active' &&
                                  visit.active_visit_id &&
                                  role !== 'DSP' ? (
                                    <Button asChild>
                                        <Link href={showVisit(visit.active_visit_id)}>
                                            Continue visit
                                        </Link>
                                    </Button>
                                ) : undefined
                            }
                        >
                            {documents?.visit_handout && (
                                <PrintPdfAction href={documents.visit_handout} />
                            )}
                            {can.update && (
                                <Button asChild variant="secondary">
                                    <Link href={edit(visit.id)}>Edit</Link>
                                </Button>
                            )}
                            {can.replace && (
                                <ReplaceDspDrawer
                                    visitId={visit.id}
                                    currentEmployeeId={visit.employee_id}
                                    dsps={dsps}
                                />
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

                <ContextStrip>
                    <ContextGroup label="Service">
                        {visit.services && visit.services.length > 0
                            ? visit.services.map((service) => service.name).join(' · ')
                            : visit.service_type}
                    </ContextGroup>
                    <ContextGroup label="Care team">
                        {canOpenDirectories && visit.employee ? (
                            <Link href={showEmployee(visit.employee.id)} className="hover:text-foreground">
                                {visit.employee.name} · DSP
                            </Link>
                        ) : (
                            <>{visit.dsp_name} · DSP</>
                        )}
                        <span className="text-muted-foreground mt-0.5 block text-xs font-normal">
                            {visit.supervisor?.name ?? visit.supervisor_name ?? 'No supervisor assigned'}
                        </span>
                    </ContextGroup>
                    <ContextGroup label="Timing">
                        {visit.service_date} · {visit.time_label}
                        <span className="text-muted-foreground mt-0.5 block text-xs font-normal">
                            {taskProgress}
                        </span>
                    </ContextGroup>
                </ContextStrip>

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
                        <ContextStrip className="border-0 bg-transparent p-0 shadow-none">
                            <ContextGroup label="Clock">
                                In {recorded.clocked_in_at_label}
                                <span className="text-muted-foreground mt-0.5 block text-xs font-normal">
                                    Out {recorded.clocked_out_at_label ?? '—'} · {recorded.duration_label ?? 'In progress'}
                                </span>
                            </ContextGroup>
                            <ContextGroup label="Attendance">
                                {recorded.location_status_label ?? '—'}
                            </ContextGroup>
                            <ContextGroup label="Exceptions">
                                {String(recorded.exceptions.length)}
                            </ContextGroup>
                        </ContextStrip>
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

                {recorded?.timeline && recorded.timeline.length > 0 && (
                    <RecordSection title="Timeline" compact>
                        <ActivityTimeline items={recorded.timeline} />
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
