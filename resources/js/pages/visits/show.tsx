import { useEffect, useState, type ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';
import { CareMessageDrawer } from '@/components/mdm/care-message-drawer';
import { StatusBadge } from '@/components/mdm/directory';
import { IdentityHeader } from '@/components/mdm/identity-header';
import { VisitWorkflow } from '@/components/mdm/visit-workflow';
import { ProgressRing } from '@/components/mdm/visual-summaries';
import { EmptyState, Panel } from '@/components/mdm/stat-card';
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

export default function VisitsShow({
    visit,
    skip_reasons = [],
    care_history = [],
    supervisor_contact = null,
    can,
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
    };
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

    const openMessage = (userId: number, name: string, item?: CareHistoryItem) => {
        setMessageUserId(userId);
        setMessageName(name);
        setMessageContext(item?.context_label ?? `${visit.client.name} · visit`);
        setHistoryItem(item ?? null);
        setMessageOpen(true);
    };

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <IdentityHeader
                    eyebrow={
                        <Link
                            href={showScheduled(visit.scheduled_visit.id)}
                            className="hover:text-foreground"
                        >
                            Scheduled visit
                        </Link>
                    }
                    title={visit.client.name}
                    meta={
                        <>
                            <StatusBadge
                                status={visit.status}
                                label={visit.status_label}
                            />
                            <span className="text-muted-foreground text-sm">
                                {visit.service_type} · {visit.client.client_number}
                            </span>
                        </>
                    }
                />

                {completed || monitoring ? (
                    <VisitMonitoring
                        visit={visit}
                        canViewExceptions={Boolean(can?.view_exceptions)}
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
                                );
                            }
                        }}
                    />
                )}
            </div>
            <CareMessageDrawer
                open={messageOpen}
                onOpenChange={setMessageOpen}
                userId={messageUserId}
                recipientName={messageName}
                contextLabel={messageContext}
                clientId={visit.client.id}
                visitId={historyItem?.visit_id ?? visit.id}
                taskTitle={historyItem?.task_title}
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
        <div className="grid gap-4 lg:grid-cols-2">
            <div className="surface-panel border-primary/20 from-primary/8 via-card to-brand-lime/15 bg-gradient-to-br p-4 md:col-span-2 md:p-5">
                <VisitWorkflow visit={visit} />
                <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <ProgressRing
                        value={percent}
                        label={`${percent}%`}
                        detail={`${visit.task_summary.completed} completed · ${visit.task_summary.pending} pending · ${visit.task_summary.skipped} skipped`}
                    />
                    <p className="text-sm">
                        {readyToComplete
                            ? 'Visit is ready for review and clock-out after notes.'
                            : 'Finish remaining required tasks, or acknowledge them at clock-out.'}
                    </p>
                </div>
            </div>
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
                title="Review, acknowledgments, and clock out"
                description="Confirm task review, required acknowledgments, then clock out. Clock-out uses server time and a new GPS request."
            >
                <VisitClockOutReview
                    visit={visit}
                    canClockOut={Boolean(can?.clock_out)}
                />
            </Panel>
        </div>
    );
}

function VisitMonitoring({
    visit,
    canViewExceptions,
}: {
    visit: ActiveVisitRecord;
    canViewExceptions: boolean;
}) {
    const skipped = visit.tasks.filter((task) => task.status === 'skipped');
    const pending = visit.tasks.filter((task) => task.status === 'pending');
    const exceptions = visit.exceptions ?? [];

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Panel title="Visit">
                <dl className="grid gap-3 text-sm">
                    <Detail label="DSP" value={visit.employee.name} />
                    <Detail label="Client" value={visit.client.name} />
                    <Detail label="Service" value={visit.service_type} />
                    <Detail
                        label="Scheduled time"
                        value={`${visit.scheduled_visit.service_date} · ${visit.scheduled_visit.time_label}${visit.scheduled_visit.shift_name ? ` · ${visit.scheduled_visit.shift_name}` : ''}`}
                    />
                    <Detail
                        label="Clock-in"
                        value={visit.clocked_in_at_label}
                    />
                    <Detail
                        label="Clock-out"
                        value={visit.clocked_out_at_label ?? '—'}
                    />
                    {visit.duration_label && (
                        <Detail label="Duration" value={visit.duration_label} />
                    )}
                    <Detail
                        label="Visit status"
                        value={visit.status_label}
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
            <Panel title="Task completion">
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
                        label="Pending"
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
                    <Detail
                        label="Pending items"
                        value={
                            pending.length === 0
                                ? 'None'
                                : pending.map((task) => task.title).join('; ')
                        }
                    />
                </dl>
            </Panel>
            {(visit.visit_notes || visit.handover_note) && (
            <Panel title="DSP notes and handover">
                <dl className="grid gap-3 text-sm">
                    {visit.visit_notes && (
                    <Detail
                        label="Visit notes"
                        value={visit.visit_notes}
                    />
                    )}
                    {visit.handover_note && (
                    <Detail
                        label="Handover"
                        value={visit.handover_note}
                    />
                    )}
                </dl>
            </Panel>
            )}
            {exceptions.length > 0 && (
            <Panel title="Related exceptions">
                    <ul className="space-y-2 text-sm">
                        {exceptions.map((exception) => (
                            <li key={exception.id}>
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
                                <p className="text-muted-foreground text-xs">
                                    {exception.status_label} · {exception.message}
                                </p>
                            </li>
                        ))}
                    </ul>
            </Panel>
            )}
            <Panel title="Care-plan tasks" className="lg:col-span-2">
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
                                {task.skip_reason_name && (
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {task.skip_reason_name}
                                        {task.skip_comment
                                            ? ` · ${task.skip_comment}`
                                            : ''}
                                    </p>
                                )}
                                {task.completion_note && (
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {task.completion_note}
                                    </p>
                                )}
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
