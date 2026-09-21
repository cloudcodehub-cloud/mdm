import { Head, Link } from '@inertiajs/react';
import { AttentionList } from '@/components/mdm/attention-list';
import { AttentionTabs } from '@/components/mdm/attention-tabs';
import { AnnouncementList } from '@/components/mdm/announcement-list';
import { ClockInAction } from '@/components/mdm/clock-in-action';
import { DashboardGreeting } from '@/components/mdm/dashboard-greeting';
import { ActivityList, PersonList } from '@/components/mdm/person-list';
import { Panel, StatCard } from '@/components/mdm/stat-card';
import { RecentCompletedVisits } from '@/components/mdm/recent-completed-visits';
import { VisitList } from '@/components/mdm/visit-list';
import {
    MetricSummary,
    MiniBarChart,
    ProgressRing,
    SegmentedStatusBar,
} from '@/components/mdm/visual-summaries';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as announcementsIndex } from '@/routes/announcements';
import {
    create as createClient,
    index as clientsIndex,
    show as showClient,
} from '@/routes/clients';
import { index as complianceIndex } from '@/routes/compliance';
import {
    create as createEmployee,
    index as employeesIndex,
    show as showEmployee,
} from '@/routes/employees';
import { index as operationsIndex } from '@/routes/operations';
import {
    create as createScheduledVisit,
    index as scheduledVisitsIndex,
    show as showScheduledVisit,
} from '@/routes/scheduled-visits';
import { index as exceptionsIndex } from '@/routes/visit-exceptions';
import { show as showVisit } from '@/routes/visits';
import type { DashboardPayload } from '@/types/dashboard';

export default function Dashboard({
    dashboard: data,
}: {
    dashboard: DashboardPayload;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="page-shell">
                <div>
                    <DashboardGreeting name={data.greeting_name} />
                    <h2 className="mt-1 text-xl font-semibold tracking-tight">
                        {headline(data.role)}
                    </h2>
                    <RoleQuickActions data={data} />
                </div>

                <div
                    className={
                        data.role === 'DSP'
                            ? 'grid gap-4 sm:grid-cols-3'
                            : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'
                    }
                >
                    {data.metrics.map((metric) => (
                        <StatCard
                            key={metric.key}
                            label={metric.label}
                            value={metric.value}
                            hint={metric.hint}
                            href={metric.href}
                        />
                    ))}
                </div>

                {data.role === 'DSP' ? (
                    <DspDashboard data={data} />
                ) : data.role === 'SUPERVISOR' ? (
                    <SupervisorDashboard data={data} />
                ) : (
                    <AdminDashboard data={data} />
                )}
            </div>
        </>
    );
}

function RoleQuickActions({ data }: { data: DashboardPayload }) {
    if (data.role === 'ADMIN') {
        return (
            <div className="mt-3 flex flex-wrap gap-2">
                <Button size="sm" asChild>
                    <Link href={createEmployee()}>Add Employee</Link>
                </Button>
                <Button size="sm" variant="secondary" asChild>
                    <Link href={createClient()}>Add Client</Link>
                </Button>
                <Button size="sm" variant="secondary" asChild>
                    <Link href={createScheduledVisit()}>Schedule Visit</Link>
                </Button>
            </div>
        );
    }

    if (data.role === 'SUPERVISOR') {
        return (
            <div className="mt-3 flex flex-wrap gap-2">
                <Button size="sm" asChild>
                    <Link href={operationsIndex()}>Open Operations</Link>
                </Button>
                <Button size="sm" variant="secondary" asChild>
                    <Link href={exceptionsIndex()}>Review exceptions</Link>
                </Button>
            </div>
        );
    }

    return (
        <div className="mt-3 flex flex-wrap gap-2">
            {data.active_visit && (
                <Button size="sm" className="min-h-10" asChild>
                    <Link href={showVisit(data.active_visit.id)}>
                        Continue Visit
                    </Link>
                </Button>
            )}
            {data.active_visit && (
                <Button size="sm" variant="secondary" className="min-h-10" asChild>
                    <Link href={showVisit(data.active_visit.id)}>
                        Complete Tasks
                    </Link>
                </Button>
            )}
            {!data.active_visit && data.clock_in_visit && (
                <Button size="sm" className="min-h-10" asChild>
                    <Link href={showScheduledVisit(data.clock_in_visit.id)}>
                        Start Visit
                    </Link>
                </Button>
            )}
        </div>
    );
}

function headline(role: DashboardPayload['role']): string {
    if (role === 'DSP') {
        return "Today's work";
    }

    if (role === 'SUPERVISOR') {
        return 'What is happening with your team right now';
    }

    return 'Operations overview';
}

