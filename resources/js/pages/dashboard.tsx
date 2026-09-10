import { Head, Link } from '@inertiajs/react';
import { AttentionList } from '@/components/mdm/attention-list';
import { ClockInAction } from '@/components/mdm/clock-in-action';
import { ActivityList, PersonList } from '@/components/mdm/person-list';
import { Panel, StatCard } from '@/components/mdm/stat-card';
import { VisitList } from '@/components/mdm/visit-list';
import { dashboard } from '@/routes';
import { index as operationsIndex } from '@/routes/operations';
import type { DashboardPayload } from '@/types/dashboard';

export default function Dashboard({
    dashboard: data,
}: {
    dashboard: DashboardPayload;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <p className="text-muted-foreground text-sm">
                        Welcome back, {data.greeting_name}
                    </p>
                    <h2 className="text-xl font-semibold tracking-tight">
                        {headline(data.role)}
                    </h2>
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

function headline(role: DashboardPayload['role']): string {
    if (role === 'DSP') {
        return "Today's work";
    }

    if (role === 'SUPERVISOR') {
        return 'Caseload operations';
    }

    return 'Operations overview';
}

function AdminDashboard({ data }: { data: DashboardPayload }) {
    return (
        <div className="grid gap-4 xl:grid-cols-3">
            <Panel
                title="Scheduled visits today"
                description="Open visits across the agency."
                className="xl:col-span-2"
            >
                <VisitList
                    visits={data.today_visits}
                    showEmployee
                    empty="No visits scheduled for today."
                />
            </Panel>
            <Panel
                title="Operational attention"
                description="Credentials, training, authorizations, and cancelled visits."
            >
                <AttentionList items={data.attention_items} />
            </Panel>
            <Panel
                title="Upcoming visits"
                description="Next scheduled visits after today."
                className="xl:col-span-2"
            >
                <VisitList visits={data.upcoming_visits} showEmployee />
            </Panel>
            <Panel
                title="Recent activity"
                description="Latest scheduled visit records."
            >
                <ActivityList items={data.activity} />
            </Panel>
        </div>
    );
}

function SupervisorDashboard({ data }: { data: DashboardPayload }) {
    return (
        <div className="grid gap-4 xl:grid-cols-3">
            <Panel
                title="Today's scheduled visits"
                description="Open the operations board for live caseload monitoring."
                className="xl:col-span-2"
            >
                <VisitList
                    visits={data.today_visits}
                    showEmployee
                    empty="No visits scheduled for your caseload today."
                />
                <p className="mt-3 text-sm">
                    <Link
                        href={operationsIndex()}
                        className="hover:text-foreground font-medium"
                    >
                        Open supervisor operations
                    </Link>
                </p>
            </Panel>
            <Panel title="Operational attention">
                <AttentionList items={data.attention_items} />
            </Panel>
            <Panel title="Assigned DSPs">
                <PersonList
                    people={data.assigned_dsps.map((dsp) => ({
                        id: dsp.id,
                        name: dsp.name,
                        detail: dsp.employee_number,
                    }))}
                    empty="No assigned DSPs."
                />
            </Panel>
            <Panel title="Assigned clients">
                <PersonList
                    people={data.assigned_clients.map((client) => ({
                        id: client.id,
                        name: client.name,
                        detail: client.client_number,
                    }))}
                    empty="No assigned clients."
                />
            </Panel>
            <Panel title="Upcoming visits">
                <VisitList visits={data.upcoming_visits} showEmployee />
            </Panel>
        </div>
    );
}

function DspDashboard({ data }: { data: DashboardPayload }) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <div className="space-y-4 lg:col-span-2">
                <ClockInAction
                    activeVisit={data.active_visit}
                    clockInVisit={data.clock_in_visit}
                />
                <Panel
                    title="Today's scheduled visits"
                    description="Client, time, and service for today."
                >
                    <VisitList
                        visits={data.today_visits}
                        empty="No visits scheduled for you today."
                    />
                </Panel>
            </div>
            <div className="space-y-4">
                <Panel title="Upcoming work">
                    <VisitList visits={data.upcoming_visits} />
                </Panel>
                <Panel title="Assigned clients">
                    <PersonList
                        people={data.assigned_clients.map((client) => ({
                            id: client.id,
                            name: client.name,
                            detail: client.client_number,
                        }))}
                        empty="No active client assignments."
                    />
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
