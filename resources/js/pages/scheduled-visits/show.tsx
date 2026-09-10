import type { ReactNode } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { StatusBadge } from '@/components/mdm/directory';
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
import type { VisitRecord } from '@/types/directory';

export default function ScheduledVisitsShow({
    visit,
    can,
}: {
    visit: VisitRecord;
    can: { update: boolean };
}) {
    const role = usePage().props.auth.user.role;
    const canOpenDirectories = role === 'ADMIN' || role === 'SUPERVISOR';

    return (
        <>
            <Head title={visit.service_type} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-muted-foreground text-sm">
                            <Link
                                href={visitsIndex()}
                                className="hover:text-foreground"
                            >
                                Scheduled Visits
                            </Link>
                        </p>
                        <h1 className="text-xl font-semibold tracking-tight">
                            {visit.client?.name ?? visit.client_name}
                        </h1>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <StatusBadge
                                status={visit.status}
                                label={visit.status_label}
                            />
                            <span className="text-muted-foreground text-sm">
                                {visit.service_date} · {visit.time_label}
                            </span>
                        </div>
                    </div>
                    {can.update && (
                        <Button asChild variant="secondary">
                            <Link href={edit(visit.id)}>Edit</Link>
                        </Button>
                    )}
                </div>

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