function visitStatusSegments(data: DashboardPayload) {
    return [
        {
            key: 'scheduled',
            label: 'Scheduled',
            value: data.today_visit_summary.scheduled,
            tone: 'info' as const,
        },
        {
            key: 'in_progress',
            label: 'In progress',
            value: data.today_visit_summary.in_progress,
            tone: 'brand' as const,
        },
        {
            key: 'completed',
            label: 'Completed',
            value: data.today_visit_summary.completed,
            tone: 'success' as const,
        },
        {
            key: 'attention',
            label: 'Attention',
            value: data.today_visit_summary.attention,
            tone: 'warning' as const,
        },
    ];
}

function AdminDashboard({ data }: { data: DashboardPayload }) {
    const health = data.compliance_health;

    return (
        <div className="grid gap-4 xl:grid-cols-3">
            <Panel
                title="Today's visits"
                description="Operational status for today's scheduled work."
            >
                <SegmentedStatusBar segments={visitStatusSegments(data)} />
            </Panel>
            {health && (
                <Panel
                    title="Compliance health"
                    description="Valid share of credentials and training with known expiry status."
                    viewAllHref={complianceIndex()}
                >
                    <ProgressRing
                        value={health.valid_percent}
                        label={`${health.valid_percent}%`}
                        detail={`${health.valid} of ${health.tracked} currently valid`}
                    />
                    <div className="mt-4">
                        <SegmentedStatusBar
                            segments={[
                                {
                                    key: 'valid',
                                    label: 'Valid',
                                    value: health.valid,
                                    tone: 'success',
                                },
                                {
                                    key: 'expiring',
                                    label: 'Expiring',
                                    value: health.expiring_soon,
                                    tone: 'warning',
                                },
                                {
                                    key: 'expired',
                                    label: 'Expired',
                                    value: health.expired,
                                    tone: 'critical',
                                },
                            ]}
                        />
                    </div>
                </Panel>
            )}
            <Panel
                title="Recent visit volume"
                description="Non-cancelled visits in the last seven days."
            >
                <MiniBarChart points={data.visit_trend} />
            </Panel>
            <Panel
                title="Scheduled visits today"
                description="Open visits across the agency."
                className="xl:col-span-2"
                viewAllHref={scheduledVisitsIndex()}
            >
                <VisitList
                    visits={data.today_visits}
                    showEmployee
                    empty="You're clear for today. No visits are currently scheduled."
                />
            </Panel>
            <Panel
                title="Recently completed visits"
                description="Latest completed care sessions that may need review."
            >
                <RecentCompletedVisits
                    visits={data.recently_completed_visits ?? []}
                />
            </Panel>
            <Panel
                title="Profiles needing attention"
                description="Aggregated missing profile items. Not the same as credential compliance."
            >
                <AttentionTabs
                    employees={data.profile_attention?.employees ?? []}
                    clients={data.profile_attention?.clients ?? []}
                />
            </Panel>
            <Panel
                title="Operational attention"
                description="Credentials, training, authorizations, and cancelled visits."
                viewAllHref={operationsIndex()}
            >
                <AttentionList items={data.attention_items} />
            </Panel>
            <Panel
                title="Upcoming visits"
                description="Next scheduled visits after today."
                className="xl:col-span-2"
                viewAllHref={scheduledVisitsIndex()}
            >
                <VisitList visits={data.upcoming_visits} showEmployee />
            </Panel>
            <Panel
                title="Recent activity"
                description="Latest scheduled visit records."
            >
                <ActivityList items={data.activity} />
            </Panel>
            <Panel
                title="Announcements"
                description="Notices for this organization."
                viewAllHref={announcementsIndex()}
            >
                <AnnouncementList announcements={data.announcements} />
            </Panel>
        </div>
    );
}

