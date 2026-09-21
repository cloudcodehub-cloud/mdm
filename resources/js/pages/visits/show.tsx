import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { CareMessageDrawer } from '@/components/mdm/care-message-drawer';
import { CompactTaskList } from '@/components/mdm/compact-task-list';
import { StatusBadge } from '@/components/mdm/directory';
import { BackLink } from '@/components/mdm/back-link';
import {
    ContextGroup,
    ContextStrip,
    RecordHeader,
    RecordHero,
    RecordPage,
    RecordSection,
} from '@/components/mdm/record-detail';
import { ActivityTimeline } from '@/components/mdm/activity-timeline';
import {
    HighPriorityIndicator,
    hasHighPriorityOpen,
} from '@/components/mdm/priority-indicator';
import { SupervisorFollowUp } from '@/components/mdm/supervisor-follow-up';
import { PrintPdfAction } from '@/components/mdm/print-pdf-action';
import { VisitElapsedTimer } from '@/components/mdm/visit-elapsed-timer';
import { VisitWorkflow } from '@/components/mdm/visit-workflow';
import { ProgressRing } from '@/components/mdm/visual-summaries';
import { EmptyState } from '@/components/mdm/stat-card';
import { Button } from '@/components/ui/button';
import { VisitClockOutReview } from '@/components/mdm/visit-clock-out-review';
import { VisitNotesForm } from '@/components/mdm/visit-notes-form';
import { VisitTaskCard } from '@/components/mdm/visit-task-card';
import { dashboard } from '@/routes';
import { show as showException } from '@/routes/visit-exceptions';
import { show as showScheduled } from '@/routes/scheduled-visits';
import { show } from '@/routes/visits';
import type {
    ActiveVisitRecord,
    SkipReasonOption,
} from '@/types/visit';
import type { CareHistoryItem, SupervisorContact } from '@/types/care';
import type { VisitExceptionRecord } from '@/types/operations';

