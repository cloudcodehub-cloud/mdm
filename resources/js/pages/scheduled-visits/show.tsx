import type { ReactNode } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ClockInAction } from '@/components/mdm/clock-in-action';
import { StatusBadge } from '@/components/mdm/directory';
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
import type { VisitRecord } from '@/types/directory';

export default function ScheduledVisitsShow({
    visit,
    can,
    activeVisit,
    clockInVisit,
}: {
    visit: VisitRecord;
    can: { update: boolean; clock_in: boolean };
    activeVisit: DashboardActiveVisit | null;
    clockInVisit: ClockInVisitSummary | null;
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
                        can.update ||
                        (Boolean(visit.active_visit_id) && role !== 'DSP') ? (
                            <>
                                {can.update && (
                                    <Button asChild variant="secondary">
                                        <Link href={edit(visit.id)}>Edit</Link>
                                    </Button>
                                )}
                                {visit.active_visit_id && role !== 'DSP' && (
                                    <Button asChild>
                                        <Link
                                            href={showVisit(
                                                visit.active_visit_id,
                                            )}
                                        >
                                            View active visit
                                        </Link>
                                    </Button>
                                )}
                            </>
                        ) : undefined
                    }
                />

                {role === 'DSP' && (
                    <ClockInAction
                        activeVisit={activeVisit}
                        clockInVisit={clockInVisit}
                        scheduledVisitId={visit.id}
                        canClockIn={can.clock_in}
                    />
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Visit details">
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
                        <p className="text-sm whitespace-pre-wrap">
                            {visit.notes || 'No notes recorded.'}
                        </p>
                    </Panel>
                </div>
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