function SupervisorDashboard({ data }: { data: DashboardPayload }) {
    return (
        <div className="grid gap-4 xl:grid-cols-3">
            <Panel
                title="Today's caseload visits"
                description="Status of today's visits for assigned DSPs and clients."
                className="xl:col-span-2"
            >
                <div className="mb-4 grid gap-4 sm:grid-cols-3">
                    <MetricSummary
                        label="In progress"
                        value={data.today_visit_summary.in_progress}
                    />
                    <MetricSummary
                        label="Completed"
                        value={data.today_visit_summary.completed}
                    />
                    <Link href={exceptionsIndex()} className="hover:text-foreground">
                        <MetricSummary
                            label="Open exceptions"
                            value={data.open_exceptions}
                        />
                    </Link>
                </div>
                <SegmentedStatusBar segments={visitStatusSegments(data)} />
            </Panel>
            <Panel
                title="DSP activity today"
                description="Visit counts from today's caseload records."
            >
                {data.assigned_dsps.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No assigned DSPs.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {data.assigned_dsps.map((dsp) => (
                            <li key={dsp.id} className="text-sm">
                                <Link
                                    href={dsp.href ?? showEmployee.url(dsp.id)}
                                    className="hover:text-foreground font-medium"
                                >
                                    {dsp.name}
                                </Link>
                                <p className="text-muted-foreground text-xs">
                                    {dsp.visits_today ?? 0} visit
                                    {(dsp.visits_today ?? 0) === 1 ? '' : 's'}{' '}
                                    today
                                    {(dsp.in_progress ?? 0) > 0
                                        ? ` · ${dsp.in_progress} in progress`
                                        : ''}
                                    {(dsp.attention ?? 0) > 0 ? (
                                        <>
                                            {' · '}
                                            <Link
                                                href={
                                                    dsp.attention_href ??
                                                    operationsIndex()
                                                }
                                                className="hover:text-foreground font-medium"
                                            >
                                                {dsp.attention} need attention
                                            </Link>
                                        </>
                                    ) : null}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
            <Panel
                title="Today's scheduled visits"
                description="Open the operations board for live caseload monitoring."
                className="xl:col-span-2"
                viewAllHref={operationsIndex()}
                viewAllLabel="Open operations"
            >
                <VisitList
                    visits={data.today_visits}
                    showEmployee
                    empty="No visits scheduled for your caseload today."
                />
            </Panel>
            <Panel
                title="Recently completed visits"
                description="Latest completed caseload visits that may need review."
            >
                <RecentCompletedVisits
                    visits={data.recently_completed_visits ?? []}
                />
            </Panel>
            <Panel title="Operational attention" viewAllHref={exceptionsIndex()}>
                <AttentionList items={data.attention_items} />
            </Panel>
            <Panel
                title="Profiles needing attention"
                description="Aggregated missing profile items. Not the same as credential compliance."
            >
                <AttentionTabs
                    employees={data.profile_attention?.employees ?? []}
                    clients={data.profile_attention?.clients ?? []}
                />
            </Panel>
            <Panel title="Assigned DSPs" viewAllHref={employeesIndex()}>
                <PersonList
                    people={data.assigned_dsps.map((dsp) => ({
                        id: dsp.id,
                        name: dsp.name,
                        detail: dsp.employee_number,
                        href: showEmployee.url(dsp.id),
                        photo_url: dsp.photo_url,
                        initials: dsp.initials,
                    }))}
                    empty="No assigned DSPs."
                />
            </Panel>
            <Panel title="Assigned clients" viewAllHref={clientsIndex()}>
                <PersonList
                    people={data.assigned_clients.map((client) => ({
                        id: client.id,
                        name: client.name,
                        detail: client.client_number,
                        href: showClient.url(client.id),
                        photo_url: client.photo_url,
                        initials: client.initials,
                    }))}
                    empty="No assigned clients."
                />
            </Panel>
            <Panel title="Upcoming visits" viewAllHref={scheduledVisitsIndex()}>
                <VisitList visits={data.upcoming_visits} showEmployee />
            </Panel>
            <Panel title="Announcements" viewAllHref={announcementsIndex()}>
                <AnnouncementList announcements={data.announcements} />
            </Panel>
        </div>
    );
}

function DspDashboard({ data }: { data: DashboardPayload }) {
    const workItems = data.work_items ?? [];
    const hasAction =
        Boolean(data.active_visit) || Boolean(data.clock_in_visit);

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <div className="space-y-4 lg:col-span-2">
                {hasAction && (
                    <ClockInAction
                        activeVisit={data.active_visit}
                        clockInVisit={data.clock_in_visit}
                        hideEmpty
                    />
                )}
                <Panel
                    title="Your clients and visits"
                    description="Work is organized by client and visit — never one shared checklist."
                >
                    {workItems.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No assigned clients or visits right now.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {workItems.map((item) => (
                                <li
                                    key={item.key}
                                    className="surface-panel flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-semibold">
                                            {item.client.name}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {item.service_type}
                                            {item.scheduled_time
                                                ? ` · ${item.scheduled_time}`
                                                : ''}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {item.state_label}
                                            {item.has_exception
                                                ? ' · Exception'
                                                : ''}
                                            {item.task_progress
                                                ? ` · ${item.task_progress.completed}/${item.task_progress.total} tasks`
                                                : ''}
                                        </p>
                                    </div>
                                    <Button
                                        className="min-h-11 w-full sm:w-auto"
                                        asChild
                                    >
                                        <Link href={item.href}>
                                            {item.action_label}
                                        </Link>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
            <div className="space-y-4">
                {!(
                    data.assigned_clients.length === 0 && workItems.length === 0
                ) && (
                <Panel title="Assigned clients">
                    <PersonList
                        people={data.assigned_clients.map((client) => ({
                            id: client.id,
                            name: client.name,
                            detail: 'View client',
                            href: showClient.url(client.id),
                        }))}
                        empty="No active client assignments."
                    />
                </Panel>
                )}
                <Panel title="Announcements" viewAllHref={announcementsIndex()}>
                    <AnnouncementList announcements={data.announcements} />
                </Panel>
            </div>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