export default function VisitsShow({
    visit,
    skip_reasons = [],
    care_history = [],
    supervisor_contact = null,
    can,
    documents,
}: {
    visit: ActiveVisitRecord;
    skip_reasons?: SkipReasonOption[];
    care_history?: CareHistoryItem[];
    supervisor_contact?: SupervisorContact | null;
    can?: {
        clock_in?: boolean;
        record_tasks?: boolean;
        update_notes?: boolean;
        clock_out?: boolean;
        view_exceptions?: boolean;
        follow_up?: boolean;
    };
    documents?: { completed_visit?: string | null };
}) {
    const completed = visit.status === 'completed';
    const monitoring = !can?.record_tasks && !can?.clock_out;
    const title = completed
        ? `Visit summary · ${visit.client.name}`
        : monitoring
          ? `Visit monitoring · ${visit.client.name}`
          : `Active visit · ${visit.client.name}`;
    const [messageOpen, setMessageOpen] = useState(false);
    const [messageUserId, setMessageUserId] = useState<number | null>(null);
    const [messageName, setMessageName] = useState('');
    const [messageContext, setMessageContext] = useState('');
    const [historyItem, setHistoryItem] = useState<CareHistoryItem | null>(null);
    const [supervisorMode, setSupervisorMode] = useState(false);

    const openMessage = (
        userId: number,
        name: string,
        item?: CareHistoryItem,
        supervisor = false,
    ) => {
        setMessageUserId(userId);
        setMessageName(name);
        setMessageContext(item?.context_label ?? `${visit.client.name} · visit`);
        setHistoryItem(item ?? null);
        setSupervisorMode(supervisor);
        setMessageOpen(true);
    };

    return (
        <>
            <Head title={title} />
            <RecordPage>
                <RecordHeader
                    eyebrow={
                        completed ? (
                            'Completed visit'
                        ) : (
                            <BackLink href={dashboard()}>Back to Dashboard</BackLink>
                        )
                    }
                    title={visit.client.name}
                    meta={
                        <>
                            <StatusBadge
                                status={visit.status}
                                label={visit.status_label}
                            />
                            <HighPriorityIndicator
                                active={Boolean(visit.has_high_priority_open)}
                                label="Unresolved high-priority exception"
                            />
                            <span className="text-muted-foreground text-sm">
                                {visit.service_type} · {visit.client.client_number}
                            </span>
                        </>
                    }
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {completed ? (
                                <Button asChild>
                                    <Link href={dashboard()}>
                                        Back to Dashboard
                                    </Link>
                                </Button>
                            ) : null}
                            {can?.view_exceptions &&
                                visit.exceptions.some(
                                    (row) => row.status !== 'resolved',
                                ) && (
                                    <Button asChild variant={completed ? 'secondary' : 'default'}>
                                        <Link
                                            href={showException.url(
                                                visit.exceptions.find(
                                                    (row) =>
                                                        row.status !==
                                                        'resolved',
                                                )!.id,
                                            )}
                                        >
                                            Review exception
                                        </Link>
                                    </Button>
                                )}
                            {documents?.completed_visit ? (
                                <PrintPdfAction href={documents.completed_visit} />
                            ) : undefined}
                            {completed ? (
                                <Button asChild variant="outline">
                                    <Link href={showScheduled(visit.scheduled_visit.id)}>
                                        View Schedule
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                {completed || monitoring ? (
                    <VisitMonitoring
                        visit={visit}
                        canViewExceptions={Boolean(can?.view_exceptions)}
                        canFollowUp={Boolean(can?.follow_up)}
                    />
                ) : (
                    <ActiveVisit
                        visit={visit}
                        skipReasons={skip_reasons}
                        can={can}
                        careHistory={care_history}
                        supervisor={supervisor_contact}
                        onMessagePrevious={(item) => {
                            if (item.previous_dsp_user_id) {
                                openMessage(
                                    item.previous_dsp_user_id,
                                    item.dsp_name,
                                    item,
                                );
                            }
                        }}
                        onContactSupervisor={() => {
                            if (supervisor_contact?.available) {
                                openMessage(
                                    supervisor_contact.user_id,
                                    supervisor_contact.name,
                                    undefined,
                                    true,
                                );
                            }
                        }}
                    />
                )}
            </RecordPage>
            <CareMessageDrawer
                open={messageOpen}
                onOpenChange={setMessageOpen}
                userId={messageUserId}
                recipientName={messageName}
                contextLabel={messageContext}
                clientId={visit.client.id}
                visitId={historyItem?.visit_id ?? visit.id}
                taskTitle={historyItem?.task_title}
                variant={supervisorMode ? 'supervisor' : 'message'}
                roleLabel={
                    supervisorMode
                        ? supervisor_contact?.role_label
                        : undefined
                }
            />
        </>
    );
}

function ActiveVisit({
    visit,
    skipReasons,
    can,
    careHistory,
    supervisor,
    onMessagePrevious,
    onContactSupervisor,
}: {
    visit: ActiveVisitRecord;
    skipReasons: SkipReasonOption[];
    can?: {
        record_tasks?: boolean;
        update_notes?: boolean;
        clock_out?: boolean;
    };
    careHistory: CareHistoryItem[];
    supervisor: SupervisorContact | null;
    onMessagePrevious: (item: CareHistoryItem) => void;
    onContactSupervisor: () => void;
}) {
    const percent =
        visit.task_summary.total === 0
            ? 0
            : Math.round(
                  (visit.task_summary.completed / visit.task_summary.total) *
                      100,
              );
    const readyToComplete =
        visit.task_summary.pending_required === 0 ||
        visit.unfinished_required_acknowledged;

    return (
        <div className="grid gap-4 lg:grid-cols-12">
            <div className="lg:col-span-12">
                <RecordHero
                    eyebrow="Active visit"
                    title={visit.client.name}
                    subtitle={`${visit.service_type} · ${visit.client.client_number}`}
                    chips={
                        <>
                            <StatusBadge
                                status={visit.status}
                                label={visit.status_label}
                            />
                            <StatusBadge
                                status="scheduled"
                                label={visit.scheduled_visit.time_label}
                            />
                            <StatusBadge
                                status={visit.location_status}
                                label={visit.location_status_label}
                            />
                        </>
                    }
                    aside={
                        <div className="text-right">
                            <VisitElapsedTimer clockedInAt={visit.clocked_in_at} />
                            <div className="mt-3 flex justify-end">
                                <ProgressRing
                                    value={percent}
                                    label={`${percent}%`}
                                    detail={`${visit.task_summary.completed} completed · ${visit.task_summary.pending} pending · ${visit.task_summary.skipped} skipped`}
                                />
                            </div>
                        </div>
                    }
                >
                    <div className="mt-4">
                        <VisitWorkflow visit={visit} />
                    </div>
                    <p className="mt-3 text-sm">
                        {readyToComplete
                            ? 'Visit is ready for wrap-up and clock-out after notes.'
                            : 'Finish remaining required tasks, or acknowledge them at clock-out.'}
                    </p>
                </RecordHero>
            </div>

            <div className="lg:col-span-12">
                <VisitFacts visit={visit} />
            </div>

            <RecordSection
                title="Care-plan tasks"
                description="Complete or skip each visit task. Care-plan templates are not changed."
                className="lg:col-span-12"
            >
                {visit.tasks.length === 0 ? (
                    <EmptyState
                        compact
                        message="No care-plan tasks apply to this visit."
                    />
                ) : (
                    <ul className="space-y-3">
                        {visit.tasks.map((task) => (
                            <VisitTaskCard
                                key={task.id}
                                visitId={visit.id}
                                task={task}
                                skipReasons={skipReasons}
                                canRecord={Boolean(can?.record_tasks)}
                                historyNote={
                                    careHistory.find(
                                        (item) => item.task_title === task.title,
                                    )?.note
                                }
                                onMessagePrevious={
                                    careHistory.find(
                                        (item) =>
                                            item.task_title === task.title &&
                                            item.previous_dsp_available,
                                    )
                                        ? () => {
                                              const item = careHistory.find(
                                                  (entry) =>
                                                      entry.task_title ===
                                                          task.title &&
                                                      entry.previous_dsp_available,
                                              );
                                              if (item) {
                                                  onMessagePrevious(item);
                                              }
                                          }
                                        : undefined
                                }
                                onContactSupervisor={
                                    supervisor?.available
                                        ? onContactSupervisor
                                        : undefined
                                }
                            />
                        ))}
                    </ul>
                )}
            </RecordSection>

            <section className="border-primary/30 from-primary/10 via-card to-brand-cyan/10 lg:col-span-12 surface-panel bg-gradient-to-br p-4 md:p-5">
                <h2 className="text-sm font-semibold tracking-wide uppercase">
                    Visit wrap-up
                </h2>
                <p className="text-muted-foreground mt-1 text-xs">
                    Visit notes and handover stay separate records. Required
                    acknowledgments and clock-out finish the visit.
                </p>
                <div className="mt-4 grid gap-6 lg:grid-cols-2">
                    <VisitNotesForm
                        visitId={visit.id}
                        visitNotes={visit.visit_notes}
                        handoverNote={visit.handover_note}
                        canUpdate={Boolean(can?.update_notes)}
                    />
                    <div>
                        <p className="mb-3 text-sm font-medium">
                            Required acknowledgments / unfinished-task review
                        </p>
                        <VisitClockOutReview
                            visit={visit}
                            canClockOut={Boolean(can?.clock_out)}
                        />
                    </div>
                </div>
            </section>
        </div>
    );
}

function VisitMonitoring({
    visit,
    canViewExceptions,
    canFollowUp,
}: {
    visit: ActiveVisitRecord;
    canViewExceptions: boolean;
    canFollowUp: boolean;
}) {
    const exceptions = visit.exceptions ?? [];
    const exceptionByTaskId = new Map(
        exceptions
            .filter((exception) => exception.visit_task_id)
            .map((exception) => [exception.visit_task_id as number, exception]),
    );

    return (
        <div className="grid gap-4 lg:grid-cols-12">
            <div className="lg:col-span-12">
                <RecordHero
                    eyebrow={
                        visit.status === 'completed'
                            ? 'Completed visit'
                            : 'Visit monitoring'
                    }
                    title={visit.client.name}
                    subtitle={`${visit.service_type} · ${visit.client.client_number}`}
                    chips={
                        <>
                            <StatusBadge
                                status={visit.status}
                                label={visit.status_label}
                            />
                            <StatusBadge
                                status="info"
                                label={visit.duration_label ?? visit.scheduled_visit.time_label}
                            />
                            {visit.exceptions.length > 0 && (
                                <StatusBadge
                                    status="exception"
                                    label={`${visit.exceptions.length} exception${visit.exceptions.length === 1 ? '' : 's'}`}
                                />
                            )}
                        </>
                    }
                />
            </div>
            <div className="lg:col-span-12">
                <VisitFacts visit={visit} completed />
            </div>
            <RecordSection title="Task summary" className="lg:col-span-8" compact>
                <CompactTaskList
                    tasks={visit.tasks}
                    empty="No care-plan tasks applied to this visit."
                    exceptionByTaskId={exceptionByTaskId}
                    renderFollowUp={
                        canFollowUp
                            ? (task) =>
                                  task.id && exceptionByTaskId.get(task.id)
                                      ? 'Supervisor follow-up available'
                                      : null
                            : undefined
                    }
                />
            </RecordSection>
            <RecordSection title="Status" className="lg:col-span-4" compact>
                <p className="text-sm">
                    {visit.task_summary.completed}/{visit.task_summary.total}{' '}
                    completed · {visit.task_summary.skipped} skipped ·{' '}
                    {visit.task_summary.pending} unfinished
                </p>
                {hasHighPriorityOpen(exceptions) && (
                    <p className="mt-2 flex items-center gap-2 text-sm">
                        <HighPriorityIndicator
                            active
                            label="Unresolved high-priority exception"
                        />
                        High-priority follow-up needed
                    </p>
                )}
            </RecordSection>
            <RecordSection title="Notes / handover" className="lg:col-span-6">
                {visit.visit_notes || visit.handover_note ? (
                    <dl className="grid gap-3 text-sm">
                        {visit.visit_notes && (
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Visit notes
                                </dt>
                                <dd className="mt-0.5 whitespace-pre-wrap">
                                    {visit.visit_notes}
                                </dd>
                            </div>
                        )}
                        {visit.handover_note && (
                            <div>
                                <dt className="text-muted-foreground text-xs">
                                    Handover
                                </dt>
                                <dd className="mt-0.5 whitespace-pre-wrap">
                                    {visit.handover_note}
                                </dd>
                            </div>
                        )}
                    </dl>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        No visit notes or handover recorded.
                    </p>
                )}
            </RecordSection>
            <RecordSection title="Exceptions" className="lg:col-span-6">
                {exceptions.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No exceptions on this visit.
                    </p>
                ) : (
                    <ul className="space-y-3 text-sm">
                        {exceptions.map((exception) => (
                            <li key={exception.id}>
                                <div className="flex items-start gap-2">
                                    <HighPriorityIndicator
                                        active={Boolean(
                                            exception.is_high_priority_open,
                                        )}
                                    />
                                    {canViewExceptions ? (
                                        <Link
                                            href={showException.url(exception.id)}
                                            className="hover:text-foreground font-medium"
                                        >
                                            {exception.type_label}
                                        </Link>
                                    ) : (
                                        <p className="font-medium">
                                            {exception.type_label}
                                        </p>
                                    )}
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    {exception.status_label} · {exception.message}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </RecordSection>
            {exceptions.length > 0 && (
                <RecordSection
                    title="Supervisor follow-up"
                    className="lg:col-span-12"
                    description="Follow-up is stored on the existing exception record. DSP notes and skip reasons are not changed."
                >
                    <ul className="space-y-4">
                        {exceptions.map((exception) => (
                            <li key={exception.id}>
                                <p className="text-sm font-medium">
                                    {exception.task_title ?? exception.type_label}
                                </p>
                                <SupervisorFollowUp
                                    exception={
                                        exception as VisitExceptionRecord
                                    }
                                    canFollowUp={canFollowUp}
                                />
                            </li>
                        ))}
                    </ul>
                </RecordSection>
            )}
            {(visit.timeline ?? []).length > 0 && (
                <RecordSection title="Timeline" className="lg:col-span-12" compact>
                    <ActivityTimeline items={visit.timeline ?? []} />
                </RecordSection>
            )}
        </div>
    );
}

function VisitFacts({
    visit,
    completed = false,
}: {
    visit: ActiveVisitRecord;
    completed?: boolean;
}) {
    return (
        <ContextStrip>
            <ContextGroup label="Care team">
                {visit.employee.name} · DSP
                <span className="text-muted-foreground mt-0.5 block text-xs font-normal">
                    {visit.supervisor?.name
                        ? `${visit.supervisor.name} · Supervisor`
                        : 'No supervisor assigned'}
                </span>
            </ContextGroup>
            <ContextGroup label="Timing">
                Scheduled {visit.scheduled_visit.time_label}
                <span className="text-muted-foreground mt-0.5 block text-xs font-normal">
                    Clocked in {visit.clocked_in_at_label}
                    {completed && visit.clocked_out_at_label
                        ? ` · Out ${visit.clocked_out_at_label}`
                        : ''}
                    {visit.duration_label ? ` · ${visit.duration_label}` : ''}
                </span>
            </ContextGroup>
            <ContextGroup label="Attendance">
                {visit.location_status_label}
                {visit.clock_out_location_status_label
                    ? ` · Out ${visit.clock_out_location_status_label}`
                    : ''}
                <span className="text-muted-foreground mt-0.5 block text-xs font-normal">
                    {visit.exceptions.length} exception
                    {visit.exceptions.length === 1 ? '' : 's'}
                    {` · ${visit.exceptions.filter((row) => row.status !== 'resolved').length} open`}
                </span>
            </ContextGroup>
        </ContextStrip>
    );
}

VisitsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Active visit', href: show.url(0) },
    ],
};
